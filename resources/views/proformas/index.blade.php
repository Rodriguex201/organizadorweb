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
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 7, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200">
                Enviar grupo 7
            </a>
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 27, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200">
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
        <form method="GET" action="{{ route('proformas.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-9">
            <div>
                <label for="nro_prof" class="mb-1 block text-sm font-medium">Número</label>
                <input id="nro_prof" name="nro_prof" value="{{ request('nro_prof', session('proformas.numero')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="codigo" class="mb-1 block text-sm font-medium">Código</label>
                <input id="codigo" name="codigo" value="{{ request('codigo', session('proformas.codigo')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="nit" class="mb-1 block text-sm font-medium">NIT</label>
                <input id="nit" name="nit" value="{{ request('nit', session('proformas.nit')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="empresa" class="mb-1 block text-sm font-medium">Empresa</label>
                <input id="empresa" name="empresa" value="{{ request('empresa', session('proformas.empresa')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="emisora" class="mb-1 block text-sm font-medium">Emisora</label>
                <input id="emisora" name="emisora" value="{{ request('emisora', session('proformas.emisora')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((string) request('mes', session('proformas.mes')) === (string) $mesNumero || (string) request('mes', session('proformas.mes')) === $mesNombre)>
                            {{ ucfirst($mesNombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ request('anio', session('proformas.anio')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoLabel)
                        <option value="{{ $estadoCodigo }}" @selected((string) request('estado', session('proformas.estado')) === (string) $estadoCodigo)>{{ $estadoLabel }}</option>
                    @endforeach
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
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">No hay proformas para los filtros seleccionados.</td>
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

<div
    id="proforma-context-menu"
    class="pointer-events-none fixed z-50 min-w-48 origin-top-left scale-95 rounded-md border border-slate-200 bg-white p-1 opacity-0 shadow-lg transition duration-150"
>
    <ul id="proforma-context-menu-items" class="space-y-1"></ul>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const ESTADO_GENERADA = {{ \App\Services\ProformasService::ESTADO_GENERADA }};
        const ESTADO_PAGADA = {{ \App\Services\ProformasService::ESTADO_PAGADA }};
        const ESTADO_FACTURADA = {{ \App\Services\ProformasService::ESTADO_FACTURADA }};
        const csrfToken = @json(csrf_token());
        const activeEstadoFilter = @json(request('estado', session('proformas.estado')));

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

            if (estadoActual === ESTADO_GENERADA) {
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
@endpush
