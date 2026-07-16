<?php

namespace App\Services;

class FinanzasDashboardService
{
    private const REGIMENES = [
        'SAS' => 'SAS',
        'PCS' => 'PCS',
        'SMP' => 'SMP',
    ];

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

    public function __construct(
        private readonly CobrosService $cobrosService,
    ) {
    }

    public function buildPortfolioReport(): array
    {
        return $this->buildDashboardReport((int) now()->format('Y'));
    }

    public function buildDashboardReport(int $year, ?int $month = null): array
    {
        $selectedYear = $this->normalizeYear($year);
        $selectedMonth = $this->normalizeMonth($month);
        $filters = $this->cobrosFilters($selectedYear, $selectedMonth);
        $previousFilters = $this->cobrosFilters($selectedYear - 1, $selectedMonth);

        $availableYears = $this->cobrosService->getAvailableYears();

        if ($availableYears === []) {
            $availableYears = [$selectedYear];
        }

        $currentPeriod = $this->buildPeriodSnapshot(
            $this->cobrosService->getPeriodSummary($filters),
            $this->cobrosService->getPeriodSummaryByRegimen($filters),
        );
        $previousPeriod = $this->buildPeriodSnapshot(
            $this->cobrosService->getPeriodSummary($previousFilters),
            $this->cobrosService->getPeriodSummaryByRegimen($previousFilters),
        );

        return [
            'filters' => $this->filters($availableYears, $selectedYear, $selectedMonth),
            'grouping' => [
                'selected' => 'regimen',
                'options' => [
                    'regimen' => ['label' => 'Empresa', 'enabled' => true],
                    'plan' => ['label' => 'Plan', 'enabled' => false],
                    'ciudad' => ['label' => 'Ciudad', 'enabled' => false],
                    'vendedor' => ['label' => 'Vendedor', 'enabled' => false],
                ],
            ],
            'summary' => [
                'monthly_expected_value' => $currentPeriod['total_value'],
                'active_companies' => $currentPeriod['total_companies'],
                'average_value_per_company' => $currentPeriod['average_value_per_company'],
                'top_regimen' => $this->topRegimen($currentPeriod['distribution']),
                'comparisons' => $this->summaryComparisons($currentPeriod, $previousPeriod),
            ],
            'distribution' => $currentPeriod['distribution'],
        ];
    }

    private function buildPeriodSnapshot(object $summary, array $regimenRows): array
    {
        $distribution = $this->baseDistribution();
        $totalValue = (float) ($summary->valor_total ?? 0);
        $totalCompanies = 0;

        foreach ($regimenRows as $row) {
            $regimen = strtoupper(trim((string) ($row->regimen ?? '')));

            if (!isset($distribution[$regimen])) {
                continue;
            }

            $companies = (int) ($row->empresas ?? 0);
            $value = (float) ($row->valor_total ?? 0);

            $distribution[$regimen]['value'] += $value;
            $distribution[$regimen]['companies'] += $companies;
            $totalCompanies += $companies;
        }

        foreach ($distribution as $regimen => $data) {
            $distribution[$regimen]['percentage'] = $this->percentage($data['value'], $totalValue);
            $distribution[$regimen]['progress_percentage'] = $this->progressPercentage($distribution[$regimen]['percentage']);
        }

        return [
            'has_data' => $totalCompanies > 0 || $totalValue > 0,
            'total_value' => $totalValue,
            'total_companies' => $totalCompanies,
            'average_value_per_company' => $totalCompanies > 0 ? $totalValue / $totalCompanies : 0.0,
            'distribution' => $distribution,
        ];
    }

    private function summaryComparisons(array $currentPeriod, array $previousPeriod): array
    {
        return [
            'monthly_expected_value' => $this->comparison(
                $currentPeriod['total_value'],
                $previousPeriod['total_value'],
                $previousPeriod['has_data'],
            ),
            'active_companies' => $this->comparison(
                $currentPeriod['total_companies'],
                $previousPeriod['total_companies'],
                $previousPeriod['has_data'],
            ),
            'average_value_per_company' => $this->comparison(
                $currentPeriod['average_value_per_company'],
                $previousPeriod['average_value_per_company'],
                $previousPeriod['has_data'],
            ),
        ];
    }

