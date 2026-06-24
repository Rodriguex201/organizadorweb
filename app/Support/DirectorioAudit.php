<?php

namespace App\Support;

use App\Models\ConfiguracionDirectorio;
use Illuminate\Support\Facades\Schema;

class DirectorioAudit
{
    private const REQUESTED_PATH = '\\\\192.168.1.150\\Soporte_00_Organizador_Empresas_Rm';

    public static function collect(): array
    {
        $configuredPath = self::configuredPath();
        $paths = [
            'requested_path' => self::REQUESTED_PATH,
        ];

        if ($configuredPath !== null && $configuredPath !== '' && $configuredPath !== self::REQUESTED_PATH) {
            $paths['configured_path'] = $configuredPath;
        }

        $pathChecks = [];

        foreach ($paths as $label => $path) {
            clearstatcache(true, $path);

            $pathChecks[$label] = [
                'path' => $path,
                'file_exists' => @file_exists($path),
                'is_dir' => @is_dir($path),
                'is_readable' => @is_readable($path),
                'is_writable' => @is_writable($path),
            ];
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'php_sapi' => PHP_SAPI,
            'php_version' => PHP_VERSION,
            'get_current_user' => get_current_user(),
            'whoami' => self::whoAmI(),
            'paths' => $pathChecks,
        ];
    }

    private static function configuredPath(): ?string
    {
        if (!Schema::hasTable('configuracion_directorio')) {
            return null;
        }

        $config = ConfiguracionDirectorio::query()->first();

        return trim((string) ($config?->ruta_clientes ?? ''));
    }

    private static function whoAmI(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        try {
            $output = shell_exec('whoami 2>NUL');
        } catch (\Throwable) {
            return null;
        }

        $value = trim((string) $output);

        return $value !== '' ? $value : null;
    }
}
