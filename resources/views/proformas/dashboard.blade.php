@extends('layouts.admin')

@section('title', 'Dashboard de Proformas')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div id="export-toast" class="pointer-events-none fixed right-6 top-6 z-[70] hidden max-w-sm rounded-xl border px-4 py-3 text-sm font-medium shadow-lg"></div>

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Dashboard de Proformas</h1>
            <p class="text-sm text-slate-600">Resumen general por periodo de <code>sg_proform</code>.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Ir al listado</a>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('proformas.dashboard') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((int) $filters['mes'] === (int) $mesNumero)>{{ ucfirst($mesNombre) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoNombre)
                        <option value="{{ $estadoCodigo }}" @selected((string) ($filters['estado'] ?? '') === (string) $estadoCodigo)>{{ $proformasService->estadoLabel($estadoCodigo) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Aplicar filtro</button>
                <a href="{{ route('proformas.dashboard') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Periodo actual</a>
                <button type="button" id="open-export-modal" class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Exportar Excel</button>
            </div>
        </form>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Total proformas</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format((int) $dashboard['total_proformas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Generadas</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format((int) $dashboard['total_generadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Enviadas</p>
            <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format((int) $dashboard['total_enviadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Pagadas</p>
            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format((int) $dashboard['total_pagadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Facturadas</p>
            <p class="mt-1 text-2xl font-bold text-purple-700">{{ number_format((int) $dashboard['total_facturadas'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-semibold uppercase text-slate-600">Suma total del periodo</h2>
            <p class="mt-2 text-2xl font-bold">$ {{ number_format((float) $dashboard['suma_total_vtotal'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Total del periodo filtrado: {{ number_format((int) $dashboard['total_periodo_filtrado'], 0, ',', '.') }}</p>
        </div>

        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-semibold uppercase text-slate-600">Suma total por estado</h2>
            <div class="mt-3 space-y-2 text-sm">
                @foreach($dashboard['suma_total_por_estado'] as $estadoCodigo => $datosEstado)
                    <div class="flex items-center justify-between">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($estadoCodigo) }}">{{ $datosEstado['label'] }}</span>
                        <span class="font-medium">{{ number_format((int) $datosEstado['cantidad'], 0, ',', '.') }} / $ {{ number_format((float) $datosEstado['total'], 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold">Últimas proformas del periodo</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Número</th>
                    <th class="px-4 py-3">Empresa</th>
                    <th class="px-4 py-3">NIT</th>
                    <th class="px-4 py-3">Emisora</th>
                    <th class="px-4 py-3">Mes</th>
                    <th class="px-4 py-3">Año</th>
                    <th class="px-4 py-3 text-right">Valor total</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($dashboard['ultimas_proformas'] as $proforma)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</td>
                        <td class="px-4 py-3">{{ $proforma->emp ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->nit ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->emisora ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proformasService->monthLabel($proforma->mes) }}</td>
                        <td class="px-4 py-3">{{ $proforma->anio ?: 'N/D' }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}">{{ $proformasService->estadoLabel($proforma->estado) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('proformas.pdf.show', $proforma->id) }}" target="_blank" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Ver PDF</a>
                                
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-slate-500">No hay proformas para el periodo seleccionado.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="export-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60" data-close-export-modal></div>
    <div class="relative mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-8">
        <div class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-20 flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Exportación avanzada a Excel</h2>
                    <p class="text-sm text-slate-500">Selecciona filtros, tipo de exportación y columnas dinámicas.</p>
                </div>
                <button type="button" class="rounded-full bg-slate-100 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200" data-close-export-modal>Cerrar</button>
            </div>

            <form id="export-form" method="POST" action="{{ route('proformas.dashboard.export') }}" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @csrf
                <input type="hidden" name="dashboard_mes" value="{{ $filters['mes'] }}">
                <input type="hidden" name="dashboard_anio" value="{{ $filters['anio'] }}">
                <input type="hidden" name="dashboard_estado" value="{{ $filters['estado'] }}">

                <div class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[340px_minmax(0,1fr)]">
                    <div class="min-h-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-6">
                        <div class="space-y-6">
                            <div>
                                <label for="mode" class="mb-2 block text-sm font-semibold text-slate-700">Tipo de exportación</label>
                                <select id="mode" name="mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    @foreach($exportOptions['modes'] as $modeOption)
                                        <option value="{{ $modeOption['value'] }}" @selected(old('mode', 'detailed') === $modeOption['value'])>{{ $modeOption['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="scope" class="mb-2 block text-sm font-semibold text-slate-700">Filtro base</label>
                                <select id="scope" name="scope" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    @foreach($exportOptions['scopes'] as $scopeOption)
                                        <option value="{{ $scopeOption['value'] }}" @selected(old('scope', 'current_filters') === $scopeOption['value'])>{{ $scopeOption['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="export-anio" class="mb-2 block text-sm font-semibold text-slate-700">Año</label>
                                    <input id="export-anio" name="anio" type="number" min="1900" max="9999" value="{{ old('anio', $exportOptions['filters']['anio']) }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label for="export-estado" class="mb-2 block text-sm font-semibold text-slate-700">Estado</label>
                                    <select id="export-estado" name="estado" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option value="">Todos</option>
                                        @foreach($estados as $estadoCodigo => $estadoNombre)
                                            <option value="{{ $estadoCodigo }}" @selected((string) old('estado', $exportOptions['filters']['estado']) === (string) $estadoCodigo)>{{ $proformasService->estadoLabel($estadoCodigo) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div id="monthly-range-fields" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="mes_desde" class="mb-2 block text-sm font-semibold text-slate-700">Mes desde</label>
                                    <select id="mes_desde" name="mes_desde" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        @foreach($meses as $mesNumero => $mesNombre)
                                            <option value="{{ $mesNumero }}" @selected((int) old('mes_desde', $exportOptions['filters']['mes']) === (int) $mesNumero)>{{ ucfirst($mesNombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="mes_hasta" class="mb-2 block text-sm font-semibold text-slate-700">Mes hasta</label>
                                    <select id="mes_hasta" name="mes_hasta" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        @foreach($meses as $mesNumero => $mesNombre)
                                            <option value="{{ $mesNumero }}" @selected((int) old('mes_hasta', $exportOptions['filters']['mes']) === (int) $mesNumero)>{{ ucfirst($mesNombre) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="format" class="mb-2 block text-sm font-semibold text-slate-700">Formato</label>
                                <select id="format" name="format" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    @foreach($exportOptions['formats'] as $formatOption)
                                        @if($formatOption['enabled'])
                                            <option value="{{ $formatOption['value'] }}" @selected(old('format', 'xlsx') === $formatOption['value'])>{{ $formatOption['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-slate-500">La estructura queda lista para futuros formatos PDF, CSV y Google Sheets.</p>
                            </div>

                            @if(isset($errors) && $errors->any())
                                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                    {{ $errors->first() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col">
                        <div class="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Columnas exportables</h3>
                                <p class="text-sm text-slate-500"><span id="selected-columns-count">0</span> columnas seleccionadas</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="select-all-columns" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Seleccionar todos</button>
                                <button type="button" id="clear-all-columns" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Deseleccionar todos</button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto scroll-smooth px-6 py-5">
                            <div class="space-y-5">
                                @foreach($exportOptions['column_groups'] as $group)
                                    <section class="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div class="mb-4 flex items-center justify-between gap-3">
                                            <div>
                                                <h4 class="text-base font-semibold text-slate-800">{{ $group['label'] }}</h4>
                                                <p class="text-sm text-slate-500">{{ count($group['columns']) }} columnas disponibles</p>
                                            </div>
                                            <button type="button" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" data-select-group="{{ $group['key'] }}">Seleccionar grupo</button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                            @foreach($group['columns'] as $column)
                                                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                                                    <input
                                                        type="checkbox"
                                                        name="columns[]"
                                                        value="{{ $column['key'] }}"
                                                        data-column-checkbox
                                                        data-column-group="{{ $group['key'] }}"
                                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                        @checked(in_array($column['key'], old('columns', $exportOptions['defaults']['detailed']), true))
                                                    >
                                                    <span>
                                                        <span class="block text-sm font-medium text-slate-800">{{ $column['label'] }}</span>
                                                        <span class="block text-xs text-slate-500">{{ $group['label'] }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </div>

                        <div class="sticky bottom-0 z-10 flex shrink-0 items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-4">
                            <p class="text-sm text-slate-500">El archivo incluirá encabezados en negrita, autofiltro, autosize, moneda y fila final de totales.</p>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-close-export-modal>Cancelar</button>
                                <button id="submit-export-button" type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-70">
                                    <span id="submit-export-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                    <span id="submit-export-label">Descargar Excel</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="export-loading-overlay" class="fixed inset-0 z-[60] hidden bg-slate-900/55 backdrop-blur-[1px]">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-emerald-200 border-t-emerald-600"></div>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-slate-900">Generando archivo Excel...</h3>
                    <p id="export-loading-message" class="mt-1 text-sm text-slate-500">Preparando la exportación con las columnas seleccionadas.</p>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full w-2/3 animate-pulse rounded-full bg-emerald-500"></div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Esto puede tardar algunos segundos. No cierres esta ventana ni vuelvas a hacer clic.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const STORAGE_KEY = 'proformas-dashboard-export-columns';
        const DEFAULTS = @json($exportOptions['defaults']);
        const modal = document.getElementById('export-modal');
        const exportForm = document.getElementById('export-form');
        const exportToast = document.getElementById('export-toast');
        const exportLoadingOverlay = document.getElementById('export-loading-overlay');
        const exportLoadingMessage = document.getElementById('export-loading-message');
        const openButton = document.getElementById('open-export-modal');
        const closeButtons = document.querySelectorAll('[data-close-export-modal]');
        const modeSelect = document.getElementById('mode');
        const scopeSelect = document.getElementById('scope');
        const rangeFields = document.getElementById('monthly-range-fields');
        const checkboxes = Array.from(document.querySelectorAll('[data-column-checkbox]'));
        const selectedCounter = document.getElementById('selected-columns-count');
        const selectAllButton = document.getElementById('select-all-columns');
        const clearAllButton = document.getElementById('clear-all-columns');
        const submitExportButton = document.getElementById('submit-export-button');
        const submitExportSpinner = document.getElementById('submit-export-spinner');
        const submitExportLabel = document.getElementById('submit-export-label');
        const groupButtons = Array.from(document.querySelectorAll('[data-select-group]'));
        const shouldOpenOnLoad = @json(isset($errors) && $errors->any() && old('format') === 'xlsx');
        const currentRecordCount = @json((int) ($dashboard['total_periodo_filtrado'] ?? 0));
        let isExporting = false;
        let toastTimer = null;

        if (!modal || !openButton || !modeSelect || !scopeSelect || !exportForm || !submitExportButton) {
            return;
        }

        const getStoredColumns = () => {
            try {
                const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        };

        const updateSelectedCount = () => {
            selectedCounter.textContent = String(checkboxes.filter((checkbox) => checkbox.checked).length);
        };

        const storeColumns = () => {
            const values = checkboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(values));
        };

        const showToast = (message, type = 'success') => {
            if (!exportToast) {
                return;
            }

            if (toastTimer) {
                window.clearTimeout(toastTimer);
            }

            exportToast.textContent = message;
            exportToast.className = 'pointer-events-none fixed right-6 top-6 z-[70] max-w-sm rounded-xl border px-4 py-3 text-sm font-medium shadow-lg';
            exportToast.classList.add(
                type === 'error' ? 'border-rose-200' : 'border-emerald-200',
                type === 'error' ? 'bg-rose-50' : 'bg-emerald-50',
                type === 'error' ? 'text-rose-700' : 'text-emerald-700'
            );
            exportToast.classList.remove('hidden');

            toastTimer = window.setTimeout(() => {
                exportToast.classList.add('hidden');
            }, 4500);
        };

        const getLoadingMessage = () => {
            if (scopeSelect.value === 'current_filters' && currentRecordCount > 0) {
                return `Exportando ${currentRecordCount} registros del dashboard actual...`;
            }

            return 'Preparando la exportaci�n con las columnas seleccionadas.';
        };

        const setExportingState = (exporting) => {
            isExporting = exporting;
            submitExportButton.disabled = exporting;
            submitExportSpinner.classList.toggle('hidden', !exporting);
            submitExportLabel.textContent = exporting ? 'Generando...' : 'Descargar Excel';
            exportLoadingOverlay.classList.toggle('hidden', !exporting);
            exportLoadingMessage.textContent = getLoadingMessage();
        };

        const setCheckedColumns = (values) => {
            const selected = new Set(values);

            checkboxes.forEach((checkbox) => {
                checkbox.checked = selected.has(checkbox.value);
            });

            updateSelectedCount();
            storeColumns();
        };

        const syncScopeState = () => {
            const isRange = scopeSelect.value === 'monthly_range';
            rangeFields.classList.toggle('opacity-50', !isRange);

            rangeFields.querySelectorAll('select').forEach((select) => {
                select.disabled = !isRange;
            });
        };

        const openModal = () => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        const triggerDownloadUrl = (downloadUrl) => {
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.style.display = 'none';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
        };

        openButton.addEventListener('click', () => {
            const storedColumns = getStoredColumns();

            if (storedColumns.length > 0) {
                setCheckedColumns(storedColumns);
            }

            openModal();
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        selectAllButton.addEventListener('click', () => {
            setCheckedColumns(checkboxes.map((checkbox) => checkbox.value));
        });

        clearAllButton.addEventListener('click', () => {
            setCheckedColumns([]);
        });

        groupButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const groupKey = button.dataset.selectGroup;
                const groupValues = checkboxes
                    .filter((checkbox) => checkbox.dataset.columnGroup === groupKey)
                    .map((checkbox) => checkbox.value);
                const currentValues = checkboxes
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.value);

                setCheckedColumns([...new Set([...currentValues, ...groupValues])]);
            });
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                updateSelectedCount();
                storeColumns();
            });
        });

        modeSelect.addEventListener('change', () => {
            setCheckedColumns(DEFAULTS[modeSelect.value] || []);
        });

        scopeSelect.addEventListener('change', syncScopeState);

        const handleExportSubmit = async (event) => {
            event.preventDefault();

            if (isExporting) {
                return;
            }

            const selectedColumns = checkboxes.filter((checkbox) => checkbox.checked);
            if (selectedColumns.length === 0) {
                showToast('Selecciona al menos una columna para exportar.', 'error');
                return;
            }

            setExportingState(true);

            try {
                const response = await fetch(exportForm.action, {
                    method: 'POST',
                    body: new FormData(exportForm),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    let errorMessage = 'No se pudo generar el archivo Excel. Intentalo nuevamente.';

                    try {
                        const errorPayload = await response.json();
                        errorMessage = errorPayload.message
                            || errorPayload.error
                            || Object.values(errorPayload.errors ?? {}).flat()[0]
                            || errorMessage;
                    } catch (parseError) {
                        const errorText = await response.text();

                        if (errorText.trim() !== '') {
                            errorMessage = errorText;
                        }
                    }

                    throw new Error(errorMessage);
                }

                const payload = await response.json();

                if (!payload.download_url) {
                    throw new Error(payload.message || 'No se recibio la URL de descarga del Excel.');
                }

                triggerDownloadUrl(payload.download_url);
                closeModal();
                showToast(
                    (payload.record_count ?? 0) > 0
                        ? `Excel generado correctamente. ${payload.record_count} registros exportados.`
                        : 'Excel generado correctamente.'
                );
            } catch (error) {
                showToast(error instanceof Error ? error.message : 'No se pudo generar el archivo Excel.', 'error');
            } finally {
                setExportingState(false);
            }
        };

        exportForm.addEventListener('submit', handleExportSubmit);

        const storedColumns = getStoredColumns();
        if (!shouldOpenOnLoad && storedColumns.length > 0) {
            setCheckedColumns(storedColumns);
        } else {
            updateSelectedCount();
        }

        syncScopeState();
        setExportingState(false);

        if (shouldOpenOnLoad) {
            openModal();
        }
    })();
</script>
@endpush


