@extends('layouts.admin')

@section('title', 'Listado de Proformas')

@section('content')
@php
    $canManageActivation = (int) session('rol_id', session('roles_idroles')) === 1;
@endphp
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Listado de Proformas</h1>
            <p class="text-sm text-slate-600">Consulta administrativa sobre <code>sg_proform</code>.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($canManageActivation)
                <button id="activacion-global-abrir" type="button" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-800 hover:bg-cyan-200">Activación</button>
            @endif
            <a href="{{ route('proformas.dashboard') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Ver Informe
            </a>
            <a href="{{ route('proformas.estado-cuenta.index') }}" class="inline-flex items-center rounded bg-amber-100 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-200">
                Estado de Cuenta
            </a>
            <a href="{{ route('proformas.cartera.index') }}" class="inline-flex items-center rounded bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200">
                &#128203; Cartera pendiente
            </a>
            <a href="{{ route('cobros.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Ir a Cobros
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form id="proformas-filter-form" method="GET" action="{{ route('proformas.index') }}" class="grid items-end gap-4 md:grid-cols-5 lg:grid-cols-7">
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="nro_prof" class="mb-1 block text-sm font-medium">Número</label>
                <input id="nro_prof" name="nro_prof" value="{{ $filters['nro_prof'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="empresa" class="mb-1 block text-sm font-medium">Código o Empresa</label>
                <input id="empresa" name="empresa" value="{{ $filters['empresa'] ?? '' }}" placeholder="CÃ³digo, nombre, empresa o NIT" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">Admite bÃºsqueda por cÃ³digo, nombre, empresa o NIT.</p>
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="emisora" class="mb-1 block text-sm font-medium">Emisora</label>
                <input id="emisora" name="emisora" value="{{ $filters['emisora'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((string) ($filters['mes'] ?? '') === (string) $mesNumero || (string) ($filters['mes'] ?? '') === $mesNombre)>
                            {{ ucfirst($mesNombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoLabel)
                        <option value="{{ $estadoCodigo }}" @selected((string) ($filters['estado'] ?? '') === (string) $estadoCodigo)>{{ $estadoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="envio" class="mb-1 block text-sm font-medium">Envío</label>
                <select id="envio" name="envio" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="1" @selected((string) ($filters['envio'] ?? '') === '1')>Enviada</option>
                    <option value="0" @selected((string) ($filters['envio'] ?? '') === '0')>No enviada</option>
                </select>
            </div>
            <div class="min-w-[120px] max-w-[180px] w-full">
                <label for="filtro_nota" class="mb-1 block text-sm font-medium">Nota</label>
                <select id="filtro_nota" name="filtro_nota" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="con" @selected((string) ($filters['filtro_nota'] ?? '') === 'con')>Con nota</option>
                    <option value="sin" @selected((string) ($filters['filtro_nota'] ?? '') === 'sin')>Sin nota</option>
                </select>
            </div>
            <div class="acciones-filtros flex items-end gap-[10px] self-end">
                <button type="submit" id="proformas-apply-filter-button" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70">
                    <span id="proformas-apply-filter-spinner" class="mr-2 hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    <span id="proformas-apply-filter-label">Filtrar</span>
                </button>
                <a id="proformas-clear-filters-button" href="{{ route('proformas.clear-filters') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div id="proformas-results-area" class="relative">
        <div id="proformas-results-loading-overlay" class="pointer-events-none absolute inset-0 z-20 hidden items-center justify-center rounded-lg bg-white/75 backdrop-blur-[1px]">
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 text-center shadow-lg">
                <div class="mx-auto mb-3 h-6 w-6 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600"></div>
                <p class="text-sm font-medium text-slate-700">Consultando proformas, por favor espere...</p>
            </div>
        </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2">Fecha arriendo</th>
                    <th class="px-3 py-2">Número</th>
                    <th class="px-3 py-2">Código</th>
                    <th class="px-3 py-2">Empresa</th>
                    <th class="px-3 py-2">Periodo</th>
                    <th class="px-3 py-2">Origen</th>
                    <th class="px-3 py-2 text-right">Valor total</th>
                    <th class="px-3 py-2 text-center">Nota</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Envío</th>
                    <th class="px-3 py-2 text-right">Acción</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @if(!($hasSearched ?? true))
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-500">Seleccione los filtros y pulse Filtrar para consultar proformas.</td>
                    </tr>
                @else
                @forelse($proformas as $proforma)
                    @php
                        $estadoCodigo = (int) ($proforma->estado ?? 0);
                        $estado = $proformasService->estadoLabel($proforma->estado);
                        $envioEstado = $proformasService->envioLabel($proforma->enviado ?? 0);
                        $envioClasses = $proformasService->envioBadgeClass($proforma->enviado ?? 0);
                        $notaCobro = trim((string) ($proforma->nota_cobro ?? ''));
                        $notaResumen = $notaCobro !== '' ? \Illuminate\Support\Str::limit($notaCobro, 50) : 'Sin nota de cobro';
                        $clientePotencialId = (int) ($proforma->cliente_potencial_id ?? 0);
                        $fechaArriendo = \Illuminate\Support\Carbon::make($proforma->cliente_fecha_arriendo)?->format('d/m/Y') ?: 'N/D';
                        $canSendProforma = $proformasService->canSendProforma($proforma);
                        $formatEstadoFecha = static function ($value): ?string {
                            if (trim((string) ($value ?? '')) === '') {
                                return null;
                            }

                            try {
                                return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
                            } catch (\Throwable) {
                                return null;
                            }
                        };
                        $estadoTooltipLines = [];

                        if (in_array($estadoCodigo, [\App\Services\ProformasService::ESTADO_PAGADA, \App\Services\ProformasService::ESTADO_FACTURADA], true)) {
                            $fechaPago = $formatEstadoFecha($proforma->fpag ?? null);

                            if ($fechaPago !== null) {
                                $estadoTooltipLines[] = "Fecha de pago: {$fechaPago}";
                            }
                        }

                        if ($estadoCodigo === \App\Services\ProformasService::ESTADO_FACTURADA) {
                            $fechaFacturacion = $formatEstadoFecha($proforma->ffac ?? null);

                            if ($fechaFacturacion !== null) {
                                $estadoTooltipLines[] = "Fecha de facturación: {$fechaFacturacion}";
                            }
                        }

                        $estadoTooltip = implode("\n", $estadoTooltipLines);
                    @endphp
                    <tr
                        class="hover:bg-slate-50"
                        data-proforma-row
                        data-proforma-id="{{ $proforma->id }}"
                        data-codigo="{{ $proforma->codigo ?? '' }}"
                        data-nit="{{ $proforma->nit ?? '' }}"
                        data-cliente-id="{{ $proforma->id_cliente ?? '' }}"
                        data-estado="{{ $estadoCodigo }}"
                        data-enviado="{{ (int) ($proforma->enviado ?? 0) }}"
                        data-update-url="{{ route('proformas.estado.update', $proforma->id) }}"
                        data-comprobante-url="{{ route('proformas.comprobante-pago.show', $proforma->id) }}"
                        data-has-comprobante="{{ trim((string) ($proforma->comprobante_pago ?? '')) !== '' ? '1' : '0' }}"
                        data-pdf-url="{{ route('proformas.pdf.show', $proforma->id) }}"
                        data-enviar-url="{{ $canSendProforma ? route('proformas.enviar', $proforma->id) : '' }}"
                        data-marcar-enviada-url="{{ route('proformas.marcar-enviada', $proforma->id) }}"
                        data-marcar-no-enviada-url="{{ route('proformas.marcar-no-enviada', $proforma->id) }}"
                        data-activacion-show-url="{{ $canManageActivation ? route('proformas.activacion.show', $proforma->id) : '' }}"
                        data-activacion-update-url="{{ $canManageActivation ? route('proformas.activacion.update', $proforma->id) : '' }}"
                        data-activacion-eventos-update-url="{{ $canManageActivation ? route('proformas.activacion.eventos.update', $proforma->id) : '' }}"
                    >
                        <td class="px-3 py-2 whitespace-nowrap text-slate-700">{{ $fechaArriendo }}</td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-800">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</p>
                            <p class="text-xs text-slate-500">ID {{ $proforma->id }}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-700">{{ $proforma->codigo ?: 'N/D' }}</td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-800">{{ $proforma->emp ?: 'N/D' }}</p>
                            <p class="text-xs text-slate-500">NIT: {{ $proforma->nit ?: 'N/D' }}</p>
                            <p class="text-xs text-slate-500">Emisora: {{ strtoupper((string) ($proforma->emisora ?? 'N/D')) }}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-700">{{ $proformasService->monthLabel($proforma->mes) }} {{ $proforma->anio ?: 'N/D' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $proformasService->resolutionSourceBadgeClass($proforma->cliente_resolution_source ?? null) }}">
                                {{ $proformasService->resolutionSourceLabel($proforma->cliente_resolution_source ?? null) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($clientePotencialId > 0)
                                <button
                                    type="button"
                                    class="nota-cobro-btn inline-flex h-8 w-8 items-center justify-center rounded-full border text-base transition {{ $notaCobro !== '' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-300 text-slate-400 hover:bg-slate-100' }}"
                                    data-cliente-id="{{ $clientePotencialId }}"
                                    data-cliente-nombre="{{ $proforma->emp ?: 'Sin nombre' }}"
                                    data-nota="{{ $notaCobro }}"
                                    title="{{ $notaCobro !== '' ? 'Tiene nota registrada' : 'Sin nota de cobro' }}"
                                    aria-label="Editar nota de cobro"
                                >&#128221;</button>
                            @else
                                <span class="text-slate-300" title="Cliente no disponible">&#128221;</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                data-estado-badge
                                data-label-generada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_GENERADA) }}"
                                data-label-enviada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_ENVIADA) }}"
                                data-label-pagada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                                data-label-facturada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                                data-style-generada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_GENERADA) }}"
                                data-style-enviada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_ENVIADA) }}"
                                data-style-pagada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                                data-style-facturada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                                @if($estadoTooltip !== '')
                                    data-estado-tooltip="{{ $estadoTooltip }}"
                                    tabindex="0"
                                    aria-label="{{ $estado }}. {{ implode('. ', $estadoTooltipLines) }}"
                                @endif
                                style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}"
                            >{{ $estado }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $envioClasses }}"
                                data-envio-badge
                                data-label-enviada="{{ $proformasService->envioLabel(1) }}"
                                data-label-no-enviada="{{ $proformasService->envioLabel(0) }}"
                                data-class-enviada="{{ $proformasService->envioBadgeClass(1) }}"
                                data-class-no-enviada="{{ $proformasService->envioBadgeClass(0) }}"
                            >{{ $envioEstado }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded bg-slate-100 px-2 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200"
                                    data-proforma-actions
                                    aria-label="Abrir acciones rápidas"
                                >⋮</button>
                                {{-- <a href="{{ route('proformas.show', array_merge(['id' => $proforma->id], request()->query())) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver detalle</a> --}}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-500">No hay proformas para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                @endif
                </tbody>
            </table>
        </div>

        @if($hasSearched ?? true)
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $proformas->links() }}
            </div>
        @endif
    </div>
    </div>
</div>

@include('partials.nota-cobro-modal')

<div
    id="proforma-context-menu"
    class="pointer-events-none fixed z-50 min-w-48 origin-top-left scale-95 rounded-md border border-slate-200 bg-white p-1 opacity-0 shadow-lg transition duration-150"
>
    <ul id="proforma-context-menu-items" class="space-y-1"></ul>
</div>

<div id="proforma-estado-tooltip" role="tooltip" aria-hidden="true" class="pointer-events-none fixed z-[100] hidden max-w-xs whitespace-pre-line rounded-md bg-slate-900 px-3 py-2 text-xs font-medium leading-5 text-white shadow-xl"></div>

@if($canManageActivation)
<div id="activacion-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-3xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Activación</h2>
                <p class="text-sm text-slate-500">Consulta y actualización de fechas de licencia de la empresa.</p>
            </div>
            <button id="activacion-cerrar-superior" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">X</button>
        </div>

        <div id="activacion-busqueda" class="hidden space-y-3 px-5 py-5" data-search-url="{{ route('proformas.activacion.clientes.buscar') }}">
            <label for="activacion-buscar-cliente" class="block text-sm font-medium text-slate-700">Buscar empresa por código o nombre</label>
            <input id="activacion-buscar-cliente" type="search" autocomplete="off" maxlength="100" aria-controls="activacion-resultados" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: A091 o MGI COMPUTERS">
            <p id="activacion-busqueda-estado" class="text-sm text-slate-500" role="status">Escribe al menos 2 caracteres.</p>
            <ul id="activacion-resultados" class="max-h-64 space-y-2 overflow-y-auto" aria-label="Clientes encontrados"></ul>
        </div>
        <div class="px-5 pt-3">
            <p id="activacion-cliente-seleccionado" class="text-sm font-medium text-slate-700"></p>
            <button id="activacion-cambiar-cliente" type="button" class="hidden mt-2 text-sm text-cyan-700 underline">Buscar otra empresa</button>
        </div>
        <form id="activacion-form" class="space-y-5 px-5 py-5">
            <div id="activacion-feedback" class="hidden rounded border px-4 py-3 text-sm"></div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Código empresa</p>
                    <p id="activacion-codigo" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Servidor detectado</p>
                            <p id="activacion-servidor" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                        </div>
                        <span id="activacion-servidor-badge" class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">-</span>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Base detectada</p>
                    <p id="activacion-base" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Sincronización actual</p>
                    <p id="activacion-sincronizacion" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Fecha inicio actual</p>
                    <p id="activacion-fecha-inicio-actual" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Fecha fin actual</p>
                    <p id="activacion-fecha-fin-actual" class="mt-1 text-sm font-semibold text-slate-900">-</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="activacion-fecha-inicio" class="mb-1 block text-sm font-medium text-slate-700">Fecha inicio</label>
                    <input id="activacion-fecha-inicio" name="fecha_inicio" type="date" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label for="activacion-fecha-fin" class="mb-1 block text-sm font-medium text-slate-700">Fecha fin</label>
                    <input id="activacion-fecha-fin" name="fecha_fin" type="date" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <button id="activacion-cerrar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
                <button id="activacion-guardar" type="submit" class="rounded bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-700">Guardar activación</button>
            </div>
        </form>
</div>
</div>
@endif

@if($canManageActivation)
<div id="activacion-eventos-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-900">Actualizar licencia de Eventos</h3>
        </div>
        <div class="space-y-3 px-5 py-5">
            <p class="text-sm text-slate-700">Esta empresa también tiene licencia en Eventos. ¿Desea actualizar la licencia de Eventos con la misma fecha de vencimiento?</p>
            <p id="activacion-eventos-detalle" class="text-sm text-slate-500"></p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button id="activacion-eventos-no" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">No</button>
            <button id="activacion-eventos-si" type="button" class="rounded bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-700">Sí, activar Eventos</button>
        </div>
    </div>
</div>
@endif

<div id="pago-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="pago-modal-titulo">
    <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="pago-modal-titulo" class="text-base font-semibold text-slate-900">Marcar proforma como pagada</h2>
            <button id="pago-modal-cerrar-superior" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">X</button>
        </div>

        <form id="pago-form" class="space-y-4 px-5 py-5">
            <div>
                <label for="pago-metodo" class="mb-1 block text-sm font-medium text-slate-700">Método de pago</label>
                <select id="pago-metodo" name="fpago" required class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <option value="">Seleccionar...</option>
                    <option value="EFECTIVO">Efectivo</option>
                    <option value="TRANSFERENCIA">Transferencia</option>
                    <option value="CONSIGNACIÓN">Consignación</option>
                </select>
            </div>

            <div id="pago-comprobante-contenedor" class="hidden">
                <span class="mb-1 block text-sm font-medium text-slate-700">Comprobante de pago</span>
                <input id="pago-comprobante" name="comprobante_pago" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only">
                <div class="flex items-center gap-3">
                    <button id="pago-comprobante-abrir" type="button" class="rounded bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60" aria-controls="pago-comprobante">
                        <span id="pago-comprobante-boton-texto">Elegir archivo</span>
                    </button>
                    <span id="pago-comprobante-nombre" class="min-w-0 truncate text-sm text-slate-600" aria-live="polite">Ningún archivo seleccionado.</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">JPG, JPEG, PNG, WEBP o PDF. Máximo 10 MB.</p>
            </div>

            <div id="pago-feedback" class="hidden rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <button id="pago-modal-cancelar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
                <button id="pago-modal-confirmar" type="submit" class="rounded bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60">Confirmar pago</button>
            </div>
        </form>
    </div>
</div>

<div id="envio-masivo-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-5xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <h2 id="envio-masivo-titulo" class="text-base font-semibold text-slate-900">Envio masivo</h2>
                <p id="envio-masivo-subtitulo" class="text-sm text-slate-500"></p>
            </div>
            <button id="envio-masivo-cerrar-superior" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">X</button>
        </div>

        <form id="envio-masivo-form" method="POST" class="space-y-4 px-4 py-4">
            @csrf
            <input type="hidden" name="mes" id="envio-masivo-mes" value="">
            <input type="hidden" name="anio" id="envio-masivo-anio" value="">

            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase text-slate-500">Total encontradas</p>
                    <p id="envio-masivo-total" class="mt-1 text-lg font-semibold text-slate-900">0</p>
                </div>
                <div class="rounded bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase text-slate-500">Validas</p>
                    <p id="envio-masivo-validas" class="mt-1 text-lg font-semibold text-emerald-600">0</p>
                </div>
                <div class="rounded bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase text-slate-500">Omitidas</p>
                    <p id="envio-masivo-omitidas" class="mt-1 text-lg font-semibold text-amber-600">0</p>
                </div>
                <div class="rounded bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase text-slate-500">Seleccionadas</p>
                    <p id="envio-masivo-seleccionadas" class="mt-1 text-lg font-semibold text-cyan-700">0</p>
                </div>
            </div>

            <div id="envio-masivo-feedback" class="hidden rounded border px-4 py-3 text-sm"></div>

            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Antes de enviar puedes marcar o desmarcar las empresas que correspondan.</p>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" id="envio-masivo-seleccionar-todas" checked class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    Seleccionar todas
                </label>
            </div>

            <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Enviar</th>
                        <th class="px-4 py-3">Proforma</th>
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Fecha arriendo</th>
                    </tr>
                    </thead>
                    <tbody id="envio-masivo-listado" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Cargando listado...</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <button id="envio-masivo-cerrar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
                <button id="envio-masivo-submit" type="submit" class="rounded bg-cyan-600 px-3 py-2 text-sm font-medium text-white hover:bg-cyan-700">Enviar seleccionadas</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('partials.filter-submit-loading-script')
<script>
    (() => {
        const tooltip = document.getElementById('proforma-estado-tooltip');
        const badges = Array.from(document.querySelectorAll('[data-estado-tooltip]'));
        let activeBadge = null;

        if (!tooltip || badges.length === 0) {
            return;
        }

        const hideTooltip = () => {
            activeBadge = null;
            tooltip.classList.add('hidden');
            tooltip.setAttribute('aria-hidden', 'true');
        };

        const positionTooltip = (badge) => {
            const badgeRect = badge.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const gap = 8;
            const viewportPadding = 8;
            let top = badgeRect.top - tooltipRect.height - gap;

            if (top < viewportPadding) {
                top = badgeRect.bottom + gap;
            }

            const centeredLeft = badgeRect.left + ((badgeRect.width - tooltipRect.width) / 2);
            const maxLeft = Math.max(viewportPadding, window.innerWidth - tooltipRect.width - viewportPadding);
            const left = Math.min(Math.max(centeredLeft, viewportPadding), maxLeft);

            tooltip.style.top = `${Math.round(top)}px`;
            tooltip.style.left = `${Math.round(left)}px`;
        };

        const showTooltip = (badge) => {
            const content = badge.dataset.estadoTooltip?.trim();
            if (!content) {
                return;
            }

            activeBadge = badge;
            tooltip.textContent = content;
            tooltip.classList.remove('hidden');
            tooltip.setAttribute('aria-hidden', 'false');
            positionTooltip(badge);
        };

        badges.forEach((badge) => {
            badge.addEventListener('mouseenter', () => showTooltip(badge));
            badge.addEventListener('mouseleave', hideTooltip);
            badge.addEventListener('focus', () => showTooltip(badge));
            badge.addEventListener('blur', hideTooltip);
            badge.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideTooltip();
                    badge.blur();
                }
            });
        });

        window.addEventListener('scroll', hideTooltip, true);
        window.addEventListener('resize', () => {
            if (activeBadge) {
                positionTooltip(activeBadge);
            }
        });
    })();
