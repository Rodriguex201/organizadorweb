<?php
/** Run with php -n and ONLY pdo_sqlite, mbstring, openssl, fileinfo extensions. */
declare(strict_types=1);

// This check runs before autoload, Laravel, configuration or any connection.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) {
    throw new RuntimeException('Only SQLite :memory: is allowed.');
}
$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/retenciones-test-'.bin2hex(random_bytes(8));
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
Schema::create('config_retenciones_fuente', function (Blueprint $t): void {
    $t->increments('id'); $t->decimal('porcentaje',6,3)->unique(); $t->string('descripcion',100)->nullable();
    $t->boolean('activo')->default(true); $t->timestamps();
});
Schema::create('clientes_potenciales', function (Blueprint $t): void {
    $t->increments('idclientes_potenciales'); $t->integer('retencion_fuente_id')->nullable();
    $t->foreign('retencion_fuente_id')->references('id')->on('config_retenciones_fuente')->nullOnDelete();
});
$checks = 0;
$check = function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label);
    $checks++; echo 'PASS: '.$label.PHP_EOL;
};
$reject = function (callable $callback, string $field) use ($check): void {
    try { $callback(); } catch (ValidationException $e) { $check(isset($e->errors()[$field]), 'validation rejects '.$field); return; }
    throw new RuntimeException('Expected rejection: '.$field);
};
$service = app(RetencionFuenteConfigService::class);
$service->save(['porcentaje'=>'3.500','descripcion'=>'Servicios','activo'=>1]);
$one = (int) DB::table('config_retenciones_fuente')->value('id');
$service->save(['porcentaje'=>'1.125','descripcion'=>null,'activo'=>1]);
$two = (int) DB::table('config_retenciones_fuente')->where('porcentaje',1.125)->value('id');
$check($service->all()->pluck('id')->all() === [$two,$one], 'list ascending and create precision');
$reject(fn ()=>$service->save(['porcentaje'=>'3.5','activo'=>1]), 'porcentaje');
foreach (['-0.1','100.001','1.1234','invalid'] as $value) $reject(fn ()=>$service->save(['porcentaje'=>$value,'activo'=>1]),'porcentaje');
$service->save(['porcentaje'=>'4.125','descripcion'=>'Actualizada','activo'=>1],$one);
$check(DB::table('config_retenciones_fuente')->where('id',$one)->value('descripcion')==='Actualizada','edit description and percentage');
$reject(fn ()=>$service->save(['porcentaje'=>'1.125','activo'=>1],$one),'porcentaje');
$service->setActive($one,false);
$check(!in_array((string)$one,$service->catalog()['ids'],true),'inactive excluded for new assignment');
$check(in_array((string)$one,$service->catalog($one)['ids'],true),'current inactive retained');
$check(str_contains($service->catalog($one)['options'][1]['label'],'inactiva'),'inactive visibly labelled');
$reject(fn () => Validator::make(['retencion_fuente_id'=>99999],['retencion_fuente_id'=>$service->selectionRules($service->catalog())])->validate(),'retencion_fuente_id');
$reject(fn () => Validator::make(['retencion_fuente_id'=>$one],['retencion_fuente_id'=>$service->selectionRules($service->catalog())])->validate(),'retencion_fuente_id');
$service->setActive($one,true);
$check(in_array((string)$one,$service->catalog()['ids'],true),'reactivation restores availability');

