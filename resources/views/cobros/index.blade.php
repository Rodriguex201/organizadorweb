@extends('layouts.admin')

@section('title', 'Cobros')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Módulo Cobros</h1>
            <p class="text-sm text-slate-600">Listado inicial desde <code>valores_externos</code> con datos de clientes potenciales.</p>
        </div>
        <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
            Proformas Generadas
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form id="cobros-filter-form" method="GET" action="{{ route('cobros.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
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

            <input type="hidden" name="orden_fecha" value="{{ $filters['orden_fecha'] ?? '' }}">

            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('cobros.index') }}" class="bg-slate-200 text-slate-700 px-4 py-2 rounded hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
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
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $fechaArriendoFormateada }}</td>
                        <td class="px-4 py-3 font-medium">{{ $cobro->codigo ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cobro->nombre ?: 'Sin nombre' }}</td>
                        <td class="px-4 py-3">{{ $cobro->regimen ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format((float) ($cobro->valor_total ?? 0), 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($clienteId > 0)
                                <button
                                    type="button"
                                    class="nota-cobro-btn inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-base transition hover:bg-slate-100 {{ $notaCobro !== '' ? 'text-amber-600' : 'text-slate-400' }}"
                                    data-cliente-id="{{ $clienteId }}"
                                    data-cliente-nombre="{{ $cobro->nombre ?: 'Sin nombre' }}"
                                    data-nota="{{ $notaCobro }}"
                                    title="{{ $notaResumen }}"
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
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay cobros disponibles para los filtros seleccionados.</td>
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

<div id="nota-cobro-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="text-base font-semibold">Nota de cobro</h2>
            <button id="nota-cobro-cancelar-top" type="button" class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Cerrar modal">✕</button>
        </div>
        <div class="space-y-3 px-4 py-4">
            <p id="nota-cobro-cliente" class="text-sm text-slate-600"></p>
            <textarea
                id="nota-cobro-textarea"
                rows="6"
                maxlength="2000"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="Escribe una nota de cobro para este cliente..."
            ></textarea>
            <p id="nota-cobro-feedback" class="hidden text-sm"></p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <button id="nota-cobro-limpiar" type="button" class="rounded bg-rose-100 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200">Limpiar</button>
            <button id="nota-cobro-cancelar" type="button" class="rounded bg-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</button>
            <button id="nota-cobro-guardar" type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
            selectedButton.title = resumenNota(nota || '');
            selectedButton.classList.toggle('text-amber-600', hasNota);
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
