<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmpresaServidorService
{
    public static function obtenerBaseEmpresa(string $codigo): string
    {
        return self::normalizarCodigo($codigo);
    }

    /**
     * PREPARADO PARA MODULO DE ACTIVACIONES.
     */
    public static function resolverConexionPorCodigo(string $codigo): string
    {
        try {
            $debug = self::debugConexionPorCodigo($codigo);
            $conexionDetectada = null;

            foreach (['mysql_213', 'mysql_167'] as $conexion) {
                if (($debug[$conexion]['show_databases_ok'] ?? false) === true) {
                    $conexionDetectada = $conexion;
                    Log::info('[ACTIVACION MYSQL DEBUG] ENCONTRADA EN '.$conexion, [
                        'codigo_original' => $debug['codigo_original'],
                        'codigo_normalizado' => $debug['codigo_normalizado'],
                        'conexion_intentada' => $conexion,
                    ]);
                    break;
                }
            }

            Log::info('[EMPRESA SERVIDOR]', [
                'codigo' => $debug['codigo_normalizado'],
                'conexion_detectada' => $conexionDetectada,
                'errores' => [
                    'mysql_213' => $debug['mysql_213']['error'] ?? null,
                    'mysql_167' => $debug['mysql_167']['error'] ?? null,
                ],
            ]);

            if ($conexionDetectada !== null) {
                return $conexionDetectada;
            }

            throw new Exception('Empresa no encontrada');
        } finally {
            self::liberarConexionesExternas();
        }
    }

    public static function debugConexionPorCodigo(string $codigo): array
    {
        try {
            $codigoOriginal = $codigo;
            $codigoNormalizado = self::obtenerBaseEmpresa($codigo);

            return [
                'codigo_original' => $codigoOriginal,
                'codigo_normalizado' => $codigoNormalizado,
                'mysql_213' => self::inspeccionarConexion('mysql_213', $codigoOriginal, $codigoNormalizado),
                'mysql_167' => self::inspeccionarConexion('mysql_167', $codigoOriginal, $codigoNormalizado),
            ];
        } finally {
            self::liberarConexionesExternas();
        }
    }

    private static function inspeccionarConexion(string $conexion, string $codigoOriginal, string $codigoNormalizado): array
    {
        self::aplicarTimeoutTemporal($conexion);

        $config = config("database.connections.{$conexion}", []);
        $resultado = [
            'conexion_ok' => false,
            'show_databases_ok' => false,
            'databases_encontradas' => [],
            'information_schema_encontradas' => [],
            'error' => null,
        ];

        Log::info('[ACTIVACION MYSQL DEBUG]', [
            'host' => (string) ($config['host'] ?? ''),
            'puerto' => (string) ($config['port'] ?? ''),
            'usuario' => (string) ($config['username'] ?? ''),
            'codigo_normalizado' => $codigoNormalizado,
            'conexion_intentada' => $conexion,
        ]);

        try {
            DB::purge($conexion);

            $simpleStart = microtime(true);
            DB::connection($conexion)->select('SELECT 1');
            $resultado['conexion_ok'] = true;

            Log::info('[ACTIVACION MYSQL DEBUG] CONEXION SIMPLE OK', [
                'host' => (string) ($config['host'] ?? ''),
                'puerto' => (string) ($config['port'] ?? ''),
                'usuario' => (string) ($config['username'] ?? ''),
                'codigo_normalizado' => $codigoNormalizado,
                'conexion_intentada' => $conexion,
                'tiempo_ms' => self::elapsedMs($simpleStart),
            ]);

            Log::info('[ACTIVACION MYSQL DEBUG] INTENTANDO SHOW DATABASES', [
                'host' => (string) ($config['host'] ?? ''),
                'puerto' => (string) ($config['port'] ?? ''),
                'usuario' => (string) ($config['username'] ?? ''),
                'codigo_normalizado' => $codigoNormalizado,
                'conexion_intentada' => $conexion,
            ]);

            $showStart = microtime(true);
            $database = addslashes($codigoNormalizado);
            $sqlShowDatabases = "SHOW DATABASES LIKE '{$database}'";
            $resultadoShowDatabases = DB::connection($conexion)->select($sqlShowDatabases);
            $resultado['databases_encontradas'] = self::normalizeRows($resultadoShowDatabases);

            Log::info('[ACTIVACION MYSQL DEBUG]', [
                'host' => (string) ($config['host'] ?? ''),
                'puerto' => (string) ($config['port'] ?? ''),
                'usuario' => (string) ($config['username'] ?? ''),
                'codigo_normalizado' => $codigoNormalizado,
                'conexion_intentada' => $conexion,
                'tiempo_ms' => self::elapsedMs($showStart),
                'resultado_crudo_completo' => $resultado['databases_encontradas'],
            ]);

            if ($resultadoShowDatabases !== []) {
                $resultado['show_databases_ok'] = true;

                return $resultado;
            }

            $schemaStart = microtime(true);
            $resultadoInformationSchema = DB::connection($conexion)->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$codigoNormalizado],
            );
            $resultado['information_schema_encontradas'] = self::normalizeRows($resultadoInformationSchema);

            Log::info('[ACTIVACION MYSQL DEBUG]', [
                'host' => (string) ($config['host'] ?? ''),
                'puerto' => (string) ($config['port'] ?? ''),
                'usuario' => (string) ($config['username'] ?? ''),
                'codigo_normalizado' => $codigoNormalizado,
                'conexion_intentada' => $conexion,
                'tiempo_ms' => self::elapsedMs($schemaStart),
                'resultado_crudo_completo' => $resultado['information_schema_encontradas'],
            ]);

            $resultado['show_databases_ok'] = $resultadoInformationSchema !== [];

            return $resultado;
        } catch (Throwable $exception) {
            $resultado['error'] = self::formatException($exception);

            Log::error('[ACTIVACION MYSQL DEBUG] CONEXION SIMPLE ERROR', [
                'codigo_original' => $codigoOriginal,
                'codigo_normalizado' => $codigoNormalizado,
                'conexion_intentada' => $conexion,
                'host' => (string) ($config['host'] ?? ''),
                'puerto' => (string) ($config['port'] ?? ''),
                'usuario' => (string) ($config['username'] ?? ''),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace_resumido' => self::traceResumen($exception),
            ]);

            return $resultado;
        } finally {
            DB::disconnect($conexion);
            DB::purge($conexion);
        }
    }

    private static function aplicarTimeoutTemporal(string $conexion): void
    {
        $config = config("database.connections.{$conexion}", []);
        $options = $config['options'] ?? [];
        $options[\PDO::ATTR_TIMEOUT] = 5;
        $config['options'] = $options;

        config(["database.connections.{$conexion}" => $config]);
    }

    private static function normalizeRows(array $rows): array
    {
        return array_map(static fn (object $row): array => (array) $row, $rows);
    }

    private static function formatException(Throwable $exception): array
    {
        return [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace_resumido' => self::traceResumen($exception),
        ];
    }

    private static function traceResumen(Throwable $exception): array
    {
        return array_slice(
            array_map(
                static fn (array $item): array => [
                    'file' => $item['file'] ?? null,
                    'line' => $item['line'] ?? null,
                    'function' => $item['function'] ?? null,
                    'class' => $item['class'] ?? null,
                ],
                $exception->getTrace(),
            ),
            0,
            8,
        );
    }

    private static function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    private static function normalizarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        $codigo = strtolower($codigo);

        return preg_replace('/[^a-z0-9]/', '', $codigo) ?? '';
    }

    private static function liberarConexionesExternas(): void
    {
        DB::disconnect('mysql_213');
        DB::purge('mysql_213');

        DB::disconnect('mysql_167');
        DB::purge('mysql_167');
    }
}
