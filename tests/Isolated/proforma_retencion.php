<?php
/** Run with php -n and ONLY pdo_sqlite, mbstring, openssl, fileinfo extensions. */
declare(strict_types=1);

// This check runs before autoload, Laravel, configuration or any connection.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) {
    throw new RuntimeException('Only SQLite :memory: is allowed.');
}
$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/proforma-retencion-test-'.bin2hex(random_bytes(8));
mkdir($temp.'/cache', 0700, true);
mkdir($temp.'/views', 0700, true);
foreach ([
    'APP_ENV'=>'testing', 'APP_DEBUG'=>'false', 'APP_KEY'=>'base64:'.base64_encode(str_repeat('t',32)),
    'APP_CONFIG_CACHE'=>$temp.'/no-config.php', 'APP_ROUTES_CACHE'=>$temp.'/no-routes.php',
    'APP_SERVICES_CACHE'=>$temp.'/cache/services.php', 'APP_PACKAGES_CACHE'=>$temp.'/cache/packages.php',
    'VIEW_COMPILED_PATH'=>$temp.'/views', 'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>':memory:',
    'DB_URL'=>'', 'SESSION_DRIVER'=>'array', 'CACHE_STORE'=>'array', 'PERF_DIAG_ENABLED'=>'false',
] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix(substr($temp, 0, 3));
$app->loadEnvironmentFrom('__retenciones_no_env__');
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($temp): void {
    $app['config']->set('database.default', 'sqlite');
    $app['config']->set('database.connections', ['sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
    $app['config']->set('logging.default', 'null');
    $app['config']->set('session.driver', 'array');
    $app['config']->set('cache.default', 'array');
});
$app->extend('db.factory', function ($factory, $app) {
    return new class($app) extends Illuminate\Database\Connectors\ConnectionFactory {
        public function make(array $config, $name = null) {
            if (($config['driver']??null)!=='sqlite' || ($config['database']??null)!==':memory:' || !empty($config['url'])) {
                throw new RuntimeException('Non-memory connection blocked.');
            }
            return parent::make($config, $name);
        }
    };
});
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): never {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
Illuminate\Support\Facades\Http::preventStrayRequests();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\RetencionFuenteConfigService;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConfiguracionTarifaController;

$connection = DB::connection();
if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') throw new RuntimeException('Isolation failed');
$app->instance('request', Request::create('/'));
Schema::create('configuracion_estados_proforma', function (Blueprint $t) {
    $t->integer('estado_codigo'); $t->string('estado_nombre'); $t->string('color_fondo'); $t->string('color_texto'); $t->boolean('activo');
});
Schema::create('config_retenciones_fuente', function (Blueprint $t) {
    $t->increments('id'); $t->string('porcentaje'); $t->boolean('activo');
});
Schema::create('clientes_potenciales', function (Blueprint $t) {
    $t->increments('idclientes_potenciales'); $t->integer('retencion_fuente_id')->nullable();
});
Schema::create('conceptos', function (Blueprint $t) {
    $t->string('codigo'); $t->string('nombre'); $t->string('cuenta')->nullable(); $t->boolean('activo');
});
Schema::create('valores_externos', function (Blueprint $t) {
    $t->increments('id_cobro'); $t->integer('Proforma')->default(0);
    $t->decimal('valor_extra')->default(0); $t->decimal('valor_extra2')->default(0);
});
Schema::create('sg_proform', function (Blueprint $t) {
    $t->increments('id'); $t->integer('id_cobro')->nullable();
    foreach (['nit','emp','emisora','fpago','mes','anio','nro_prof','estado','vlr_mens','vlr_nom','vlr_fe','vlr_rec','vlr_sop','vext1','vext2','vtotal','valor_original','retencion_porcentaje','retencion_valor','cfe','csop','crec','cnom','rpdf','npdf','hpdf'] as $key) $t->string($key)->nullable();
});
Schema::create('sg_proford', function (Blueprint $t) {
    $t->increments('id'); $t->integer('proforma_id');
    foreach (['ref_codigo','descripcion','cantidad','vr_unidad','vr_parcial','orden','moneda'] as $key) $t->string($key);
});
$checks = 0;
$check = function (bool $condition, string $name) use (&$checks) {
    if (!$condition) throw new RuntimeException('FAIL: '.$name);
    echo 'PASS: '.$name.PHP_EOL; $checks++;
};
$calc = app(App\Services\ProformaImportesCalculator::class);
foreach ([['100.00','3.250','3.25','96.75'], ['0.50','1.000','0.01','0.49'], ['100','0','0.00','100.00'], ['100','100','100.00','0.00'], ['9999999999999.99','99.999','9999899999999.99','100000000.00']] as [$original,$rate,$ret,$net]) {
    $values = $calc->calculate([['valor_parcial'=>$original]],$rate);
    $check($values['retencion_valor']===$ret && $values['vtotal']===$net, 'exact decimals '.$original.' / '.$rate);
}
DB::table('config_retenciones_fuente')->insert(['id'=>1,'porcentaje'=>'3.250','activo'=>1]);
DB::table('clientes_potenciales')->insert(['idclientes_potenciales'=>1,'retencion_fuente_id'=>1]);
foreach (['0010','0011','0099','0081','0101','0102','0103','EXTRA'] as $code) DB::table('conceptos')->insert(['codigo'=>$code,'nombre'=>'Concepto '.$code,'activo'=>1]);
DB::table('valores_externos')->insert(['id_cobro'=>1]);
$cobro = (object) ['id_cobro'=>1,'cliente_id'=>1,'cliente_nit'=>'123456789','cliente_empresa'=>'Prueba aislada','cliente_regimen'=>'SAS','mes'=>10,'año'=>2026,'cliente_numequipos'=>1,'cliente_vlrprincipal'=>100,'cliente_vlrpaginaweb'=>10,'cliente_vlrextra'=>5,'cliente_vlrextra2'=>2];
$preview = app(App\Services\ProformaPreviewService::class);
$store = app(App\Services\ProformaStoreService::class);
$p = $preview->buildFromCobro($cobro);
$check($p['detalle']['importes']['valor_original']==='117.00', 'sum includes all definitive concepts');
$saved = $store->storeFromCobro($cobro);
$row = DB::table('sg_proform')->find($saved['proforma_id']);
foreach ($p['detalle']['importes'] as $key=>$value) $check($row->$key===$value,'preview equals saved '.$key);
$check(abs((float)DB::table('sg_proford')->sum('vr_parcial')-117)<0.001,'positive details sum to original, no negative retention concept');
$before = (array)$row; $linesBefore = DB::table('sg_proford')->get()->toArray();
DB::table('config_retenciones_fuente')->where('id',1)->update(['porcentaje'=>'20.000','activo'=>0]);
$cobro->cliente_vlrprincipal = 999;
$store->regenerateFromCobro($cobro);
$check((array) DB::table('sg_proform')->find($row->id)===$before,'regeneration preserves all issued amounts and percentage');
$check(DB::table('sg_proford')->get()->toArray()==$linesBefore,'regeneration preserves issued concepts');
$persistedPreview = $preview->buildFromCobro($cobro,$row);
$check($persistedPreview['detalle']['importes']==$p['detalle']['importes'],'issued preview ignores current inactive configuration');
DB::table('valores_externos')->insert(['id_cobro'=>2]); $cobro->id_cobro=2; $cobro->mes=11;
foreach ([fn()=>$preview->buildFromCobro($cobro), fn()=>$store->storeFromCobro($cobro)] as $attempt) {
    try { $attempt(); throw new RuntimeException('Inactive accepted'); }
    catch (ValidationException $e) { $check(isset($e->errors()['retencion_fuente_id']),'inactive blocks with configuration message'); }
}
$check(DB::table('sg_proform')->count()===1,'inactive rejection persists no new document');
$cobrosMock = Mockery::mock(App\Services\CobrosService::class);
$cobrosMock->shouldReceive('findCobroById')->with(2)->andReturn($cobro);
$app->instance(App\Services\CobrosService::class,$cobrosMock);
$blockedView = app(App\Http\Controllers\CobrosController::class)->previewProforma(2);
$check(str_contains($blockedView->getData()['generationError'],'inactiva'),'real preview controller shows corrective message without redirect loop');
DB::table('clientes_potenciales')->where('idclientes_potenciales',1)->update(['retencion_fuente_id'=>null]);
$without = $store->storeFromCobro($cobro); $noRetention = DB::table('sg_proform')->find($without['proforma_id']);
$check($noRetention->retencion_porcentaje==='0.000' && $noRetention->retencion_valor==='0.00' && $noRetention->vtotal===$noRetention->valor_original,'NULL client retention freezes zero and full total');
DB::table('sg_proform')->where('id',$noRetention->id)->update(['valor_original'=>null,'retencion_porcentaje'=>null,'retencion_valor'=>null,'vtotal'=>'321.00']);
$legacy = DB::table('sg_proform')->find($noRetention->id);
$store->regenerateFromCobro($cobro);
$check((array)DB::table('sg_proform')->find($legacy->id)===(array)$legacy,'historical regeneration preserves NULL and total');
$legacyPreview = $preview->buildFromCobro($cobro,$legacy);
$check($legacyPreview['detalle']['importes']['valor_original']===null && $legacyPreview['detalle']['total_preview']==='321.00','historical preview never infers original or retention');
$html = view('proformas.partials.importes',['importes'=>$row])->render();
$check(str_contains($html,'3,250 %') && str_contains($html,'113,20') && str_contains($html,'117,00'),'render original deduction and net');
$legacyHtml = view('proformas.partials.importes',['importes'=>$legacy])->render();
$check(!str_contains($legacyHtml,'Valor original:') && str_contains($legacyHtml,'321,00'),'historical presentation does not infer');
$export = app(App\Services\ProformaDashboardExportService::class);
$definitions = (new ReflectionMethod($export,'columnDefinitions'))->invoke($export);
foreach (['proforma_valor_original','proforma_retencion_porcentaje','proforma_retencion_valor','proforma_valor_total'] as $key) {
    $q = DB::table('sg_proform as p')->where('id',$row->id);
    $definitions[$key]['select']($q);
    $check($definitions[$key]['value']($q->first())!==null,'export persisted '.$key);
}
$q = DB::table('sg_proform as p')->where('id',$legacy->id);
$definitions['proforma_valor_original']['select']($q);
$check($definitions['proforma_valor_original']['value']($q->first())===null,'historical export remains blank');
$dashboard = app(App\Services\ProformasService::class)->getDashboardData(10,2026);
$check(abs($dashboard['suma_total_vtotal']-113.20)<0.001,'dashboard sums net amount');
$dashboardRows = app(App\Services\ProformasService::class)->paginateDashboardProformas(10,2026);
$check($dashboardRows->first()->retencion_porcentaje==='3.250','dashboard rows use persisted snapshot');
$words = app(App\Services\NumeroALetrasService::class)->toColombianPesos((float)$row->vtotal);
$pdfHtml = view('proformas.pdf', [
    'cabecera'=>$row, 'detalle'=>collect($linesBefore), 'logo_path'=>null,
    'mes_nombre'=>'Octubre', 'fecha_emision'=>'2026-10-21', 'total_en_letras'=>$words,
])->render();
$check(str_contains($pdfHtml, $words) && str_contains($pdfHtml,'3,250 %') && str_contains($pdfHtml,'113,20'), 'PDF template contains net in words and frozen breakdown');
$statementHtml = view('proformas.estado-cuenta-pdf', ['estadoCuenta'=>[
    'empresa'=>'Prueba aislada', 'nit'=>'123456789', 'total_acumulado'=>$row->vtotal,
    'proformas'=>[['nro_prof'=>$row->nro_prof, 'periodo'=>'Octubre 2026','estado'=>'Generada','emisora'=>'SAS','importes'=>(array)$row]],
]])->render();
$check(str_contains($statementHtml,'113,20') && str_contains($statementHtml,'3,250 %'),'account statement includes frozen deduction and net');
if (in_array('--render-pdf', $argv, true)) {
    $directory = getenv('PROFORMA_TEST_PDF_DIR');
    if (!$directory || !is_dir($directory)) throw new RuntimeException('Explicit test PDF directory required');
    foreach (['proforma'=>$pdfHtml, 'estado-cuenta'=>$statementHtml] as $name=>$html) {
        $options = new Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setTempDir($temp);
        $options->setFontCache($temp);
        $pdf = new Dompdf\Dompdf($options);
        $pdf->loadHtml($html); $pdf->setPaper('A4'); $pdf->render();
        file_put_contents($directory.'/'.$name.'.pdf', $pdf->output());
    }
}
// Compile affected Blade templates without executing queries or generating files in project storage.
foreach (['cobros/proforma-preview','proformas/show','proformas/index','proformas/dashboard','proformas/pdf','proformas/estado-cuenta','proformas/estado-cuenta-pdf'] as $name) {
    $compiled = app('blade.compiler')->compileString(file_get_contents($root.'/resources/views/'.$name.'.blade.php'));
    $check($compiled!=='','Blade compiles '.$name);
}
echo 'OK: '.$checks.' checks; only SQLite :memory:, HTTP blocked.'.PHP_EOL;
