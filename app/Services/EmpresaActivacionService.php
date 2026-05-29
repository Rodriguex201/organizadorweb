<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EmpresaActivacionService
{
    private const TABLA_INDIVIDUAL = 'xxxxsegx';
    private const SEG_CLAVE_TEMPORAL = 'dropzxz';
    private const ADVERTENCIA_REGISTRO_INDIVIDUAL = 'No existe registro individual de activación. Se utilizarán las fechas globales como referencia. Al guardar se creará el registro individual.';

    public function obtenerDetalle(string $codigo): array
    {
        try {
            $contexto = $this->resolverContexto($codigo);
            $actuales = $this->consultarFechasActuales(
                $contexto['conexion'],
                $contexto['codigo'],
                $contexto['base'],
            );

            return array_merge($contexto, $actuales);
        } finally {
            $this->liberarConexionesExternas();
        }
    }

    public function guardarActivacion(string $codigo, string $fechaInicio, string $fechaFin, string $usuario): array
    {
        $payloadLog = [
            'codigo' => strtoupper(trim($codigo)),
            'conexion' => null,
            'base' => null,
            'fecha_inicio_anterior' => null,
            'fecha_fin_anterior' => null,
            'fecha_inicio_nueva' => $fechaInicio,
            'fecha_fin_nueva' => $fechaFin,
            'usuario' => $usuario,
            'errores' => null,
        ];

        try {
            $contexto = $this->resolverContexto($codigo);
            $actuales = $this->consultarFechasActuales(
                $contexto['conexion'],
                $contexto['codigo'],
                $contexto['base'],
            );

            $payloadLog = [
                'codigo' => $contexto['codigo'],
                'conexion' => $contexto['conexion'],
                'base' => $contexto['base'],
                'fecha_inicio_anterior' => $actuales['fecha_inicio_actual'],
                'fecha_fin_anterior' => $actuales['fecha_fin_actual'],
                'fecha_inicio_nueva' => $fechaInicio,
                'fecha_fin_nueva' => $fechaFin,
                'usuario' => $usuario,
                'errores' => null,
            ];

            DB::connection($contexto['conexion'])->transaction(function () use ($contexto, $fechaInicio, $fechaFin): void {
                $tablaIndividual = $this->tablaCalificada($contexto['base'], self::TABLA_INDIVIDUAL);
                $registroIndividualExiste = DB::connection($contexto['conexion'])->selectOne(
                    "SELECT seg_clave FROM {$tablaIndividual} LIMIT 1"
                ) !== null;

                if ($registroIndividualExiste) {
                    DB::connection($contexto['conexion'])->update(
                        "UPDATE {$tablaIndividual} SET seg_fecha = ?, seg_maxima = ? LIMIT 1",
                        [$fechaInicio, $fechaFin],
                    );
                } else {
                    DB::connection($contexto['conexion'])->insert(
                        "INSERT INTO {$tablaIndividual} (seg_clave, seg_fecha, seg_maxima) VALUES (?, ?, ?)",
                        [self::SEG_CLAVE_TEMPORAL, $fechaInicio, $fechaFin],
                    );
                }

                $filasGlobales = DB::connection($contexto['conexion'])->update(
                    'UPDATE `empresas`.`empresas` SET emprfinit = ?, emprfpago = ? WHERE emprobra = ?',
                    [$fechaInicio, $fechaFin, $contexto['codigo']],
                );

                if ($filasGlobales < 1) {
                    throw new RuntimeException('No se encontró el registro global de la empresa para actualizar la activación.');
                }
            });

            Log::info('[ACTIVACION EMPRESA] ACTUALIZACION EXITOSA', $payloadLog);

            return array_merge($contexto, [
                'fecha_inicio_actual' => $fechaInicio,
                'fecha_fin_actual' => $fechaFin,
                'fecha_inicio_global_actual' => $fechaInicio,
                'fecha_fin_global_actual' => $fechaFin,
                'sincronizado' => true,
                'registro_individual_existe' => true,
                'registro_individual_creado' => !$actuales['registro_individual_existe'],
                'warning_message' => null,
                'hay_diferencias_final' => false,
            ]);
        } catch (Throwable $exception) {
            $payloadLog['errores'] = $exception->getMessage();

            Log::error('[ACTIVACION EMPRESA] ERROR', $payloadLog);

            throw $exception;
        } finally {
            $this->liberarConexionesExternas();
        }
    }

    private function resolverContexto(string $codigo): array
    {
        $codigoNormalizado = strtoupper(trim($codigo));

        if ($codigoNormalizado === '') {
            throw new RuntimeException('La proforma no tiene un código de empresa válido para consultar la activación.');
        }

        try {
            $conexion = EmpresaServidorService::resolverConexionPorCodigo($codigoNormalizado);
        } catch (Throwable $exception) {
            throw new RuntimeException('No fue posible encontrar la empresa en los servidores configurados.');
        }

        $base = EmpresaServidorService::obtenerBaseEmpresa($codigoNormalizado);

        return [
            'codigo' => $codigoNormalizado,
            'conexion' => $conexion,
            'base' => $base,
            'servidor' => $this->servidorLabel($conexion),
            'servidor_badge' => $this->servidorBadge($conexion),
        ];
    }

    private function consultarFechasActuales(string $conexion, string $codigo, string $base): array
    {
        $tablaIndividual = $this->tablaCalificada($base, self::TABLA_INDIVIDUAL);

        $individual = DB::connection($conexion)->selectOne(
            "SELECT seg_fecha, seg_maxima FROM {$tablaIndividual} LIMIT 1"
        );

        $global = DB::connection($conexion)->selectOne(
            'SELECT emprfpago, emprfinit FROM `empresas`.`empresas` WHERE emprobra = ? LIMIT 1',
            [$codigo],
        );

        if ($global === null) {
            throw new RuntimeException('No se encontraron fechas de activación en la tabla global de empresas.');
        }

        Log::info('[ACTIVACION EMPRESA] empresa global encontrada por emprobra', [
            'codigo' => $codigo,
            'conexion' => $conexion,
            'base' => $base,
        ]);

        $registroIndividualExiste = $individual !== null;
        $fechaInicioIndividual = $this->normalizarFecha($individual->seg_fecha ?? null);
        $fechaFinIndividual = $this->normalizarFecha($individual->seg_maxima ?? null);
        $fechaInicioGlobal = $this->normalizarFecha($global->emprfinit ?? null);
        $fechaFinGlobal = $this->normalizarFecha($global->emprfpago ?? null);
        $fechaInicioReferencia = $registroIndividualExiste ? $fechaInicioIndividual : $fechaFinGlobal;
        $fechaFinReferencia = $registroIndividualExiste ? $fechaFinIndividual : $fechaInicioGlobal;
        $comparacion1 = $fechaInicioIndividual === $fechaFinGlobal;
        $comparacion2 = $fechaFinIndividual === $fechaInicioGlobal;
        $hayDiferencias = $registroIndividualExiste && (!$comparacion1 || !$comparacion2);

        Log::info('[ACTIVACION FECHAS DEBUG]', [
            'registro_individual_existe' => $registroIndividualExiste,
            'seg_fecha_raw' => $individual->seg_fecha ?? null,
            'seg_maxima_raw' => $individual->seg_maxima ?? null,
            'emprfpago_raw' => $global->emprfpago ?? null,
            'emprfinit_raw' => $global->emprfinit ?? null,
            'seg_fecha_normalizada' => $fechaInicioIndividual,
            'seg_maxima_normalizada' => $fechaFinIndividual,
            'emprfpago_normalizada' => $fechaFinGlobal,
            'emprfinit_normalizada' => $fechaInicioGlobal,
            'comparacion_1' => [
                'columnas' => 'seg_fecha vs emprfpago',
                'resultado' => $comparacion1,
            ],
            'comparacion_2' => [
                'columnas' => 'seg_maxima vs emprfinit',
                'resultado' => $comparacion2,
            ],
            'hay_diferencias_final' => $hayDiferencias,
        ]);

        return [
            'fecha_inicio_actual' => $fechaInicioReferencia,
            'fecha_fin_actual' => $fechaFinReferencia,
            'fecha_inicio_global_actual' => $fechaInicioGlobal,
            'fecha_fin_global_actual' => $fechaFinGlobal,
            'sincronizado' => $registroIndividualExiste
                ? ($fechaInicioIndividual === $fechaInicioGlobal
                    && $fechaFinIndividual === $fechaFinGlobal)
                : false,
            'registro_individual_existe' => $registroIndividualExiste,
            'registro_individual_creado' => false,
            'warning_message' => $registroIndividualExiste ? null : self::ADVERTENCIA_REGISTRO_INDIVIDUAL,
            'hay_diferencias_final' => $hayDiferencias,
        ];
    }

    private function tablaCalificada(string $base, string $tabla): string
    {
        return $this->identificador($base).'.'.$this->identificador($tabla);
    }

    private function identificador(string $valor): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $valor)) {
            throw new RuntimeException('Se detectó un nombre de base o tabla inválido para la activación.');
        }

        return '`'.$valor.'`';
    }

    private function normalizarFecha(mixed $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        $valor = trim((string) $fecha);

        if ($valor === '' || $valor === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (Throwable) {
            return substr($valor, 0, 10);
        }
    }

    private function servidorLabel(string $conexion): string
    {
        return match ($conexion) {
            'mysql_213' => 'Servidor 213',
            'mysql_167' => 'Servidor 167',
            default => 'Servidor detectado',
        };
    }

    private function servidorBadge(string $conexion): string
    {
        return match ($conexion) {
            'mysql_213' => '213',
            'mysql_167' => '167',
            default => 'N/D',
        };
    }

    private function liberarConexionesExternas(): void
    {
        DB::disconnect('mysql_213');
        DB::purge('mysql_213');

        DB::disconnect('mysql_167');
        DB::purge('mysql_167');
    }
}
