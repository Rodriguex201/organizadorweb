<?php
declare(strict_types=1);

// No application bootstrap, .env or MySQL driver. All fixtures are memory-only.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) throw new RuntimeException('Only SQLite memory');
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

// SQLite equality is case-sensitive already; adapt only MySQL's unary BINARY syntax.
Illuminate\Database\Connection::resolverFor('sqlite', function ($pdo,$database,$prefix,$config) {
    if ($database !== ':memory:') throw new RuntimeException('Persistent database forbidden');
    return new class($pdo,$database,$prefix,$config) extends Illuminate\Database\SQLiteConnection {
        public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []) {
            return parent::select(preg_replace('/\bBINARY\s+/', '', $query),$bindings,$useReadPdo,$fetchUsing);
        }
    };
});
$capsule=new Capsule();$capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
$capsule->setAsGlobal();$capsule->getContainer()->instance('db',$capsule->getDatabaseManager());
Facade::setFacadeApplication($capsule->getContainer());
$db=$capsule->getConnection();
if($db->getDriverName()!=='sqlite'||$db->getDatabaseName()!==':memory:')throw new RuntimeException('Isolation failed');
$schema=$db->getSchemaBuilder();
$capsule->getContainer()->instance('db.schema',$schema);
$schema->create('config_retenciones_fuente',function(Blueprint $t){$t->integer('id')->primary();$t->string('porcentaje');$t->boolean('activo');});
$schema->create('clientes_potenciales',function(Blueprint $t){
    $t->integer('idclientes_potenciales')->primary();$t->integer('retencion_fuente_id')->nullable();
    foreach(['codigo','nota_cobro','fecha_arriendo','nit','regimen']as$c)$t->string($c)->nullable();
});
$schema->create('valores_externos',function(Blueprint $t){$t->integer('id_cobro');$t->string('id_cliente');$t->string('mes');$t->integer('año');});
$schema->create('cliente_notas',function(Blueprint $t){$t->integer('cliente_id');$t->timestamp('deleted_at')->nullable();});
$schema->create('sg_proform',function(Blueprint $t){
    $t->integer('id')->primary();$t->integer('id_cobro')->nullable();
    foreach(['nro_prof','emp','nit','emisora','mes','anio','vtotal','valor_original','retencion_porcentaje','retencion_valor','estado','fpag','ffac','rpdf','npdf','hpdf','comprobante_pago','enviado','fecha_envio','intentos_envio']as$c)$t->string($c)->nullable();
});
DB::table('config_retenciones_fuente')->insert([['id'=>1,'porcentaje'=>'3.250','activo'=>1],['id'=>2,'porcentaje'=>'1.125','activo'=>0],['id'=>3,'porcentaje'=>'0.000','activo'=>1]]);
foreach([1=>1,2=>2,3=>null,4=>3,5=>1,6=>2]as$id=>$ret){
    DB::table('clientes_potenciales')->insert(['idclientes_potenciales'=>$id,'retencion_fuente_id'=>$ret,'codigo'=>'C'.$id,'nit'=>$id>=5?'AMB':'N'.$id,'regimen'=>'SAS']);
    DB::table('valores_externos')->insert(['id_cobro'=>$id,'id_cliente'=>(string)$id,'mes'=>'octubre','año'=>2026]);
}
foreach(range(1,9)as$id){
    DB::table('sg_proform')->insert(['id'=>$id,'id_cobro'=>$id<=4?$id:($id===8?999:($id===9?20:null)),
        'nit'=>$id===5?'N1':($id===6?'AMB':'NONE'),'mes'=>'10','anio'=>'2026','emisora'=>'SAS','vtotal'=>'100.00','retencion_porcentaje'=>null]);
}
DB::table('valores_externos')->insert([['id_cobro'=>20,'id_cliente'=>'1','mes'=>'octubre','año'=>2026],['id_cobro'=>20,'id_cliente'=>'2','mes'=>'octubre','año'=>2026]]);
$service=new App\Services\ProformasService(new App\Services\EstadoProformaConfigService());
$count=0;$check=function($ok,$name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$before=DB::table('sg_proform')->orderBy('id')->get()->toJson();
$rows=$service->paginateProformas([],30)->getCollection()->keyBy('id');
$check($rows[1]->retencion_cliente_porcentaje==='3.250','active current client rate');
$check($rows[2]->retencion_cliente_porcentaje==='1.125','inactive associated rate remains visible');
$check($rows[3]->retencion_cliente_porcentaje===null,'no association');
$check($rows[4]->retencion_cliente_porcentaje==='0.000','zero is not no association');
$check($rows[5]->retencion_cliente_porcentaje==='3.250','historical fallback and repeated same client');
$check($rows[6]->retencion_cliente_porcentaje===null,'ambiguous historical clients');
$check($rows[7]->retencion_cliente_porcentaje===null,'unresolved historical client');
$check($rows[8]->retencion_cliente_porcentaje===null,'broken explicit relation does not fall back');
$check($rows[9]->retencion_cliente_porcentaje===null,'ambiguous explicit relation');
DB::table('config_retenciones_fuente')->where('id',1)->update(['porcentaje'=>'4.500']);
$rows=$service->paginateProformas([],30)->getCollection()->keyBy('id');
$check($rows[1]->retencion_cliente_porcentaje==='4.500'&&$rows[5]->retencion_cliente_porcentaje==='4.500','current rate changes independently of historical snapshot');
$check(DB::table('sg_proform')->orderBy('id')->get()->toJson()===$before,'all proforma fields untouched');
$db->enableQueryLog();$db->flushQueryLog();$service->paginateProformas([],9);$small=count($db->getQueryLog());
foreach(range(10,59)as$id)DB::table('sg_proform')->insert(['id'=>$id,'id_cobro'=>1,'mes'=>'10','anio'=>'2026','nit'=>'N1','emisora'=>'SAS']);
$db->flushQueryLog();$service->paginateProformas([],100);$large=count($db->getQueryLog());
$check($small===$large,'query count constant for 9 versus 59 proformas');
foreach($db->getQueryLog()as$q)$check(str_starts_with(strtolower($q['query']),'select'),'listing only selects');
$view=file_get_contents(__DIR__.'/../../resources/views/proformas/index.blade.php');
preg_match('/<td[^>]*title="Configuración actual del cliente; no modifica el importe emitido"[^>]*>.*?<\/td>/s',$view,$match);
$compiler=new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(),sys_get_temp_dir());
$compiled=$compiler->compileString($match[0]);
foreach(['3.250'=>'3,250 %','1.125'=>'1,125 %','0.000'=>'0,000 %','none'=>'—']as$value=>$expected){
    $proforma=(object)['retencion_cliente_porcentaje'=>$value==='none'?null:$value];ob_start();eval('?>'.$compiled);$html=ob_get_clean();
    $check(str_contains($html,$expected)&&str_contains($html,'text-center')&&str_contains($html,'w-20'),'render '.$expected);
}
$check(substr_count($view,'colspan="12"')===2,'empty and unsearched table spans');
echo "OK: $count checks; SQLite memory; $large listing queries.\n";
