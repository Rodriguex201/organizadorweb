<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmpresaServidorService
{
    /**
     * PREPARADO PARA MODULO DE ACTIVACIONES.
     */
    public static function resolverConexionPorCodigo(string $codigo): string
    {
        $baseEmpresa = obtenerBaseEmpresa($codigo);
        $inicio = microtime(true);
        $conexionDetectada = null;
        $errores = [];

        foreach (['mysql_213', 'mysql_167'] as $conexion) {
            try {
                if (self::baseExisteEnConexion($conexion, $baseEmpresa)) {
                    $conexionDetectada = $conexion;
                    break;
                }
            } catch (Throwable $exception) {
                $errores[$conexion] = $exception->getMessage();
            } finally {
                DB::disconnect($conexion);
            }
        }

        $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);

        Log::info('[EMPRESA SERVIDOR]', [
            'codigo' => $baseEmpresa,
            'conexion_detectada' => $conexionDetectada,
            'tiempo_ms' => $tiempoMs,
            'errores' => $errores,
        ]);

        if ($conexionDetectada !== null) {
            return $conexionDetectada;
        }

        throw new Exception('Empresa no encontrada');
    }

    /**
     * PREPARADO PARA MODULO DE ACTIVACIONES.
     */
    protected static function baseExisteEnConexion(string $conexion, string $baseEmpresa): bool
    {
        DB::purge($conexion);

        $resultado = DB::connection($conexion)->select(
            'SHOW DATABASES LIKE ?',
            [$baseEmpresa],
        );

        return $resultado !== [];
    }
}