// Exercise real client store/update methods against the minimal in-memory schema.
session()->put(['idusuario'=>1,'rol_nombre'=>'admin']);
$request = function (array $data): Request {
    $r = Request::create('/clientes','POST',$data); $r->setLaravelSession(session()->driver());
    app()->instance('request',$r); return $r;
};
$controller = app(ClientesController::class);
$controller->store($request(['retencion_fuente_id'=>$one]));
$client = (int) DB::table('clientes_potenciales')->value('idclientes_potenciales');
$check((int)DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->value('retencion_fuente_id')===$one,'create client with retention');
$controller->store($request(['retencion_fuente_id'=>null]));
$check(DB::table('clientes_potenciales')->whereNull('retencion_fuente_id')->count()===1,'create client without retention');
$controller->update($request(['retencion_fuente_id'=>$two]),$client);
$check((int)DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->value('retencion_fuente_id')===$two,'edit client selection');
$service->setActive($two,false);
$controller->update($request(['retencion_fuente_id'=>$two]),$client);
$check((int)DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->value('retencion_fuente_id')===$two,'preserve inactive current selection');
$reject(fn ()=>$controller->store($request(['retencion_fuente_id'=>$two])),'retencion_fuente_id');
$reject(fn ()=>$controller->update($request(['retencion_fuente_id'=>99999]),$client),'retencion_fuente_id');
foreach ([99999, 'manipulado', ['id'=>1]] as $invalid) {
    try {
        $controller->update($request(['retencion_fuente_id'=>$invalid]),$client);
        throw new RuntimeException('Expected invalid ID rejection');
    } catch (ValidationException $e) {
        $check($e->errors()['retencion_fuente_id'][0] === 'Selecciona una retención en la fuente válida o elige No aplica.', 'invalid retention message in Spanish');
    }
}
$controller->update($request([]),$client);
$check((int)DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->value('retencion_fuente_id')===$two,'omitted field preserves selection');
$controller->update($request(['retencion_fuente_id'=>null]),$client);
$check(DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->value('retencion_fuente_id')===null,'No aplica clears selection');

foreach (['store','update','estado'] as $action) {
    $route = app('router')->getRoutes()->getByName('configuracion.tarifas.retenciones.'.$action);
    $check(in_array('role:admin,user',$route->gatherMiddleware(),true),'operational route permissions '.$action);
}
session()->put('rol_nombre','user');
$tarifas = app(ConfiguracionTarifaController::class);
$tarifas->estadoRetencion($request(['activo'=>'0']),$one,$service);
$check((int)DB::table('config_retenciones_fuente')->where('id',$one)->value('activo')===0,'user may deactivate');
session()->put('rol_nombre','viewer');
try { $tarifas->estadoRetencion($request(['activo'=>'1']),$one,$service); throw new RuntimeException('Expected 403'); }
catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { $check($e->getStatusCode()===403,'non-operator denied'); }
$check(DB::table('config_retenciones_fuente')->count()===2,'no physical deletion');
session()->put('rol_nombre','admin');
view()->share('errors', new Illuminate\Support\ViewErrorBag());
DB::table('clientes_potenciales')->where('idclientes_potenciales',$client)->update(['retencion_fuente_id'=>$two]);
$editView = $controller->edit($client);
$editView->getData()['cliente']->empresa = 'Empresa de prueba';
$editView->getData()['cliente']->nombre = 'Cliente de prueba';
$check((int)$editView->getData()['cliente']->retencion_fuente_id===$two,'edit loads stored selection');
$editHtml = $editView->render();
$check(str_contains($editHtml,'Retención en la fuente') && preg_match('/value="'.$two.'"\s+selected[^>]*>[^<]*inactiva/s',$editHtml)===1,'edit HTML selects current inactive retention');
$createHtml = $controller->create()->render();
$check(str_contains($createHtml,'No aplica') && !str_contains($createHtml,'(inactiva)'),'create HTML offers No aplica without inactive choices');
$tarifaHtml = $tarifas->index($service)->render();
$check(str_contains($tarifaHtml,'Crear retención') && str_contains($tarifaHtml,'step="0.001"'),'Tarifas renders management with three-decimal precision');
$check(!str_contains($tarifaHtml,'value="DELETE"'),'Tarifas provides no delete operation');
// Simulate another request inserting after validation and before our INSERT.
$race = true;
$connection->beforeExecuting(function ($sql) use (&$race): void {
    if ($race && str_starts_with($sql,'insert into "config_retenciones_fuente"')) {
        $race = false;
        DB::table('config_retenciones_fuente')->insert(['porcentaje'=>7.777,'activo'=>1]);
    }
});
$reject(fn ()=>$service->save(['porcentaje'=>'7.777','activo'=>1]),'porcentaje');
$check(DB::table('config_retenciones_fuente')->where('porcentaje',7.777)->count()===1,'UNIQUE protects concurrent duplicate');
echo $checks.' checks passed; only SQLite :memory:, no MySQL or .env loaded.'.PHP_EOL;
