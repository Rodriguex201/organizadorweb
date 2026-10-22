<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/portal.php';
            require __DIR__.'/../routes/bold_webhook.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Preserve webhook bytes and avoid JSON parsing by global input transforms.
        $webhook = fn (\Illuminate\Http\Request $request): bool => $request->is('portal/pagos/bold/webhook');
        $middleware->trimStrings(except: [$webhook]);
        $middleware->convertEmptyStringsToNull(except: [$webhook]);
        $middleware->alias([
            'auth.custom' => \App\Http\Middleware\CustomAuth::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'role.admin' => \App\Http\Middleware\RoleAdminMiddleware::class,
            'role.user' => \App\Http\Middleware\RoleUserMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
