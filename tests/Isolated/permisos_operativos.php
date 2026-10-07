<?php

// Registro de rutas y sesiones en memoria. Sin bootstrap de Laravel, .env ni BD.
require __DIR__.'/../../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new class extends Container {
    public function isProduction(): bool { return true; }
    public function abort($code, $message = '', array $headers = []): never { throw new RuntimeException($message, $code); }
};
Container::setInstance($app);
Facade::setFacadeApplication($app);
$session = new Store('test', new ArraySessionHandler(120));
$app->instance('session', $session);
$router = new Router(new Dispatcher($app), $app);
Route::swap($router);
require __DIR__.'/../../routes/web.php';
function ensure(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$userRoutes = 0;
foreach ($router->getRoutes() as $route) {
    $middleware = $route->gatherMiddleware();
    if (str_starts_with($route->uri(), 'configuracion/usuarios')) {
        $userRoutes++;
        ensure(in_array('auth.custom', $middleware) && in_array('role.admin', $middleware), 'Usuarios requiere sesión y admin');
    } else {
        ensure(!in_array('role.admin', $middleware), 'Restricción admin fuera de usuarios: '.$route->uri());
    }
    if (str_contains($route->uri(), '/activacion') || str_contains($route->uri(), '/whatsapp/') || str_contains($route->uri(), '/envio-masivo/') || (str_starts_with($route->uri(), 'configuracion/') && !str_starts_with($route->uri(), 'configuracion/usuarios'))) {
        ensure(in_array('auth.custom', $middleware) && in_array('role:admin,user', $middleware), 'Ruta operativa protegida: '.$route->uri());
    }
}
ensure($userRoutes === 5, 'Las cinco rutas de usuarios conservan protección');
$next = fn ($request) => new Response('OK');
$request = Request::create('/');
foreach (['admin', 'user', 'otro', null] as $role) {
    $session->flush();
    $session->put(['idusuario'=>12, 'rol_nombre'=>$role, 'rol_id'=>$role === 'admin' ? 1 : 2]);
    $allowed = in_array($role, ['admin','user'], true);
    ensure(puedeOperar() === $allowed, 'Política operativa');
    try {
        (new App\Http\Middleware\RoleMiddleware)->handle($request, $next, 'admin', 'user');
        ensure($allowed, 'Rol desconocido aceptado');
    } catch (RuntimeException $e) { ensure(!$allowed && $e->getCode() === 403, 'Rechazo operativo'); }
    try {
        (new App\Http\Middleware\RoleAdminMiddleware)->handle($request, $next);
        ensure($role === 'admin', 'Gestión de usuarios abierta indebidamente');
    } catch (RuntimeException $e) { ensure($role !== 'admin' && $e->getCode() === 403, 'Rechazo admin'); }
    if ($allowed) {
        $controller = (new ReflectionClass(App\Http\Controllers\ProformasController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'denyIfNotActivationOperator');
        ensure($method->invoke($controller) === null, 'Activación permite ambos roles');
    }
}
$session->flush(); $session->put('rol_nombre', 'admin');
ensure(!puedeOperar(), 'No permite operar sin sesión de usuario');
echo "OK: rutas, admin/user, roles desconocidos, sesión requerida y usuarios exclusivos de admin; sin BD.\n";
