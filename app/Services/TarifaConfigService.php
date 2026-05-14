<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TarifaConfigService
{
    public const GLOBAL_CATEGORY = 'global';

    /**
     * @var array<int, array<string, mixed>>
     */
    public const DEFAULTS = [
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrprincipal', 'descripcion' => 'Valor principal por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 10],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'numequipos', 'descripcion' => 'Numero de equipos por defecto.', 'valor' => 1, 'activo' => true, 'orden' => 20],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrterminal', 'descripcion' => 'Valor terminal por equipo adicional.', 'valor' => 0, 'activo' => true, 'orden' => 30],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrterminal_recepcion', 'descripcion' => 'Valor terminal recepcion por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 40],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrnomina', 'descripcion' => 'Valor principal nomina por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 50],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'nominaterminal', 'descripcion' => 'Numero de equipos nomina por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 60],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrterminal_nomina', 'descripcion' => 'Valor terminal nomina por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 70],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrrecepcion', 'descripcion' => 'Precio acuse o valor recepcion por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 80],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrfactura', 'descripcion' => 'Valor factura por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 90],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrsoporte', 'descripcion' => 'Valor soporte por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 100],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrextra', 'descripcion' => 'Otro valor extra por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 110],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'numeromoviles', 'descripcion' => 'Numero de moviles por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 120],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrmovil', 'descripcion' => 'Valor movil por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 130],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'numextra', 'descripcion' => 'Numero de equipos extra por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 140],
        ['categoria' => self::GLOBAL_CATEGORY, 'clave' => 'vlrextrae', 'descripcion' => 'Valor de equipo extra por defecto.', 'valor' => 0, 'activo' => true, 'orden' => 150],
    ];

    public function syncDefaults(): void
    {
        if (!Schema::hasTable('config_tarifas')) {
            return;
        }

        $now = now();

        foreach (self::DEFAULTS as $default) {
            $existing = DB::table('config_tarifas')
                ->where('categoria', $default['categoria'])
                ->where('clave', $default['clave'])
                ->first();

            DB::table('config_tarifas')->updateOrInsert(
                [
                    'categoria' => $default['categoria'],
                    'clave' => $default['clave'],
                ],
                [
                    'valor' => $existing?->valor ?? $default['valor'],
                    'descripcion' => $default['descripcion'],
                    'activo' => $existing?->activo ?? ($default['activo'] ? 1 : 0),
                    'orden' => $default['orden'],
                    'updated_at' => $now,
                    'created_at' => $existing?->created_at ?? $now,
                ],
            );
        }
    }

    /**
     * @return Collection<int, object>
     */
    public function all(string $categoria = self::GLOBAL_CATEGORY): Collection
    {
        if (!Schema::hasTable('config_tarifas')) {
            return collect();
        }

        $this->syncDefaults();

        return DB::table('config_tarifas')
            ->where('categoria', $categoria)
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function clientCreateDefaults(): array
    {
        return $this->all()
            ->filter(fn (object $row): bool => (int) ($row->activo ?? 0) === 1)
            ->mapWithKeys(function (object $row): array {
                return [$this->resolveClientFieldName((string) $row->clave) => $this->normalizeNumericForInput($row->valor)];
            })
            ->all();
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     */
    public function updateMany(array $rows): void
    {
        if (!Schema::hasTable('config_tarifas')) {
            return;
        }

        $this->syncDefaults();
        $indexedDefaults = collect(self::DEFAULTS)->keyBy('clave');

        DB::transaction(function () use ($rows, $indexedDefaults): void {
            $now = now();

            foreach ($indexedDefaults as $clave => $default) {
                $row = $rows[$clave] ?? [];

                DB::table('config_tarifas')->updateOrInsert(
                    [
                        'categoria' => (string) $default['categoria'],
                        'clave' => (string) $clave,
                    ],
                    [
                        'valor' => $this->normalizeNumericForDatabase($row['valor'] ?? $default['valor']),
                        'descripcion' => (string) $default['descripcion'],
                        'activo' => (int) ((bool) ($row['activo'] ?? false)),
                        'orden' => (int) $default['orden'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        });
    }

    public function resolveClientFieldName(string $clave): string
    {
        return match ($clave) {
            'vlrrecepcion' => 'vlracuse',
            default => $clave,
        };
    }

    private function normalizeNumericForDatabase(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function normalizeNumericForInput(mixed $value): string
    {
        $normalized = number_format((float) $value, 2, '.', '');

        return str_contains($normalized, '.')
            ? rtrim(rtrim($normalized, '0'), '.')
            : $normalized;
    }
}