    private function comparison(float|int $currentValue, float|int $previousValue, bool $hasPreviousData): array
    {
        if (!$hasPreviousData) {
            return [
                'previous_value' => null,
                'absolute_difference' => null,
                'percentage_difference' => null,
            ];
        }

        $absoluteDifference = $currentValue - $previousValue;

        return [
            'previous_value' => $previousValue,
            'absolute_difference' => $absoluteDifference,
            'percentage_difference' => $previousValue != 0
                ? ($absoluteDifference / abs($previousValue)) * 100
                : null,
        ];
    }

    private function baseDistribution(): array
    {
        $distribution = [];

        foreach (self::REGIMENES as $key => $label) {
            $distribution[$key] = [
                'key' => $key,
                'label' => $label,
                'value' => 0.0,
                'companies' => 0,
                'percentage' => 0.0,
                'progress_percentage' => 0.0,
            ];
        }

        return $distribution;
    }

    private function topRegimen(array $distribution): array
    {
        $top = null;

        foreach ($distribution as $data) {
            if ($top === null || $data['value'] > $top['value']) {
                $top = $data;
            }
        }

        return [
            'key' => $top['key'] ?? null,
            'label' => $top['label'] ?? null,
            'value' => $top['value'] ?? 0.0,
            'companies' => $top['companies'] ?? 0,
            'percentage' => $top['percentage'] ?? 0.0,
        ];
    }

    private function percentage(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return ($value / $total) * 100;
    }

    private function progressPercentage(float $percentage): float
    {
        return max(0.0, min(100.0, $percentage));
    }

    private function filters(array $availableYears, int $selectedYear, ?int $selectedMonth): array
    {
        if (!in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            sort($availableYears);
        }

        return [
            'available_years' => $availableYears,
            'available_months' => self::MONTHS,
            'selected_year' => $selectedYear,
            'selected_month' => $selectedMonth,
        ];
    }

    private function normalizeYear(int $year): int
    {
        return $year >= 1900 && $year <= 9999 ? $year : (int) now()->format('Y');
    }

    private function normalizeMonth(?int $month): ?int
    {
        return $month !== null && $month >= 1 && $month <= 12 ? $month : null;
    }

    private function cobrosFilters(int $year, ?int $month): array
    {
        $filters = [
            'anio' => $year,
        ];

        if ($month !== null) {
            $filters['mes'] = CobrosService::MESES[$month] ?? null;
        }

        return $filters;
    }

    private function emptyReport(?int $selectedYear = null, ?int $selectedMonth = null): array
    {
        $year = $selectedYear ?? (int) now()->format('Y');

        return [
            'filters' => $this->filters([$year], $year, $this->normalizeMonth($selectedMonth)),
            'grouping' => [
                'selected' => 'regimen',
                'options' => [
                    'regimen' => ['label' => 'Empresa', 'enabled' => true],
                    'plan' => ['label' => 'Plan', 'enabled' => false],
                    'ciudad' => ['label' => 'Ciudad', 'enabled' => false],
                    'vendedor' => ['label' => 'Vendedor', 'enabled' => false],
                ],
            ],
            'summary' => [
                'monthly_expected_value' => 0.0,
                'active_companies' => 0,
                'average_value_per_company' => 0.0,
                'top_regimen' => [
                    'key' => null,
                    'label' => null,
                    'value' => 0.0,
                    'companies' => 0,
                    'percentage' => 0.0,
                ],
                'comparisons' => [
                    'monthly_expected_value' => $this->emptyComparison(),
                    'active_companies' => $this->emptyComparison(),
                    'average_value_per_company' => $this->emptyComparison(),
                ],
            ],
            'distribution' => $this->baseDistribution(),
        ];
    }

    private function emptyComparison(): array
    {
        return [
            'previous_value' => null,
            'absolute_difference' => null,
            'percentage_difference' => null,
        ];
    }

}
