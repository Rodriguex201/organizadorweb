@php
    $historical = $growthHistoricalReport ?? null;
    $hasHistoricalData = !empty($historical['years']) && !empty($historical['months']);
@endphp

<div class="mt-6">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Evolucion historica</h3>
            <p class="text-sm text-slate-500">Resumen del crecimiento real por impacto economico, balance de empresas y movimiento mensual.</p>
        </div>
        <button type="button" id="growth-customizer-open" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
            Personalizar dashboard
        </button>
    </div>

    <div class="space-y-8">
        <section data-widget-category-section="balances">
            <div class="mb-4 border-l-4 border-slate-300 pl-3">
                <h4 class="text-base font-semibold text-slate-800">Balances anuales</h4>
                <p class="mt-1 text-sm text-slate-500">Resultado neto del crecimiento en valor economico y cantidad de empresas.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-growth-widget
                    data-widget-key="value-balance"
                    data-widget-category="balances"
                    data-widget-name="Balance economico anual"
                    data-widget-description="Impacto neto del crecimiento en valor mensual."
                    data-widget-icon="currency"
                    data-widget-order="10"
                    data-widget-default-visible="true"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Balance economico anual</h4>
                            @if($hasHistoricalData)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $historical['metadata']['first_year'] ?? 'N/D' }} - {{ $historical['metadata']['last_year'] ?? 'N/D' }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Sin historico disponible</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Valor neto</span>
                    </div>

                    @if($hasHistoricalData)
                        <div class="relative h-72 md:h-80">
                            <canvas id="growth-value-balance-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-72 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center md:h-80">
                            <p class="text-sm font-medium text-slate-500">No hay datos historicos suficientes para generar este grafico.</p>
                        </div>
                    @endif
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-growth-widget
                    data-widget-key="company-balance"
                    data-widget-category="balances"
                    data-widget-name="Balance anual de empresas"
                    data-widget-description="Crecimiento neto de empresas por ano."
                    data-widget-icon="balance"
                    data-widget-order="20"
                    data-widget-default-visible="true"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Balance anual de empresas</h4>
                            @if($hasHistoricalData)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $historical['metadata']['first_year'] ?? 'N/D' }} - {{ $historical['metadata']['last_year'] ?? 'N/D' }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Sin historico disponible</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Balance</span>
                    </div>

                    @if($hasHistoricalData)
                        <div class="relative h-72 md:h-80">
                            <canvas id="growth-company-balance-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-72 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center md:h-80">
                            <p class="text-sm font-medium text-slate-500">No hay datos historicos suficientes para generar este grafico.</p>
                        </div>
                    @endif
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-growth-widget
                    data-widget-key="income-vs-retirements"
                    data-widget-category="balances"
                    data-widget-name="Ingresos vs retiros economicos"
                    data-widget-description="Valor incorporado comparado con valor perdido por ano."
                    data-widget-icon="compare"
                    data-widget-order="30"
                    data-widget-default-visible="true"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Ingresos vs retiros economicos</h4>
                            @if($hasHistoricalData)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $historical['metadata']['first_year'] ?? 'N/D' }} - {{ $historical['metadata']['last_year'] ?? 'N/D' }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Sin historico disponible</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Comparativo</span>
                    </div>

                    @if($hasHistoricalData)
                        <div class="relative h-72 md:h-80">
                            <canvas id="growth-income-vs-retirements-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-72 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center md:h-80">
                            <p class="text-sm font-medium text-slate-500">No hay datos historicos suficientes para generar este grafico.</p>
                        </div>
                    @endif
                </section>
            </div>
        </section>

        <section data-widget-category-section="movement">
            <div class="mb-4 border-l-4 border-slate-300 pl-3">
                <h4 class="text-base font-semibold text-slate-800">Movimiento mensual</h4>
                <p class="mt-1 text-sm text-slate-500">Entradas y retiros que explican la tendencia del crecimiento.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-growth-widget
                    data-widget-key="new-companies"
                    data-widget-category="movement"
                    data-widget-name="Empresas nuevas por mes"
                    data-widget-description="Empresas que iniciaron facturacion en cada mes."
                    data-widget-icon="growth"
                    data-widget-order="40"
                    data-widget-default-visible="true"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Empresas nuevas por mes</h4>
                            @if($hasHistoricalData)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $historical['metadata']['first_year'] ?? 'N/D' }} - {{ $historical['metadata']['last_year'] ?? 'N/D' }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Sin historico disponible</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Ingresos</span>
                    </div>

                    @if($hasHistoricalData)
                        <div class="relative h-72 md:h-80">
                            <canvas id="growth-new-companies-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-72 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center md:h-80">
                            <p class="text-sm font-medium text-slate-500">No hay datos historicos suficientes para generar este grafico.</p>
                        </div>
                    @endif
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-growth-widget
                    data-widget-key="retired-companies"
                    data-widget-category="movement"
                    data-widget-name="Empresas retiradas por mes"
                    data-widget-description="Empresas retiradas agrupadas por mes."
                    data-widget-icon="retirement"
                    data-widget-order="50"
                    data-widget-default-visible="true"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Empresas retiradas por mes</h4>
                            @if($hasHistoricalData)
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $historical['metadata']['first_year'] ?? 'N/D' }} - {{ $historical['metadata']['last_year'] ?? 'N/D' }}
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Sin historico disponible</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">Retiros</span>
                    </div>

                    @if($hasHistoricalData)
                        <div class="relative h-72 md:h-80">
                            <canvas id="growth-retired-companies-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-72 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 text-center md:h-80">
                            <p class="text-sm font-medium text-slate-500">No hay datos historicos suficientes para generar este grafico.</p>
                        </div>
                    @endif
                </section>
            </div>
        </section>

        <div id="growth-dashboard-empty-state" class="hidden rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center shadow-sm">
            <p class="text-sm font-semibold text-slate-700">No hay indicadores visibles.</p>
            <p class="mt-1 text-sm text-slate-500">Activa al menos un indicador desde Personalizar dashboard.</p>
        </div>
    </div>
