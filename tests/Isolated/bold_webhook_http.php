<?php
declare(strict_types=1);

if (PDO::getAvailableDrivers() !== [] || extension_loaded('mysqli')) throw new RuntimeException('No database drivers');
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Only an in-memory container/router, no kernel bootstrap, providers, config files or .env.
$app = new Application(dirname(__DIR__, 2));
$app->instance('env', 'local');
$app->instance('config', new Repository(['bold'=>[
    'environment'=>'sandbox','database'=>['database'=>'organizadorprueba'],
    'webhook'=>['enabled'=>true,'merchant_id'=>'fixture-merchant'],
]]));
$router = new Router(new Dispatcher($app), $app);
$app->instance('router', $router);
Route::setFacadeApplication($app);
require __DIR__.'/../../routes/bold_webhook.php';
$count=0;
$check=function($ok,$name)use(&$count){if(!$ok)throw new RuntimeException($name);++$count;echo "PASS: $name\n";};
$dispatch=function(string $body, string $signature='', string $remote='127.0.0.1')use($router,$app){
    $r=Request::create('http://localhost/portal/pagos/bold/webhook','POST',[],[],[],[
        'REMOTE_ADDR'=>$remote,'CONTENT_TYPE'=>'application/json','HTTP_X_BOLD_SIGNATURE'=>$signature,
    ],$body);
    $app->instance('request',$r);
    return $router->dispatch($r);
};
$check($dispatch('{')->getStatusCode()===400,'actual route rejects unsigned body');
$check($dispatch('{','', '203.0.113.1')->getStatusCode()===404,'actual route rejects remote caller');
$app['config']->set('bold.webhook.enabled',false);
$check($dispatch('{')->getStatusCode()===404,'actual route disabled');
$app['config']->set('bold.webhook.enabled',true);
$app->instance('env','production');
$check($dispatch('{')->getStatusCode()===404,'actual route rejects production');
$app->instance('env','local');
$body=str_repeat('x',65537);
$check($dispatch($body)->getStatusCode()===413,'controller bounded input');
$body='{"id":"fixture-event","type":"SALE_APPROVED","data":{}}';
$check($dispatch($body,hash_hmac('sha256',base64_encode($body),''))->getStatusCode()===503,'actual route safely fails with missing dedicated credentials');
$route=$router->getRoutes()->getRoutes()[0];
$check($route->gatherMiddleware()===[],'route has no web/admin/session middleware');
echo "OK: $count HTTP checks; no DB drivers or application bootstrap.\n";
