<?php

namespace App\Services;

use App\Models\Concepto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConceptosConfigService
{
    /**
     * Códigos oficiales usados por preview/generación actual.
     *
     * @var array<int, string>
     */
    private const PREVIEW_PROTECTED_CODES = ['0010', '0011', '0099', '0081', '0101', '0102', 'EXTRA'];

    /**
     * @return Collection<int, Concepto>
     */
    public function all(): Collection
    {
        return Concepto::query()
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function allWithUsage(): Collection
    {
        return $this->all()->map(function (Concepto $concepto): array {
            $usage = $this->usageSummary($concepto);

            return [
                'concepto' => $concepto,
                'usage' => $usage,
            ];
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Concepto
    {
        return Concepto::query()->create([
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'cuenta' => $data['cuenta'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Concepto $concepto, array $data): Concepto
    {
        $nuevoCodigo = (string) $data['codigo'];

        if ($nuevoCodigo !== $concepto->codigo && $this->blocksCodeMutation($concepto)) {
            throw ValidationException::withMessages([
                'codigo' => 'No se puede cambiar el código de un concepto usado en proformas o protegido por el preview actual. Solo puede editar nombre, cuenta o estado.',
            ]);
        }

        $concepto->fill([
            'codigo' => $nuevoCodigo,
            'nombre' => $data['nombre'],
            'cuenta' => $data['cuenta'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ]);

        $concepto->save();

        return $concepto->refresh();
    }

    /**
     * @return array{deleted:bool,message:string,status_type:string}
     */
    public function delete(Concepto $concepto): array
    {
        $usage = $this->usageSummary($concepto);

        if ($usage['can_delete'] === false) {
            return [
                'deleted' => false,
                'message' => 'El concepto no se puede eliminar porque ya está en uso o hace parte del catálogo base de preview/generación. Solo se permite desactivarlo.',
                'status_type' => 'warning',
            ];
        }

        $concepto->delete();

        return [
            'deleted' => true,
            'message' => 'Concepto eliminado correctamente.',
            'status_type' => 'success',
        ];
    }

    public function toggleActive(Concepto $concepto): bool
    {
        $concepto->activo = !$concepto->activo;
        $concepto->save();

        return (bool) $concepto->activo;
    }

    /**
     * @return array{used_in_sg_proford:bool,sg_proford_count:int,used_in_preview:bool,can_delete:bool}
     */
    public function usageSummary(Concepto $concepto): array
    {
        $codigo = trim((string) $concepto->codigo);
        $sgProfordCount = $this->countUsageInSgProford($codigo);
        $usedInPreview = in_array($codigo, self::PREVIEW_PROTECTED_CODES, true);

        return [
            'used_in_sg_proford' => $sgProfordCount > 0,
            'sg_proford_count' => $sgProfordCount,
            'used_in_preview' => $usedInPreview,
            'can_delete' => $sgProfordCount === 0 && !$usedInPreview,
        ];
    }

    private function countUsageInSgProford(string $codigo): int
    {
        return (int) DB::table('sg_proford')
            ->where('ref_codigo', $codigo)
            ->count();
    }

    private function blocksCodeMutation(Concepto $concepto): bool
    {
        $usage = $this->usageSummary($concepto);

        return $usage['used_in_sg_proford'] || $usage['used_in_preview'];
    }
}
