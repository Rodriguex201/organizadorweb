<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClienteCrecimientoReportService
{
    private const MONTHS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    public function buildAnnualReport(int $anio): array
    {
        $mapping = $this->resolveColumnMapping();
        $base = [];

        foreach (self::MONTHS as $monthNumber => $label) {
            $base[$monthNumber] = [
                'mes' => $monthNumber,
                'label' => $label,
                'ingresos' => ['empresas' => 0, 'valor' => 0.0],
                'retiros' => ['empresas' => 0, 'valor' => 0.0],
                'balance' => ['empresas' => 0, 'valor' => 0.0],
            ];
        }

        $ingresos = $this->aggregateByMonth(
            $mapping['fecha_inicio_facturacion'],
            $mapping['valor_total'],
            $anio,
        );

        $retiros = $this->aggregateByMonth(
            $mapping['fecha_retiro'],
            $mapping['valor_total'],
            $anio,
        );

        foreach ($ingresos as $month => $data) {
            if (!isset($base[$month])) {
                continue;
            }

            $base[$month]['ingresos'] = $data;
        }

        foreach ($retiros as $month => $data) {
            if (!isset($base[$month])) {
                continue;
            }

            $base[$month]['retiros'] = $data;
        }

        foreach ($base as $month => $data) {
            $base[$month]['balance'] = [
                'empresas' => $data['ingresos']['empresas'] - $data['retiros']['empresas'],
                'valor' => $data['ingresos']['valor'] - $data['retiros']['valor'],
            ];
        }

        return [
            'mensual' => array_values($base),
            'resumen_anual' => [
                'ingresos' => [
                    'empresas' => array_sum(array_map(static fn (array $row): int => $row['ingresos']['empresas'], $base)),
                    'valor' => array_sum(array_map(static fn (array $row): float => $row['ingresos']['valor'], $base)),
                ],
                'retiros' => [
                    'empresas' => array_sum(array_map(static fn (array $row): int => $row['retiros']['empresas'], $base)),
                    'valor' => array_sum(array_map(static fn (array $row): float => $row['retiros']['valor'], $base)),
                ],
                'balance' => [
                    'empresas' => array_sum(array_map(static fn (array $row): int => $row['balance']['empresas'], $base)),
                    'valor' => array_sum(array_map(static fn (array $row): float => $row['balance']['valor'], $base)),
                ],
            ],
        ];
    }

    public function buildHistoricalGrowthReport(): array
    {
        $mapping = $this->resolveColumnMapping();
        $rows = $this->aggregateHistoricalGrowth(
            $mapping['fecha_inicio_facturacion'],
            $mapping['fecha_retiro'],
            $mapping['valor_total'],
        );

        $years = [];

        foreach ($rows as $row) {
            $year = (int) ($row->anio ?? 0);

            if ($year > 0 && !in_array($year, $years, true)) {
                $years[] = $year;
            }
        }

        sort($years);

        $monthly = [];
        $annual = [];

        foreach ($years as $year) {
            $monthly[$year] = [];
            $annual[$year] = $this->emptyGrowthMetrics();

            foreach (self::MONTHS as $monthNumber => $label) {
                $monthly[$year][$monthNumber] = [
                    'mes' => $monthNumber,
                    'label' => $label,
                    'ingresos' => ['empresas' => 0, 'valor' => 0.0],
                    'retiros' => ['empresas' => 0, 'valor' => 0.0],
                    'balance' => ['empresas' => 0, 'valor' => 0.0],
                ];
            }
        }

        foreach ($rows as $row) {
            $year = (int) ($row->anio ?? 0);
            $month = (int) ($row->mes ?? 0);
            $type = (string) ($row->tipo ?? '');

            if (!isset($monthly[$year][$month]) || !in_array($type, ['ingresos', 'retiros'], true)) {
                continue;
            }

            $monthly[$year][$month][$type] = [
                'empresas' => (int) ($row->empresas ?? 0),
                'valor' => (float) ($row->valor ?? 0),
            ];
        }

        foreach ($monthly as $year => $months) {
            foreach ($months as $month => $data) {
                $monthly[$year][$month]['balance'] = [
                    'empresas' => $data['ingresos']['empresas'] - $data['retiros']['empresas'],
                    'valor' => $data['ingresos']['valor'] - $data['retiros']['valor'],
                ];

                foreach (['ingresos', 'retiros', 'balance'] as $type) {
                    $annual[$year][$type]['empresas'] += $monthly[$year][$month][$type]['empresas'];
                    $annual[$year][$type]['valor'] += $monthly[$year][$month][$type]['valor'];
                }
            }
        }

        return [
            'metadata' => [
                'first_year' => $years[0] ?? null,
                'last_year' => $years === [] ? null : $years[count($years) - 1],
                'available_years' => $years,
            ],
            'years' => $years,
            'months' => self::MONTHS,
            'monthly' => $monthly,
            'annual' => $annual,
        ];
    }

    /**
     * @return array<int, array{empresas:int, valor:float}>
     */
    private function aggregateByMonth(?string $dateColumn, ?string $valueColumn, int $anio): array
    {
        if ($dateColumn === null || $valueColumn === null) {
            return [];
        }

        return DB::table('clientes_potenciales')
            ->selectRaw("MONTH({$dateColumn}) as mes")
            ->selectRaw('COUNT(*) as empresas')
            ->selectRaw("COALESCE(SUM(COALESCE({$valueColumn}, 0)), 0) as valor")
            ->whereNotNull($dateColumn)
            ->whereRaw("TRIM(COALESCE({$dateColumn}, '')) <> ''")
            ->whereRaw("YEAR({$dateColumn}) = ?", [$anio])
            ->groupByRaw("MONTH({$dateColumn})")
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                (int) ($row->mes ?? 0) => [
                    'empresas' => (int) ($row->empresas ?? 0),
                    'valor' => (float) ($row->valor ?? 0),
                ],
            ])
            ->all();
    }

    private function aggregateHistoricalGrowth(?string $startDateColumn, ?string $retirementDateColumn, ?string $valueColumn): array
    {
        if ($valueColumn === null) {
            return [];
        }

        $queries = [];

        if ($startDateColumn !== null) {
            $queries[] = $this->historicalEventQuery('ingresos', $startDateColumn, $valueColumn);
        }

        if ($retirementDateColumn !== null) {
            $queries[] = $this->historicalEventQuery('retiros', $retirementDateColumn, $valueColumn);
        }

        if ($queries === []) {
            return [];
        }

        $eventsQuery = array_shift($queries);

        foreach ($queries as $query) {
            $eventsQuery->unionAll($query);
        }

        return DB::query()
            ->fromSub($eventsQuery, 'growth_events')
            ->select('tipo', 'anio', 'mes')
            ->selectRaw('SUM(empresas) as empresas')
            ->selectRaw('SUM(valor) as valor')
            ->groupBy('tipo', 'anio', 'mes')
            ->orderBy('anio')
            ->orderBy('mes')
            ->get()
            ->all();
    }

    private function historicalEventQuery(string $type, string $dateColumn, string $valueColumn): \Illuminate\Database\Query\Builder
    {
        return DB::table('clientes_potenciales')
            ->selectRaw('? as tipo', [$type])
            ->selectRaw("YEAR({$dateColumn}) as anio")
            ->selectRaw("MONTH({$dateColumn}) as mes")
            ->selectRaw('COUNT(*) as empresas')
            ->selectRaw("COALESCE(SUM(COALESCE({$valueColumn}, 0)), 0) as valor")
            ->whereNotNull($dateColumn)
            ->whereRaw("TRIM(COALESCE({$dateColumn}, '')) <> ''")
            ->groupByRaw("YEAR({$dateColumn})")
            ->groupByRaw("MONTH({$dateColumn})");
    }

    private function emptyGrowthMetrics(): array
    {
        return [
            'ingresos' => ['empresas' => 0, 'valor' => 0.0],
            'retiros' => ['empresas' => 0, 'valor' => 0.0],
            'balance' => ['empresas' => 0, 'valor' => 0.0],
        ];
    }

    /**
     * @return array{fecha_inicio_facturacion:?string, fecha_retiro:?string, valor_total:?string}
     */
    private function resolveColumnMapping(): array
    {
        $columns = [];

        try {
            $columns = Schema::getColumnListing('clientes_potenciales');
        } catch (\Throwable) {
            $columns = [];
        }

        $pick = static function (array $candidates) use ($columns): ?string {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    return $candidate;
                }
            }

            return null;
        };

        return [
            'fecha_inicio_facturacion' => $pick(['fecha_inicio_facturacion']),
            'fecha_retiro' => $pick(['fecha_retiro']),
            'valor_total' => $pick(['valor_total']),
        ];
    }
}
