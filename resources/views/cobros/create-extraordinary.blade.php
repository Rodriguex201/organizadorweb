@extends('layouts.admin')

@section('title', 'Crear cobro extraordinario')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Generar cobro extraordinario</h1>
            <p class="text-sm text-slate-600">Crea un registro excepcional en <code>valores_externos</code> sin afectar la generación masiva mensual.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cobros.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Volver a cobros
            </a>
            @if(!empty($selectedClienteId))
                <a href="{{ route('clientes.edit', $selectedClienteId) }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                    Ver cliente
                </a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded border px-4 py-3 text-sm {{
            session('status_type') === 'warning'
                ? 'border-amber-300 bg-amber-50 text-amber-800'
                : 'border-emerald-300 bg-emerald-50 text-emerald-700'
        }}">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold mb-1">Hay errores de validación:</p>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cobros.extraordinario.store') }}" class="space-y-6">
        @csrf

        <section class="bg-white rounded-lg shadow p-5 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Cliente</span>
                        <input
                            type="text"
                            id="cliente_search"
                            autocomplete="off"
                            placeholder="Buscar por código o nombre..."
                            value="@if($selectedCliente){{ trim((string) ($selectedCliente->codigo ?? '')).' - '.trim((string) ($selectedCliente->empresa ?: $selectedCliente->nombre ?: 'Sin nombre')).((!empty($selectedCliente->fecha_retiro ?? null) || (int) ($selectedCliente->retirado ?? 0) === 1) ? ' (Retirado)' : '') }}@endif"
                            class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <div id="cliente_results" class="mt-2 hidden max-h-72 overflow-y-auto rounded border border-slate-200 bg-white shadow-sm"></div>
                        <select name="cliente_id" id="cliente_id" class="hidden">
                            <option value="">Seleccione un cliente</option>
                            @foreach($clientes as $cliente)
                                @php
                                    $label = trim((string) ($cliente->codigo ?? '')).' - '.trim((string) ($cliente->empresa ?: $cliente->nombre ?: 'Sin nombre'));
                                    $retirado = !empty($cliente->fecha_retiro ?? null) || (int) ($cliente->retirado ?? 0) === 1;
                                @endphp
                                <option
                                    value="{{ $cliente->id }}"
                                    data-vlrprincipal="{{ (float) ($cliente->vlrprincipal ?? 0) }}"
                                    data-numequipos="{{ (float) ($cliente->numequipos ?? 0) }}"
                                    data-vlrterminal="{{ (float) ($cliente->vlrterminal ?? 0) }}"
                                    data-numextra="{{ (float) ($cliente->numextra ?? 0) }}"
                                    data-vlrextrae="{{ (float) ($cliente->vlrextrae ?? 0) }}"
                                    data-vlrnomina="{{ (float) ($cliente->vlrnomina ?? 0) }}"
                                    data-numeromoviles="{{ (float) ($cliente->numeromoviles ?? 0) }}"
                                    data-vlrmovil="{{ (float) ($cliente->vlrmovil ?? 0) }}"
                                    data-vlrfactura="{{ (float) ($cliente->vlrfactura ?? 0) }}"
                                    data-vlrsoporte="{{ (float) ($cliente->vlrsoporte ?? 0) }}"
                                    data-vlrecepcion="{{ (float) ($cliente->vlrecepcion ?? 0) }}"
                                    data-vlrextra="{{ (float) ($cliente->vlrextra ?? 0) }}"
                                    data-vlrextra2="{{ (float) ($cliente->vlrextra2 ?? 0) }}"
                                    data-regimen="{{ $cliente->regimen ?? '' }}"
                                    @selected((int) old('cliente_id', $selectedClienteId ?? 0) === (int) $cliente->id)
                                >
                                    {{ $label }}{{ $retirado ? ' (Retirado)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Mes</span>
                        <select name="mes" id="mes" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($meses as $nombre)
                                <option value="{{ $nombre }}" @selected(old('mes', $selectedMes) === $nombre)>{{ ucfirst($nombre) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Año</span>
                        <input type="number" name="anio" min="1900" max="9999" value="{{ old('anio', $selectedAnio) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Número facturas</span>
                        <input type="number" step="1" min="0" name="numero_facturas" id="numero_facturas" value="{{ old('numero_facturas', 0) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Número soportes</span>
                        <input type="number" step="1" min="0" name="numero_documento_soporte" id="numero_documento_soporte" value="{{ old('numero_documento_soporte', 0) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Número acuses</span>
                        <input type="number" step="1" min="0" name="numero_acuse" id="numero_acuse" value="{{ old('numero_acuse', 0) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Valor extra</span>
                        <input type="number" step="0.01" min="0" name="valor_extra" id="valor_extra" value="{{ old('valor_extra', $selectedCliente->vlrextra ?? 0) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="block text-sm">
                        <span class="text-slate-500">Valor extra 2</span>
                        <input type="number" step="0.01" min="0" name="valor_extra2" id="valor_extra2" value="{{ old('valor_extra2', $selectedCliente->vlrextra2 ?? 0) }}" class="mt-2 w-full rounded border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-lg shadow p-5 space-y-4">
            <h2 class="text-lg font-semibold">Resumen calculado</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-4 text-sm">
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Código empresa</p>
                    <p id="preview_codigo" class="mt-1 font-semibold text-slate-900">{{ $selectedCliente->codigo ?? 'N/D' }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Valor mensualidad</p>
                    <p id="preview_valor_mensualidad" class="mt-1 font-semibold text-slate-900">{{ number_format((float) ($preview['total_mensualidad'] ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Valor facturas</p>
                    <p id="preview_valor_facturas" class="mt-1 font-semibold text-slate-900">{{ number_format((float) ($preview['valor_facturas'] ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Valor documentos</p>
                    <p id="preview_valor_documentos" class="mt-1 font-semibold text-slate-900">{{ number_format((float) ($preview['valor_documentos'] ?? 0), 2, ',', '.') }}</p>
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Valor acuse</p>
                    <p id="preview_valor_acuse" class="mt-1 font-semibold text-slate-900">{{ number_format((float) ($preview['valor_acuse'] ?? 0), 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="rounded border border-indigo-200 bg-indigo-50 px-4 py-4">
                <p class="text-sm text-indigo-700">Valor total a guardar en <code>valores_externos.valor_total</code></p>
                <p id="preview_valor_total" class="mt-2 text-2xl font-bold text-indigo-900">{{ number_format((float) ($preview['valor_total_proforma'] ?? 0), 2, ',', '.') }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Crear cobro
                </button>
                <a href="{{ route('cobros.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                    Cancelar
                </a>
            </div>
        </section>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect = document.getElementById('cliente_id');
    const clienteSearch = document.getElementById('cliente_search');
    const clienteResults = document.getElementById('cliente_results');
    const facturasInput = document.getElementById('numero_facturas');
    const soportesInput = document.getElementById('numero_documento_soporte');
    const acusesInput = document.getElementById('numero_acuse');
    const extraInput = document.getElementById('valor_extra');
    const extra2Input = document.getElementById('valor_extra2');

    if (!clienteSelect || !clienteSearch || !clienteResults || !facturasInput || !soportesInput || !acusesInput || !extraInput || !extra2Input) {
        return;
    }

    const toNumber = (value) => parseFloat(value || '0') || 0;
    const money = (value) => value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const clienteOptions = Array.from(clienteSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
            value: option.value,
            label: option.text.trim(),
            search: option.text.trim().toLowerCase(),
            option,
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'es', { sensitivity: 'base' }));

    const hideResults = () => {
        clienteResults.innerHTML = '';
        clienteResults.classList.add('hidden');
    };

    const getSelectedOption = () => clienteSelect.options[clienteSelect.selectedIndex] || null;

    const syncSearchWithSelection = () => {
        const option = getSelectedOption();
        clienteSearch.value = option && option.value !== '' ? option.text.trim() : '';
    };

    const selectCliente = (value) => {
        clienteSelect.value = value;
        syncSearchWithSelection();
        hideResults();
        const option = getSelectedOption();
        if (!extraInput.dataset.touched) {
            extraInput.value = option?.dataset.vlrextra || '0';
        }
        if (!extra2Input.dataset.touched) {
            extra2Input.value = option?.dataset.vlrextra2 || '0';
        }
        render();
    };

    const renderResults = (term) => {
        const normalized = term.trim().toLowerCase();

        if (normalized === '') {
            hideResults();
            return;
        }

        const matches = clienteOptions
            .filter((item) => item.search.includes(normalized))
            .slice(0, 25);

        if (matches.length === 0) {
            clienteResults.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500">Sin coincidencias</div>';
            clienteResults.classList.remove('hidden');
            return;
        }

        clienteResults.innerHTML = matches.map((item) => {
            const selectedClass = clienteSelect.value === item.value ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-slate-50';
            return `<button type="button" data-cliente-value="${item.value}" class="block w-full px-3 py-2 text-left text-sm ${selectedClass}">${item.label}</button>`;
        }).join('');

        clienteResults.classList.remove('hidden');
    };

    const render = () => {
        const option = getSelectedOption();
        const codigo = option?.text?.split(' - ')[0] || 'N/D';
        const valorPrincipal = toNumber(option?.dataset.vlrprincipal);
        const numeroEquipos = toNumber(option?.dataset.numequipos);
        const valorTerminal = toNumber(option?.dataset.vlrterminal);
        const numextra = toNumber(option?.dataset.numextra);
        const vlrextrae = toNumber(option?.dataset.vlrextrae);
        const vlrnomina = toNumber(option?.dataset.vlrnomina);
        const numeromoviles = toNumber(option?.dataset.numeromoviles);
        const vlrmovil = toNumber(option?.dataset.vlrmovil);
        const vlrfactura = toNumber(option?.dataset.vlrfactura);
        const vlrsoporte = toNumber(option?.dataset.vlrsoporte);
        const vlrecepcion = toNumber(option?.dataset.vlrecepcion);

        const facturas = toNumber(facturasInput.value);
        const soportes = toNumber(soportesInput.value);
        const acuses = toNumber(acusesInput.value);
        const extra = toNumber(extraInput.value);
        const extra2 = toNumber(extra2Input.value);

        const equiposAdicionales = Math.max(numeroEquipos - 1, 0);
        const valorMensualidad = valorPrincipal + (valorTerminal * equiposAdicionales) + (numextra * vlrextrae) + vlrnomina + (numeromoviles * vlrmovil) + extra + extra2;
        const valorFacturas = facturas * vlrfactura;
        const valorDocumentos = soportes * vlrsoporte;
        const valorAcuse = acuses * vlrecepcion;
        const valorTotal = valorMensualidad + valorFacturas + valorDocumentos + valorAcuse;

        document.getElementById('preview_codigo').textContent = codigo || 'N/D';
        document.getElementById('preview_valor_mensualidad').textContent = money(valorMensualidad);
        document.getElementById('preview_valor_facturas').textContent = money(valorFacturas);
        document.getElementById('preview_valor_documentos').textContent = money(valorDocumentos);
        document.getElementById('preview_valor_acuse').textContent = money(valorAcuse);
        document.getElementById('preview_valor_total').textContent = money(valorTotal);
    };

    [facturasInput, soportesInput, acusesInput, extraInput, extra2Input].forEach((element) => {
        element.addEventListener('input', render);
        element.addEventListener('change', render);
    });

    clienteSearch.addEventListener('input', function () {
        if (clienteSearch.value.trim() === '') {
            clienteSelect.value = '';
            render();
        }
        renderResults(clienteSearch.value);
    });

    clienteSearch.addEventListener('focus', function () {
        if (clienteSearch.value.trim() !== '') {
            renderResults(clienteSearch.value);
        }
    });

    clienteResults.addEventListener('click', function (event) {
        const button = event.target.closest('[data-cliente-value]');
        if (!button) {
            return;
        }

        selectCliente(button.dataset.clienteValue);
    });

    extraInput.addEventListener('input', () => {
        extraInput.dataset.touched = '1';
    });

    extra2Input.addEventListener('input', () => {
        extra2Input.dataset.touched = '1';
    });

    document.addEventListener('click', function (event) {
        if (!clienteResults.contains(event.target) && event.target !== clienteSearch) {
            hideResults();
        }
    });

    clienteSearch.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideResults();
            clienteSearch.blur();
        }
    });

    syncSearchWithSelection();
    render();
});
</script>
@endpush
