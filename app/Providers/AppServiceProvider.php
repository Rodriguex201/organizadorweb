<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootPerformanceDiagnostics();
    }

    private function bootPerformanceDiagnostics(): void
    {
        if (!filter_var(env('PERF_DIAG_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        $slowMs = max(0, (float) env('PERF_DIAG_SLOW_MS', 0));

        DB::listen(function (QueryExecuted $query) use ($slowMs): void {
            $request = request();

            if (!$request || !$this->shouldTraceRequest($request->path(), $request->route()?->getName())) {
                return;
            }

            if (!$this->shouldTraceSql($query->sql) || $query->time < $slowMs) {
                return;
            }

            Log::info('performance.sql', [
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'filters' => $request->except([
                    '_token',
                    '_method',
                    'password',
                    'password_confirmation',
                ]),
                'connection' => $query->connectionName,
                'time_ms' => round($query->time, 2),
                'sql' => $query->sql,
                'bindings' => $query->bindings,
            ]);
        });
    }

    private function shouldTraceRequest(string $path, ?string $routeName): bool
    {
        $normalizedPath = '/'.trim($path, '/');

        if (in_array($routeName, [
            'proformas.index',
            'proformas.dashboard',
            'proformas.show',
            'cobros.index',
            'cobros.show',
            'cobros.proforma.preview',
            'cobros.proforma.store',
            'cobros.proforma.regenerar',
        ], true)) {
            return true;
        }

        foreach ([
            '/proformas',
            '/proformas/dashboard',
            '/cobros',
        ] as $prefix) {
            if ($normalizedPath === $prefix || str_starts_with($normalizedPath, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function shouldTraceSql(string $sql): bool
    {
        foreach ([
            'sg_proform',
            'sg_proford',
            'valores_externos',
            'clientes_potenciales',
        ] as $table) {
            if (str_contains($sql, $table)) {
                return true;
            }
        }

        return false;
    }
}
