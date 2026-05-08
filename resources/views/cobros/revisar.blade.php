@extends('layouts.admin')

@section('title', 'Revision de Proforma')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Modulo Cobros</p>
            <h1 class="text-2xl font-bold">Revision manual de proforma</h1>
            <p class="text-sm text-slate-600">Cobro #{{ $cobro->id_cobro }} · {{ ucfirst((string) ($cobro->mes ?? '')) }} {{ $cobro->{'año'} ?? $cobro->{'aÃ±o'} ?? '' }}</p>
        </div>
        <a href="{{ route('cobros.show', array_merge(['id' => $cobro->id_cobro], request()->query())) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
            Volver al detalle
        </a>
    </div>

    @if (session('status'))
        <div class="rounded border px-4 py-3 text-sm {{
            session('status_type') === 'warning'
                ? 'border-amber-300 bg-amber-50 text-amber-700'
                : (session('status_type') === 'error'
                    ? 'border-rose-300 bg-rose-50 text-rose-700'
                    : 'border-emerald-300 bg-emerald-50 text-emerald-700')
        }}">
            {{ session('status') }}
        </div>
    @endif

    @if(!empty($proformaPersistidaId))
        <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Ya existe una proforma generada para este cobro. Si cambió algún valor manual, puede reconstruirla sin crear duplicados.
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold mb-1">Hay errores de validacion:</p>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="revisionProformaForm" method="POST" action="{{ route('cobros.revisar.guardar', $cobro->id_cobro) }}" class="space-y-6">
        @csrf

        <input type="hidden" name="id_cliente" value="{{ $cobro->id_cliente }}">
        <input type="hidden" name="codigo_concepto_extra" id="codigoConceptoExtraHidden" value="{{ old('codigo_concepto_extra') }}">
        <input type="hidden" name="descripcion_concepto_extra" id="descripcionConceptoExtraHidden" value="{{ old('descripcion_concepto_extra') }}">

        <section class="bg-white rounded-lg shadow p-5">
            <h2 class="text-lg font-semibold mb-4">a) Datos del cliente</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-slate-500">Codigo</dt><dd class="font-medium">{{ $cobro->cliente_codigo ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $cobro->cliente_nombre ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Correo</dt><dd class="font-medium">{{ $cobro->cliente_email ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Regimen</dt><dd class="font-medium">{{ $cobro->cliente_regimen ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">{{ $cobro->cliente_empresa ?? $cobro->cliente_nombre ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Celular</dt><dd class="font-medium">{{ $cobro->cliente_celular1 ?? $cobro->cliente_celular2 ?? 'N/D' }}</dd></div>
                <div class="md:col-span-2 lg:col-span-3"><dt class="text-slate-500">Notas</dt><dd class="font-medium">{{ $cobro->notas ?? 'Sin notas registradas' }}</dd></div>
            </dl>
        </section>

        <section class="bg-white rounded-lg shadow p-5 space-y-5">
            <h2 class="text-lg font-semibold">b) Valores de la proforma</h2>

            @php
                $columnMap = [
                    'numero_equipos' => 'numero_equipos',
                    'valor_principal' => 'valor_principal',
                    'valor_terminal' => 'valor_terminal',
                    'numero_equipos_extra' => 'numextra',
                    'valor_equipo_extra' => 'vlrextrae',
                    'empleados' => 'empleados',
                    'valor_nomina' => 'vlrnomina',
                    'numero_moviles' => 'numero_moviles',
                    'valor_movil' => 'valor_movil',
                    'facturas' => 'numero_facturas',
                    'nota_debito' => 'numero_nota_debito',
                    'nota_credito' => 'numero_nota_credito',
                    'soporte' => 'numero_documento_soporte',
                    'nota_ajuste' => 'numero_nota_ajuste',
                    'acuse' => 'numero_acuse',
                    'otro_valor_extra' => 'valor_extra',
                    'valor_terminal_recepcion' => 'valor_extra2',
                    'precio_soporte' => 'precio_soporte',
                    'precio_acuse' => 'precio_acuse',
                ];

                $calculatedColumnMap = [
                    'total_facturas' => 'total_facturas',
                    'valor_facturas' => 'valor_facturas',
                    'total_documentos' => 'total_documentos',
                    'valor_documentos' => 'valor_documentos',
                    'valor_acuse' => 'valor_acuse',
                    'total_mensualidad' => 'valor_mensualidad',
                    'valor_total_proforma' => 'valor_total',
                ];

                $inputLabels = [
                    'numero_equipos' => 'Numero equipos',
                    'valor_principal' => 'Valor principal',
                    'valor_terminal' => 'Valor terminal',
                    'numero_equipos_extra' => 'Numero equipos extra',
                    'valor_equipo_extra' => 'Valor equipo extra',
                    'empleados' => 'Empleados',
                    'valor_nomina' => 'Valor nomina',
                    'numero_moviles' => 'Numero moviles',
                    'valor_movil' => 'Valor movil',
                    'facturas' => 'Facturas',
                    'nota_debito' => 'Nota debito',
                    'nota_credito' => 'Nota credito',
                    'soporte' => 'Soporte',
                    'nota_ajuste' => 'Nota ajuste',
                    'acuse' => 'Acuse',
                    'otro_valor_extra' => 'Otro valor extra',
                    'valor_terminal_recepcion' => 'Valor terminal recepcion',
                    'precio_factura' => 'Precio factura (configuracion cliente)',
                    'precio_soporte' => 'Precio soporte',
                    'precio_acuse' => 'Precio acuse',
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                @foreach($inputLabels as $key => $label)
                    @php
                        $defaultValue = $key === 'precio_factura'
                            ? ($formData[$key] ?? 0)
                            : ($valores?->{$columnMap[$key]} ?? ($formData[$key] ?? 0));
                    @endphp
                    <label class="block">
                        <span class="text-slate-500">{{ $label }}</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="{{ $key }}"
                            value="{{ old($key, $defaultValue) }}"
                            data-revision-input="{{ $key }}"
                            class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </label>
                @endforeach
            </div>

            <div class="border-t pt-4">
                <h3 class="font-semibold mb-3">Campos calculados (solo lectura)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    @foreach([
                        'total_facturas' => 'Total facturas',
                        'valor_facturas' => 'Valor facturas',
                        'total_documentos' => 'Total documentos',
                        'valor_documentos' => 'Valor soporte / documentos',
                        'valor_acuse' => 'Valor acuse',
                        'total_mensualidad' => 'Total mensualidad',
                        'valor_total_proforma' => 'Valor total proforma',
                    ] as $key => $label)
                        <label class="block">
                            <span class="text-slate-500">{{ $label }}</span>
                            <input
                                type="number"
                                step="0.01"
                                value="{{ $valores?->{$calculatedColumnMap[$key]} ?? ($formData[$key] ?? 0) }}"
                                readonly
                                data-calculated-field="{{ $key }}"
                                class="mt-1 w-full rounded border-slate-200 bg-slate-100 text-slate-700"
                            >
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pt-2 flex flex-wrap gap-3">
                <button type="submit" name="accion" value="recalcular" class="inline-flex items-center rounded bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Recalcular
                </button>
                <button type="submit" name="accion" value="guardar" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar revision
                </button>
                @if(!empty($proformaPersistidaId))
                    <button type="submit" form="regenerarProformaForm" class="inline-flex items-center gap-2 rounded bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 1 1-9.588-3.368H3.5a.75.75 0 0 1 0-1.5h4a.75.75 0 0 1 .75.75v4a.75.75 0 0 1-1.5 0V9.18a4 4 0 1 0 6.98 2.45.75.75 0 0 1 1.482-.206Z" clip-rule="evenodd" />
                        </svg>
                        Regenerar proforma
                    </button>
                    <a href="{{ route('proformas.pdf.show', $proformaPersistidaId) }}" target="_blank" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Ver PDF de proforma guardada
                    </a>
                @else
                    <button type="submit" name="accion" value="generar" id="btnGenerarProforma" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Generar proforma
                    </button>
                @endif
            </div>
        </section>
    </form>
</div>

@if(!empty($proformaPersistidaId))
    <form
        id="regenerarProformaForm"
        method="POST"
        action="{{ route('cobros.proforma.regenerar', $cobro->id_cobro) }}"
        onsubmit="return confirm('Esto reemplazará la proforma actual y actualizará sus valores. ¿Desea continuar?');"
    >
        @csrf
        <input type="hidden" name="redirect_to" value="revisar">
    </form>
@endif

<div id="conceptoExtraModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4" aria-hidden="true">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="border-b px-5 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Informacion adicional para valor extra</h3>
            <p class="mt-1 text-sm text-slate-600">Debes ingresar el codigo y la descripcion del concepto antes de generar la proforma.</p>
        </div>
        <div class="space-y-4 px-5 py-4">
            <label class="block text-sm">
                <span class="text-slate-600">Codigo concepto</span>
                <input id="codigoConceptoExtraInput" type="text" class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" maxlength="100">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Descripcion concepto</span>
                <textarea id="descripcionConceptoExtraInput" rows="4" class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" maxlength="500"></textarea>
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t px-5 py-4">
            <button type="button" id="cancelarConceptoExtraModal" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Cancelar
            </button>
            <button type="button" id="confirmarConceptoExtraModal" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('revisionProformaForm');
    const modal = document.getElementById('conceptoExtraModal');
    const codigoInput = document.getElementById('codigoConceptoExtraInput');
    const descripcionInput = document.getElementById('descripcionConceptoExtraInput');
    const codigoHidden = document.getElementById('codigoConceptoExtraHidden');
    const descripcionHidden = document.getElementById('descripcionConceptoExtraHidden');
    const confirmarBtn = document.getElementById('confirmarConceptoExtraModal');
    const cancelarBtn = document.getElementById('cancelarConceptoExtraModal');
    const inputKeys = [
        'numero_equipos',
        'valor_principal',
        'valor_terminal',
        'numero_equipos_extra',
        'valor_equipo_extra',
        'empleados',
        'valor_nomina',
        'numero_moviles',
        'valor_movil',
        'facturas',
        'nota_debito',
        'nota_credito',
        'soporte',
        'nota_ajuste',
        'acuse',
        'otro_valor_extra',
        'valor_terminal_recepcion',
        'precio_factura',
        'precio_soporte',
        'precio_acuse',
    ];

    let permitirEnvioGenerar = false;

    if (!form || !modal || !codigoInput || !descripcionInput || !codigoHidden || !descripcionHidden || !confirmarBtn || !cancelarBtn) {
        return;
    }

    const parseNumber = function (name) {
        const field = form.querySelector(`[data-revision-input="${name}"]`);
        return parseFloat(field ? field.value : '0') || 0;
    };

    const setCalculated = function (name, value) {
        const field = form.querySelector(`[data-calculated-field="${name}"]`);
        if (field) {
            field.value = value.toFixed(2);
        }
    };

    const recalcularVista = function () {
        const numeroEquipos = parseNumber('numero_equipos');
        const valorPrincipal = parseNumber('valor_principal');
        const valorTerminal = parseNumber('valor_terminal');
        const numeroEquiposExtra = parseNumber('numero_equipos_extra');
        const valorEquipoExtra = parseNumber('valor_equipo_extra');
        const valorNomina = parseNumber('valor_nomina');
        const facturas = parseNumber('facturas');
        const notaDebito = parseNumber('nota_debito');
        const notaCredito = parseNumber('nota_credito');
        const soporte = parseNumber('soporte');
        const notaAjuste = parseNumber('nota_ajuste');
        const acuse = parseNumber('acuse');
        const otroValorExtra = parseNumber('otro_valor_extra');
        const valorTerminalRecepcion = parseNumber('valor_terminal_recepcion');
        const precioFactura = parseNumber('precio_factura');
        const precioSoporte = parseNumber('precio_soporte');
        const precioAcuse = parseNumber('precio_acuse');
        const equiposAdicionales = Math.max(numeroEquipos - 1, 0);

        const totalFacturas = facturas + notaDebito + notaCredito;
        const valorFacturas = facturas * precioFactura;
        const totalDocumentos = soporte + notaAjuste;
        const valorDocumentos = soporte * precioSoporte;
        const valorAcuse = acuse * precioAcuse;
        const totalMensualidad = valorPrincipal
            + (valorTerminal * equiposAdicionales)
            + (valorEquipoExtra * numeroEquiposExtra)
            + valorNomina;
        const valorTotalProforma = totalMensualidad
            + valorFacturas
            + valorDocumentos
            + valorAcuse
            + otroValorExtra
            + valorTerminalRecepcion;

        setCalculated('total_facturas', totalFacturas);
        setCalculated('valor_facturas', valorFacturas);
        setCalculated('total_documentos', totalDocumentos);
        setCalculated('valor_documentos', valorDocumentos);
        setCalculated('valor_acuse', valorAcuse);
        setCalculated('total_mensualidad', totalMensualidad);
        setCalculated('valor_total_proforma', valorTotalProforma);
    };

    inputKeys.forEach(function (key) {
        const field = form.querySelector(`[data-revision-input="${key}"]`);
        if (field) {
            field.addEventListener('input', recalcularVista);
        }
    });

    recalcularVista();

    const abrirModal = function () {
        codigoInput.value = codigoHidden.value || '';
        descripcionInput.value = descripcionHidden.value || '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        codigoInput.focus();
    };

    const cerrarModal = function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    };

    form.addEventListener('submit', function (event) {
        const accion = event.submitter?.value;

        if (accion !== 'generar' || permitirEnvioGenerar) {
            return;
        }

        const valorExtra = parseNumber('otro_valor_extra');

        if (valorExtra <= 0) {
            return;
        }

        event.preventDefault();
        abrirModal();
    });

    confirmarBtn.addEventListener('click', function () {
        const codigo = codigoInput.value.trim();
        const descripcion = descripcionInput.value.trim();

        if (!codigo || !descripcion) {
            alert('Debes ingresar codigo y descripcion del concepto para continuar.');
            return;
        }

        codigoHidden.value = codigo;
        descripcionHidden.value = descripcion;
        permitirEnvioGenerar = true;
        cerrarModal();
        form.requestSubmit(document.getElementById('btnGenerarProforma'));
    });

    cancelarBtn.addEventListener('click', function () {
        cerrarModal();
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            cerrarModal();
        }
    });
});
</script>
@endsection
