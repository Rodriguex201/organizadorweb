@extends('layouts.admin')

@section('title', 'Importaciones')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Configuracion lateral</p>
            <h1 class="text-2xl font-bold text-slate-900">Importaciones</h1>
            <p class="text-sm text-slate-600">Carga archivos CSV con delimitador ;, XLSX o XLS para facturas, documento soporte y recepcion/eventos.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded border px-4 py-3 text-sm {{
            session('status_type') === 'warning'
                ? 'border-amber-300 bg-amber-50 text-amber-800'
                : (session('status_type') === 'error'
                    ? 'border-rose-300 bg-rose-50 text-rose-800'
                    : 'border-emerald-300 bg-emerald-50 text-emerald-800')
        }}">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">Hay errores de validacion:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-lg bg-white p-6 shadow">
        <form method="POST" action="{{ route('configuracion.importaciones.preview') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <label class="block text-sm">
                    <span class="text-slate-600">Mes</span>
                    <select name="mes" class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($meses as $mes)
                            <option value="{{ $mes }}" @selected(old('mes', $selectedMes) === $mes)>{{ ucfirst($mes) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm">
                    <span class="text-slate-600">Anio</span>
                    <input
                        type="number"
                        min="2000"
                        max="9999"
                        name="anio"
                        value="{{ old('anio', $selectedAnio) }}"
                        class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </label>

                <label class="block text-sm md:col-span-2 lg:col-span-1">
                    <span class="text-slate-600">Subir Facturas</span>
                    <input type="file" name="facturas_file" accept=".csv,.xlsx,.xls" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </label>

                <label class="block text-sm md:col-span-2 lg:col-span-1">
                    <span class="text-slate-600">Subir Documento soporte</span>
                    <input type="file" name="soporte_file" accept=".csv,.xlsx,.xls" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </label>

                <label class="block text-sm md:col-span-2 lg:col-span-1">
                    <span class="text-slate-600">Subir Recepcion/Eventos</span>
                    <input type="file" name="recepcion_file" accept=".csv,.xlsx,.xls" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </label>
            </div>

            <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Se extraen columnas con compatibilidad escritorio:
                <span class="font-medium">col[2] NIT</span>,
                <span class="font-medium">col[3] Emisor</span>,
                facturas <span class="font-medium">col[4]-col[6]</span>,
                soporte <span class="font-medium">col[4]-col[5]</span>,
                recepcion <span class="font-medium">col[4]</span>.
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Generar vista previa
                </button>
                @if ($preview)
                    <button type="submit" form="clearImportPreviewForm" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                        Limpiar temporal
                    </button>
                @endif
            </div>
        </form>

        @if ($preview)
            <form id="clearImportPreviewForm" method="POST" action="{{ route('configuracion.importaciones.clear') }}">
                @csrf
            </form>
        @endif
    </section>

    @if ($preview)
        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Vista previa antes de procesar</h2>
                    <p class="text-sm text-slate-600">Periodo: {{ ucfirst($preview['periodo']['mes']) }} {{ $preview['periodo']['anio'] }}.</p>
                </div>

                <form method="POST" action="{{ route('configuracion.importaciones.extract') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        EXTRAER DATOS
                    </button>
                </form>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-4">
                <div class="rounded border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Registros</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $preview['summary']['total'] }}</p>
                </div>
                <div class="rounded border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Listos</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $preview['summary']['ready'] }}</p>
                </div>
                <div class="rounded border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-amber-700">Con errores</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $preview['summary']['with_errors'] }}</p>
                </div>
                <div class="rounded border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-rose-700">Errores de lectura</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-900">{{ $preview['summary']['parse_errors'] }}</p>
                </div>
            </div>

            @if (!empty($preview['sources']))
                <div class="mt-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">Archivos cargados</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($preview['sources'] as $source)
                            <li>{{ $source['original_name'] ?? 'archivo' }} ({{ strtoupper((string) ($source['type'] ?? '')) }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Estado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">NIT</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Emisor</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Importado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Calculado</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Origen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="px-3 py-3 align-top">
                                    @if ($row['status'] === 'ready')
                                        <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">Listo</span>
                                    @else
                                        <span class="rounded bg-rose-100 px-2 py-1 text-xs font-medium text-rose-800">Error</span>
                                        <p class="mt-2 text-xs text-rose-700">{{ $row['error_message'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ $row['cliente'] }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ $row['nit'] }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">{{ $row['emisor'] }}</td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    F: {{ number_format((float) $row['imported']['facturas'], 0, ',', '.') }}
                                    | ND: {{ number_format((float) $row['imported']['nota_debito'], 0, ',', '.') }}
                                    | NC: {{ number_format((float) $row['imported']['nota_credito'], 0, ',', '.') }}
                                    | DS: {{ number_format((float) $row['imported']['soporte'], 0, ',', '.') }}
                                    | NA: {{ number_format((float) $row['imported']['nota_ajuste'], 0, ',', '.') }}
                                    | AC: {{ number_format((float) $row['imported']['acuse'], 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    VF: $ {{ number_format((float) $row['calculated']['valor_facturas'], 2, ',', '.') }}<br>
                                    VD: $ {{ number_format((float) $row['calculated']['valor_documentos'], 2, ',', '.') }}<br>
                                    VA: $ {{ number_format((float) $row['calculated']['valor_acuse'], 2, ',', '.') }}<br>
                                    VM: $ {{ number_format((float) $row['calculated']['valor_mensualidad'], 2, ',', '.') }}<br>
                                    VT: $ {{ number_format((float) $row['calculated']['valor_total'], 2, ',', '.') }}
                                </td>
                                <td class="px-3 py-3 align-top text-slate-700">
                                    {{ implode(', ', $row['sources']) }}<br>
                                    <span class="text-xs text-slate-500">Filas: {{ implode(', ', array_map('strval', $row['rows'])) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @php
        $erroresFinales = session('importacion_errores_finales', []);
        $erroresPreview = array_merge($preview['parse_errors'] ?? [], $preview['process_errors'] ?? []);
    @endphp

    @if (!empty($erroresPreview) || !empty($erroresFinales))
        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-slate-900">Errores encontrados</h2>
            <div class="mt-4 space-y-3">
                @foreach (array_merge($erroresPreview, $erroresFinales) as $error)
                    <div class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p><span class="font-semibold">Archivo:</span> {{ $error['file'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Fila:</span> {{ $error['row'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Detalle:</span> {{ $error['message'] ?? 'Error no especificado.' }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
