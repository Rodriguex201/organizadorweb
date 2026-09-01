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
        @php
            $previewRowsCollection = collect($preview['rows'] ?? []);
            $pendingAssignmentRows = $previewRowsCollection->filter(fn (array $row) => ($row['status'] ?? null) === 'pending_assignment');
            $pendingAssignmentsByNit = $pendingAssignmentRows->groupBy(fn (array $row) => (string) ($row['nit'] ?? ''));
            $duplicadosPendientes = $pendingAssignmentsByNit->count();
            $duplicadosResueltos = $previewRowsCollection->filter(fn (array $row) => ($row['status'] ?? null) === 'ready' && !empty($row['resolved_manually']))->count();
            $processablePreviewRows = $previewRowsCollection->reject(fn (array $row) => ($row['status'] ?? null) === 'pending_assignment');
        @endphp
        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Vista previa antes de procesar</h2>
                    <p class="text-sm text-slate-600">Periodo: {{ ucfirst($preview['periodo']['mes']) }} {{ $preview['periodo']['anio'] }}.</p>
                </div>

                @if (!($preview['requires_base_generation'] ?? false))
                    <form method="POST" action="{{ route('configuracion.importaciones.extract') }}">
                        @csrf
                        <button
                            type="submit"
                            @disabled($duplicadosPendientes > 0)
                            class="inline-flex items-center rounded px-4 py-2 text-sm font-medium text-white {{ $duplicadosPendientes > 0 ? 'cursor-not-allowed bg-slate-400' : 'bg-emerald-600 hover:bg-emerald-700' }}"
                        >
                            EXTRAER DATOS
                        </button>
                        @if ($duplicadosPendientes > 0)
                            <p class="mt-2 max-w-xs text-xs text-amber-700">
                                Debes resolver {{ $duplicadosPendientes }} NIT pendiente(s) de asignacion antes de extraer.
                            </p>
                        @endif
                    </form>
                @endif
            </div>

            @if ($preview['requires_base_generation'] ?? false)
                <div class="mt-4 rounded border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    <p class="font-semibold">Primero debes generar los registros base del período.</p>
                    <p class="mt-1">{{ $preview['base_generation_notice'] }}</p>

                    <form method="POST" action="{{ route('configuracion.importaciones.generate-base') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            Generar registros base
                        </button>
                    </form>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Usa las tarjetas como filtros rapidos del preview.</p>
                <button
                    type="button"
                    data-preview-filter-clear
                    class="inline-flex items-center rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Limpiar filtro
                </button>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3 xl:grid-cols-6" data-preview-filters>
                <button type="button" data-filter-card="all" class="rounded border border-slate-200 bg-slate-50 p-4 text-left transition hover:border-slate-300 hover:bg-slate-100">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Registros</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $preview['summary']['total'] }}</p>
                </button>
                <button type="button" data-filter-card="ready" class="rounded border border-emerald-200 bg-emerald-50 p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-100">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Listos</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $preview['summary']['ready'] }}</p>
                </button>
                <button type="button" data-filter-card="error" class="rounded border border-amber-200 bg-amber-50 p-4 text-left transition hover:border-amber-300 hover:bg-amber-100">
                    <p class="text-xs uppercase tracking-wide text-amber-700">Con errores</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $preview['summary']['with_errors'] }}</p>
                </button>
                <button type="button" data-filter-card="parse_error" class="rounded border border-rose-200 bg-rose-50 p-4 text-left transition hover:border-rose-300 hover:bg-rose-100">
                    <p class="text-xs uppercase tracking-wide text-rose-700">Errores de lectura</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-900">{{ $preview['summary']['parse_errors'] }}</p>
                </button>
                <button type="button" data-filter-card="duplicate_pending" class="rounded border border-amber-300 bg-amber-50 p-4 text-left transition hover:border-amber-400 hover:bg-amber-100">
                    <p class="text-xs uppercase tracking-wide text-amber-800">Duplicados pendientes</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $duplicadosPendientes }}</p>
                </button>
                <button type="button" data-filter-card="duplicate_resolved" class="rounded border border-emerald-300 bg-emerald-50 p-4 text-left transition hover:border-emerald-400 hover:bg-emerald-100">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Duplicados resueltos</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $duplicadosResueltos }}</p>
                </button>
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

            @if ($pendingAssignmentsByNit->isNotEmpty())
                <section class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <h3 class="text-base font-semibold text-amber-900">NIT pendientes de asignacion</h3>
                    <p class="mt-1 text-sm text-amber-800">
                        Se encontraron multiples clientes potenciales para estos NIT. Selecciona el cliente correcto para continuar con la importacion.
                    </p>

                    <div class="mt-4 space-y-4">
                        @foreach ($pendingAssignmentsByNit as $nit => $nitRows)
                            @php
                                $firstPendingRow = $nitRows->first();
                                $totalRegistrosNit = $nitRows->sum(fn (array $row) => count((array) ($row['rows'] ?? [])));
                            @endphp
                            <article class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">NIT: {{ $nit }}</p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            Este NIT aparece en {{ $totalRegistrosNit }} registro(s) del archivo.
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Seleccione el cliente potencial correspondiente.
                                        </p>
                                    </div>
                                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                        {{ $firstPendingRow['match_count'] ?? 0 }} coincidencias
                                    </span>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                                        <thead class="bg-slate-100">
                                            <tr>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">id_cliente</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">id_cobro</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Codigo</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Nombre</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Empresa</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Regimen</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha arriendo</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha retiro</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Estado</th>
                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach (($firstPendingRow['matches'] ?? []) as $match)
                                                <tr>
                                                    <td class="px-2 py-2 align-top">{{ $match['id_cliente'] }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['id_cobro'] }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['codigo'] !== '' ? $match['codigo'] : '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['nombre'] !== '' ? $match['nombre'] : '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['empresa'] !== '' ? $match['empresa'] : '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['regimen'] !== '' ? $match['regimen'] : '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['fecha_arriendo'] ?: '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['fecha_retiro'] ?: '-' }}</td>
                                                    <td class="px-2 py-2 align-top">{{ $match['estado'] }}</td>
                                                    <td class="px-2 py-2 align-top">
                                                        <form method="POST" action="{{ route('configuracion.importaciones.assign-ambiguous') }}">
                                                            @csrf
                                                            <input type="hidden" name="entry_id" value="{{ $firstPendingRow['entry_id'] }}">
                                                            <input type="hidden" name="id_cobro" value="{{ $match['id_cobro'] }}">
                                                            <button type="submit" class="rounded bg-indigo-600 px-2 py-1 font-medium text-white hover:bg-indigo-700">
                                                                Asignar a este cliente
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($processablePreviewRows->isNotEmpty())
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
                        <tbody class="divide-y divide-slate-100 bg-white" data-preview-table-body>
                            @foreach ($processablePreviewRows as $row)
                                <tr
                                    data-preview-row
                                    data-filter-type="{{ ($row['status'] ?? null) === 'ready' ? (!empty($row['resolved_manually']) ? 'duplicate_resolved' : 'ready') : (!empty($row['match_count']) ? 'duplicate_pending' : 'error') }}"
                                    @class([
                                    'bg-emerald-50/70' => !empty($row['resolved_manually']),
                                ])>
                                    <td class="px-3 py-3 align-top">
                                        @if ($row['status'] === 'ready')
                                            <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">Listo</span>
                                            @if (!empty($row['resolved_manually']))
                                                <div class="mt-2 space-y-2">
                                                    <span class="inline-flex items-center rounded bg-emerald-600 px-2 py-1 text-xs font-medium text-white">
                                                        ✓ Resuelto manualmente
                                                    </span>
                                                    <p class="text-xs font-medium text-emerald-800">
                                                        Asignado a {{ $row['selected_codigo'] !== '' ? $row['selected_codigo'] : 'id_cobro '.$row['selected_id_cobro'] }}
                                                    </p>
                                                    @if (!empty($row['matches']))
                                                        <details class="rounded border border-emerald-200 bg-white p-3 text-xs text-slate-700">
                                                            <summary class="cursor-pointer font-medium text-emerald-800">Cambiar asignacion</summary>
                                                            <div class="mt-3 space-y-3">
                                                                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                                                                    <p><span class="font-semibold">NIT + DV:</span> {{ $row['nit'] }}</p>
                                                                    <p><span class="font-semibold">Emisor Excel:</span> {{ $row['emisor'] }}</p>
                                                                    <p><span class="font-semibold">Archivo/Fila:</span> {{ implode(', ', $row['sources']) }} / filas {{ implode(', ', array_map('strval', $row['rows'])) }}</p>
                                                                    <p><span class="font-semibold">Coincidencias encontradas:</span> {{ $row['match_count'] }}</p>
                                                                </div>
                                                                <div class="overflow-x-auto">
                                                                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                                        <thead class="bg-slate-100">
                                                                            <tr>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">id_cliente</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">id_cobro</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Codigo</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Nombre</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Empresa</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Regimen</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha arriendo</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha retiro</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Estado</th>
                                                                                <th class="px-2 py-2 text-left font-medium text-slate-600">Accion</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-slate-100 bg-white">
                                                                            @foreach ($row['matches'] as $match)
                                                                                <tr @class([
                                                                                    'bg-emerald-50' => (int) $match['id_cobro'] === (int) ($row['selected_id_cobro'] ?? 0),
                                                                                ])>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['id_cliente'] }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['id_cobro'] }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['codigo'] !== '' ? $match['codigo'] : '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['nombre'] !== '' ? $match['nombre'] : '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['empresa'] !== '' ? $match['empresa'] : '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['regimen'] !== '' ? $match['regimen'] : '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['fecha_arriendo'] ?: '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['fecha_retiro'] ?: '—' }}</td>
                                                                                    <td class="px-2 py-2 align-top">{{ $match['estado'] }}</td>
                                                                                    <td class="px-2 py-2 align-top">
                                                                                        @if ((int) $match['id_cobro'] === (int) ($row['selected_id_cobro'] ?? 0))
                                                                                            <span class="rounded bg-emerald-100 px-2 py-1 font-medium text-emerald-800">Seleccionado</span>
                                                                                        @else
                                                                                            <form method="POST" action="{{ route('configuracion.importaciones.assign-ambiguous') }}">
                                                                                                @csrf
                                                                                                <input type="hidden" name="entry_id" value="{{ $row['entry_id'] }}">
                                                                                                <input type="hidden" name="id_cobro" value="{{ $match['id_cobro'] }}">
                                                                                                <button type="submit" class="rounded bg-indigo-600 px-2 py-1 font-medium text-white hover:bg-indigo-700">
                                                                                                    Asignar a este cliente
                                                                                                </button>
                                                                                            </form>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </details>
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            <span class="rounded bg-rose-100 px-2 py-1 text-xs font-medium text-rose-800">Error</span>
                                            <p class="mt-2 text-xs text-rose-700">{{ $row['error_message'] }}</p>
                                            @if (!empty($row['match_count']))
                                                <p class="mt-1 text-xs text-slate-600">Coincidencias encontradas: {{ $row['match_count'] }}</p>
                                            @endif
                                            @if (!empty($row['matches']))
                                                <details class="mt-3 rounded border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                                                    <summary class="cursor-pointer font-medium text-slate-900">Ver coincidencias</summary>
                                                    <div class="mt-3 space-y-3">
                                                        <div class="rounded border border-slate-200 bg-white p-3">
                                                            <p><span class="font-semibold">NIT + DV:</span> {{ $row['nit'] }}</p>
                                                            <p><span class="font-semibold">Emisor Excel:</span> {{ $row['emisor'] }}</p>
                                                            <p><span class="font-semibold">Archivo/Fila:</span> {{ implode(', ', $row['sources']) }} / filas {{ implode(', ', array_map('strval', $row['rows'])) }}</p>
                                                        </div>
                                                        <div class="overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                                <thead class="bg-slate-100">
                                                                    <tr>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">id_cliente</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">id_cobro</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Codigo</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Nombre</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Empresa</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Regimen</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha arriendo</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Fecha retiro</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Estado</th>
                                                                        <th class="px-2 py-2 text-left font-medium text-slate-600">Accion</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-slate-100 bg-white">
                                                                    @foreach ($row['matches'] as $match)
                                                                        <tr>
                                                                            <td class="px-2 py-2 align-top">{{ $match['id_cliente'] }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['id_cobro'] }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['codigo'] !== '' ? $match['codigo'] : '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['nombre'] !== '' ? $match['nombre'] : '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['empresa'] !== '' ? $match['empresa'] : '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['regimen'] !== '' ? $match['regimen'] : '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['fecha_arriendo'] ?: '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['fecha_retiro'] ?: '—' }}</td>
                                                                            <td class="px-2 py-2 align-top">{{ $match['estado'] }}</td>
                                                                            <td class="px-2 py-2 align-top">
                                                                                <form method="POST" action="{{ route('configuracion.importaciones.assign-ambiguous') }}">
                                                                                    @csrf
                                                                                    <input type="hidden" name="entry_id" value="{{ $row['entry_id'] }}">
                                                                                    <input type="hidden" name="id_cobro" value="{{ $match['id_cobro'] }}">
                                                                                    <button type="submit" class="rounded bg-indigo-600 px-2 py-1 font-medium text-white hover:bg-indigo-700">
                                                                                        Asignar a este cliente
                                                                                    </button>
                                                                                </form>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </details>
                                            @endif
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
                            <tr class="hidden" data-preview-empty-row>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">
                                    No hay registros para el filtro seleccionado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @php
        $erroresFinales = session('importacion_errores_finales', []);
        $erroresPreview = array_merge($preview['parse_errors'] ?? [], $preview['process_errors'] ?? []);
    @endphp

    @if (!empty($erroresPreview) || !empty($erroresFinales))
        <section class="rounded-lg bg-white p-6 shadow" data-preview-errors-section>
            <h2 class="text-lg font-semibold text-slate-900">Errores encontrados</h2>
            <div class="mt-4 space-y-3" data-preview-errors-list>
                @foreach ($preview['parse_errors'] ?? [] as $error)
                    <div data-preview-error data-filter-type="parse_error" class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p><span class="font-semibold">Archivo:</span> {{ $error['file'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Fila:</span> {{ $error['row'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Detalle:</span> {{ $error['message'] ?? 'Error no especificado.' }}</p>
                    </div>
                @endforeach
                @foreach (array_merge($preview['process_errors'] ?? [], $erroresFinales) as $error)
                    <div data-preview-error data-filter-type="error" class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p><span class="font-semibold">Archivo:</span> {{ $error['file'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Fila:</span> {{ $error['row'] ?? 'N/D' }}</p>
                        <p><span class="font-semibold">Detalle:</span> {{ $error['message'] ?? 'Error no especificado.' }}</p>
                    </div>
                @endforeach
                <div class="hidden rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600" data-preview-errors-empty>
                    No hay errores para el filtro seleccionado.
                </div>
            </div>
        </section>
    @endif
</div>

@if ($preview)
    <script>
        (() => {
            const cards = Array.from(document.querySelectorAll('[data-filter-card]'));
            const clearButton = document.querySelector('[data-preview-filter-clear]');
            const previewRows = Array.from(document.querySelectorAll('[data-preview-row]'));
            const errorRows = Array.from(document.querySelectorAll('[data-preview-error]'));
            const tableBody = document.querySelector('[data-preview-table-body]');
            const emptyPreviewRow = document.querySelector('[data-preview-empty-row]');
            const errorsSection = document.querySelector('[data-preview-errors-section]');
            const emptyErrorsState = document.querySelector('[data-preview-errors-empty]');
            const activeClasses = ['ring-2', 'ring-offset-2', 'ring-slate-400', 'shadow-sm'];

            if (cards.length === 0) {
                return;
            }

            const matchesFilter = (type, filter) => {
                if (filter === 'all') {
                    return true;
                }

                if (filter === 'error') {
                    return type === 'error' || type === 'duplicate_pending';
                }

                return type === filter;
            };

            const applyFilter = (filter) => {
                previewRows.forEach((row) => {
                    row.classList.toggle('hidden', !matchesFilter(row.dataset.filterType || '', filter));
                });

                errorRows.forEach((row) => {
                    row.classList.toggle('hidden', !matchesFilter(row.dataset.filterType || '', filter));
                });

                if (tableBody) {
                    const visiblePreviewRows = previewRows.filter((row) => !row.classList.contains('hidden')).length;
                    tableBody.dataset.emptyFilter = visiblePreviewRows === 0 ? 'true' : 'false';
                    emptyPreviewRow?.classList.toggle('hidden', visiblePreviewRows !== 0);
                }

                if (errorsSection) {
                    const visibleErrorRows = errorRows.filter((row) => !row.classList.contains('hidden')).length;
                    const shouldShowErrorsSection = filter === 'all' || filter === 'parse_error';
                    errorsSection.classList.toggle('hidden', !shouldShowErrorsSection);
                    emptyErrorsState?.classList.toggle('hidden', !shouldShowErrorsSection || visibleErrorRows !== 0);
                }

                cards.forEach((card) => {
                    const isActive = card.dataset.filterCard === filter;
                    activeClasses.forEach((className) => card.classList.toggle(className, isActive));
                });
            };

            cards.forEach((card) => {
                card.addEventListener('click', () => applyFilter(card.dataset.filterCard || 'all'));
            });

            clearButton?.addEventListener('click', () => applyFilter('all'));

            applyFilter('all');
        })();
    </script>
@endif
@endsection
