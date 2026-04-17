<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EstadoProformaConfigService
{
    private static ?array $cachedMap = null;
    private static ?Collection $cachedAll = null;
    private static bool $defaultsChecked = false;

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

        $this->flushCache();
    }

    public function all(): Collection
    {
        if (self::$cachedAll instanceof Collection) {
            return self::$cachedAll;
        }

        $this->ensureDefaultsIfNeeded();

        self::$cachedAll = DB::table('configuracion_estados_proforma')
            ->whereIn('estado_codigo', array_keys(self::DEFAULTS))
            ->orderBy('estado_codigo')
            ->get();

        return self::$cachedAll;
    }

    public function getMap(): array
    {
        if (self::$cachedMap !== null) {
            return self::$cachedMap;
        }

        self::$cachedMap = $this->all()
            ->keyBy(fn ($row) => (int) $row->estado_codigo)
            ->map(fn ($row) => [
                'estado_nombre' => (string) $row->estado_nombre,
                'color_fondo' => (string) $row->color_fondo,
                'color_texto' => (string) $row->color_texto,
                'activo' => (int) $row->activo === 1,
            ])
            ->all();

        return self::$cachedMap;
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

        $this->flushCache();
    }

    private function ensureDefaultsIfNeeded(): void
    {
        if (self::$defaultsChecked) {
            return;
        }

        $count = DB::table('configuracion_estados_proforma')
            ->whereIn('estado_codigo', array_keys(self::DEFAULTS))
            ->count();

        if ((int) $count === 0) {
            $this->syncDefaults();
        }

        self::$defaultsChecked = true;
    }

    private function flushCache(): void
    {
        self::$cachedMap = null;
        self::$cachedAll = null;
        self::$defaultsChecked = false;
    }
}