</div>

<div id="growth-customizer-overlay" class="fixed inset-0 z-50 hidden bg-slate-900/50"></div>
<aside id="growth-customizer-panel" class="fixed top-0 bottom-0 right-0 z-50 hidden w-full max-w-md overflow-y-auto border-l border-slate-200 bg-white shadow-2xl">
    <div class="flex min-h-full flex-col">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Personalizar dashboard</h3>
                    <p id="growth-widget-counter" class="mt-1 text-sm text-slate-500">Mostrando 0 de 0 indicadores</p>
                </div>
                <button type="button" id="growth-customizer-close" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200">Cerrar</button>
            </div>
        </div>

        <div id="growth-widget-list" class="flex-1 space-y-5 px-5 py-5"></div>

        <div class="border-t border-slate-200 px-5 py-4">
            <button type="button" id="growth-customizer-reset" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Restaurar predeterminado
            </button>
        </div>
    </div>
</aside>

@push('scripts')
    <script>
        (() => {
            const STORAGE_KEY = 'growth-dashboard-widget-visibility';
            const STORAGE_VERSION = 1;
            const CATEGORY_LABELS = {
                balances: 'Balances',
                movement: 'Movimiento mensual',
                analysis: 'Analisis',
                kpi: 'KPI',
            };
            const ICON_LABELS = {
                currency: '$',
                balance: '=',
                compare: 'VS',
                growth: '+',
                retirement: '-',
            };

            const openButton = document.getElementById('growth-customizer-open');
            const closeButton = document.getElementById('growth-customizer-close');
            const resetButton = document.getElementById('growth-customizer-reset');
            const overlay = document.getElementById('growth-customizer-overlay');
            const panel = document.getElementById('growth-customizer-panel');
            const widgetList = document.getElementById('growth-widget-list');
            const counter = document.getElementById('growth-widget-counter');
            const emptyState = document.getElementById('growth-dashboard-empty-state');
            const categorySections = Array.from(document.querySelectorAll('[data-widget-category-section]'));

            if (!openButton || !closeButton || !resetButton || !overlay || !panel || !widgetList || !counter) {
                return;
            }

            const discoverWidgets = () => Array.from(document.querySelectorAll('[data-growth-widget]'))
                .map((element) => ({
                    element,
                    key: element.dataset.widgetKey || '',
                    category: element.dataset.widgetCategory || 'analysis',
                    name: element.dataset.widgetName || 'Indicador',
                    description: element.dataset.widgetDescription || '',
                    icon: element.dataset.widgetIcon || 'chart',
                    order: Number(element.dataset.widgetOrder || 0),
                    defaultVisible: element.dataset.widgetDefaultVisible !== 'false',
                }))
                .filter((widget) => widget.key !== '')
                .sort((left, right) => left.order - right.order);

            const widgets = discoverWidgets();

            const defaultState = () => ({
                version: STORAGE_VERSION,
                widgets: Object.fromEntries(widgets.map((widget) => [
                    widget.key,
                    { visible: widget.defaultVisible },
                ])),
            });

            const readState = () => {
                try {
                    const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');

                    if (!parsed || parsed.version !== STORAGE_VERSION || typeof parsed.widgets !== 'object') {
                        return defaultState();
                    }

                    const state = defaultState();

                    widgets.forEach((widget) => {
                        if (Object.prototype.hasOwnProperty.call(parsed.widgets, widget.key)) {
                            state.widgets[widget.key].visible = parsed.widgets[widget.key]?.visible !== false;
                        }
                    });

                    return state;
                } catch (error) {
                    return defaultState();
                }
            };

            const writeState = (state) => {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            };

            let state = readState();

            const visibleCount = () => widgets.filter((widget) => state.widgets[widget.key]?.visible !== false).length;

            const updateCategorySections = () => {
                categorySections.forEach((section) => {
                    const category = section.dataset.widgetCategorySection;
                    const hasVisibleWidget = widgets.some((widget) => (
                        widget.category === category
                        && state.widgets[widget.key]?.visible !== false
                    ));

                    section.classList.toggle('hidden', !hasVisibleWidget);
                });
            };

            const resizeVisibleCharts = () => {
                const registry = window.organizadorGrowthCharts || {};

                widgets.forEach((widget) => {
                    if (state.widgets[widget.key]?.visible === false) {
                        return;
                    }

                    const canvas = widget.element.querySelector('canvas');

                    if (canvas && registry[canvas.id]) {
                        registry[canvas.id].resize();
                    }
                });
            };

            const applyVisibility = () => {
                widgets.forEach((widget) => {
                    widget.element.classList.toggle('hidden', state.widgets[widget.key]?.visible === false);
                });

                updateCategorySections();
                counter.textContent = `Mostrando ${visibleCount()} de ${widgets.length} indicadores`;

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount() !== 0);
                }

                window.requestAnimationFrame(resizeVisibleCharts);
            };

            const categoryOrder = ['balances', 'movement', 'analysis', 'kpi'];

            const widgetsByCategory = () => {
                const grouped = new Map(categoryOrder.map((category) => [category, []]));

                widgets.forEach((widget) => {
                    if (!grouped.has(widget.category)) {
                        grouped.set(widget.category, []);
                    }

                    grouped.get(widget.category).push(widget);
                });

                return grouped;
            };

            const renderWidgetList = () => {
                widgetList.innerHTML = '';

                widgetsByCategory().forEach((categoryWidgets, category) => {
                    if (categoryWidgets.length === 0) {
                        return;
                    }

                    const group = document.createElement('section');
                    group.className = 'space-y-3';

                    const heading = document.createElement('h4');
                    heading.className = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
                    heading.textContent = CATEGORY_LABELS[category] || category;
                    group.appendChild(heading);

                    categoryWidgets.forEach((widget) => {
                        const label = document.createElement('label');
                        label.className = 'flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-indigo-200 hover:bg-indigo-50/40';

                        const icon = document.createElement('span');
                        icon.className = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-600';
                        icon.textContent = ICON_LABELS[widget.icon] || '*';

                        const copy = document.createElement('span');
                        copy.className = 'min-w-0 flex-1';

                        const name = document.createElement('span');
                        name.className = 'block text-sm font-semibold text-slate-800';
                        name.textContent = widget.name;

                        const description = document.createElement('span');
                        description.className = 'mt-1 block text-xs leading-5 text-slate-500';
                        description.textContent = widget.description;

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
                        checkbox.checked = state.widgets[widget.key]?.visible !== false;
                        checkbox.dataset.widgetToggle = widget.key;

                        copy.appendChild(name);
                        copy.appendChild(description);
                        label.appendChild(icon);
                        label.appendChild(copy);
                        label.appendChild(checkbox);
                        group.appendChild(label);
                    });

                    widgetList.appendChild(group);
                });
            };

            const syncToggles = () => {
                widgetList.querySelectorAll('[data-widget-toggle]').forEach((toggle) => {
                    toggle.checked = state.widgets[toggle.dataset.widgetToggle]?.visible !== false;
                });
            };

            const openPanel = () => {
                renderWidgetList();
                syncToggles();
                overlay.classList.remove('hidden');
                panel.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            const closePanel = () => {
                overlay.classList.add('hidden');
                panel.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            widgetList.addEventListener('change', (event) => {
                const toggle = event.target.closest('[data-widget-toggle]');

                if (!toggle) {
                    return;
                }

                state.widgets[toggle.dataset.widgetToggle] = { visible: toggle.checked };
                writeState(state);
                applyVisibility();
                syncToggles();
            });

            resetButton.addEventListener('click', () => {
                state = defaultState();
                writeState(state);
                applyVisibility();
                renderWidgetList();
            });

            openButton.addEventListener('click', openPanel);
            closeButton.addEventListener('click', closePanel);
            overlay.addEventListener('click', closePanel);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
                    closePanel();
                }
            });

            applyVisibility();
        })();
    </script>
