@extends('layouts.admin')

@section('title', 'Listado de Proformas')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Listado de Proformas</h1>
            <p class="text-sm text-slate-600">Consulta administrativa sobre <code>sg_proform</code>.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 7, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" data-envio-grupo="7" data-confirmar-url="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 7]) }}" data-enviar-url="{{ route('proformas.envio-masivo.enviar', ['grupo' => 7]) }}" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200">
                Enviar grupo 7
            </a>
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 27, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" data-envio-grupo="27" data-confirmar-url="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 27]) }}" data-enviar-url="{{ route('proformas.envio-masivo.enviar', ['grupo' => 27]) }}" class="inline-flex items-center rounded bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200">
                Enviar grupo 27
            </a>
            <a href="{{ route('proformas.dashboard') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Ver dashboard
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
        <form id="proformas-filter-form" method="GET" action="{{ route('proformas.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-11">
            <div>
                <label for="nro_prof" class="mb-1 block text-sm font-medium">Número</label>
                <input id="nro_prof" name="nro_prof" value="{{ $filters['nro_prof'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="codigo" class="mb-1 block text-sm font-medium">Código</label>
                <input id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="nit" class="mb-1 block text-sm font-medium">NIT</label>
                <input id="nit" name="nit" value="{{ $filters['nit'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="empresa" class="mb-1 block text-sm font-medium">Empresa</label>
                <input id="empresa" name="empresa" value="{{ $filters['empresa'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="emisora" class="mb-1 block text-sm font-medium">Emisora</label>
                <input id="emisora" name="emisora" value="{{ $filters['emisora'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
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
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoLabel)
                        <option value="{{ $estadoCodigo }}" @selected((string) ($filters['estado'] ?? '') === (string) $estadoCodigo)>{{ $estadoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="envio" class="mb-1 block text-sm font-medium">Envío</label>
                <select id="envio" name="envio" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="1" @selected((string) ($filters['envio'] ?? '') === '1')>Enviada</option>
                    <option value="0" @selected((string) ($filters['envio'] ?? '') === '0')>No enviada</option>
                </select>
            </div>
            <div>
                <label for="filtro_nota" class="mb-1 block text-sm font-medium">Nota</label>
                <select id="filtro_nota" name="filtro_nota" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    <option value="con" @selected((string) ($filters['filtro_nota'] ?? '') === 'con')>Con nota</option>
                    <option value="sin" @selected((string) ($filters['filtro_nota'] ?? '') === 'sin')>Sin nota</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('proformas.clear-filters') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2">Número</th>
                    <th class="px-3 py-2">Código</th>
                    <th class="px-3 py-2">Empresa</th>
                    <th class="px-3 py-2">Periodo</th>
                    <th class="px-3 py-2 text-right">Valor total</th>
                    <th class="px-3 py-2 text-center">Nota</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Envío</th>
                    <th class="px-3 py-2 text-right">Acción</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($proformas as $proforma)
                    @php
                        $estadoCodigo = (int) ($proforma->estado ?? 0);
                        $estado = $proformasService->estadoLabel($proforma->estado);
                        $envioEstado = $proformasService->envioLabel($proforma->enviado ?? 0);
                        $envioClasses = $proformasService->envioBadgeClass($proforma->enviado ?? 0);
                        $notaCobro = trim((string) ($proforma->nota_cobro ?? ''));
                        $notaResumen = $notaCobro !== '' ? \Illuminate\Support\Str::limit($notaCobro, 50) : 'Sin nota de cobro';
                        $clientePotencialId = (int) ($proforma->cliente_potencial_id ?? 0);
                    @endphp
                    <tr
                        class="hover:bg-slate-50"
                        data-proforma-row
                        data-proforma-id="{{ $proforma->id }}"
                        data-estado="{{ $estadoCodigo }}"
                        data-update-url="{{ route('proformas.estado.update', $proforma->id) }}"

                        data-pdf-url="{{ route('proformas.pdf.show', $proforma->id) }}"

                    >
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
                        <td class="px-3 py-2 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($clientePotencialId > 0)
                                <button
                                    type="button"
                                    class="nota-cobro-btn inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-base transition hover:bg-slate-100 {{ $notaCobro !== '' ? 'text-amber-600' : 'text-slate-400' }}"
                                    data-cliente-id="{{ $clientePotencialId }}"
                                    data-cliente-nombre="{{ $proforma->emp ?: 'Sin nombre' }}"
                                    data-nota="{{ $notaCobro }}"
                                    title="{{ $notaResumen }}"
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
                                data-label-pagada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                                data-label-facturada="{{ $proformasService->estadoLabel(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                                data-style-generada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_GENERADA) }}"
                                data-style-pagada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_PAGADA) }}"
                                data-style-facturada="{{ $proformasService->estadoBadgeStyle(\App\Services\ProformasService::ESTADO_FACTURADA) }}"
                                style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}"
                            >{{ $estado }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $envioClasses }}">{{ $envioEstado }}</span>
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
                        <td colspan="9" class="px-4 py-8 text-center text-slate-500">No hay proformas para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $proformas->links() }}
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
<script>
    (() => {
        const ESTADO_GENERADA = {{ \App\Services\ProformasService::ESTADO_GENERADA }};
        const ESTADO_ENVIADA = {{ \App\Services\ProformasService::ESTADO_ENVIADA }};
        const ESTADO_PAGADA = {{ \App\Services\ProformasService::ESTADO_PAGADA }};
        const ESTADO_FACTURADA = {{ \App\Services\ProformasService::ESTADO_FACTURADA }};
        const csrfToken = @json(csrf_token());
        const activeEstadoFilter = @json($filters['estado'] ?? null);

        const tableRows = Array.from(document.querySelectorAll('[data-proforma-row]'));
        const menu = document.getElementById('proforma-context-menu');
        const menuItems = document.getElementById('proforma-context-menu-items');

        if (!menu || !menuItems || tableRows.length === 0) {
            return;
        }

        let currentRow = null;

        let feedbackTimeout = null;

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


        const hideMenu = () => {
            menu.classList.add('pointer-events-none', 'opacity-0', 'scale-95');
            menu.classList.remove('opacity-100', 'scale-100');
            currentRow = null;
        };

        const showMenu = (x, y, row) => {

            const acciones = getActionsForState(Number(row.dataset.estado || 0), row.dataset.pdfUrl || '');

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
                    <button type="button" class="w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100" data-target-state="${accion.estado}">
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


        const getActionsForState = (estadoActual, pdfUrl) => {
            const acciones = [];
            if (pdfUrl) {
                acciones.push({ type: 'link', label: 'Ver PDF', url: pdfUrl });
            }

            if (estadoActual === ESTADO_GENERADA || estadoActual === ESTADO_ENVIADA) {
                acciones.push({ type: 'estado', estado: ESTADO_PAGADA, label: 'Marcar pagada' });
            }

            if (estadoActual === ESTADO_PAGADA) {
                acciones.push({ type: 'estado', estado: ESTADO_FACTURADA, label: 'Marcar facturada' });
            }

            return acciones;

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

        const runAction = async (row, estadoDestino) => {
            const url = row.dataset.updateUrl;
            if (!url) {
                return;
            }

            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        estado: estadoDestino,
                        redirect_to: 'index',
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'No se pudo actualizar el estado.');
                }

                updateRowState(row, Number(payload.to || estadoDestino));

                showFeedback(payload.message || 'Estado actualizado correctamente.', 'success');
            } catch (error) {
                console.error(error);
                showFeedback(error.message || 'No se pudo actualizar el estado.', 'error');

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
            const targetButton = event.target.closest('button[data-target-state]');
            if (!targetButton || !currentRow) {
                return;
            }

            const estadoDestino = Number(targetButton.dataset.targetState);

            const row = currentRow;
            hideMenu();
            await runAction(row, estadoDestino);

        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                hideMenu();
            }
        });

        window.addEventListener('scroll', hideMenu, true);
        window.addEventListener('resize', hideMenu);
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
