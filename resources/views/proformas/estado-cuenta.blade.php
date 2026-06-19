@extends('layouts.admin')

@section('title', 'Estado de Cuenta')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Estado de Cuenta</h1>
            <p class="text-sm text-slate-600">Consolide temporalmente proformas existentes para consultar deuda por cliente.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Volver a Proformas
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
        <p class="font-semibold uppercase tracking-wide">Advertencia</p>
        <p class="mt-1">{{ $warningMessage }}</p>
    </div>

    <div class="mb-6 rounded-xl bg-white p-5 shadow">
        <form method="GET" action="{{ route('proformas.estado-cuenta.index') }}" class="grid gap-4 md:grid-cols-4">
            <div>
                <label for="busqueda" class="mb-1 block text-sm font-medium text-slate-700">Código o Empresa</label>
                <input id="busqueda" name="busqueda" value="{{ $filters['busqueda'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Código, nombre o empresa">
            </div>
            <div>
                <label for="nit" class="mb-1 block text-sm font-medium text-slate-700">NIT</label>
                <input id="nit" name="nit" value="{{ $filters['nit'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="NIT del cliente">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($estadoOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($filters['estado'] ?? 'default') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Por defecto se incluyen Generada, Enviada y Facturada.</p>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Buscar
                </button>
                <a href="{{ route('proformas.estado-cuenta.index') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="overflow-hidden rounded-xl bg-white shadow">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Histórico de proformas</h2>
                <p class="mt-1 text-sm text-slate-600">Seleccione una o varias proformas del mismo NIT para generar el estado de cuenta consolidado.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-center">Sel.</th>
                            <th class="px-4 py-3">Proforma</th>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Periodo</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @if(!$hasSearched)
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">Ingrese un código, empresa o NIT para consultar proformas.</td>
                        </tr>
                    @else
                        @forelse($proformas as $proforma)
                            <tr
                                class="hover:bg-slate-50"
                                data-proforma-row
                                data-id="{{ $proforma->id }}"
                                data-nit="{{ $proforma->nit }}"
                                data-id-cobro="{{ $proforma->id_cobro ?? '' }}"
                                data-mes="{{ $proforma->mes }}"
                                data-anio="{{ $proforma->anio }}"
                                data-emisora="{{ $proforma->emisora }}"
                                data-valor="{{ (float) ($proforma->vtotal ?? 0) }}"
                                data-periodo="{{ $proforma->periodo_label }}"
                                data-empresa="{{ $proforma->empresa_resuelta }}"
                                data-email="{{ $proforma->cliente_email_raw ?? '' }}"
                                data-creado-en="{{ $proforma->creado_en ?? '' }}"
                                data-nro-prof="{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}"
                            >
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" name="proformas[]" value="{{ $proforma->id }}" form="estado-cuenta-actions-form" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" data-proforma-checkbox>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</p>
                                    <p class="text-xs text-slate-500">ID {{ $proforma->id }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $proforma->codigo ?: 'N/D' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $proforma->empresa_resuelta }}</p>
                                    <p class="text-xs text-slate-500">NIT: {{ $proforma->nit ?: 'N/D' }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $proforma->periodo_label }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}">
                                        {{ $proforma->estado_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">No se encontraron proformas con los filtros indicados.</td>
                            </tr>
                        @endforelse
                    @endif
                    </tbody>
                </table>
            </div>

            @if($hasSearched)
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $proformas->links() }}
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-slate-900">Resumen</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Empresa</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-empresa">N/D</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">NIT</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-nit">N/D</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Cantidad seleccionada</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-count">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Vigentes tras depuración</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-effective-count">0</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Periodo más antiguo</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-oldest">N/D</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Periodo más reciente</dt>
                        <dd class="text-right font-medium text-slate-800" id="summary-latest">N/D</dd>
                    </div>
                </dl>

                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total acumulado</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900" id="summary-total">$ 0,00</p>
                </div>

                <div id="summary-alert" class="mt-4 hidden rounded-lg border px-4 py-3 text-sm"></div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-slate-900">Acciones</h2>
                <form id="estado-cuenta-actions-form" method="POST" action="{{ route('proformas.estado-cuenta.pdf') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label for="destinatarios" class="mb-1 block text-sm font-medium text-slate-700">Destinatarios</label>
                        <input id="destinatarios" name="destinatarios" value="{{ old('destinatarios', $defaultDestinatarios) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="correo1@empresa.com, correo2@empresa.com">
                        <p class="mt-1 text-xs text-slate-500">Se admiten múltiples correos separados por coma o punto y coma. Se precarga el correo del cliente cuando está disponible.</p>
                    </div>

                    <input type="hidden" name="busqueda" value="{{ $filters['busqueda'] ?? '' }}">
                    <input type="hidden" name="nit" value="{{ $filters['nit'] ?? '' }}">
                    <input type="hidden" name="estado" value="{{ $filters['estado'] ?? 'default' }}">

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="submit" name="accion" value="pdf" class="inline-flex items-center justify-center rounded bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" data-submit-action="pdf">
                            Generar PDF
                        </button>
                        <button type="submit" name="accion" value="enviar" class="inline-flex items-center justify-center rounded bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" data-submit-action="enviar">
                            Generar y Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const rows = Array.from(document.querySelectorAll('[data-proforma-row]'));
        const checkboxes = Array.from(document.querySelectorAll('[data-proforma-checkbox]'));
        const summaryCount = document.getElementById('summary-count');
        const summaryEffectiveCount = document.getElementById('summary-effective-count');
        const summaryTotal = document.getElementById('summary-total');
        const summaryNit = document.getElementById('summary-nit');
        const summaryEmpresa = document.getElementById('summary-empresa');
        const summaryOldest = document.getElementById('summary-oldest');
        const summaryLatest = document.getElementById('summary-latest');
        const summaryAlert = document.getElementById('summary-alert');
        const destinatariosInput = document.getElementById('destinatarios');
        const submitButtons = Array.from(document.querySelectorAll('[data-submit-action]'));

        if (rows.length === 0 || checkboxes.length === 0) {
            return;
        }

        const currency = new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const parsePeriodValue = (row) => {
            const anio = Number(row.dataset.anio || 0);
            const mes = Number(row.dataset.mes || 0);

            return (anio * 100) + mes;
        };

        const selectedRows = () => rows.filter((row) => {
            const checkbox = row.querySelector('[data-proforma-checkbox]');

            return checkbox && checkbox.checked;
        });

        const setAlert = (message, tone = 'slate') => {
            if (!message) {
                summaryAlert.className = 'mt-4 hidden rounded-lg border px-4 py-3 text-sm';
                summaryAlert.innerHTML = '';
                return;
            }

            const tones = {
                rose: 'mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700',
                amber: 'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800',
                emerald: 'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700',
                slate: 'mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700',
            };

            summaryAlert.className = tones[tone] || tones.slate;
            summaryAlert.innerHTML = message;
        };

        const updateSummary = () => {
            const selected = selectedRows();
            let total = 0;
            let firstEmail = '';
            let firstNit = '';
            let firstEmpresa = '';
            let minPeriod = null;
            let maxPeriod = null;
            let mixedNit = false;

            const groups = new Map();

            selected.forEach((row) => {
                if (!firstNit) {
                    firstNit = row.dataset.nit || '';
                    firstEmpresa = row.dataset.empresa || 'N/D';
                } else if ((row.dataset.nit || '') !== firstNit) {
                    mixedNit = true;
                }

                if (!firstEmail && (row.dataset.email || '').trim() !== '') {
                    firstEmail = row.dataset.email.trim();
                }

                const idCobro = String(row.dataset.idCobro || '').trim();
                const logicalKey = [
                    row.dataset.nit || '',
                    row.dataset.mes || '',
                    row.dataset.anio || '',
                    (row.dataset.emisora || '').toUpperCase(),
                ].join('|');
                const groupKey = (idCobro !== '' && idCobro !== '0')
                    ? `id_cobro:${idCobro}`
                    : `logical:${logicalKey}`;

                if (!groups.has(groupKey)) {
                    groups.set(groupKey, []);
                }

                groups.get(groupKey).push(row);
            });

            const effectiveRows = [];
            const dedupeMessages = [];

            groups.forEach((rowsInGroup) => {
                const sorted = [...rowsInGroup].sort((left, right) => {
                    const leftDate = Date.parse(left.dataset.creadoEn || '') || 0;
                    const rightDate = Date.parse(right.dataset.creadoEn || '') || 0;

                    if (leftDate === rightDate) {
                        return Number(right.dataset.id || 0) - Number(left.dataset.id || 0);
                    }

                    return rightDate - leftDate;
                });

                const keptRow = sorted[0];
                const excludedRows = sorted.slice(1);
                effectiveRows.push(keptRow);

                if (excludedRows.length > 0) {
                    const periodo = keptRow.dataset.periodo || 'N/D';
                    const emisora = (keptRow.dataset.emisora || 'N/D').toUpperCase();
                    const keptLabel = `${keptRow.dataset.nroProf || '#'} ($${currency.format(Number(keptRow.dataset.valor || 0))})`;
                    const excludedLabel = excludedRows
                        .map((row) => `${row.dataset.nroProf || '#'} ($${currency.format(Number(row.dataset.valor || 0))})`)
                        .join(', ');

                    dedupeMessages.push(
                        `Se detectaron ${rowsInGroup.length} proformas para <strong>${periodo} / ${emisora}</strong>.<br>` +
                        `Se conservará automáticamente la proforma <strong>${keptLabel}</strong>.<br>` +
                        `Se excluirá${excludedRows.length > 1 ? 'n' : ''} la${excludedRows.length > 1 ? 's' : ''} proforma${excludedRows.length > 1 ? 's' : ''} <strong>${excludedLabel}</strong>.`
                    );
                }
            });

            effectiveRows.forEach((row) => {
                total += Number(row.dataset.valor || 0);

                const periodValue = parsePeriodValue(row);
                if (minPeriod === null || periodValue < minPeriod.periodValue) {
                    minPeriod = { periodValue, label: row.dataset.periodo || 'N/D' };
                }
                if (maxPeriod === null || periodValue > maxPeriod.periodValue) {
                    maxPeriod = { periodValue, label: row.dataset.periodo || 'N/D' };
                }
            });

            summaryCount.textContent = String(selected.length);
            summaryEffectiveCount.textContent = String(effectiveRows.length);
            summaryTotal.textContent = `$ ${currency.format(total)}`;
            summaryNit.textContent = mixedNit ? 'Múltiples NIT' : (firstNit || 'N/D');
            summaryEmpresa.textContent = mixedNit ? 'Selección inválida' : (firstEmpresa || 'N/D');
            summaryOldest.textContent = minPeriod?.label || 'N/D';
            summaryLatest.textContent = maxPeriod?.label || 'N/D';

            if (selected.length === 0) {
                setAlert('Seleccione al menos una proforma para habilitar las acciones.', 'slate');
            } else if (mixedNit) {
                setAlert('La selección mezcla proformas de diferentes NIT. Debe consolidar un solo cliente por vez.', 'rose');
            } else if (dedupeMessages.length > 0) {
                setAlert(dedupeMessages.join('<hr class="my-3 border-amber-200">'), 'amber');
            } else {
                setAlert('Selección lista para generar el estado de cuenta consolidado.', 'emerald');
            }

            submitButtons.forEach((button) => {
                button.disabled = selected.length === 0 || mixedNit;
            });

            if (destinatariosInput && destinatariosInput.value.trim() === '' && firstEmail) {
                destinatariosInput.value = firstEmail;
            }
        };

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSummary);
        });

        updateSummary();
    })();
</script>
@endpush