@endpush

@if($hasHistoricalData)
    @push('scripts')
        @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        @endonce
        <script>
            (() => {
                const historical = @json($historical);
                const registry = window.organizadorGrowthCharts = window.organizadorGrowthCharts || {};

                /* ==========================================================
                 * CONFIGURACION VISUAL DE GRAFICOS
                 * ========================================================== */
                const CHART_BASE_CONFIG = {
                    colors: {
                        text: '#475569',
                        muted: '#64748b',
                        grid: '#e2e8f0',
                        tooltipBackground: '#0f172a',
                        tooltipTitle: '#f8fafc',
                        tooltipBody: '#e2e8f0',
                    },
                    legend: {
                        boxWidth: 10,
                        boxHeight: 10,
                        fontSize: 12,
                        position: 'bottom',
                    },
                    tooltip: {
                        padding: 12,
                    },
                };
                const NEW_COMPANIES_CONFIG = {
                    colors: ['#16a34a', '#22c55e', '#4ade80', '#15803d', '#86efac'],
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
                };
                const RETIRED_COMPANIES_CONFIG = {
                    colors: ['#dc2626', '#ef4444', '#f87171', '#b91c1c', '#fecaca'],
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.35,
                };
                const COMPANY_BALANCE_CONFIG = {
                    colors: ['#2563eb', '#3b82f6', '#60a5fa', '#1d4ed8', '#bfdbfe'],
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 44,
                };
                const VALUE_BALANCE_CONFIG = {
                    colors: ['#7c3aed', '#8b5cf6', '#a78bfa', '#6d28d9', '#ddd6fe'],
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 44,
                };
                const INCOME_VS_RETIREMENTS_CONFIG = {
                    colors: ['#16a34a', '#dc2626'],
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 36,
                };

                if (!window.Chart || !historical || !Array.isArray(historical.years) || historical.years.length === 0) {
                    return;
                }

                const orderedEntries = (items) => Object.entries(items || {})
                    .sort(([keyA], [keyB]) => Number(keyA) - Number(keyB));

                const baseChartOptions = (tooltipLabelCallback, yTickOptions = {}) => ({
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: CHART_BASE_CONFIG.legend.position,
                            labels: {
                                boxWidth: CHART_BASE_CONFIG.legend.boxWidth,
                                boxHeight: CHART_BASE_CONFIG.legend.boxHeight,
                                color: CHART_BASE_CONFIG.colors.text,
                                font: {
                                    size: CHART_BASE_CONFIG.legend.fontSize,
                                },
                            },
                        },
                        tooltip: {
                            backgroundColor: CHART_BASE_CONFIG.colors.tooltipBackground,
                            titleColor: CHART_BASE_CONFIG.colors.tooltipTitle,
                            bodyColor: CHART_BASE_CONFIG.colors.tooltipBody,
                            padding: CHART_BASE_CONFIG.tooltip.padding,
                            callbacks: {
                                label: tooltipLabelCallback,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: CHART_BASE_CONFIG.colors.muted,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: CHART_BASE_CONFIG.colors.muted,
                                ...yTickOptions,
                            },
                            grid: {
                                color: CHART_BASE_CONFIG.colors.grid,
                            },
                        },
                    },
                });

                const destroyChart = (canvasId) => {
                    if (registry[canvasId]) {
                        registry[canvasId].destroy();
                        delete registry[canvasId];
                    }
                };

                const registerChart = (canvasId, chart) => {
                    registry[canvasId] = chart;
                };

                const createLineDataset = (label, data, color, config) => ({
                    label: String(label),
                    data,
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: config.borderWidth,
                    pointRadius: config.pointRadius,
                    pointHoverRadius: config.pointHoverRadius,
                    tension: config.tension,
                });

                const createBarDataset = (label, data, color, config) => ({
                    label: String(label),
                    data,
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: config.borderWidth,
                    borderRadius: config.borderRadius,
                    maxBarThickness: config.maxBarThickness,
                });

                const initNewCompaniesChart = () => {
                    const canvasId = 'growth-new-companies-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!chartCanvas) {
                        return;
                    }

                    const monthEntries = orderedEntries(historical.months);
                    const labels = monthEntries.map(([, label]) => label);
                    const datasets = historical.years.map((year, index) => createLineDataset(
                        year,
                        monthEntries.map(([month]) => Number(historical.monthly?.[year]?.[month]?.ingresos?.empresas ?? 0)),
                        NEW_COMPANIES_CONFIG.colors[index % NEW_COMPANIES_CONFIG.colors.length],
                        NEW_COMPANIES_CONFIG,
                    ));

                    destroyChart(canvasId);
                    registerChart(canvasId, new Chart(chartCanvas, {
                        type: 'line',
                        data: {
                            labels,
                            datasets,
                        },
                        options: baseChartOptions(
                            (context) => `${context.dataset.label}: ${context.parsed.y} empresas`,
                            { precision: 0 },
                        ),
                    }));
                };

                const initRetiredCompaniesChart = () => {
                    const canvasId = 'growth-retired-companies-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!chartCanvas) {
                        return;
                    }

                    const monthEntries = orderedEntries(historical.months);
                    const labels = monthEntries.map(([, label]) => label);
                    const datasets = historical.years.map((year, index) => createLineDataset(
                        year,
                        monthEntries.map(([month]) => Number(historical.monthly?.[year]?.[month]?.retiros?.empresas ?? 0)),
                        RETIRED_COMPANIES_CONFIG.colors[index % RETIRED_COMPANIES_CONFIG.colors.length],
                        RETIRED_COMPANIES_CONFIG,
                    ));

                    destroyChart(canvasId);
                    registerChart(canvasId, new Chart(chartCanvas, {
                        type: 'line',
                        data: {
                            labels,
                            datasets,
                        },
                        options: baseChartOptions(
                            (context) => `${context.dataset.label}: ${context.parsed.y} empresas`,
                            { precision: 0 },
                        ),
                    }));
                };

                const initCompanyBalanceChart = () => {
                    const canvasId = 'growth-company-balance-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!chartCanvas) {
                        return;
                    }

                    const labels = historical.years.map((year) => String(year));
                    const data = historical.years.map((year) => Number(historical.annual?.[year]?.balance?.empresas ?? 0));

                    destroyChart(canvasId);
                    registerChart(canvasId, new Chart(chartCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                createBarDataset('Balance empresas', data, COMPANY_BALANCE_CONFIG.colors[0], COMPANY_BALANCE_CONFIG),
                            ],
                        },
                        options: baseChartOptions(
                            (context) => `${context.dataset.label}: ${context.parsed.y} empresas`,
                            { precision: 0 },
                        ),
                    }));
                };

                const initValueBalanceChart = () => {
                    const canvasId = 'growth-value-balance-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!chartCanvas) {
                        return;
                    }

                    const labels = historical.years.map((year) => String(year));
                    const data = historical.years.map((year) => Number(historical.annual?.[year]?.balance?.valor ?? 0));

                    destroyChart(canvasId);
                    registerChart(canvasId, new Chart(chartCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                createBarDataset('Balance economico', data, VALUE_BALANCE_CONFIG.colors[0], VALUE_BALANCE_CONFIG),
                            ],
                        },
                        options: baseChartOptions(
                            (context) => `${context.dataset.label}: $ ${Number(context.parsed.y || 0).toLocaleString('es-CO')}`,
                        ),
                    }));
                };

                const initIncomeVsRetirementsChart = () => {
                    const canvasId = 'growth-income-vs-retirements-chart';
                    const chartCanvas = document.getElementById(canvasId);

                    if (!chartCanvas) {
                        return;
                    }

                    const labels = historical.years.map((year) => String(year));
                    const incomeData = historical.years.map((year) => Number(historical.annual?.[year]?.ingresos?.valor ?? 0));
                    const retirementData = historical.years.map((year) => Number(historical.annual?.[year]?.retiros?.valor ?? 0));

                    destroyChart(canvasId);
                    registerChart(canvasId, new Chart(chartCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                createBarDataset('Ingresos', incomeData, INCOME_VS_RETIREMENTS_CONFIG.colors[0], INCOME_VS_RETIREMENTS_CONFIG),
                                createBarDataset('Retiros', retirementData, INCOME_VS_RETIREMENTS_CONFIG.colors[1], INCOME_VS_RETIREMENTS_CONFIG),
                            ],
                        },
                        options: baseChartOptions(
                            (context) => `${context.dataset.label}: $ ${Number(context.parsed.y || 0).toLocaleString('es-CO')}`,
                        ),
                    }));
                };

                initNewCompaniesChart();
                initRetiredCompaniesChart();
                initCompanyBalanceChart();
                initValueBalanceChart();
                initIncomeVsRetirementsChart();
            })();
        </script>
    @endpush
@endif