</script>
<script>
    (() => {
        window.initFilterSubmitLoading({
            formId: 'proformas-filter-form',
            submitButtonId: 'proformas-apply-filter-button',
            submitLabelId: 'proformas-apply-filter-label',
            submitSpinnerId: 'proformas-apply-filter-spinner',
            idleText: 'Filtrar',
            loadingText: 'Cargando...',
            disableTargetIds: ['proformas-clear-filters-button'],
            resultsAreaId: 'proformas-results-area',
            resultsOverlayId: 'proformas-results-loading-overlay',
            overlayMessage: 'Consultando proformas, por favor espere...',
            overlayDelayMs: 500,
        });

        const companySearchInput = document.getElementById('empresa');
        const companySearchLabel = document.querySelector('label[for="empresa"]');
        const companySearchHelp = companySearchInput?.parentElement?.querySelector('p');

        if (companySearchLabel) {
            companySearchLabel.textContent = 'Codigo o Empresa';
            companySearchLabel.title = 'Busca por codigo, nombre, empresa o NIT';
        }

        if (companySearchInput) {
            companySearchInput.placeholder = 'Codigo, nombre, empresa o NIT';
            companySearchInput.title = 'Busca por codigo, nombre, empresa o NIT';
        }

        companySearchHelp?.remove();

        const ESTADO_GENERADA = {{ \App\Services\ProformasService::ESTADO_GENERADA }};
        const ESTADO_ENVIADA = {{ \App\Services\ProformasService::ESTADO_ENVIADA }};
        const ESTADO_PAGADA = {{ \App\Services\ProformasService::ESTADO_PAGADA }};
        const ESTADO_FACTURADA = {{ \App\Services\ProformasService::ESTADO_FACTURADA }};
        const csrfToken = @json(csrf_token());
        const activeEstadoFilter = @json($filters['estado'] ?? null);
        const activeEnvioFilter = @json($filters['envio'] ?? null);
        const confirmManualEnvioMessage = '¿Marcar esta proforma como enviada manualmente?\nÚselo para WhatsApp u otros medios externos.';
        const confirmManualNoEnvioMessage = '¿Marcar esta proforma como NO enviada manualmente?';

        const tableRows = Array.from(document.querySelectorAll('[data-proforma-row]'));
        const menu = document.getElementById('proforma-context-menu');
        const menuItems = document.getElementById('proforma-context-menu-items');
        const paymentModal = document.getElementById('pago-modal');
        const paymentForm = document.getElementById('pago-form');
        const paymentMethod = document.getElementById('pago-metodo');
        const paymentReceiptContainer = document.getElementById('pago-comprobante-contenedor');
        const paymentReceipt = document.getElementById('pago-comprobante');
        const paymentReceiptOpenButton = document.getElementById('pago-comprobante-abrir');
        const paymentReceiptButtonText = document.getElementById('pago-comprobante-boton-texto');
        const paymentReceiptName = document.getElementById('pago-comprobante-nombre');
        const paymentFeedback = document.getElementById('pago-feedback');
        const paymentCloseTopButton = document.getElementById('pago-modal-cerrar-superior');
        const paymentCancelButton = document.getElementById('pago-modal-cancelar');
        const paymentConfirmButton = document.getElementById('pago-modal-confirmar');
        const activationModal = document.getElementById('activacion-modal');
        const activationForm = document.getElementById('activacion-form');
        const activationCloseTopButton = document.getElementById('activacion-cerrar-superior');
        const activationCloseButton = document.getElementById('activacion-cerrar');
        const activationSubmitButton = document.getElementById('activacion-guardar');
        const activationFeedback = document.getElementById('activacion-feedback');
        const activationCodigo = document.getElementById('activacion-codigo');
        const activationServidor = document.getElementById('activacion-servidor');
        const activationServidorBadge = document.getElementById('activacion-servidor-badge');
        const activationBase = document.getElementById('activacion-base');
        const activationSync = document.getElementById('activacion-sincronizacion');
        const activationFechaInicioActual = document.getElementById('activacion-fecha-inicio-actual');
        const activationFechaFinActual = document.getElementById('activacion-fecha-fin-actual');
        const activationFechaInicioInput = document.getElementById('activacion-fecha-inicio');
        const activationFechaFinInput = document.getElementById('activacion-fecha-fin');
        const eventosModal = document.getElementById('activacion-eventos-modal');
        const eventosDetalle = document.getElementById('activacion-eventos-detalle');
        const eventosNoButton = document.getElementById('activacion-eventos-no');
        const eventosSiButton = document.getElementById('activacion-eventos-si');

        if (!menu || !menuItems) {
            return;
        }

        let currentRow = null;
        let pendingPaymentRow = null;
        let paymentSubmitting = false;
        let paymentReceiptPickerOpening = false;
        let paymentReceiptFocusHandler = null;
        let paymentReceiptSafetyTimeout = null;

        let feedbackTimeout = null;
        let activationBusy = false;
        let activationLoadVersion = 0;
        const activationGlobalButton = document.getElementById('activacion-global-abrir');
        const activationSearchPanel = document.getElementById('activacion-busqueda');
        const activationSearchInput = document.getElementById('activacion-buscar-cliente');
        const activationSearchResults = document.getElementById('activacion-resultados');
        const activationSearchStatus = document.getElementById('activacion-busqueda-estado');
        const activationSelectedClient = document.getElementById('activacion-cliente-seleccionado');
        const activationChangeClient = document.getElementById('activacion-cambiar-cliente');
        let activationSearchTimer = null;
        let activationSearchRequest = null;

        const activationRequestContext = () => activationForm?.dataset.global === '1' ? {} : {
            codigo: activationForm?.dataset.codigo || '',
            id_proforma: activationForm?.dataset.proformaId || '',
            nit: activationForm?.dataset.nit || '',
            id_cliente: activationForm?.dataset.clienteId || '',
        };

        const updatePaymentReceiptName = () => {
            if (paymentReceiptName) {
                paymentReceiptName.textContent = paymentReceipt?.files?.[0]?.name || 'Ningún archivo seleccionado.';
            }
        };

        const finishOpeningPaymentReceipt = () => {
            if (paymentReceiptFocusHandler) {
                window.removeEventListener('focus', paymentReceiptFocusHandler);
                paymentReceiptFocusHandler = null;
            }

            if (paymentReceiptSafetyTimeout) {
                window.clearTimeout(paymentReceiptSafetyTimeout);
                paymentReceiptSafetyTimeout = null;
            }

            paymentReceiptPickerOpening = false;
            if (paymentReceiptOpenButton) {
                paymentReceiptOpenButton.disabled = false;
            }
            if (paymentReceiptButtonText) {
                paymentReceiptButtonText.textContent = 'Elegir archivo';
            }
            updatePaymentReceiptName();
        };

        const openPaymentReceiptPicker = () => {
            if (!paymentReceipt || !paymentReceiptOpenButton || paymentReceiptPickerOpening) {
                return;
            }

            paymentReceiptPickerOpening = true;
            paymentReceiptOpenButton.disabled = true;
            if (paymentReceiptButtonText) {
                paymentReceiptButtonText.textContent = '⏳ Abriendo...';
            }

            paymentReceiptFocusHandler = () => window.setTimeout(finishOpeningPaymentReceipt, 0);
            window.addEventListener('focus', paymentReceiptFocusHandler, { once: true });
            paymentReceiptSafetyTimeout = window.setTimeout(finishOpeningPaymentReceipt, 30000);

            window.requestAnimationFrame(() => {
                window.setTimeout(() => {
                    if (paymentReceiptPickerOpening) {
                        paymentReceipt.click();
                    }
                }, 0);
            });
        };

        const showFeedback = (message, type = 'success') => {
            let container = document.getElementById('proforma-feedback');
            if (!container) {
                container = document.createElement('div');
                container.id = 'proforma-feedback';
                container.className = 'fixed right-4 top-4 z-50 rounded-md border px-4 py-2 text-sm shadow transition';
                document.body.appendChild(container);
            }

            container.textContent = message;
            container.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'border-rose-200', 'bg-rose-50', 'text-rose-700');
            container.classList.add(
                ...(type === 'success'
                    ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700']
                    : ['border-rose-200', 'bg-rose-50', 'text-rose-700']),
            );

            container.classList.remove('opacity-0');
            container.classList.add('opacity-100');

            if (feedbackTimeout) {
                window.clearTimeout(feedbackTimeout);
            }

            feedbackTimeout = window.setTimeout(() => {
                container.classList.remove('opacity-100');
                container.classList.add('opacity-0');
            }, 2500);
        };

        const openPaymentModal = (row) => {
            if (!paymentModal || !paymentForm || !paymentMethod || !paymentConfirmButton) {
                return;
            }

            pendingPaymentRow = row;
            paymentSubmitting = false;
            paymentForm.reset();
            finishOpeningPaymentReceipt();
            syncPaymentReceiptRequirement();
            paymentFeedback?.classList.add('hidden');
            if (paymentFeedback) {
                paymentFeedback.textContent = '';
            }
            paymentConfirmButton.disabled = false;
            paymentConfirmButton.textContent = 'Confirmar pago';
            paymentModal.classList.remove('hidden');
            paymentModal.classList.add('flex');
            paymentMethod.focus();
        };

        const closePaymentModal = () => {
            if (!paymentModal || paymentSubmitting) {
                return;
            }

            paymentModal.classList.add('hidden');
            paymentModal.classList.remove('flex');
            paymentForm?.reset();
            finishOpeningPaymentReceipt();
            pendingPaymentRow = null;
        };


        const hideMenu = () => {
            menu.classList.add('pointer-events-none', 'opacity-0', 'scale-95');
            menu.classList.remove('opacity-100', 'scale-100');
            currentRow = null;
        };

        const showMenu = (x, y, row) => {
            const acciones = getActionsForState(
                Number(row.dataset.estado || 0),
                Number(row.dataset.enviado || 0),
                row.dataset.pdfUrl || '',
                row.dataset.enviarUrl || '',
                row.dataset.activacionShowUrl || '',
                row.dataset.comprobanteUrl || '',
                Number(row.dataset.hasComprobante || 0),
            );

            if (acciones.length === 0) {
                hideMenu();
                return;
            }


            menuItems.innerHTML = acciones.map((accion) => {
                if (accion.type === 'link') {
                    return `<li>
                        <a href="${accion.url}" target="_blank" rel="noopener noreferrer" class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
                            ${accion.label}
                        </a>
                    </li>`;
                }

                return `<li>
                    <button type="button" class="w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100" ${accion.estado !== undefined ? `data-target-state="${accion.estado}"` : ''} ${accion.envioAction ? `data-envio-action="${accion.envioAction}"` : ''} ${accion.correoAction ? `data-correo-action="${accion.correoAction}"` : ''} ${accion.activacionAction ? `data-activacion-action="${accion.activacionAction}"` : ''}>
                        ${accion.label}
                    </button>
                </li>`;
            }).join('');


            currentRow = row;
            menu.style.left = `${x}px`;
            menu.style.top = `${y}px`;
            menu.classList.remove('pointer-events-none', 'opacity-0', 'scale-95');
            menu.classList.add('opacity-100', 'scale-100');
        };

        const versionedPdfUrl = (url) => `${url}${url.includes('?') ? '&' : '?'}v=${Date.now()}`;

        const getActionsForState = (estadoActual, enviadoActual, pdfUrl, enviarUrl, activationShowUrl, comprobanteUrl, hasComprobante) => {
            const acciones = [];
            if (pdfUrl) {
                acciones.push({ type: 'link', label: 'Ver PDF', url: versionedPdfUrl(pdfUrl) });
            }

            if (hasComprobante === 1 && comprobanteUrl) {
                acciones.push({ type: 'link', label: 'Ver comprobante', url: comprobanteUrl });
            }

            if (activationShowUrl) {
                acciones.push({ type: 'activacion', activacionAction: 'abrir', label: 'Activación' });
            }

            if (estadoActual === ESTADO_PAGADA) {
                acciones.push({ type: 'estado', estado: ESTADO_FACTURADA, label: 'Marcar facturada' });
                return acciones;
            }

            if (enviarUrl) {
                acciones.push({
                    type: 'correo',
                    correoAction: 'enviar',
                    label: enviadoActual === 1 ? 'Reenviar correo' : 'Enviar correo ahora',
                });
            }

            if (estadoActual === ESTADO_GENERADA && enviadoActual !== 1) {
                acciones.push({ type: 'envio', envioAction: 'marcar', label: '&#128241; Marcar enviada' });
            }

            if (estadoActual === ESTADO_GENERADA || estadoActual === ESTADO_ENVIADA) {
                acciones.push({ type: 'estado', estado: ESTADO_PAGADA, label: 'Marcar pagada' });
            }

            return acciones;

        };

        const setActivationFeedback = (message, type = 'warning') => {
            if (!activationFeedback) {
                return;
            }

            activationFeedback.textContent = message;
            activationFeedback.classList.remove(
                'hidden',
                'border-emerald-200', 'bg-emerald-50', 'text-emerald-700',
                'border-rose-200', 'bg-rose-50', 'text-rose-700',
                'border-amber-200', 'bg-amber-50', 'text-amber-700',
            );
            activationFeedback.classList.add(...(type === 'success'
                ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700']
                : (type === 'error'
                    ? ['border-rose-200', 'bg-rose-50', 'text-rose-700']
                    : ['border-amber-200', 'bg-amber-50', 'text-amber-700'])));
        };

        const clearActivationFeedback = () => {
            if (!activationFeedback) {
                return;
            }

            activationFeedback.classList.add('hidden');
            activationFeedback.textContent = '';
            activationFeedback.classList.remove(
                'border-emerald-200', 'bg-emerald-50', 'text-emerald-700',
                'border-rose-200', 'bg-rose-50', 'text-rose-700',
                'border-amber-200', 'bg-amber-50', 'text-amber-700',
            );
        };

        const openEventosModal = () => {
            if (!eventosModal) {
                return;
            }

            eventosModal.classList.remove('hidden');
            eventosModal.classList.add('flex');
        };

        const closeEventosModal = () => {
            if (!eventosModal) {
                return;
            }

            eventosModal.classList.add('hidden');
            eventosModal.classList.remove('flex');
        };

        const activationHasDifferences = (data) => data?.hay_diferencias_final === true;
        const activationHasMissingIndividualRecord = (data) => data?.registro_individual_existe === false;

        const fillActivationModal = (data) => {
            if (!activationCodigo || !activationServidor || !activationServidorBadge || !activationBase || !activationSync || !activationFechaInicioActual || !activationFechaFinActual || !activationFechaInicioInput || !activationFechaFinInput) {
                return;
            }

            activationCodigo.textContent = data.codigo || 'N/D';
            activationServidor.textContent = data.servidor || 'Servidor detectado';
            activationServidorBadge.textContent = data.servidor || data.servidor_badge || 'N/D';
            activationBase.textContent = data.base || 'N/D';
            activationSync.textContent = activationHasMissingIndividualRecord(data)
                ? 'No existe registro individual de activación. Se utilizarán las fechas globales como referencia. Al guardar se creará el registro individual.'
                : (activationHasDifferences(data)
                    ? 'Hay diferencias entre la base individual y la tabla global'
                    : 'Base individual y tabla global sincronizadas');
            const eventosMensaje = data?.eventos_licencia?.mensaje || '';
            if (eventosMensaje) {
                activationSync.textContent = `${activationSync.textContent} · ${eventosMensaje}`;
            }
            activationFechaInicioActual.textContent = data.fecha_inicio_actual || 'Sin fecha';
            activationFechaFinActual.textContent = data.fecha_fin_actual || 'Sin fecha';
            activationFechaInicioInput.value = data.fecha_inicio_actual || '';
            activationFechaFinInput.value = data.fecha_fin_actual || '';
        };

        const fillActivationModalHeader = ({ codigo, nit, proforma, empresa }) => {
            if (!activationCodigo) {
                return;
            }

            activationCodigo.textContent = codigo || 'N/D';

            if (activationServidor) {
                activationServidor.textContent = 'Consultando...';
            }

            if (activationServidorBadge) {
                activationServidorBadge.textContent = '...';
            }

            if (activationBase) {
                activationBase.textContent = 'Consultando...';
            }

            if (activationSync) {
                activationSync.textContent = empresa ? `Empresa: ${empresa} · NIT ${nit || 'Sin NIT'}` : nit
                    ? `Proforma ${proforma || 'N/D'} · NIT ${nit}`
                    : `Proforma ${proforma || 'N/D'}`;
            }

            if (activationFechaInicioActual) {
                activationFechaInicioActual.textContent = 'Consultando...';
            }

            if (activationFechaFinActual) {
                activationFechaFinActual.textContent = 'Consultando...';
            }
        };

        const openActivationModal = () => {
            if (!activationModal) {
                return;
            }

            activationModal.classList.remove('hidden');
            activationModal.classList.add('flex');
        };

        const closeActivationModal = () => {
            if (!activationModal || !activationForm || !activationSubmitButton || activationBusy) {
                return;
            }

            activationLoadVersion++;
            activationSearchRequest?.abort();
            window.clearTimeout(activationSearchTimer);
            activationModal.classList.add('hidden');
            activationModal.classList.remove('flex');
            activationForm.dataset.updateUrl = '';
            activationForm.dataset.eventosUpdateUrl = '';
            clearActivationFeedback();
            activationSubmitButton.disabled = false;
            activationSubmitButton.classList.remove('opacity-60', 'cursor-not-allowed');
        };

        const loadActivationData = async ({ showUrl, updateUrl, eventosUpdateUrl = '', codigo = '', proforma = '', nit = '', clienteId = '', empresa = '', global = false }) => {

            if (!showUrl || !updateUrl || !activationForm || !activationSubmitButton) {
                return;
            }

            console.info('[ACTIVACION MODAL]', {
                codigo,
                proforma,
                nit,
                id_cliente: clienteId,
            });

            openActivationModal();
            const loadVersion = ++activationLoadVersion;
            let loaded = false;
            activationSearchPanel?.classList.add('hidden');
            activationForm.classList.remove('hidden');
            activationForm.reset();
            activationForm.dataset.global = global ? '1' : '0';
            activationForm.dataset.loaded = '0';
            activationChangeClient?.classList.toggle('hidden', !global);
            if (activationSelectedClient) {
                activationSelectedClient.textContent = global ? `${codigo || 'Sin código'} · ${empresa} · NIT ${nit || 'Sin NIT'}` : '';
            }
            clearActivationFeedback();
            activationForm.dataset.updateUrl = updateUrl;
            activationForm.dataset.codigo = codigo;
            activationForm.dataset.proformaId = proforma;
            activationForm.dataset.nit = nit;
            activationForm.dataset.clienteId = clienteId;
            activationForm.dataset.eventosUpdateUrl = eventosUpdateUrl;
            fillActivationModalHeader({ codigo, nit, proforma, empresa });
            activationSubmitButton.disabled = true;
            activationSubmitButton.classList.add('opacity-60', 'cursor-not-allowed');
            setActivationFeedback('Consultando valores actuales de activación...', 'warning');

            try {
                const searchParams = new URLSearchParams(activationRequestContext());

                const response = await fetch(global ? showUrl : `${showUrl}?${searchParams.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json();

                if (loadVersion !== activationLoadVersion) return;

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No fue posible consultar la activación.');
                }

                fillActivationModal(payload.data || {});
                loaded = true;
                activationForm.dataset.loaded = '1';

                if (activationHasDifferences(payload.data)) {
                    setActivationFeedback('Se detectó una diferencia entre la base individual y la tabla global. Al guardar quedarán sincronizadas.', 'warning');
                } else {
                    clearActivationFeedback();
                }
            } catch (error) {
                if (loadVersion !== activationLoadVersion) return;
                console.error(error);
                setActivationFeedback(error.message || 'No fue posible consultar la activación.', 'error');
            } finally {
                if (loadVersion === activationLoadVersion) {
                    activationSubmitButton.disabled = !loaded;
                    activationSubmitButton.classList.toggle('opacity-60', !loaded);
                    activationSubmitButton.classList.toggle('cursor-not-allowed', !loaded);
                }
            }
        };

        const openGlobalActivationSearch = () => {
            if (activationBusy || !activationSearchPanel || !activationForm) return;
            activationLoadVersion++;
            activationSearchRequest?.abort();
            window.clearTimeout(activationSearchTimer);
            activationForm.reset();
            activationForm.dataset.loaded = '0';
            activationForm.dataset.updateUrl = '';
            activationForm.classList.add('hidden');
            activationChangeClient.classList.add('hidden');
            activationSelectedClient.textContent = '';
            activationSearchPanel.classList.remove('hidden');
            activationSearchInput.value = '';
            activationSearchResults.replaceChildren();
            activationSearchStatus.textContent = 'Escribe al menos 2 caracteres.';
            openActivationModal();
            activationSearchInput.focus();
        };

        const searchActivationClients = async (term) => {
            const request = new AbortController();
            activationSearchRequest = request;
            activationSearchStatus.textContent = 'Buscando empresas...';
            try {
                const params = new URLSearchParams({ q: term });
                const response = await fetch(`${activationSearchPanel.dataset.searchUrl}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: request.signal,
                });
                const payload = await response.json();
                if (request.signal.aborted || request !== activationSearchRequest) return;
                if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible buscar empresas.');
                activationSearchResults.replaceChildren();
                const clients = payload.data || [];
                activationSearchStatus.textContent = clients.length ? `${clients.length} resultados. Selecciona una empresa.` : 'No se encontraron empresas.';
                clients.forEach((client) => {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'w-full rounded border border-slate-200 px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:ring-2 focus:ring-cyan-500';
                    button.textContent = `Código: ${client.codigo || 'Sin código'} · Empresa: ${client.empresa || 'Sin nombre'} · NIT: ${client.nit || 'Sin NIT'}`;
                    button.addEventListener('click', () => loadActivationData({
                        showUrl: client.show_url,
                        updateUrl: client.update_url,
                        eventosUpdateUrl: client.eventos_url,
                        clienteId: client.id,
                        codigo: client.codigo,
                        empresa: client.empresa,
                        nit: client.nit,
                        global: true,
                    }));
                    item.append(button);
                    activationSearchResults.append(item);
                });
            } catch (error) {
                if (request.signal.aborted || request !== activationSearchRequest) return;
                activationSearchStatus.textContent = error.message || 'No fue posible buscar empresas.';
            }
        };

        activationGlobalButton?.addEventListener('click', openGlobalActivationSearch);
        activationChangeClient?.addEventListener('click', openGlobalActivationSearch);
        activationSearchInput?.addEventListener('input', () => {
            window.clearTimeout(activationSearchTimer);
            activationSearchRequest?.abort();
            activationSearchResults.replaceChildren();
            const term = activationSearchInput.value.trim();
            activationSearchStatus.textContent = term.length < 2 ? 'Escribe al menos 2 caracteres.' : 'Buscando empresas...';
            if (term.length >= 2) activationSearchTimer = window.setTimeout(() => searchActivationClients(term), 300);
        });
        activationSearchInput?.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activationSearchResults.querySelector('button')?.focus();
            }
        });
        activationSearchResults?.addEventListener('keydown', (event) => {
            const buttons = Array.from(activationSearchResults.querySelectorAll('button'));
            const index = buttons.indexOf(event.target);
            if (index >= 0 && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                const next = index + (event.key === 'ArrowDown' ? 1 : -1);
                if (next < 0) activationSearchInput.focus();
                else buttons[Math.min(next, buttons.length - 1)]?.focus();
            }
        });

        const promptEventosActivation = (eventosLicencia) => new Promise((resolve) => {
            if (!eventosModal || !eventosDetalle || !eventosNoButton || !eventosSiButton) {
                resolve(false);
                return;
            }

            const empresa = eventosLicencia?.empresa || activationForm?.dataset.codigo || 'N/D';
            const vencimientoActual = eventosLicencia?.fecha_vencimiento_actual || 'Sin fecha';

            eventosDetalle.textContent = `Empresa: ${empresa} · Vencimiento actual en Eventos: ${vencimientoActual}`;
            openEventosModal();

            const onNo = () => {
                eventosSiButton.removeEventListener('click', onYes);
                closeEventosModal();
                resolve(false);
            };

            const onYes = () => {
                eventosNoButton.removeEventListener('click', onNo);
                closeEventosModal();
                resolve(true);
            };

            eventosNoButton.addEventListener('click', onNo, { once: true });
            eventosSiButton.addEventListener('click', onYes, { once: true });
        });

        const syncEventosActivation = async ({ eventosUpdateUrl, context, fechaFin }) => {
            const response = await fetch(eventosUpdateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ...context,
                    fecha_fin: fechaFin,
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'No fue posible actualizar la licencia de Eventos.');
            }

            return payload;
        };


        const saveActivationDataWithEventos = async () => {
            if (!activationForm || !activationSubmitButton || !activationFechaInicioInput || !activationFechaFinInput) {
                return;
            }
            if (activationBusy || activationForm.dataset.loaded !== '1') return;

            const updateUrl = activationForm.dataset.updateUrl || '';
            const context = activationRequestContext();
            const eventosUpdateUrl = activationForm.dataset.eventosUpdateUrl || '';

            if (!updateUrl) {
                setActivationFeedback('No se encontrÃ³ la ruta para guardar la activaciÃ³n.', 'error');
                return;
            }

            activationBusy = true;
            activationSubmitButton.disabled = true;
            activationSubmitButton.classList.add('opacity-60', 'cursor-not-allowed');
            clearActivationFeedback();

            try {
                const response = await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        ...context,
                        fecha_inicio: activationFechaInicioInput.value,
                        fecha_fin: activationFechaFinInput.value,
                    }),
                });

                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No fue posible guardar la activaciÃ³n.');
                }

                fillActivationModal(payload.data || {});
                setActivationFeedback(payload.message || 'ActivaciÃ³n actualizada correctamente.', 'success');
                showFeedback(payload.message || 'ActivaciÃ³n actualizada correctamente.', 'success');

                if (payload.data?.eventos_licencia?.existe === true && eventosUpdateUrl) {
                    const accepted = await promptEventosActivation(payload.data.eventos_licencia);

                    if (accepted) {
                        const eventosPayload = await syncEventosActivation({
                            eventosUpdateUrl,
                            context,
                            fechaFin: activationFechaFinInput.value,
                        });

                        setActivationFeedback(`${eventosPayload.message || 'La licencia de Eventos se actualizÃ³ correctamente.'} Vencimiento anterior: ${eventosPayload.data?.fecha_vencimiento_anterior || 'Sin fecha'} Â· nuevo: ${eventosPayload.data?.fecha_vencimiento_nueva || activationFechaFinInput.value}.`, 'success');
                        showFeedback(eventosPayload.message || 'La licencia de Eventos se actualizÃ³ correctamente.', 'success');
                    }
                }
            } catch (error) {
                console.error(error);
                setActivationFeedback(error.message || 'No fue posible guardar la activaciÃ³n.', 'error');
            } finally {
                activationBusy = false;
                activationSubmitButton.disabled = false;
                activationSubmitButton.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        };

        const updateRowEnvio = (row, enviado, fechaEnvio = null) => {
            row.dataset.enviado = String(enviado);

            const badge = row.querySelector('[data-envio-badge]');
            if (badge) {
                const isEnviado = Number(enviado) === 1;
                badge.textContent = isEnviado ? badge.dataset.labelEnviada : badge.dataset.labelNoEnviada;
                badge.classList.remove(...(badge.dataset.classEnviada || '').split(' ').filter(Boolean));
                badge.classList.remove(...(badge.dataset.classNoEnviada || '').split(' ').filter(Boolean));
                badge.classList.add(...((isEnviado ? badge.dataset.classEnviada : badge.dataset.classNoEnviada) || '').split(' ').filter(Boolean));
            }

            if (fechaEnvio !== undefined) {
                row.dataset.fechaEnvio = fechaEnvio || '';
            }

            const hasEnvioFilter = activeEnvioFilter !== null && activeEnvioFilter !== '';
            if (hasEnvioFilter && String(activeEnvioFilter) !== String(enviado)) {
                row.remove();
            }
        };

        const updateRowState = (row, nuevoEstado) => {
            row.dataset.estado = String(nuevoEstado);
            const badge = row.querySelector('[data-estado-badge]');
            if (!badge) {
                return;
            }

            const map = {
                [ESTADO_GENERADA]: {
                    label: badge.dataset.labelGenerada,
                    style: badge.dataset.styleGenerada,
                },
                [ESTADO_ENVIADA]: {
                    label: badge.dataset.labelEnviada,
                    style: badge.dataset.styleEnviada,
                },
                [ESTADO_PAGADA]: {
                    label: badge.dataset.labelPagada,
                    style: badge.dataset.stylePagada,
                },
                [ESTADO_FACTURADA]: {
                    label: badge.dataset.labelFacturada,
                    style: badge.dataset.styleFacturada,
                },
            };

            const estadoInfo = map[nuevoEstado];
            if (!estadoInfo) {
                return;
            }

            badge.textContent = estadoInfo.label;
            badge.setAttribute('style', estadoInfo.style);

            const hasEstadoFilter = activeEstadoFilter !== null && activeEstadoFilter !== '';
            if (!hasEstadoFilter) {
                return;
            }

            if (String(activeEstadoFilter) !== String(nuevoEstado)) {
                row.remove();
            }
        };

        const runCorreoAction = async (row) => {
            const url = row.dataset.enviarUrl;
            if (!url) {
                return;
            }

            const isReenvio = Number(row.dataset.enviado || 0) === 1;
            const successMessage = isReenvio
                ? 'Proforma reenviada por correo correctamente.'
                : 'Proforma enviada por correo correctamente.';
            const errorMessage = isReenvio
                ? 'No se pudo reenviar el correo.'
                : 'No se pudo enviar el correo.';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || errorMessage);
                }

                updateRowEnvio(
                    row,
                    Number(payload.proforma?.enviado ?? 1),
                    payload.proforma?.fecha_envio ?? null,
                );

                if (payload.proforma?.estado !== undefined) {
                    updateRowState(row, Number(payload.proforma.estado));
                }

                showFeedback(payload.message || successMessage, 'success');
            } catch (error) {
                console.error(error);
                showFeedback(error.message || errorMessage, 'error');
            }
        };

        const runEnvioAction = async (row, action) => {
            const isMarking = action === 'marcar';
            const url = isMarking ? row.dataset.marcarEnviadaUrl : row.dataset.marcarNoEnviadaUrl;

            if (!url) {
                return;
            }

            if (isMarking && !window.confirm(confirmManualEnvioMessage)) {
                return;
            }

            if (!isMarking && !window.confirm(confirmManualNoEnvioMessage)) {
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No se pudo actualizar el envío.');
                }

                updateRowEnvio(
                    row,
                    Number(payload.proforma?.enviado ?? (isMarking ? 1 : 0)),
                    payload.proforma?.fecha_envio ?? null,
                );
                if (payload.proforma?.estado !== undefined) {
                    updateRowState(row, Number(payload.proforma.estado));
                }

                showFeedback(payload.message || 'Envío actualizado correctamente.', 'success');
            } catch (error) {
                console.error(error);
                showFeedback(error.message || 'No se pudo actualizar el envío.', 'error');
            }
        };

        const runAction = async (row, estadoDestino, metodoPago = null) => {
            const url = row.dataset.updateUrl;
            if (!url) {
                return false;
            }

            try {
                const requestPayload = {
                    estado: estadoDestino,
                    redirect_to: 'index',
                };

                if (estadoDestino === ESTADO_PAGADA) {
                    requestPayload.fpago = metodoPago;
                }

                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(requestPayload),
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No se pudo actualizar el estado.');
                }

                updateRowState(row, Number(payload.to || estadoDestino));

                showFeedback(payload.message || 'Estado actualizado correctamente.', 'success');
                return true;
            } catch (error) {
                console.error(error);
                showFeedback(error.message || 'No se pudo actualizar el estado.', 'error');
                return false;
            }
        };

        const runPaymentAction = async (row, metodoPago, comprobante) => {
            const url = row.dataset.updateUrl;
            if (!url) {
                return false;
            }

            const requestData = new FormData();
            requestData.append('_method', 'PATCH');
            requestData.append('estado', String(ESTADO_PAGADA));
            requestData.append('redirect_to', 'index');
            requestData.append('fpago', metodoPago);

            if (comprobante) {
                requestData.append('comprobante_pago', comprobante);
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: requestData,
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No se pudo actualizar el estado.');
                }

                row.dataset.hasComprobante = payload.comprobante_url ? '1' : '0';
                if (payload.comprobante_url) {
                    row.dataset.comprobanteUrl = payload.comprobante_url;
                }

                updateRowState(row, Number(payload.to || ESTADO_PAGADA));
                showFeedback(payload.message || 'Estado actualizado correctamente.', 'success');

                return true;
            } catch (error) {
                console.error(error);
                showFeedback(error.message || 'No se pudo actualizar el estado.', 'error');
                return false;
            }
        };

        tableRows.forEach((row) => {
            row.addEventListener('contextmenu', (event) => {
                event.preventDefault();
                showMenu(event.clientX, event.clientY, row);
            });

            const button = row.querySelector('[data-proforma-actions]');
            button?.addEventListener('click', (event) => {
                event.preventDefault();
                const rect = button.getBoundingClientRect();
                showMenu(rect.left, rect.bottom + 6, row);
            });
        });

        menu.addEventListener('click', async (event) => {
            const targetButton = event.target.closest('button[data-target-state], button[data-envio-action], button[data-correo-action], button[data-activacion-action]');
            if (!targetButton || !currentRow) {
                return;
            }

            const row = currentRow;
            hideMenu();

            if (targetButton.dataset.envioAction) {
                await runEnvioAction(row, targetButton.dataset.envioAction);
                return;
            }

            if (targetButton.dataset.correoAction) {
                await runCorreoAction(row);
                return;
            }

            if (targetButton.dataset.activacionAction) {
                await loadActivationData({
                    showUrl: row.dataset.activacionShowUrl,
                    updateUrl: row.dataset.activacionUpdateUrl,
                    eventosUpdateUrl: row.dataset.activacionEventosUpdateUrl,
                    codigo: row.dataset.codigo,
                    proforma: row.dataset.proformaId,
                    nit: row.dataset.nit,
                    clienteId: row.dataset.clienteId,
                });
                return;
            }

            const estadoDestino = Number(targetButton.dataset.targetState);

            if (estadoDestino === ESTADO_PAGADA) {
                openPaymentModal(row);
                return;
            }

            await runAction(row, estadoDestino);

        });

        [paymentCloseTopButton, paymentCancelButton].forEach((button) => {
            button?.addEventListener('click', closePaymentModal);
        });

        paymentModal?.addEventListener('click', (event) => {
            if (event.target === paymentModal) {
                closePaymentModal();
            }
        });

        paymentForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (paymentSubmitting || !pendingPaymentRow || !paymentMethod || !paymentConfirmButton) {
                return;
            }

            if (!paymentMethod.value) {
                paymentMethod.reportValidity();
                return;
            }

            const receiptRequired = paymentMethod.value === 'TRANSFERENCIA' || paymentMethod.value === 'CONSIGNACIÓN';
            const receiptFile = paymentReceipt?.files?.[0] || null;

            if (receiptRequired && !receiptFile) {
                paymentReceipt?.reportValidity();
                return;
            }

            paymentSubmitting = true;
            paymentConfirmButton.disabled = true;
            paymentConfirmButton.textContent = 'Confirmando...';

            const updated = await runPaymentAction(pendingPaymentRow, paymentMethod.value, receiptFile);

            paymentSubmitting = false;
            paymentConfirmButton.disabled = false;
            paymentConfirmButton.textContent = 'Confirmar pago';

            if (updated) {
                closePaymentModal();
                return;
            }

            if (paymentFeedback) {
                paymentFeedback.textContent = 'No se pudo confirmar el pago. Revisa la información e intenta nuevamente.';
                paymentFeedback.classList.remove('hidden');
            }
        });

        const syncPaymentReceiptRequirement = () => {
            if (!paymentMethod || !paymentReceiptContainer || !paymentReceipt) {
                return;
            }

            const required = paymentMethod.value === 'TRANSFERENCIA' || paymentMethod.value === 'CONSIGNACIÓN';
            paymentReceipt.required = required;
            paymentReceiptContainer.classList.toggle('hidden', !required);

            if (!required) {
                paymentReceipt.value = '';
                updatePaymentReceiptName();
            }
        };

        paymentMethod?.addEventListener('change', syncPaymentReceiptRequirement);
        paymentReceiptOpenButton?.addEventListener('click', openPaymentReceiptPicker);
        paymentReceipt?.addEventListener('change', finishOpeningPaymentReceipt);
        paymentReceipt?.addEventListener('cancel', finishOpeningPaymentReceipt);

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                hideMenu();
            }
        });

        window.addEventListener('scroll', hideMenu, true);
        window.addEventListener('resize', hideMenu);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && paymentModal?.classList.contains('flex')) {
                closePaymentModal();
            }
        });

        [activationCloseTopButton, activationCloseButton].forEach((button) => {
            button?.addEventListener('click', closeActivationModal);
        });

        activationModal?.addEventListener('click', (event) => {
            if (event.target === activationModal) {
                closeActivationModal();
            }
        });

        activationForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            await saveActivationDataWithEventos();
        });
    })();
