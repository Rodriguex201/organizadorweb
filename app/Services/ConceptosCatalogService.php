<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConceptosCatalogService
{
    /**
     * @var array<string, array{codigo:string,nombre:string,cuenta:mixed,activo:mixed}>
     */
    private array $cache = [];

    /**
     * @param array<int, string> $codigos
     * @return array<string, array{codigo:string,nombre:string,cuenta:mixed,activo:mixed}>
     */
    public function findByCodes(array $codigos): array
    {
        $codigosNormalizados = array_values(array_unique(array_filter(array_map(
            fn ($codigo) => trim((string) $codigo),
            $codigos,
        ))));

        $faltantes = array_values(array_filter(
            $codigosNormalizados,
            fn (string $codigo) => !isset($this->cache[$codigo]),
        ));

        if ($faltantes !== []) {
            $registros = DB::table('conceptos')
                ->select('codigo', 'nombre', 'cuenta', 'activo')
                ->whereIn('codigo', $faltantes)
                ->get();

            foreach ($registros as $concepto) {
                $this->cache[(string) $concepto->codigo] = [
                    'codigo' => (string) $concepto->codigo,
                    'nombre' => (string) $concepto->nombre,
                    'cuenta' => $concepto->cuenta ?? null,
                    'activo' => $concepto->activo ?? null,
                ];
            }
        }

        $resultado = [];
        foreach ($codigosNormalizados as $codigo) {
            if (isset($this->cache[$codigo])) {
                $resultado[$codigo] = $this->cache[$codigo];
            }
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{codigo:string,nombre:string,cuenta:mixed,activo:mixed,exists:bool}
     */
    public function resolve(string $codigo, ?string $fallbackNombre = null, array $context = []): array
    {
        $codigoNormalizado = trim($codigo);
        $catalogo = $this->findByCodes([$codigoNormalizado]);

        if (isset($catalogo[$codigoNormalizado])) {
            $concepto = $catalogo[$codigoNormalizado];

            if (!$this->isActivo($concepto['activo'])) {
                Log::warning('Concepto encontrado en tabla conceptos pero marcado como inactivo.', [
                    'codigo' => $codigoNormalizado,
                    'nombre' => $concepto['nombre'],
                    'cuenta' => $concepto['cuenta'],
                    'activo' => $concepto['activo'],
                    'context' => $context,
                ]);
            }

            return $concepto + ['exists' => true];
        }

        Log::warning('Concepto no encontrado en tabla conceptos. Se usa fallback no fatal.', [
            'codigo' => $codigoNormalizado,
            'context' => $context,
        ]);

        return [
            'codigo' => $codigoNormalizado,
            'nombre' => $fallbackNombre !== null && trim($fallbackNombre) !== ''
                ? trim($fallbackNombre)
                : 'Concepto no configurado',
            'cuenta' => null,
            'activo' => null,
            'exists' => false,
        ];
    }

    private function isActivo(mixed $activo): bool
    {
        if ($activo === null) {
            return true;
        }

        $valor = strtolower(trim((string) $activo));

        return !in_array($valor, ['0', 'false', 'inactivo', 'no'], true);
    }
}
