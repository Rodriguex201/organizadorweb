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
            $conexionesConBase = array_values(array_filter(
                ['mysql_213', 'mysql_167'],
                static fn (string $conexion): bool => ($debug[$conexion]['show_databases_ok'] ?? false) === true
            ));
            $conexionDetectada = null;

            if (count($conexionesConBase) > 1) {
                Log::warning('[ACTIVACION MYSQL DEBUG] BASE DUPLICADA EN MULTIPLES SERVIDORES', [
                    'codigo_original' => $debug['codigo_original'],
                    'codigo_normalizado' => $debug['codigo_normalizado'],
                    'conexiones' => $conexionesConBase,
                ]);
            }

            if (count($conexionesConBase) === 1) {
                $conexionDetectada = $conexionesConBase[0];
            } elseif (count($conexionesConBase) > 1) {
                $conexionesConRegistroGlobal = array_values(array_filter(
                    $conexionesConBase,
                    static fn (string $conexion): bool => ($debug[$conexion]['global_empresa_encontrada'] ?? false) === true
                ));

                if (count($conexionesConRegistroGlobal) === 1) {
                    $conexionDetectada = $conexionesConRegistroGlobal[0];
                } elseif (count($conexionesConRegistroGlobal) > 1) {
                    $conexionDetectada = $conexionesConRegistroGlobal[0];

                    Log::warning('[ACTIVACION MYSQL DEBUG] REGISTRO GLOBAL DUPLICADO EN MULTIPLES SERVIDORES', [
                        'codigo_original' => $debug['codigo_original'],
                        'codigo_normalizado' => $debug['codigo_normalizado'],
                        'conexiones' => $conexionesConRegistroGlobal,
                        'conexion_seleccionada' => $conexionDetectada,
                    ]);
                } else {
                    $conexionDetectada = $conexionesConBase[0];

                    Log::warning('[ACTIVACION MYSQL DEBUG] BASE DUPLICADA SIN REGISTRO GLOBAL ENCONTRADO', [
                        'codigo_original' => $debug['codigo_original'],
                        'codigo_normalizado' => $debug['codigo_normalizado'],
                        'conexiones' => $conexionesConBase,
                        'conexion_seleccionada' => $conexionDetectada,
                    ]);
                }
            }

            if ($conexionDetectada !== null) {
                Log::info('[ACTIVACION MYSQL DEBUG] ENCONTRADA EN '.$conexionDetectada, [
                    'codigo_original' => $debug['codigo_original'],
                    'codigo_normalizado' => $debug['codigo_normalizado'],
                    'conexion_intentada' => $conexionDetectada,
                    'motivo' => self::resolverMotivoSeleccion($debug, $conexionDetectada, $conexionesConBase),
                ]);
            }

            Log::info('[EMPRESA SERVIDOR]', [
                'codigo' => $debug['codigo_normalizado'],
                'conexion_detectada' => $conexionDetectada,
                'errores' => [
                    'mysql_213' => $debug['mysql_213']['error'] ?? null,
                    'mysql_167' => $debug['mysql_167']['error'] ?? null,
                ],
                'global_empresa_encontrada' => [
                    'mysql_213' => $debug['mysql_213']['global_empresa_encontrada'] ?? false,
                    'mysql_167' => $debug['mysql_167']['global_empresa_encontrada'] ?? false,
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
            'global_empresa_encontrada' => false,
            'global_empresa_registros' => [],
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
                self::cargarRegistroGlobal($conexion, $codigoOriginal, $resultado, $config);

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

            if ($resultado['show_databases_ok']) {
                self::cargarRegistroGlobal($conexion, $codigoOriginal, $resultado, $config);
            }

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

    private static function cargarRegistroGlobal(string $conexion, string $codigoOriginal, array &$resultado, array $config): void
    {
        $globalStart = microtime(true);
        $globalRows = DB::connection($conexion)->select(
            'SELECT emprobra, emprfinit, emprfpago FROM `empresas`.`empresas` WHERE emprobra = ? LIMIT 1',
            [$codigoOriginal],
        );

        $resultado['global_empresa_registros'] = self::normalizeRows($globalRows);
        $resultado['global_empresa_encontrada'] = $globalRows !== [];

        Log::info('[ACTIVACION MYSQL DEBUG] CONSULTA GLOBAL EMPRESAS', [
            'host' => (string) ($config['host'] ?? ''),
            'puerto' => (string) ($config['port'] ?? ''),
            'usuario' => (string) ($config['username'] ?? ''),
            'codigo_original' => $codigoOriginal,
            'conexion_intentada' => $conexion,
            'tiempo_ms' => self::elapsedMs($globalStart),
            'registro_encontrado' => $resultado['global_empresa_encontrada'],
            'resultado_crudo_completo' => $resultado['global_empresa_registros'],
        ]);
    }

    private static function resolverMotivoSeleccion(array $debug, string $conexionDetectada, array $conexionesConBase): string
    {
        if (count($conexionesConBase) === 1) {
            return 'base_encontrada_en_un_solo_servidor';
        }

        $conexionesConRegistroGlobal = array_values(array_filter(
            $conexionesConBase,
            static fn (string $conexion): bool => ($debug[$conexion]['global_empresa_encontrada'] ?? false) === true
        ));

        if (count($conexionesConRegistroGlobal) === 1) {
            return 'registro_global_encontrado_en_servidor_unico';
        }

        if (count($conexionesConRegistroGlobal) > 1) {
            return 'registro_global_duplicado_se_aplica_prioridad_deterministica';
        }

        return 'base_duplicada_sin_registro_global_se_aplica_prioridad_deterministica';
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
