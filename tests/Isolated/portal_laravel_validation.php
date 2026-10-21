<?php

/**
 * Real Laravel HTTP validation without any database driver or real environment.
 * Start only with: php -n -d extension_dir=<PHP_EXT_DIRECTORY>
 * -d extension=mbstring -d extension=openssl
 * -d extension=fileinfo -S 127.0.0.1:8789 tests/Isolated/portal_laravel_validation.php
 * The caller must set PORTAL_VALIDATION_DIR to an isolated writable directory.
 * CLI invocation exports the real registered route collection.
 */
declare(strict_types=1);

if (PDO::getAvailableDrivers() !== [] || extension_loaded('mysqli')) {
    throw new RuntimeException('Aborted: database drivers must not be loaded.');
}
$root = dirname(__DIR__, 2);
$isolated = getenv('PORTAL_VALIDATION_DIR');
if (!$isolated || !is_dir($isolated)) {
    throw new RuntimeException('PORTAL_VALIDATION_DIR must exist.');
}
$isolated = realpath($isolated);
foreach (['views', 'cache'] as $dir) {
    if (!is_dir($isolated.'/'.$dir)) {
        mkdir($isolated.'/'.$dir, 0700, true);
    }
}
$env = [
    'APP_ENV' => getenv('PORTAL_VALIDATION_REVIEW') === '1' ? 'local' : 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('v', 32)),
    'APP_URL' => 'http://127.0.0.1:8789',
    'APP_CONFIG_CACHE' => $isolated.'/never-load-config.php',
    'APP_ROUTES_CACHE' => $isolated.'/never-load-routes.php',
    'APP_SERVICES_CACHE' => $isolated.'/cache/services.php',
    'APP_PACKAGES_CACHE' => $isolated.'/cache/packages.php',
    'VIEW_COMPILED_PATH' => $isolated.'/views',
    'SESSION_DRIVER' => 'array',
    'SESSION_COOKIE' => 'portal_validation_only',
    'CACHE_STORE' => 'array',
    'LOG_CHANNEL' => 'single',
    'PERF_DIAG_ENABLED' => 'false',
    'DB_CONNECTION' => 'blocked',
    'DB_URL' => '',
    'MAIL_MAILER' => 'log',
];
foreach ($env as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if (is_file($env['APP_CONFIG_CACHE']) || is_file($env['APP_ROUTES_CACHE'])) {
    throw new RuntimeException('Aborted: unexpected validation cache.');
}

// Only known public assets are served; no arbitrary project files.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (PHP_SAPI === 'cli-server') {
    $assets = [
        '/portal-mock/portal.css' => 'text/css',
        '/portal-mock/portal.js' => 'text/javascript',
        '/images/logos/LogoRM.png' => 'image/png',
        '/favicon.ico' => 'image/x-icon',
    ];
    $manifest = json_decode(file_get_contents($root.'/public/build/manifest.json'), true);
    foreach ($manifest as $entry) {
        if (isset($entry['file'])) {
            $assets['/build/'.$entry['file']] = str_ends_with($entry['file'], '.css')
                ? 'text/css' : 'text/javascript';
        }
    }
    if (isset($assets[$path])) {
        header('Content-Type: '.$assets[$path]);
        readfile($root.'/public'.$path);
        return;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix(substr($isolated, 0, 3));
// Do not load the project's .env, even when it contains production credentials.
$app->loadEnvironmentFrom('__portal_validation_no_env__');
$app->afterBootstrapping(
    Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    function ($app) use ($isolated): void {
        $app['config']->set('database.default', 'blocked');
        $app['config']->set('database.connections', ['blocked' => ['driver' => 'blocked']]);
        $app['config']->set('logging.channels.single.path', $isolated.'/laravel.log');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.debug', false);
        // The optional review UI also remains unable to connect to a real DB.
        $app['config']->set('portal_review.enabled', getenv('PORTAL_VALIDATION_REVIEW') === '1');
        foreach (['database', 'username', 'password'] as $key) {
            $app['config']->set('portal_review.'.$key, null);
        }
    },
);
$app->extend('db.factory', function ($factory, $app) use ($isolated) {
    return new class($app, $isolated) extends Illuminate\Database\Connectors\ConnectionFactory {
        public function __construct($container, private string $isolated) { parent::__construct($container); }
        public function make(array $config, $name = null) {
            file_put_contents($this->isolated.'/blocked-effects.log', "DATABASE BLOCKED\n", FILE_APPEND);
            throw new RuntimeException('Database access is prohibited during portal validation.');
        }
    };
});
$app->extend('mail.manager', function () use ($isolated) {
    file_put_contents($isolated.'/blocked-effects.log', "MAIL BLOCKED\n", FILE_APPEND);
    throw new RuntimeException('Mail is prohibited during portal validation.');
});
$app->extend('queue', function () use ($isolated) {
    file_put_contents($isolated.'/blocked-effects.log', "QUEUE BLOCKED\n", FILE_APPEND);
    throw new RuntimeException('Queue dispatch is prohibited during portal validation.');
});
$app->afterBootstrapping(
    Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    function (): void {
        Illuminate\Support\Facades\Http::preventStrayRequests();
    },
);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
if (PHP_SAPI === 'cli') {
    $kernel->bootstrap();
    if (in_array('--review-tests', $argv, true)) {
        try {
            require __DIR__.'/portal_read_only_checks.php';
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage()."\n");
            exit(1);
        }
        exit;
    }
    $describe = static function ($routes): array {
        $result = [];
        foreach ($routes as $route) {
            $result[] = [
                'methods' => $route->methods(), 'uri' => $route->uri(),
                'name' => $route->getName(), 'action' => $route->getActionName(),
                'middleware' => $route->gatherMiddleware(), 'where' => $route->wheres,
            ];
        }
        return $result;
    };
    $actual = $describe($app['router']->getRoutes());
    // Re-register the unchanged administrative source on a fresh route collection.
    $original = $app['router']->getRoutes();
    $app['router']->setRoutes(new Illuminate\Routing\RouteCollection());
    Illuminate\Support\Facades\Route::middleware('web')->group($root.'/routes/web.php');
    $baseline = $describe($app['router']->getRoutes());
    $app['router']->setRoutes($original);
    $byName = [];
    foreach ($actual as $route) {
        $byName[$route['name'] ?? implode('|', $route['methods']).$route['uri']] = $route;
    }
    foreach ($baseline as $route) {
        $key = $route['name'] ?? implode('|', $route['methods']).$route['uri'];
        if (($byName[$key] ?? null) !== $route) {
            throw new RuntimeException('Administrative route changed: '.$key);
        }
    }
    echo json_encode([
        'database_drivers' => PDO::getAvailableDrivers(),
        'administrative_routes_unchanged' => count($baseline),
        'routes' => $actual,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
file_put_contents($isolated.'/requests.jsonl', json_encode([
    'method' => $request->method(), 'path' => $request->path(),
    'status' => $response->getStatusCode(),
    'route' => $request->route()?->getName(),
    'location' => $response->headers->get('Location'),
])."\n", FILE_APPEND);
$response->send();
$kernel->terminate($request, $response);
