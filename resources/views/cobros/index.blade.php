@extends('layouts.admin')

@section('title', 'Cobros')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    @php
        $proformasListasParaEnvio = session('cobros.proformas_listas_para_envio');
        $proformasListas = is_array($proformasListasParaEnvio['proformas'] ?? null) ? $proformasListasParaEnvio['proformas'] : [];
        $grupoListoParaEnvio = (int) ($proformasListasParaEnvio['grupo'] ?? 0);
        $proformasMasivoOmitidas = is_array(session('cobros_proformas_masivo_omitidas')) ? session('cobros_proformas_masivo_omitidas') : [];
        $pendientesFacturacionPayload = session('cobros.proformas_masivo_pendientes_facturacion');
        $pendientesFacturacionItems = is_array($pendientesFacturacionPayload['items'] ?? null) ? $pendientesFacturacionPayload['items'] : [];
        $pendientesFacturacionGrupo = (int) ($pendientesFacturacionPayload['grupo'] ?? 0);
        $regeneracionPendientesPayload = session('cobros.proformas_masivo_regenerar_pendientes');
        $regeneracionPendientesGrupo = (int) ($regeneracionPendientesPayload['grupo'] ?? 0);
        $activacionPendientesResult = is_array(session('cobros_proformas_masivo_activados')) ? session('cobros_proformas_masivo_activados') : null;
        $loteResumen = is_array(session('cobros_proformas_masivo_lote_resumen')) ? session('cobros_proformas_masivo_lote_resumen') : null;
        $currentExecutionCount = (int) ($loteResumen['current_execution_count'] ?? count($proformasListas));
        $pendingBatchCount = (int) ($loteResumen['pending_batch_count'] ?? count($proformasListas));
    @endphp

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Módulo Cobros</h1>
            <p class="text-sm text-slate-600">Listado inicial desde <code>valores_externos</code> con datos de clientes potenciales.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('cobros.extraordinario.create') }}" class="inline-flex items-center rounded bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-200">
                Generar cobro extraordinario
            </a>
            @if(($canClearPendingBatch ?? false) === true)
                <form method="POST" action="{{ route('cobros.lote-pendiente.limpiar') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200">
                        Limpiar lote pendiente de envio
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('cobros.proformas-masivo', ['grupo' => 7]) }}" data-mass-generation-form data-grupo="7" data-progress-url-template="{{ route('cobros.proformas-masivo.progress', ['executionId' => '__EXECUTION_ID__']) }}">
                @csrf
                <input type="hidden" name="mes" value="{{ $filters['mes'] ?? '' }}">
                <input type="hidden" name="anio" value="{{ $filters['anio'] ?? '' }}">
                <input type="hidden" name="proforma" value="{{ $filters['proforma'] ?? '' }}">
                <input type="hidden" name="codigo" value="{{ $filters['codigo'] ?? '' }}">
                <input type="hidden" name="buscar" value="{{ $filters['buscar'] ?? '' }}">
                <input type="hidden" name="orden_fecha" value="{{ $filters['orden_fecha'] ?? '' }}">
                <input type="hidden" name="grupo_fecha" value="{{ $filters['grupo_fecha'] ?? '' }}">
                <input type="hidden" name="filtro_nota" value="{{ $filters['filtro_nota'] ?? '' }}">
                <input type="hidden" name="filtro_envio" value="{{ $filters['filtro_envio'] ?? '' }}">
                <button type="submit" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-70" data-mass-generation-button>
                    <span class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-cyan-300 border-t-cyan-700" data-mass-generation-spinner></span>
                    <span data-mass-generation-label>Generar proformas grupo 7</span>
                </button>
            </form>

            <form method="POST" action="{{ route('cobros.proformas-masivo', ['grupo' => 27]) }}" data-mass-generation-form data-grupo="27" data-progress-url-template="{{ route('cobros.proformas-masivo.progress', ['executionId' => '__EXECUTION_ID__']) }}">
                @csrf
                <input type="hidden" name="mes" value="{{ $filters['mes'] ?? '' }}">
                <input type="hidden" name="anio" value="{{ $filters['anio'] ?? '' }}">
                <input type="hidden" name="proforma" value="{{ $filters['proforma'] ?? '' }}">
                <input type="hidden" name="codigo" value="{{ $filters['codigo'] ?? '' }}">
                <input type="hidden" name="buscar" value="{{ $filters['buscar'] ?? '' }}">
                <input type="hidden" name="orden_fecha" value="{{ $filters['orden_fecha'] ?? '' }}">
                <input type="hidden" name="grupo_fecha" value="{{ $filters['grupo_fecha'] ?? '' }}">
                <input type="hidden" name="filtro_nota" value="{{ $filters['filtro_nota'] ?? '' }}">
                <input type="hidden" name="filtro_envio" value="{{ $filters['filtro_envio'] ?? '' }}">
                <button type="submit" class="inline-flex items-center rounded bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200 disabled:cursor-not-allowed disabled:opacity-70" data-mass-generation-button>
                    <span class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-sky-300 border-t-sky-700" data-mass-generation-spinner></span>
                    <span data-mass-generation-label>Generar proformas grupo 27</span>
                </button>
            </form>

            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Proformas Generadas
            </a>
        </div>
    </div>

    <div id="mass-generation-feedback" class="mb-4 hidden rounded border px-4 py-3 text-sm"></div>
    <div id="mass-generation-send-batch-panel-container"></div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{
            session('status_type') === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-amber-200 bg-amber-50 text-amber-800'
        }}">
            {{ session('status') }}
        </div>
    @endif

    @if($proformasMasivoOmitidas !== [])
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="font-semibold">Clientes omitidos</h2>
                @if($pendientesFacturacionItems !== [] && in_array($pendientesFacturacionGrupo, [7, 27], true))
                    <button
                        type="button"
                        id="open-pendientes-facturacion-modal"
                        class="inline-flex items-center rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                    >
                        Revisar pendientes
                    </button>
                @endif
            </div>
            <div class="mt-3 space-y-3">
                @foreach($proformasMasivoOmitidas as $omitida)
                    <div class="rounded border border-amber-100 bg-white/70 px-3 py-2">
                        <p class="font-medium">&bull; {{ $omitida['codigo'] ?? 'Sin codigo' }} - {{ $omitida['empresa'] ?? 'Sin nombre' }}</p>
                        <p class="mt-1 text-amber-800">{{ $omitida['motivo'] ?? 'Motivo no especificado' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($activacionPendientesResult && is_array($regeneracionPendientesPayload) && in_array($regeneracionPendientesGrupo, [7, 27], true))
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
            <p class="font-semibold">Se activaron {{ (int) ($activacionPendientesResult['count'] ?? 0) }} clientes.</p>
            <p class="mt-1">Desea regenerar automaticamente las proformas omitidas?</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('cobros.proformas-masivo.pendientes.regenerar', ['grupo' => $regeneracionPendientesGrupo]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Si, generar ahora
                    </button>
                </form>
                <form method="POST" action="{{ route('cobros.proformas-masivo.pendientes.descartar', ['grupo' => $regeneracionPendientesGrupo]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                        No
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($proformasListas !== [] && in_array($grupoListoParaEnvio, [7, 27], true))
        <div
            class="mb-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm"
            data-static-send-batch-panel
            data-send-batch-group="{{ $grupoListoParaEnvio }}"
            data-send-batch-count="{{ count($proformasListas) }}"
            data-send-batch-current-count="{{ $currentExecutionCount }}"
            data-send-batch-pending-count="{{ $pendingBatchCount }}"
            data-send-batch-send-url="{{ route('cobros.proformas-masivo.enviar', ['grupo' => $grupoListoParaEnvio]) }}"
            data-send-batch-progress-url-template="{{ route('cobros.proformas-masivo.envio.progress', ['executionId' => '__EXECUTION_ID__']) }}"
            data-send-batch-companies="{{ e(collect($proformasListas)->pluck('empresa')->take(8)->implode('||')) }}"
        >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-900">
                        Proformas generadas en esta ejecución: {{ $currentExecutionCount }}.
                    </p>
                    <p class="text-sm text-slate-600">
                        Total de proformas pendientes para envío en el lote actual: {{ $pendingBatchCount }}. Desea enviarlas ahora?
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-70" data-send-batch-now>
                        <span class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-cyan-200 border-t-white" data-send-batch-spinner></span>
                        <span data-send-batch-label>Enviar ahora</span>
                    </button>
                    <button type="button" class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" data-send-batch-later>
                        Guardar para después
                    </button>
                </div>
            </div>

            <p class="mt-3 text-xs text-slate-500">
                Empresas listas: {{ collect($proformasListas)->pluck('empresa')->take(8)->implode(', ') }}{{ count($proformasListas) > 8 ? '...' : '' }}
            </p>
        </div>
    @endif

    <div
        id="mass-generation-progress-modal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/65 px-4 py-6"
        aria-hidden="true"
    >
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="mt-1 h-10 w-10 flex-none rounded-full bg-cyan-100 p-2 text-cyan-700">
                    <div class="h-full w-full animate-spin rounded-full border-2 border-cyan-200 border-t-cyan-700"></div>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-slate-900" id="mass-generation-modal-title">Procesando proformas...</h2>
                    <p class="mt-1 text-sm text-slate-600" id="mass-generation-modal-message">Preparando ejecución...</p>
                </div>
            </div>

            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between text-sm text-slate-600">
                    <span id="mass-generation-progress-text">Procesando 0 de 0 (0%)</span>
                    <span id="mass-generation-progress-percentage" class="font-semibold text-slate-800">0%</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                    <div id="mass-generation-progress-bar" class="h-full w-0 rounded-full bg-gradient-to-r from-cyan-500 to-sky-600 transition-all duration-500"></div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
                    <p id="mass-generation-total" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Procesadas</p>
                    <p id="mass-generation-processed" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Grupo</p>
                    <p id="mass-generation-group" class="mt-1 text-lg font-semibold text-slate-900">-</p>
                </div>
            </div>

            <p class="mt-5 text-xs text-slate-500">
                No cierre esta ventana ni vuelva a pulsar botones de generación mientras el proceso esté activo.
            </p>
        </div>
    </div>

    <div
        id="mass-send-progress-modal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/65 px-4 py-6"
        aria-hidden="true"
    >
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="mt-1 h-10 w-10 flex-none rounded-full bg-cyan-100 p-2 text-cyan-700">
                    <div class="h-full w-full animate-spin rounded-full border-2 border-cyan-200 border-t-cyan-700"></div>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-slate-900" id="mass-send-modal-title">Enviando proformas</h2>
                    <p class="mt-1 text-sm text-slate-600" id="mass-send-modal-message">Preparando envío...</p>
                </div>
            </div>

            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between text-sm text-slate-600">
                    <span id="mass-send-progress-text">Enviando 0 de 0 (0%)</span>
                    <span id="mass-send-progress-percentage" class="font-semibold text-slate-800">0%</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                    <div id="mass-send-progress-bar" class="h-full w-0 rounded-full bg-gradient-to-r from-cyan-500 to-sky-600 transition-all duration-500"></div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
                    <p id="mass-send-total" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Enviadas</p>
                    <p id="mass-send-sent" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Excluidas</p>
                    <p id="mass-send-excluded" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Fallidas</p>
                    <p id="mass-send-failed" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
            </div>
        </div>
    </div>

    @if($pendientesFacturacionItems !== [] && in_array($pendientesFacturacionGrupo, [7, 27], true))
        <div
            id="pendientes-facturacion-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 px-4 py-6"
            aria-hidden="true"
        >
            <div class="w-full max-w-5xl rounded-xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Pendientes de facturacion</h2>
                        <p class="mt-1 text-sm text-slate-500">Seleccione los clientes que desea activar sin salir del modulo de Cobros.</p>
                    </div>
                    <button
                        type="button"
                        id="close-pendientes-facturacion-modal"
                        class="rounded p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Cerrar modal"
                    >
                        &times;
                    </button>
                </div>

                <form method="POST" action="{{ route('cobros.proformas-masivo.pendientes.activar', ['grupo' => $pendientesFacturacionGrupo]) }}" class="px-5 py-4">
                    @csrf

                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-600">Clientes pendientes encontrados: {{ count($pendientesFacturacionItems) }}</p>
                        <button type="button" id="select-all-pendientes-facturacion" class="inline-flex items-center rounded bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                            Seleccionar todos
                        </button>
                    </div>

                    <div class="max-h-[60vh] overflow-auto rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Sel.</th>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Codigo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Empresa</th>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Fecha arriendo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Fecha creacion cliente</th>
                                    <th class="px-3 py-3 text-right font-semibold text-slate-700">Valor total actual</th>
                                    <th class="px-3 py-3 text-left font-semibold text-slate-700">Estado Facturacion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($pendientesFacturacionItems as $item)
                                    <tr>
                                        <td class="px-3 py-3 align-top">
                                            <input type="checkbox" name="clientes[]" value="{{ (int) ($item['cliente_id'] ?? 0) }}" class="pendiente-facturacion-checkbox h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        </td>
                                        <td class="px-3 py-3 align-top">{{ $item['codigo'] ?? 'Sin codigo' }}</td>
                                        <td class="px-3 py-3 align-top">{{ $item['empresa'] ?? 'Sin nombre' }}</td>
                                        <td class="px-3 py-3 align-top">{{ $item['fecha_arriendo'] ?? 'N/D' }}</td>
                                        <td class="px-3 py-3 align-top">{{ $item['fecha_creacion_cliente'] ?? 'N/D' }}</td>
                                        <td class="px-3 py-3 align-top text-right">{{ number_format((float) ($item['valor_total_actual'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="px-3 py-3 align-top">
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                                {{ $item['estado_facturacion'] ?? 'PENDIENTE' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" id="cancel-pendientes-facturacion-modal" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                            Cerrar
                        </button>
                        <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Activar seleccionados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form id="cobros-filter-form" method="GET" action="{{ route('cobros.index') }}" class="grid grid-cols-1 md:grid-cols-9 gap-4 items-end">
            <div>
                <label for="mes" class="block text-sm font-medium mb-1">Mes</label>

                <select id="mes" name="mes"
                        class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Todos los meses</option>
                    @foreach($meses as $numero => $nombre)
                        <option value="{{ $nombre }}" @selected(($filters['mes'] ?? '') === $nombre || (string) ($filters['mes'] ?? '') === (string) $numero)>
                            {{ ucfirst($nombre) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="anio" class="block text-sm font-medium mb-1">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] ?? '' }}"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="proforma" class="block text-sm font-medium mb-1">Proforma</label>
                <input id="proforma" name="proforma" type="text" value="{{ $filters['proforma'] ?? '' }}"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="codigo" class="block text-sm font-medium mb-1">Codigo</label>
                <input id="codigo" name="codigo" type="text" value="{{ $filters['codigo'] ?? '' }}"
                       placeholder="Ej: B340"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div> 

            <div>
                <label for="buscar" class="block text-sm font-medium mb-1">Buscar</label>
                <input id="buscar" name="buscar" type="text" value="{{ $filters['buscar'] ?? '' }}"
                       placeholder="Nombre, código o empresa"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="grupo_fecha" class="block text-sm font-medium mb-1">Grupo fecha</label>
                <select id="grupo_fecha" name="grupo_fecha"
                        class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Todos</option>
                    <option value="7" @selected(($filters['grupo_fecha'] ?? null) === '7')>Grupo 7</option>
                    <option value="27" @selected(($filters['grupo_fecha'] ?? null) === '27')>Grupo 27</option>
                </select>
            </div>

            <div>
                <label for="filtro_nota" class="block text-sm font-medium mb-1">Nota cobro</label>
                <select id="filtro_nota" name="filtro_nota"
                        class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Todas</option>
                    <option value="con" @selected(($filters['filtro_nota'] ?? '') === 'con')>Con nota</option>
                    <option value="sin" @selected(($filters['filtro_nota'] ?? '') === 'sin')>Sin nota</option>
                </select>
            </div>

            <div>
                <label for="filtro_envio" class="block text-sm font-medium mb-1">Envio proforma</label>
                <select id="filtro_envio" name="filtro_envio"
                        class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">Todas</option>
                    <option value="enviadas" @selected(($filters['filtro_envio'] ?? '') === 'enviadas')>Enviadas</option>
                    <option value="no_enviadas" @selected(($filters['filtro_envio'] ?? '') === 'no_enviadas')>No enviadas</option>
                </select>
            </div>

            <input type="hidden" name="orden_fecha" value="{{ $filters['orden_fecha'] ?? '' }}">

            <div class="flex gap-2">
                <button
                    type="submit"
                    id="cobros-apply-filter-button"
                    class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
                >
                    <span id="cobros-apply-filter-spinner" class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    <span id="cobros-apply-filter-label">Filtrar</span>
                </button>
                <a
                    id="cobros-clear-filters-button"
                    href="{{ route('cobros.index') }}"
                    class="rounded bg-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-300"
                >
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    @if($hasSearched ?? false)
    @php
        $periodSummaryMetrics = [
            ['label' => 'Total Facturas', 'value' => $periodSummary->total_facturas ?? 0, 'tone' => 'text-sky-700 bg-sky-50 border-sky-200', 'type' => 'count'],
            ['label' => 'Total Notas Débito', 'value' => $periodSummary->total_notas_debito ?? 0, 'tone' => 'text-indigo-700 bg-indigo-50 border-indigo-200', 'type' => 'count'],
            ['label' => 'Total Notas Crédito', 'value' => $periodSummary->total_notas_credito ?? 0, 'tone' => 'text-violet-700 bg-violet-50 border-violet-200', 'type' => 'count'],
            ['label' => 'Total Documentos Soporte', 'value' => $periodSummary->total_documentos_soporte ?? 0, 'tone' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'type' => 'count'],
            ['label' => 'Total Notas Ajuste', 'value' => $periodSummary->total_notas_ajuste ?? 0, 'tone' => 'text-amber-700 bg-amber-50 border-amber-200', 'type' => 'count'],
            ['label' => 'Total Acuses', 'value' => $periodSummary->total_acuses ?? 0, 'tone' => 'text-cyan-700 bg-cyan-50 border-cyan-200', 'type' => 'count'],
            ['label' => 'Valor Facturas', 'value' => $periodSummary->valor_facturas ?? 0, 'tone' => 'text-slate-700 bg-slate-50 border-slate-200', 'type' => 'currency'],
            ['label' => 'Valor Documentos', 'value' => $periodSummary->valor_documentos ?? 0, 'tone' => 'text-teal-700 bg-teal-50 border-teal-200', 'type' => 'currency'],
            ['label' => 'Valor Acuse', 'value' => $periodSummary->valor_acuse ?? 0, 'tone' => 'text-blue-700 bg-blue-50 border-blue-200', 'type' => 'currency'],
            ['label' => 'Valor Mensualidad', 'value' => $periodSummary->valor_mensualidad ?? 0, 'tone' => 'text-fuchsia-700 bg-fuchsia-50 border-fuchsia-200', 'type' => 'currency'],
            ['label' => 'Valor Total', 'value' => $periodSummary->valor_total ?? 0, 'tone' => 'text-rose-700 bg-rose-50 border-rose-200', 'type' => 'currency'],
        ];
    @endphp

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Resumen del período</h2>
                <p class="text-sm text-slate-600">
                    Totales leídos directamente desde <code>valores_externos</code>
                    @if(!empty($filters['mes']) && !empty($filters['anio']))
                        para {{ ucfirst((string) $filters['mes']) }} {{ $filters['anio'] }}.
                    @else
                        con los filtros actuales.
                    @endif
                </p>
            </div>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-600">
                Base de datos
            </span>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach($periodSummaryMetrics as $metric)
                <div class="rounded-xl border px-4 py-3 {{ $metric['tone'] }}">
                    <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-bold">
                        @if($metric['type'] === 'currency')
                            ${{ number_format((float) $metric['value'], 0, ',', '.') }}
                        @else
                            {{ number_format((float) $metric['value'], 0, ',', '.') }}
                        @endif
                    </p>
                    <p class="mt-1 text-xs opacity-75">Listo para futura comparación Excel vs base.</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div id="cobros-results-area" class="relative">
        <div id="cobros-results-loading-overlay" class="pointer-events-none absolute inset-0 z-20 hidden items-center justify-center rounded-lg bg-white/75 backdrop-blur-[1px]">
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 text-center shadow-lg">
                <div class="mx-auto mb-3 h-6 w-6 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></div>
                <p class="text-sm font-medium text-slate-700">Consultando cobros, por favor espere...</p>
            </div>
        </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                <tr>

                    <th id="fecha-arriendo-header" class="text-left px-4 py-3 relative">
                        @php
                            $ordenFechaActual = $filters['orden_fecha'] ?? null;
                            $siguienteOrdenFecha = $ordenFechaActual === 'asc' ? 'desc' : 'asc';
                        @endphp
                        <a href="{{ route('cobros.index', array_merge(request()->query(), ['orden_fecha' => $siguienteOrdenFecha])) }}" class="inline-flex items-center gap-1 hover:text-slate-900">
                            <span>Fecha Arriendo</span>
                            @if($ordenFechaActual === 'asc')
                                <span aria-hidden="true">↑</span>
                            @elseif($ordenFechaActual === 'desc')
                                <span aria-hidden="true">↓</span>
                            @endif
                        </a>
                        <div id="fecha-arriendo-context-menu" class="hidden fixed z-50 min-w-[140px] rounded border border-slate-200 bg-white p-1 shadow-lg normal-case">
                            <button type="button" data-grupo-fecha="7" class="w-full rounded px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-100">Ver grupo 7</button>
                            <button type="button" data-grupo-fecha="27" class="w-full rounded px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-100">Ver grupo 27</button>
                        </div>
                    </th>

                    <th class="text-left px-4 py-3">Código</th>
                    <th class="text-left px-4 py-3">Cliente Potencial</th>
                    <th class="text-left px-4 py-3">Régimen</th>
                    <th class="text-right px-4 py-3">Valor Total</th>
                    <th class="text-center px-4 py-3">Nota</th>
                    <th class="text-left px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($cobros as $cobro)
                    @php
                        $fechaArriendoFormateada = '—';

                        if (!empty($cobro->fecha_arriendo)) {
                            try {
                                $fechaArriendoFormateada = \Carbon\Carbon::createFromFormat('d-m-Y', trim($cobro->fecha_arriendo))->format('d/m/Y');
                            } catch (\Throwable) {
                                $fechaArriendoFormateada = $cobro->fecha_arriendo;
                            }
                        }

                        $notaCobro = trim((string) ($cobro->nota_cobro ?? ''));
                        $notaResumen = $notaCobro !== '' ? \Illuminate\Support\Str::limit($notaCobro, 50) : 'Sin nota de cobro';
                        $clienteId = (int) ($cobro->cliente_id ?? 0);
                        $estadoFacturacion = \App\Models\ClientePotencial::normalizeEstadoFacturacion($cobro->estado_facturacion ?? null);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $fechaArriendoFormateada }}</td>
                        <td class="px-4 py-3 font-medium">{{ $cobro->codigo ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <span>{{ $cobro->nombre ?: 'Sin nombre' }}</span>
                                <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $estadoFacturacion === \App\Models\ClientePotencial::ESTADO_FACTURACION_ACTIVO ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $estadoFacturacion }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $cobro->regimen ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format((float) ($cobro->valor_total ?? 0), 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($clienteId > 0)
                                <button
                                    type="button"
                                    class="nota-cobro-btn inline-flex h-8 w-8 items-center justify-center rounded-full border text-base transition {{ $notaCobro !== '' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-300 text-slate-400 hover:bg-slate-100' }}"
                                    data-cliente-id="{{ $clienteId }}"
                                    data-cliente-nombre="{{ $cobro->nombre ?: 'Sin nombre' }}"
                                    data-nota="{{ $notaCobro }}"
                                    title="{{ $notaCobro !== '' ? 'Tiene nota registrada' : 'Sin nota de cobro' }}"
                                    aria-label="Editar nota de cobro"
                                >
                                    📝
                                </button>
                            @else
                                <span class="text-slate-300" title="Cliente no disponible">📝</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('cobros.show', array_merge(['id' => $cobro->id_cobro], request()->query())) }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                            @if($hasSearched ?? false)
                                No hay cobros disponibles para los filtros seleccionados.
                            @else
                                Seleccione los filtros y pulse Filtrar para consultar cobros.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200">
            {{ $cobros->links() }}
        </div>
    </div>
    </div>
</div>

@include('partials.nota-cobro-modal')
@endsection

@push('scripts')
@include('partials.filter-submit-loading-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const massGenerationForms = Array.from(document.querySelectorAll('[data-mass-generation-form]'));
        const feedback = document.getElementById('mass-generation-feedback');
        const sendBatchPanelContainer = document.getElementById('mass-generation-send-batch-panel-container');
        const progressModal = document.getElementById('mass-generation-progress-modal');
        const modalTitle = document.getElementById('mass-generation-modal-title');
        const modalMessage = document.getElementById('mass-generation-modal-message');
        const progressText = document.getElementById('mass-generation-progress-text');
        const progressPercentage = document.getElementById('mass-generation-progress-percentage');
        const progressBar = document.getElementById('mass-generation-progress-bar');
        const totalNode = document.getElementById('mass-generation-total');
        const processedNode = document.getElementById('mass-generation-processed');
        const groupNode = document.getElementById('mass-generation-group');
        const massSendModal = document.getElementById('mass-send-progress-modal');
        const massSendModalTitle = document.getElementById('mass-send-modal-title');
        const massSendModalMessage = document.getElementById('mass-send-modal-message');
        const massSendProgressText = document.getElementById('mass-send-progress-text');
        const massSendProgressPercentage = document.getElementById('mass-send-progress-percentage');
        const massSendProgressBar = document.getElementById('mass-send-progress-bar');
        const massSendTotalNode = document.getElementById('mass-send-total');
        const massSendSentNode = document.getElementById('mass-send-sent');
        const massSendExcludedNode = document.getElementById('mass-send-excluded');
        const massSendFailedNode = document.getElementById('mass-send-failed');

        let activeMassGeneration = null;
        let activePollTimer = null;
        let activeMassSend = null;
        let activeMassSendPollTimer = null;
        let currentDynamicSendBatch = null;

        const randomExecutionId = () => {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return `exec-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        };

        const showMassGenerationFeedback = (message, type = 'success') => {
            if (!feedback) {
                return;
            }

            feedback.className = 'mb-4 rounded border px-4 py-3 text-sm';
            feedback.classList.remove('hidden');

            if (type === 'success') {
                feedback.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            } else {
                feedback.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
            }

            feedback.innerHTML = message;
        };

        const setMassGenerationButtonsState = (disabled, processingForm = null) => {
            massGenerationForms.forEach((massForm) => {
                const button = massForm.querySelector('[data-mass-generation-button]');
                const spinner = massForm.querySelector('[data-mass-generation-spinner]');
                const label = massForm.querySelector('[data-mass-generation-label]');

                if (label && !massForm.dataset.originalLabel) {
                    massForm.dataset.originalLabel = label.textContent || '';
                }

                if (button) {
                    button.disabled = disabled;
                }

                if (spinner) {
                    spinner.classList.toggle('hidden', !(disabled && massForm === processingForm));
                }

                if (label) {
                    label.textContent = disabled && massForm === processingForm
                        ? 'Procesando...'
                        : (massForm.dataset.originalLabel || '');
                }
            });
        };

        const openMassGenerationModal = (group) => {
            if (!progressModal) {
                return;
            }

            modalTitle.textContent = `Procesando proformas grupo ${group}...`;
            modalMessage.textContent = 'Preparando ejecución...';
            progressText.textContent = 'Procesando 0 de 0 (0%)';
            progressPercentage.textContent = '0%';
            progressBar.style.width = '0%';
            totalNode.textContent = '0';
            processedNode.textContent = '0';
            groupNode.textContent = String(group);
            progressModal.classList.remove('hidden');
            progressModal.classList.add('flex');
            progressModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        };

        const closeMassGenerationModal = () => {
            if (!progressModal) {
                return;
            }

            progressModal.classList.add('hidden');
            progressModal.classList.remove('flex');
            progressModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        };

        const renderMassGenerationProgress = (payload) => {
            const progress = payload && payload.progress ? payload.progress : (payload || {});
            const total = Number(progress.total || 0);
            const processed = Number(progress.processed || 0);
            const percentage = Number(progress.percentage || 0);

            modalMessage.textContent = progress.message || 'Procesando...';
            progressText.textContent = `Procesando ${processed} de ${total} (${percentage}%)`;
            progressPercentage.textContent = `${percentage}%`;
            progressBar.style.width = `${Math.max(0, Math.min(100, percentage))}%`;
            totalNode.textContent = String(total);
            processedNode.textContent = String(processed);
            groupNode.textContent = String(progress.grupo || activeMassGeneration?.group || '-');
        };

        const stopMassGenerationPolling = () => {
            if (activePollTimer) {
                window.clearTimeout(activePollTimer);
                activePollTimer = null;
            }
        };

        const pollMassGenerationProgress = async () => {
            if (!activeMassGeneration || !activeMassGeneration.progressUrl) {
                return;
            }

            try {
                const response = await fetch(activeMassGeneration.progressUrl, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (response.ok) {
                    const payload = await response.json();
                    renderMassGenerationProgress(payload);
                }
            } catch (error) {
                // Reintento silencioso: el request principal puede seguir ejecutándose.
            } finally {
                if (activeMassGeneration) {
                    activePollTimer = window.setTimeout(pollMassGenerationProgress, 1000);
                }
            }
        };

        const buildMassGenerationSummaryHtml = (group, summary, message) => `
            <p class="font-semibold">${message || `Generación masiva grupo ${group} finalizada.`}</p>
            <div class="mt-2 grid grid-cols-1 gap-1 md:grid-cols-2">
                <p>Generadas: ${summary.generadas ?? 0}</p>
                <p>Actualizadas: ${summary.actualizadas ?? 0}</p>
                <p>Omitidas protegidas: ${summary.omitidas_protegidas ?? 0}</p>
                <p>Omitidas: ${summary.omitidas ?? 0}</p>
                <p>Fallidas: ${summary.fallidas ?? 0}</p>
                <p>PDF regenerados: ${summary.pdf_regenerados ?? 0}</p>
            </div>
        `;

        const buildMassSendSummaryHtml = (summary, message) => `
            <p class="font-semibold">${message || 'Envío masivo finalizado.'}</p>
            <div class="mt-2 grid grid-cols-1 gap-1 md:grid-cols-3">
                <p>Enviadas: ${summary.sent ?? 0}</p>
                <p>Excluidas: ${summary.excluded ?? 0}</p>
                <p>Fallidas: ${summary.failed ?? 0}</p>
            </div>
        `;

        const clearSendBatchPanel = () => {
            if (!sendBatchPanelContainer) {
                return;
            }

            currentDynamicSendBatch = null;
            sendBatchPanelContainer.innerHTML = '';
        };

        const setSendBatchActionState = (button, processing) => {
            if (!button) {
                return;
            }

            const spinner = button.querySelector('[data-send-batch-spinner]');
            const label = button.querySelector('[data-send-batch-label]');

            button.disabled = processing;

            if (spinner) {
                spinner.classList.toggle('hidden', !processing);
            }

            if (label) {
                label.textContent = processing ? 'Enviando...' : 'Enviar ahora';
            }
        };

        const setAllSendBatchButtonsState = (disabled, activeButton = null) => {
            document.querySelectorAll('[data-send-batch-now]').forEach((button) => {
                setSendBatchActionState(button, disabled && button === activeButton);

                if (!disabled && button !== activeButton) {
                    button.disabled = false;
                }

                if (disabled && button !== activeButton) {
                    button.disabled = true;
                }
            });

            document.querySelectorAll('[data-send-batch-later]').forEach((button) => {
                button.disabled = disabled;
                button.classList.toggle('opacity-60', disabled);
                button.classList.toggle('pointer-events-none', disabled);
            });
        };

        const openMassSendModal = () => {
            if (!massSendModal) {
                return;
            }

            massSendModalTitle.textContent = 'Enviando proformas';
            massSendModalMessage.textContent = 'Preparando envío...';
            massSendProgressText.textContent = 'Enviando 0 de 0 (0%)';
            massSendProgressPercentage.textContent = '0%';
            massSendProgressBar.style.width = '0%';
            massSendTotalNode.textContent = '0';
            massSendSentNode.textContent = '0';
            massSendExcludedNode.textContent = '0';
            massSendFailedNode.textContent = '0';
            massSendModal.classList.remove('hidden');
            massSendModal.classList.add('flex');
            massSendModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        };

        const closeMassSendModal = () => {
            if (!massSendModal) {
                return;
            }

            massSendModal.classList.add('hidden');
            massSendModal.classList.remove('flex');
            massSendModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        };

        const renderMassSendProgress = (payload) => {
            const progress = payload && payload.progress ? payload.progress : (payload || {});
            const total = Number(progress.total || 0);
            const processed = Number(progress.processed || 0);
            const percentage = Number(progress.percentage || 0);

            massSendModalMessage.textContent = progress.message || 'Enviando...';
            massSendProgressText.textContent = `Enviando ${processed} de ${total} (${percentage}%)`;
            massSendProgressPercentage.textContent = `${percentage}%`;
            massSendProgressBar.style.width = `${Math.max(0, Math.min(100, percentage))}%`;
            massSendTotalNode.textContent = String(total);
            massSendSentNode.textContent = String(progress.sent || 0);
            massSendExcludedNode.textContent = String(progress.excluded || 0);
            massSendFailedNode.textContent = String(progress.failed || 0);
        };

        const stopMassSendPolling = () => {
            if (activeMassSendPollTimer) {
                window.clearTimeout(activeMassSendPollTimer);
                activeMassSendPollTimer = null;
            }
        };

        const pollMassSendProgress = async () => {
            if (!activeMassSend || !activeMassSend.progressUrl) {
                return;
            }

            try {
                const response = await fetch(activeMassSend.progressUrl, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (response.ok) {
                    const payload = await response.json();
                    renderMassSendProgress(payload);
                }
            } catch (error) {
                // Reintento silencioso.
            } finally {
                if (activeMassSend) {
                    activeMassSendPollTimer = window.setTimeout(pollMassSendProgress, 1000);
                }
            }
        };

        const showSendBatchSavedToast = (sendBatch) => {
            const count = Number(sendBatch?.pending_batch_count ?? sendBatch?.count ?? 0);
            showMassGenerationFeedback(`Las ${count} proformas quedaron guardadas para envío posterior.`, 'success');
        };

        const buildSendBatchContext = (source, root = null) => {
            if (!source) {
                return null;
            }

            if (source.ready !== undefined) {
                return {
                    ready: !!source.ready,
                    group: Number(source.group || 0),
                    count: Number(source.count || source.pending_batch_count || 0),
                    current_execution_count: Number(source.current_execution_count || source.count || 0),
                    pending_batch_count: Number(source.pending_batch_count || source.count || 0),
                    companies_preview: Array.isArray(source.companies_preview) ? source.companies_preview : [],
                    send_url: source.send_url || null,
                    progress_url_template: source.progress_url_template || null,
                };
            }

            const companiesRaw = root?.dataset.sendBatchCompanies || '';

            return {
                ready: true,
                group: Number(root?.dataset.sendBatchGroup || 0),
                count: Number(root?.dataset.sendBatchCount || 0),
                current_execution_count: Number(root?.dataset.sendBatchCurrentCount || 0),
                pending_batch_count: Number(root?.dataset.sendBatchPendingCount || 0),
                companies_preview: companiesRaw !== '' ? companiesRaw.split('||').filter(Boolean) : [],
                send_url: root?.dataset.sendBatchSendUrl || null,
                progress_url_template: root?.dataset.sendBatchProgressUrlTemplate || null,
            };
        };

        const startMassSend = async (sendBatch, triggerButton = null) => {
            if (!sendBatch || !sendBatch.send_url || activeMassGeneration || activeMassSend) {
                return;
            }

            const executionId = randomExecutionId();
            const progressUrlTemplate = sendBatch.progress_url_template || '';
            const progressUrl = progressUrlTemplate.replace('__EXECUTION_ID__', encodeURIComponent(executionId));
            const formData = new FormData();
            formData.set('execution_id', executionId);
            formData.set('_token', '{{ csrf_token() }}');

            activeMassSend = {
                executionId,
                group: sendBatch.group,
                progressUrl,
            };

            setAllSendBatchButtonsState(true, triggerButton);
            openMassSendModal();
            stopMassSendPolling();
            pollMassSendProgress();

            try {
                const response = await fetch(sendBatch.send_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData,
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || 'No fue posible completar el envío masivo.');
                }

                stopMassSendPolling();
                renderMassSendProgress({
                    progress: {
                        total: Number(payload.total || sendBatch.pending_batch_count || 0),
                        processed: Number(payload.total || sendBatch.pending_batch_count || 0),
                        sent: Number(payload.summary?.sent || 0),
                        excluded: Number(payload.summary?.excluded || 0),
                        failed: Number(payload.summary?.failed || 0),
                        percentage: 100,
                        message: payload.message || 'Proceso finalizado.',
                    },
                });
                closeMassSendModal();
                clearSendBatchPanel();
                document.querySelectorAll('[data-static-send-batch-panel]').forEach((panel) => panel.classList.add('hidden'));
                showMassGenerationFeedback(buildMassSendSummaryHtml(payload.summary || {}, payload.message), payload.summary?.failed > 0 ? 'error' : 'success');
            } catch (error) {
                stopMassSendPolling();
                closeMassSendModal();
                showMassGenerationFeedback(error.message || 'Ocurrió un error durante el envío masivo.', 'error');
            } finally {
                activeMassSend = null;
                setAllSendBatchButtonsState(false, triggerButton);
            }
        };

        const bindSendBatchPanel = (root, sendBatch) => {
            if (!root) {
                return;
            }

            const context = buildSendBatchContext(sendBatch, root);
            const sendNowButton = root.querySelector('[data-send-batch-now]');
            const saveLaterButton = root.querySelector('[data-send-batch-later]');

            sendNowButton?.addEventListener('click', () => startMassSend(context, sendNowButton));
            saveLaterButton?.addEventListener('click', () => {
                root.classList.add('hidden');
                showSendBatchSavedToast(context);
            });
        };

        const enhanceSendBatchPanel = (root) => {
            if (!root) {
                return;
            }

            root.classList.remove('rounded', 'border-cyan-200', 'bg-cyan-50', 'shadow-none');
            root.classList.add('rounded-2xl', 'border-slate-200', 'bg-white', 'shadow-sm');

            root.querySelectorAll('.text-cyan-800').forEach((node) => {
                node.classList.remove('text-cyan-800');
                node.classList.add('text-slate-900');
            });

            root.querySelectorAll('.text-cyan-700').forEach((node) => {
                node.classList.remove('text-cyan-700');
                node.classList.add('text-slate-600');
            });

            const helperText = root.querySelector('.mt-3.text-xs');
            if (helperText) {
                helperText.classList.remove('text-cyan-700');
                helperText.classList.add('text-slate-500');
            }

            const sendNowButton = root.querySelector('[data-send-batch-now]');
            if (sendNowButton) {
                sendNowButton.className = 'inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-70';

                if (!sendNowButton.querySelector('[data-send-batch-spinner]')) {
                    sendNowButton.innerHTML = '<span class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-cyan-200 border-t-white" data-send-batch-spinner></span><span data-send-batch-label>Enviar ahora</span>';
                }
            }

            const saveLaterButton = root.querySelector('[data-send-batch-later]');
            if (saveLaterButton) {
                saveLaterButton.className = 'inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100';
                saveLaterButton.textContent = 'Guardar para después';
            }
        };

        const renderSendBatchPanel = (sendBatch) => {
            if (!sendBatchPanelContainer || !sendBatch || !sendBatch.ready || !sendBatch.send_url) {
                clearSendBatchPanel();
                return;
            }

            const companies = Array.isArray(sendBatch.companies_preview) ? sendBatch.companies_preview : [];
            const companiesText = companies.length > 0
                ? `${companies.join(', ')}${sendBatch.count > companies.length ? '...' : ''}`
                : 'Lote listo para envÃ­o.';

            sendBatchPanelContainer.innerHTML = `
                <div class="mb-4 rounded border border-cyan-200 bg-cyan-50 px-4 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-cyan-800">
                                Proformas generadas en esta ejecución: ${sendBatch.current_execution_count ?? sendBatch.count}.
                            </p>
                            <p class="text-sm text-cyan-700">
                                Total de proformas pendientes para envío en el lote actual: ${sendBatch.pending_batch_count ?? sendBatch.count}. ¿Desea enviarlas ahora?
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center rounded bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700" data-send-batch-now>
                                Enviar ahora
                            </button>
                            <button type="button" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300" data-send-batch-later>
                                MÃ¡s tarde
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-cyan-700">
                        Empresas listas: ${companiesText}
                    </p>
                </div>
            `;

            sendBatchPanelContainer.querySelector('[data-send-batch-now]')?.addEventListener('click', () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = sendBatch.send_url;
                form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
                document.body.appendChild(form);
                form.submit();
            });

            sendBatchPanelContainer.querySelector('[data-send-batch-later]')?.addEventListener('click', () => {
                clearSendBatchPanel();
            });
        };

        massGenerationForms.forEach((massForm) => {
            massForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (activeMassGeneration) {
                    return;
                }

                const group = massForm.dataset.grupo || '?';
                const executionId = randomExecutionId();
                const progressUrl = (massForm.dataset.progressUrlTemplate || '').replace('__EXECUTION_ID__', encodeURIComponent(executionId));
                const formData = new FormData(massForm);
                formData.set('execution_id', executionId);

                activeMassGeneration = {
                    executionId,
                    group,
                    progressUrl,
                };

                setMassGenerationButtonsState(true, massForm);
                openMassGenerationModal(group);
                stopMassGenerationPolling();
                pollMassGenerationProgress();

                try {
                    const response = await fetch(massForm.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': massForm.querySelector('input[name=\"_token\"]')?.value || '',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || payload.ok === false) {
                        throw new Error(payload.message || 'No fue posible completar la generación masiva.');
                    }

                    stopMassGenerationPolling();
                    renderMassGenerationProgress({
                        progress: {
                            grupo: group,
                            total: Number(payload.total || totalNode.textContent || 0),
                            processed: Number(payload.total || totalNode.textContent || 0),
                            percentage: 100,
                            message: payload.message || 'Proceso finalizado.',
                        },
                    });
                    closeMassGenerationModal();
                    showMassGenerationFeedback(buildMassGenerationSummaryHtml(group, payload.summary || {}, payload.message), 'success');
                    currentDynamicSendBatch = payload.send_batch || null;
                    if (currentDynamicSendBatch) {
                        currentDynamicSendBatch.progress_url_template = '{{ route('cobros.proformas-masivo.envio.progress', ['executionId' => '__EXECUTION_ID__']) }}';
                    }
                    renderSendBatchPanel(payload.send_batch || null);
                    enhanceSendBatchPanel(sendBatchPanelContainer.firstElementChild);
                } catch (error) {
                    stopMassGenerationPolling();
                    closeMassGenerationModal();
                    showMassGenerationFeedback(error.message || 'Ocurrió un error durante la generación masiva.', 'error');
                    clearSendBatchPanel();
                } finally {
                    activeMassGeneration = null;
                    setMassGenerationButtonsState(false);
                }
            });
        });

        document.querySelectorAll('[data-static-send-batch-panel]').forEach((panel) => {
            enhanceSendBatchPanel(panel);
        });

        document.addEventListener('click', (event) => {
            const sendNowButton = event.target.closest('[data-send-batch-now]');
            if (sendNowButton) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const panel = sendNowButton.closest('[data-static-send-batch-panel], [data-dynamic-send-batch-panel]');
                const context = panel?.matches('[data-static-send-batch-panel]')
                    ? buildSendBatchContext(null, panel)
                    : currentDynamicSendBatch;

                startMassSend(context, sendNowButton);
                return;
            }

            const saveLaterButton = event.target.closest('[data-send-batch-later]');
            if (saveLaterButton) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const panel = saveLaterButton.closest('[data-static-send-batch-panel], [data-dynamic-send-batch-panel]');
                const context = panel?.matches('[data-static-send-batch-panel]')
                    ? buildSendBatchContext(null, panel)
                    : currentDynamicSendBatch;

                if (panel) {
                    panel.classList.add('hidden');
                }

                if (!panel?.matches('[data-static-send-batch-panel]')) {
                    clearSendBatchPanel();
                }

                showSendBatchSavedToast(context);
            }
        }, true);

        window.initFilterSubmitLoading({
            formId: 'cobros-filter-form',
            submitButtonId: 'cobros-apply-filter-button',
            submitLabelId: 'cobros-apply-filter-label',
            submitSpinnerId: 'cobros-apply-filter-spinner',
            idleText: 'Filtrar',
            loadingText: 'Cargando...',
            disableTargetIds: ['cobros-clear-filters-button'],
            resultsAreaId: 'cobros-results-area',
            resultsOverlayId: 'cobros-results-loading-overlay',
            overlayMessage: 'Consultando cobros, por favor espere...',
            overlayDelayMs: 500,
        });

        const form = document.getElementById('cobros-filter-form');
        const grupoFechaInput = document.getElementById('grupo_fecha');
        const header = document.getElementById('fecha-arriendo-header');
        const menu = document.getElementById('fecha-arriendo-context-menu');

        if (!form || !grupoFechaInput || !header || !menu) {
            return;
        }

        const ocultarMenu = () => menu.classList.add('hidden');

        header.addEventListener('contextmenu', function (event) {
            event.preventDefault();
            menu.style.left = `${event.clientX}px`;
            menu.style.top = `${event.clientY}px`;
            menu.classList.remove('hidden');
        });

        menu.querySelectorAll('button[data-grupo-fecha]').forEach((button) => {
            button.addEventListener('click', function () {
                grupoFechaInput.value = this.dataset.grupoFecha;
                ocultarMenu();
                form.submit();
            });
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                ocultarMenu();
            }
        });

        window.addEventListener('scroll', ocultarMenu);
        window.addEventListener('resize', ocultarMenu);

        const notaButtons = document.querySelectorAll('.nota-cobro-btn');
        const notaModal = document.getElementById('nota-cobro-modal');
        const notaCliente = document.getElementById('nota-cobro-cliente');
        const notaTextarea = document.getElementById('nota-cobro-textarea');
        const notaFeedback = document.getElementById('nota-cobro-feedback');
        const notaGuardar = document.getElementById('nota-cobro-guardar');
        const notaLimpiar = document.getElementById('nota-cobro-limpiar');
        const notaCancelar = document.getElementById('nota-cobro-cancelar');
        const notaCancelarTop = document.getElementById('nota-cobro-cancelar-top');

        if (!notaModal || !notaTextarea || !notaCliente || !notaGuardar || !notaLimpiar || !notaCancelar || !notaCancelarTop || !notaFeedback) {
            return;
        }

        const csrfToken = '{{ csrf_token() }}';
        const updateUrlTemplate = '{{ route('cobros.nota.update', ['id' => '__CLIENTE_ID__']) }}';
        const clearUrlTemplate = '{{ route('cobros.nota.clear', ['id' => '__CLIENTE_ID__']) }}';

        let selectedClientId = null;
        let selectedButton = null;

        const closeNotaModal = () => {
            notaModal.classList.add('hidden');
            notaModal.classList.remove('flex');
            selectedClientId = null;
            selectedButton = null;
            notaFeedback.classList.add('hidden');
            notaFeedback.textContent = '';
            notaFeedback.classList.remove('text-rose-600', 'text-emerald-600');
        };

        const openNotaModal = (button) => {
            selectedButton = button;
            selectedClientId = button.dataset.clienteId;
            notaCliente.textContent = `Cliente: ${button.dataset.clienteNombre || 'Sin nombre'}`;
            notaTextarea.value = button.dataset.nota || '';
            notaFeedback.classList.add('hidden');
            notaFeedback.textContent = '';
            notaFeedback.classList.remove('text-rose-600', 'text-emerald-600');
            notaModal.classList.remove('hidden');
            notaModal.classList.add('flex');
            notaTextarea.focus();
        };

        const resumenNota = (nota) => {
            const notaNormalizada = (nota || '').trim();
            if (!notaNormalizada) {
                return 'Sin nota de cobro';
            }

            return notaNormalizada.length > 50 ? `${notaNormalizada.substring(0, 50)}…` : notaNormalizada;
        };

        const updateVisualState = (nota) => {
            if (!selectedButton) return;

            const hasNota = (nota || '').trim().length > 0;
            selectedButton.dataset.nota = nota || '';
            selectedButton.title = hasNota ? 'Tiene nota registrada' : 'Sin nota de cobro';
            selectedButton.classList.toggle('border-emerald-200', hasNota);
            selectedButton.classList.toggle('bg-emerald-50', hasNota);
            selectedButton.classList.toggle('hover:bg-emerald-100', hasNota);
            selectedButton.classList.toggle('text-emerald-700', hasNota);
            selectedButton.classList.toggle('border-slate-300', !hasNota);
            selectedButton.classList.toggle('hover:bg-slate-100', !hasNota);
            selectedButton.classList.toggle('text-slate-400', !hasNota);
        };

        const showFeedback = (message, isError = false) => {
            notaFeedback.textContent = message;
            notaFeedback.classList.remove('hidden', 'text-rose-600', 'text-emerald-600');
            notaFeedback.classList.add(isError ? 'text-rose-600' : 'text-emerald-600');
        };

        const setButtonsDisabled = (disabled) => {
            [notaGuardar, notaLimpiar, notaCancelar, notaCancelarTop].forEach((element) => {
                element.disabled = disabled;
            });
        };

        const requestNota = async (url, method, nota = null) => {
            const body = method === 'PATCH' ? JSON.stringify({ nota_cobro: nota }) : null;

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body,
            });

            const payload = await response.json();

            if (!response.ok) {
                const errorMessage = payload?.message || 'No fue posible actualizar la nota de cobro.';
                throw new Error(errorMessage);
            }

            return payload;
        };

        notaButtons.forEach((button) => {
            button.addEventListener('click', () => openNotaModal(button));
        });

        notaGuardar.addEventListener('click', async () => {
            if (!selectedClientId) return;

            setButtonsDisabled(true);

            try {
                const payload = await requestNota(updateUrlTemplate.replace('__CLIENTE_ID__', selectedClientId), 'PATCH', notaTextarea.value);
                updateVisualState(payload.nota_cobro || '');
                showFeedback(payload.message || 'Nota guardada correctamente.');
            } catch (error) {
                showFeedback(error.message || 'Error al guardar la nota.', true);
            } finally {
                setButtonsDisabled(false);
            }
        });

        notaLimpiar.addEventListener('click', async () => {
            if (!selectedClientId) return;

            setButtonsDisabled(true);

            try {
                const payload = await requestNota(clearUrlTemplate.replace('__CLIENTE_ID__', selectedClientId), 'DELETE');
                notaTextarea.value = '';
                updateVisualState('');
                showFeedback(payload.message || 'Nota eliminada correctamente.');
            } catch (error) {
                showFeedback(error.message || 'Error al limpiar la nota.', true);
            } finally {
                setButtonsDisabled(false);
            }
        });

        [notaCancelar, notaCancelarTop].forEach((button) => {
            button.addEventListener('click', closeNotaModal);
        });

        notaModal.addEventListener('click', (event) => {
            if (event.target === notaModal) {
                closeNotaModal();
            }
        });
    });
</script>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('pendientes-facturacion-modal');
        const openButton = document.getElementById('open-pendientes-facturacion-modal');
        const closeButton = document.getElementById('close-pendientes-facturacion-modal');
        const cancelButton = document.getElementById('cancel-pendientes-facturacion-modal');
        const selectAllButton = document.getElementById('select-all-pendientes-facturacion');
        const checkboxes = Array.from(document.querySelectorAll('.pendiente-facturacion-checkbox'));

        if (!modal || !openButton || !closeButton || !cancelButton || !selectAllButton || checkboxes.length === 0) {
            return;
        }

        let allSelected = false;

        const openModal = () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        };

        openButton.addEventListener('click', openModal);
        closeButton.addEventListener('click', closeModal);
        cancelButton.addEventListener('click', closeModal);

        selectAllButton.addEventListener('click', () => {
            allSelected = !allSelected;
            checkboxes.forEach((checkbox) => {
                checkbox.checked = allSelected;
            });
            selectAllButton.textContent = allSelected ? 'Limpiar seleccion' : 'Seleccionar todos';
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    });
</script>
@endpush
