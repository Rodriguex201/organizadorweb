@extends('layouts.admin')

@section('title', 'Cobros')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    @php
        $proformasListasParaEnvio = session('cobros.proformas_listas_para_envio');
        $proformasListas = is_array($proformasListasParaEnvio['proformas'] ?? null) ? $proformasListasParaEnvio['proformas'] : [];
        $grupoListoParaEnvio = (int) ($proformasListasParaEnvio['grupo'] ?? 0);
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
            <form method="POST" action="{{ route('cobros.proformas-masivo', ['grupo' => 7]) }}">
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
                <button type="submit" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200">
                    Generar proformas grupo 7
                </button>
            </form>

            <form method="POST" action="{{ route('cobros.proformas-masivo', ['grupo' => 27]) }}">
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
                <button type="submit" class="inline-flex items-center rounded bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200">
                    Generar proformas grupo 27
                </button>
            </form>

            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Proformas Generadas
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{
            session('status_type') === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-amber-200 bg-amber-50 text-amber-800'
        }}">
            {{ session('status') }}
        </div>
    @endif

    @if($proformasListas !== [] && in_array($grupoListoParaEnvio, [7, 27], true))
        <div class="mb-4 rounded border border-cyan-200 bg-cyan-50 px-4 py-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-cyan-800">
                        Se generaron o actualizaron {{ count($proformasListas) }} proformas del grupo {{ $grupoListoParaEnvio }}.
                    </p>
                    <p class="text-sm text-cyan-700">
                        Desea enviarlas ahora a los correos registrados?
                    </p>
                </div>

                <form method="POST" action="{{ route('cobros.proformas-masivo.enviar', ['grupo' => $grupoListoParaEnvio]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">
                        Si, enviar correos
                    </button>
                </form>
            </div>

            <p class="mt-3 text-xs text-cyan-700">
                Empresas listas: {{ collect($proformasListas)->pluck('empresa')->take(8)->implode(', ') }}{{ count($proformasListas) > 8 ? '...' : '' }}
            </p>
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
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('cobros.index') }}" class="bg-slate-200 text-slate-700 px-4 py-2 rounded hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

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

@include('partials.nota-cobro-modal')
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
