<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EstadoProformaConfigService
{
    public const DEFAULTS = [
        2 => ['estado_nombre' => 'Generada', 'color_fondo' => '#DBEAFE', 'color_texto' => '#1D4ED8'],
        3 => ['estado_nombre' => 'Enviada', 'color_fondo' => '#E0E7FF', 'color_texto' => '#3730A3'],
        4 => ['estado_nombre' => 'Pagada', 'color_fondo' => '#D1FAE5', 'color_texto' => '#047857'],
        6 => ['estado_nombre' => 'Facturada', 'color_fondo' => '#F3E8FF', 'color_texto' => '#7E22CE'],
    ];

    public function syncDefaults(): void
    {
        $now = now();

        foreach (self::DEFAULTS as $codigo => $default) {
            DB::table('configuracion_estados_proforma')->updateOrInsert(
                ['estado_codigo' => $codigo],
                [
                    'estado_nombre' => $default['estado_nombre'],
                    'color_fondo' => $default['color_fondo'],
                    'color_texto' => $default['color_texto'],
                    'activo' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function all(): Collection
    {
        $this->syncDefaults();

        return DB::table('configuracion_estados_proforma')
            ->whereIn('estado_codigo', array_keys(self::DEFAULTS))
            ->orderBy('estado_codigo')
            ->get();
    }

    public function getMap(): array
    {
        return $this->all()
            ->keyBy(fn ($row) => (int) $row->estado_codigo)
            ->map(fn ($row) => [
                'estado_nombre' => (string) $row->estado_nombre,
                'color_fondo' => (string) $row->color_fondo,
                'color_texto' => (string) $row->color_texto,
                'activo' => (int) $row->activo === 1,
            ])
            ->all();
    }

    public function updateColors(int $estadoCodigo, string $colorFondo, string $colorTexto): void
    {
        $default = self::DEFAULTS[$estadoCodigo] ?? null;
        if (!$default) {
            return;
        }

        DB::table('configuracion_estados_proforma')
            ->updateOrInsert(
                ['estado_codigo' => $estadoCodigo],
                [
                    'estado_nombre' => $default['estado_nombre'],
                    'color_fondo' => $colorFondo,
                    'color_texto' => $colorTexto,
                    'activo' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
    }
}