</script>
<script>
    (() => {
        const triggerButtons = Array.from(document.querySelectorAll('[data-envio-grupo]'));
        const modal = document.getElementById('envio-masivo-modal');
        const modalForm = document.getElementById('envio-masivo-form');
        const closeTopButton = document.getElementById('envio-masivo-cerrar-superior');
        const closeButton = document.getElementById('envio-masivo-cerrar');
        const title = document.getElementById('envio-masivo-titulo');
        const subtitle = document.getElementById('envio-masivo-subtitulo');
        const total = document.getElementById('envio-masivo-total');
        const validas = document.getElementById('envio-masivo-validas');
        const omitidas = document.getElementById('envio-masivo-omitidas');
        const seleccionadas = document.getElementById('envio-masivo-seleccionadas');
        const listado = document.getElementById('envio-masivo-listado');
        const hiddenMes = document.getElementById('envio-masivo-mes');
        const hiddenAnio = document.getElementById('envio-masivo-anio');
        const selectAll = document.getElementById('envio-masivo-seleccionar-todas');
        const feedback = document.getElementById('envio-masivo-feedback');
        const submitButton = document.getElementById('envio-masivo-submit');
        const mesInput = document.getElementById('mes');
        const anioInput = document.getElementById('anio');

        if (triggerButtons.length === 0 || !modal || !modalForm || !closeTopButton || !closeButton || !title || !subtitle || !total || !validas || !omitidas || !seleccionadas || !listado || !hiddenMes || !hiddenAnio || !selectAll || !feedback || !submitButton) {
            return;
        }

        let currentGrupo = null;

        const openModal = () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            currentGrupo = null;
            feedback.classList.add('hidden');
            feedback.textContent = '';
            feedback.classList.remove('border-rose-200', 'bg-rose-50', 'text-rose-700', 'border-amber-200', 'bg-amber-50', 'text-amber-700');
        };

        const currentPeriodo = () => ({
            mes: mesInput?.value || @json($filters['mes'] ?? null) || '',
            anio: anioInput?.value || @json($filters['anio'] ?? null) || '',
        });

        const checkedBoxes = () => Array.from(listado.querySelectorAll('input[name="proformas[]"]:checked'));
        const allBoxes = () => Array.from(listado.querySelectorAll('input[name="proformas[]"]'));

        const syncSelectionCounter = () => {
            const totalBoxes = allBoxes();
            const selectedBoxes = checkedBoxes();
            seleccionadas.textContent = String(selectedBoxes.length);
            selectAll.checked = totalBoxes.length > 0 && selectedBoxes.length === totalBoxes.length;
            selectAll.indeterminate = selectedBoxes.length > 0 && selectedBoxes.length < totalBoxes.length;
            submitButton.disabled = selectedBoxes.length === 0;
            submitButton.classList.toggle('opacity-60', selectedBoxes.length === 0);
            submitButton.classList.toggle('cursor-not-allowed', selectedBoxes.length === 0);
        };

        const renderRows = (items) => {
            if (items.length === 0) {
                listado.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay proformas validas para enviar con ese grupo y periodo.</td></tr>';
                syncSelectionCounter();
                return;
            }

            listado.innerHTML = items.map((item) => `
                <tr>
                    <td class="px-4 py-3">
                        <input type="checkbox" name="proformas[]" value="${item.id}" checked class="envio-proforma-checkbox rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-800">${item.nro_prof || '#' + item.id}</p>
                        <p class="text-xs text-slate-500">ID ${item.id}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-700">${item.empresa || 'N/D'}</td>
                    <td class="px-4 py-3 text-slate-700">${item.email || 'Sin correo'}</td>
                    <td class="px-4 py-3 text-slate-700">${item.fecha_arriendo || 'N/D'}</td>
                </tr>
            `).join('');

            allBoxes().forEach((checkbox) => {
                checkbox.addEventListener('change', syncSelectionCounter);
            });

            syncSelectionCounter();
        };

        const showFeedback = (message, type = 'warning') => {
            feedback.textContent = message;
            feedback.classList.remove('hidden', 'border-rose-200', 'bg-rose-50', 'text-rose-700', 'border-amber-200', 'bg-amber-50', 'text-amber-700');
            feedback.classList.add(...(type === 'error'
                ? ['border-rose-200', 'bg-rose-50', 'text-rose-700']
                : ['border-amber-200', 'bg-amber-50', 'text-amber-700']));
        };

        const loadResumen = async (button) => {
            const grupo = button.dataset.envioGrupo;
            const confirmarUrl = button.dataset.confirmarUrl;
            const enviarUrl = button.dataset.enviarUrl;
            const periodo = currentPeriodo();
            const searchParams = new URLSearchParams();

            if (periodo.mes !== '') {
                searchParams.set('mes', periodo.mes);
            }

            if (periodo.anio !== '') {
                searchParams.set('anio', periodo.anio);
            }

            currentGrupo = grupo;
            title.textContent = `Envio masivo grupo ${grupo}`;
            subtitle.textContent = `Periodo ${periodo.mes || '-'} / ${periodo.anio || '-'}`;
            total.textContent = '...';
            validas.textContent = '...';
            omitidas.textContent = '...';
            seleccionadas.textContent = '0';
            hiddenMes.value = periodo.mes;
            hiddenAnio.value = periodo.anio;
            modalForm.action = enviarUrl;
            listado.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Cargando listado...</td></tr>';
            selectAll.checked = true;
            openModal();

            try {
                const response = await fetch(`${confirmarUrl}?${searchParams.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'No se pudo cargar el resumen del envio masivo.');
                }

                const resumen = payload.resumen || {};
                title.textContent = `Envio masivo grupo ${payload.grupo}`;
                subtitle.textContent = `Periodo ${payload.periodo?.mes || '-'} / ${payload.periodo?.anio || '-'}`;
                total.textContent = String(resumen.total_encontradas || 0);
                validas.textContent = String(resumen.validas_count || 0);
                omitidas.textContent = String(resumen.omitidas_count || 0);
                hiddenMes.value = payload.periodo?.mes || '';
                hiddenAnio.value = payload.periodo?.anio || '';

                if ((resumen.omitidas_count || 0) > 0) {
                    const omitidasPorMotivo = resumen.omitidas_por_motivo || {};
                    showFeedback(`Omitidas: sin correo ${omitidasPorMotivo.sin_correo || 0}, sin PDF ${omitidasPorMotivo.sin_pdf || 0}, ya enviadas ${omitidasPorMotivo.ya_enviadas || 0}, no generadas ${omitidasPorMotivo.no_generadas || 0}.`);
                } else {
                    feedback.classList.add('hidden');
                }

                renderRows(resumen.validas || []);
            } catch (error) {
                listado.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-rose-600">No fue posible cargar el listado.</td></tr>';
                showFeedback(error.message || 'No fue posible cargar el listado.', 'error');
                syncSelectionCounter();
            }
        };

        triggerButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                loadResumen(button);
            });
        });

        selectAll.addEventListener('change', function () {
            allBoxes().forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });

            syncSelectionCounter();
        });

        [closeTopButton, closeButton].forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        modalForm.addEventListener('submit', (event) => {
            if (checkedBoxes().length > 0) {
                return;
            }

            event.preventDefault();
            showFeedback('Selecciona al menos una proforma antes de enviar.', 'error');
        });
    })();
</script>
@endpush

@include('partials.nota-cobro-script')
