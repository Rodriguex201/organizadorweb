@extends('layouts.admin')

@section('title', 'Cartera pendiente de proformas')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $isPorCobrarMode ? 'Cartera por Cobrar' : 'Cartera pendiente' }}</h1>
            <p class="text-sm text-slate-600">
                @if($isPorCobrarMode)
                    Empresas con proformas en estado <code>Enviada - Pago Pendiente</code>, sin limitarse al periodo seleccionado.
                @else
                    Empresas con proformas pendientes en estados <code>Generada</code> y <code>Enviada</code>.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Ir al listado
            </a>
            <a href="{{ route('proformas.dashboard') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Ver Informe
            </a>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">{{ $isPorCobrarMode ? 'Empresas con saldo pendiente' : 'Empresas con deuda' }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format((int) $summary['empresas_con_deuda'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">{{ $isPorCobrarMode ? 'Total cartera por cobrar' : 'Total cartera' }}</p>
            <p class="mt-1 text-2xl font-bold text-rose-700">$ {{ number_format((float) $summary['total_cartera'], 2, ',', '.') }}</p>
        </div>
        @unless($isPorCobrarMode)
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Promedio deuda</p>
            <p class="mt-1 text-2xl font-bold text-amber-700">$ {{ number_format((float) $summary['promedio_deuda'], 2, ',', '.') }}</p>
        </div>
        @endunless
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Proformas pendientes</p>
            <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format((int) $summary['cantidad_proformas_pendientes'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <form method="GET" action="{{ route('proformas.cartera.index') }}" class="grid flex-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
                @if($isPorCobrarMode)
                    <input type="hidden" name="modo" value="por_cobrar">
                @endif
                <div>
                    <label for="codigo" class="mb-1 block text-sm font-medium">Codigo</label>
                    <input id="codigo" name="codigo" value="{{ $filters['codigo'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="empresa" class="mb-1 block text-sm font-medium">Empresa</label>
                    <input id="empresa" name="empresa" value="{{ $filters['empresa'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="nit" class="mb-1 block text-sm font-medium">NIT</label>
                    <input id="nit" name="nit" value="{{ $filters['nit'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                @unless($isPorCobrarMode)
                <div>
                    <label for="fecha_desde" class="mb-1 block text-sm font-medium">Fecha desde</label>
                    <input id="fecha_desde" name="fecha_desde" type="date" value="{{ $filters['fecha_desde'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="fecha_hasta" class="mb-1 block text-sm font-medium">Fecha hasta</label>
                    <input id="fecha_hasta" name="fecha_hasta" type="date" value="{{ $filters['fecha_hasta'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                    <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Todos</option>
                        @foreach($estados as $estadoCodigo => $estadoNombre)
                            <option value="{{ $estadoCodigo }}" @selected((string) ($filters['estado'] ?? '') === (string) $estadoCodigo)>{{ $estadoNombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 rounded border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" name="solo_acumuladas" value="1" @checked($filters['solo_acumuladas'] ?? false) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Solo acumuladas
                    </label>
                </div>
                @endunless
                <div class="flex items-end gap-2 xl:col-span-2">
                    <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                    <a href="{{ $isPorCobrarMode ? route('proformas.cartera.index', ['modo' => 'por_cobrar']) : route('proformas.cartera.index') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Limpiar</a>
                </div>
            </form>

            <form method="POST" action="{{ route('proformas.cartera.export') }}" class="shrink-0">
                @csrf
                @foreach($exportFilters as $key => $value)
                    @if(is_array($value))
                        @continue
                    @endif
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Exportar Excel
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto rounded-lg">
            <table class="{{ $isPorCobrarMode ? 'min-w-full' : 'min-w-[1400px]' }} text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    @if($isPorCobrarMode)
                    <th class="whitespace-nowrap px-4 py-3">Empresa</th>
                    <th class="whitespace-nowrap px-4 py-3">NIT</th>
                    <th class="whitespace-nowrap px-4 py-3 text-center">Proformas pendientes</th>
                    <th class="whitespace-nowrap px-4 py-3 text-right">Valor acumulado pendiente</th>
                    <th class="whitespace-nowrap px-4 py-3">Periodo más antiguo</th>
                    <th class="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                    @else
                    <th class="whitespace-nowrap px-4 py-3">Codigo</th>
                    <th class="whitespace-nowrap px-4 py-3">Empresa</th>
                    <th class="whitespace-nowrap px-4 py-3">NIT</th>
                    <th class="whitespace-nowrap px-4 py-3">Email</th>
                    <th class="whitespace-nowrap px-4 py-3">Celular</th>
                    <th class="whitespace-nowrap px-4 py-3 text-center">Meses pendientes</th>
                    <th class="whitespace-nowrap px-4 py-3 text-center">Cantidad proformas</th>
                    <th class="whitespace-nowrap px-4 py-3 text-right">Valor total deuda</th>
                    <th class="whitespace-nowrap px-4 py-3">Ultima proforma</th>
                    <th class="whitespace-nowrap px-4 py-3">Estado actual</th>
                    <th class="whitespace-nowrap px-4 py-3 text-center">Dias vencido</th>
                    <th class="whitespace-nowrap px-4 py-3">Nota</th>
                    @endif
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($cartera as $item)
                    @php
                        $oldestPeriodKey = trim((string) ($item->oldest_period_key ?? ''));
                        $oldestMes = strlen($oldestPeriodKey) >= 6 ? (int) substr($oldestPeriodKey, 4, 2) : 0;
                        $oldestAnio = strlen($oldestPeriodKey) >= 6 ? (int) substr($oldestPeriodKey, 0, 4) : null;
                        $oldestMesNombre = $meses[$oldestMes] ?? null;
                        $oldestPeriod = $oldestMesNombre !== null && $oldestAnio
                            ? ucfirst($oldestMesNombre).' '.$oldestAnio
                            : 'N/D';
                        $mesNombre = $meses[(int) ($item->ultima_proforma_mes ?? 0)] ?? null;
                        $ultimaProforma = $mesNombre !== null
                            ? ucfirst($mesNombre).' '.($item->ultima_proforma_anio ?? 'N/D')
                            : 'Periodo N/D';
                        $ultimaProforma .= ' - '.($item->ultima_proforma_numero ?: '#'.$item->ultima_proforma_id);
                        $verProformasUrl = trim((string) ($item->nit ?? '')) !== ''
                            ? route('proformas.index', ['empresa' => $item->nit, 'estado' => \App\Services\ProformasService::ESTADO_ENVIADA])
                            : null;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        @if($isPorCobrarMode)
                        <td class="min-w-[240px] break-words px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $item->empresa ?: 'N/D' }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $item->nit ?: 'N/D' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center font-medium text-slate-900">{{ number_format((int) ($item->cantidad_proformas ?? 0), 0, ',', '.') }}</td>
                        <td class="min-w-[180px] whitespace-nowrap px-4 py-3 text-right font-semibold text-rose-700">$ {{ number_format((float) ($item->valor_total_deuda ?? 0), 2, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $oldestPeriod }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if($verProformasUrl)
                                <a href="{{ $verProformasUrl }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                                    Ver proformas
                                </a>
                            @else
                                <span class="text-xs text-slate-400">No disponible</span>
                            @endif
                        </td>
                        @else
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $item->codigo ?: 'N/D' }}</td>
                        <td class="min-w-[180px] break-words px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $item->empresa ?: 'N/D' }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $item->nit ?: 'N/D' }}</td>
                        <td class="min-w-[220px] px-4 py-3 text-slate-700">{{ $item->email ?: 'N/D' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $item->celular ?: 'N/D' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center font-medium text-slate-900">{{ number_format((int) ($item->meses_pendientes ?? 0), 0, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center font-medium text-slate-900">{{ number_format((int) ($item->cantidad_proformas ?? 0), 0, ',', '.') }}</td>
                        <td class="min-w-[150px] whitespace-nowrap px-4 py-3 text-right font-semibold text-rose-700">$ {{ number_format((float) ($item->valor_total_deuda ?? 0), 2, ',', '.') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $ultimaProforma }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformaCarteraService->estadoBadgeStyle($item->estado_actual) }}">
                                {{ $proformaCarteraService->estadoLabel($item->estado_actual) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-center font-medium {{ (int) ($item->dias_vencido ?? 0) > 30 ? 'text-rose-700' : 'text-slate-800' }}">
                            {{ number_format((int) ($item->dias_vencido ?? 0), 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ \Illuminate\Support\Str::limit($item->nota ?: 'N/D', 90) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isPorCobrarMode ? 6 : 12 }}" class="px-4 py-8 text-center text-slate-500">
                            {{ $isPorCobrarMode ? 'No hay empresas con cartera por cobrar.' : 'No hay empresas con cartera pendiente para los filtros seleccionados.' }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $cartera->links() }}
        </div>
    </div>
</div>
@endsection
