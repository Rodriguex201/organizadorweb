<div class="mt-6 space-y-6">
    <div class="rounded-lg bg-white p-4 shadow">
        <div class="mb-6">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Finanzas</h2>
                <p class="mt-1 text-sm text-slate-500">Distribucion del valor mensual esperado de la cartera actualmente facturable.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('proformas.dashboard') }}" class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <input type="hidden" name="tab" value="finanzas">

            <div>
                <label for="finance-grouping" class="mb-1 block text-sm font-medium text-slate-700">Agrupar por</label>
                <select id="finance-grouping" name="grouping" onchange="this.form.submit()" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($financeReport['grouping']['options'] as $optionKey => $option)
                        <option value="{{ $optionKey }}" @selected($financeReport['grouping']['selected'] === $optionKey) @disabled(!($option['enabled'] ?? false))>
                            {{ $option['label'] }}{{ ($option['enabled'] ?? false) ? '' : ' - Proximamente' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="finance-year" class="mb-1 block text-sm font-medium text-slate-700">Año</label>
                <select id="finance-year" name="anio" onchange="this.form.submit()" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($financeReport['filters']['available_years'] as $year)
                        <option value="{{ $year }}" @selected((int) $financeReport['filters']['selected_year'] === (int) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="finance-month" class="mb-1 block text-sm font-medium text-slate-700">Mes</label>
                <select id="finance-month" name="mes" onchange="this.form.submit()" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="" @selected($financeReport['filters']['selected_month'] === null)>Todos</option>
                    @foreach($financeReport['filters']['available_months'] as $monthNumber => $monthLabel)
                        <option value="{{ $monthNumber }}" @selected((int) ($financeReport['filters']['selected_month'] ?? 0) === (int) $monthNumber)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor mensual esperado</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">$ {{ number_format((float) $financeReport['summary']['monthly_expected_value'], 2, ',', '.') }}</p>
                @php($comparison = $financeReport['summary']['comparisons']['monthly_expected_value'])
                @if($comparison['previous_value'] === null)
                    <p class="mt-2 text-xs font-medium text-slate-500">Sin comparacion con el año anterior</p>
                @elseif((float) $comparison['absolute_difference'] > 0)
                    <p class="mt-2 text-xs font-semibold text-emerald-600">▲ $ {{ number_format((float) $comparison['absolute_difference'], 2, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format((float) $comparison['percentage_difference'], 2, ',', '.').'%' : '' }} vs año anterior</p>
                @elseif((float) $comparison['absolute_difference'] < 0)
                    <p class="mt-2 text-xs font-semibold text-rose-600">▼ $ {{ number_format(abs((float) $comparison['absolute_difference']), 2, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format(abs((float) $comparison['percentage_difference']), 2, ',', '.').'%' : '' }} vs año anterior</p>
                @else
                    <p class="mt-2 text-xs font-semibold text-slate-500">Sin variacion vs año anterior</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empresas activas</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) $financeReport['summary']['active_companies'], 0, ',', '.') }}</p>
                @php($comparison = $financeReport['summary']['comparisons']['active_companies'])
                @if($comparison['previous_value'] === null)
                    <p class="mt-2 text-xs font-medium text-slate-500">Sin comparacion con el año anterior</p>
                @elseif((float) $comparison['absolute_difference'] > 0)
                    <p class="mt-2 text-xs font-semibold text-emerald-600">▲ {{ number_format((int) $comparison['absolute_difference'], 0, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format((float) $comparison['percentage_difference'], 2, ',', '.').'%' : '' }} vs año anterior</p>
                @elseif((float) $comparison['absolute_difference'] < 0)
                    <p class="mt-2 text-xs font-semibold text-rose-600">▼ {{ number_format(abs((int) $comparison['absolute_difference']), 0, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format(abs((float) $comparison['percentage_difference']), 2, ',', '.').'%' : '' }} vs año anterior</p>
                @else
                    <p class="mt-2 text-xs font-semibold text-slate-500">Sin variacion vs año anterior</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Promedio por empresa</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">$ {{ number_format((float) $financeReport['summary']['average_value_per_company'], 2, ',', '.') }}</p>
                @php($comparison = $financeReport['summary']['comparisons']['average_value_per_company'])
                @if($comparison['previous_value'] === null)
                    <p class="mt-2 text-xs font-medium text-slate-500">Sin comparacion con el año anterior</p>
                @elseif((float) $comparison['absolute_difference'] > 0)
                    <p class="mt-2 text-xs font-semibold text-emerald-600">▲ $ {{ number_format((float) $comparison['absolute_difference'], 2, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format((float) $comparison['percentage_difference'], 2, ',', '.').'%' : '' }} vs año anterior</p>
                @elseif((float) $comparison['absolute_difference'] < 0)
                    <p class="mt-2 text-xs font-semibold text-rose-600">▼ $ {{ number_format(abs((float) $comparison['absolute_difference']), 2, ',', '.') }}{{ $comparison['percentage_difference'] !== null ? ' / '.number_format(abs((float) $comparison['percentage_difference']), 2, ',', '.').'%' : '' }} vs año anterior</p>
                @else
                    <p class="mt-2 text-xs font-semibold text-slate-500">Sin variacion vs año anterior</p>
                @endif
            </div>

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Mayor participacion</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">{{ $financeReport['summary']['top_regimen']['label'] ?? 'N/D' }}</p>
                <p class="mt-1 text-sm font-medium text-indigo-600">{{ number_format((float) $financeReport['summary']['top_regimen']['percentage'], 2, ',', '.') }}% del total</p>
            </div>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-slate-900">Distribucion por regimen</h3>
            <p class="mt-1 text-sm text-slate-500">Valor mensual esperado, cantidad de empresas y participacion sobre el total.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach($financeReport['distribution'] as $regimen)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Regimen</p>
                            <h4 class="mt-1 text-xl font-bold text-slate-900">{{ $regimen['label'] }}</h4>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ number_format((float) $regimen['percentage'], 2, ',', '.') }}%</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="text-xs text-slate-500">Valor mensual esperado</p>
                            <p class="text-lg font-bold text-slate-900">$ {{ number_format((float) $regimen['value'], 2, ',', '.') }}</p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-500">Cantidad de empresas</p>
                            <p class="text-lg font-bold text-slate-900">{{ number_format((int) $regimen['companies'], 0, ',', '.') }}</p>
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                <span>Participacion</span>
                                <span>{{ number_format((float) $regimen['percentage'], 2, ',', '.') }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-indigo-600" style="width: {{ $regimen['progress_percentage'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="mb-4">
                <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Distribucion visual por regimen</h4>
                <p class="mt-1 text-sm text-slate-500">Participacion de SAS, PCS y SMP sobre el valor total del periodo.</p>
            </div>

            @if((float) ($financeReport['summary']['monthly_expected_value'] ?? 0) > 0)
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
                    <div class="relative h-72">
                        <canvas id="finance-distribution-chart"></canvas>
                    </div>

                    <div class="space-y-3">
                        @foreach($financeReport['distribution'] as $regimen)
                            <div class="rounded-lg bg-white px-4 py-3 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-700">{{ $regimen['label'] }}</span>
                                    <span class="text-sm font-bold text-slate-900">{{ number_format((float) $regimen['percentage'], 2, ',', '.') }}%</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">$ {{ number_format((float) $regimen['value'], 2, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex h-56 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-6 text-center">
                    <p class="text-sm font-medium text-slate-500">No hay datos suficientes para generar la dona de distribucion.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@if((float) ($financeReport['summary']['monthly_expected_value'] ?? 0) > 0)
    @push('scripts')
        @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        @endonce
        <script>
            (() => {
                const distribution = @json(array_values($financeReport['distribution']));
                const registry = window.organizadorFinanceCharts = window.organizadorFinanceCharts || {};

                const FINANCE_DISTRIBUTION_CONFIG = {
                    colors: ['#2563eb', '#16a34a', '#f97316'],
                    borderColor: '#ffffff',
                    borderWidth: 4,
                    cutout: '62%',
                };

                const destroyFinanceChart = (canvasId) => {
                    if (registry[canvasId]) {
                        registry[canvasId].destroy();
                        delete registry[canvasId];
                    }
                };

                const registerFinanceChart = (canvasId, chart) => {
                    registry[canvasId] = chart;
                };

                const initFinanceDistributionChart = () => {
                    const canvasId = 'finance-distribution-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!window.Chart || !chartCanvas || !Array.isArray(distribution)) {
                        return;
                    }

                    const labels = distribution.map((item) => item.label);
                    const values = distribution.map((item) => Number(item.value || 0));
                    const percentages = distribution.map((item) => Number(item.percentage || 0));

                    destroyFinanceChart(canvasId);
                    registerFinanceChart(canvasId, new Chart(chartCanvas, {
                        type: 'doughnut',
                        data: {
                            labels,
                            datasets: [
                                {
                                    data: values,
                                    backgroundColor: FINANCE_DISTRIBUTION_CONFIG.colors,
                                    borderColor: FINANCE_DISTRIBUTION_CONFIG.borderColor,
                                    borderWidth: FINANCE_DISTRIBUTION_CONFIG.borderWidth,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: FINANCE_DISTRIBUTION_CONFIG.cutout,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        color: '#475569',
                                        font: {
                                            size: 12,
                                        },
                                    },
                                },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    titleColor: '#f8fafc',
                                    bodyColor: '#e2e8f0',
                                    padding: 12,
                                    callbacks: {
                                        label: (context) => {
                                            const value = Number(context.parsed || 0).toLocaleString('es-CO');
                                            const percentage = percentages[context.dataIndex] || 0;

                                            return `${context.label}: $ ${value} (${percentage.toLocaleString('es-CO', { maximumFractionDigits: 2 })}%)`;
                                        },
                                    },
                                },
                            },
                        },
                    }));
                };

                initFinanceDistributionChart();
            })();
        </script>
    @endpush
@endif
