<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RetencionFuenteConfigService
{
    private const TABLE = 'config_retenciones_fuente';

    public function all(): Collection
    {
        return DB::table(self::TABLE)->orderBy('porcentaje')->orderBy('id')->get();
    }

    /** Only the stored selection may remain available when inactive. */
    public function catalog(?int $currentId = null): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($query) use ($currentId): void {
                $query->where('activo', 1);
                if ($currentId !== null) $query->orWhere('id', $currentId);
            })->orderBy('porcentaje')->orderBy('id')->get(['id', 'porcentaje', 'descripcion', 'activo']);

        return [
            'options' => $rows->map(fn ($row) => [
                'id' => (string) $row->id,
                'label' => $row->porcentaje.' %'.($row->descripcion ? ' — '.$row->descripcion : '').((int) $row->activo === 1 ? '' : ' (inactiva)'),
            ])->all(),
            'ids' => $rows->map(fn ($row) => (string) $row->id)->all(),
        ];
    }

    public function selectionRules(array $catalog): array
    {
        return ['nullable', 'integer', Rule::in($catalog['ids'])];
    }

    public function save(array $input, ?int $id = null): void
    {
        if ($id !== null) abort_unless(DB::table(self::TABLE)->where('id', $id)->exists(), 404);
        $unique = Rule::unique(self::TABLE, 'porcentaje');
        if ($id !== null) $unique->ignore($id);
        $data = Validator::make($input, [
            'porcentaje' => ['required', 'numeric', 'between:0,100', 'decimal:0,3', $unique],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ], [
            'porcentaje.unique' => 'Ya existe una retención con ese porcentaje, aunque esté inactiva.',
            'porcentaje.between' => 'El porcentaje debe estar entre 0 y 100.',
            'porcentaje.decimal' => 'El porcentaje admite hasta tres decimales.',
        ])->validate();
        $data['porcentaje'] = number_format((float) $data['porcentaje'], 3, '.', '');
        $data['descripcion'] = $data['descripcion'] ?? null;
        $data['activo'] = (int) $data['activo'];
        // Explicit timestamps also make persistence portable in isolated SQLite tests.
        $data['updated_at'] = now();
        try {
            if ($id === null) {
                $data['created_at'] = now();
                DB::table(self::TABLE)->insert($data);
            } else {
                DB::table(self::TABLE)->where('id', $id)->update($data);
            }
        } catch (UniqueConstraintViolationException $exception) {
            // The existing UNIQUE index handles simultaneous requests, too.
            throw ValidationException::withMessages(['porcentaje' => 'Ya existe una retención con ese porcentaje.']);
        }
    }

    public function setActive(int $id, bool $active): void
    {
        abort_unless(DB::table(self::TABLE)->where('id', $id)->exists(), 404);
        DB::table(self::TABLE)->where('id', $id)->update(['activo' => (int) $active, 'updated_at' => now()]);
    }
}
