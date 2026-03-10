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
        <form method="GET" action="{{ route('cobros.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
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
                <p class="mt-1 text-xs text-slate-500">Puedes seleccionar un mes o buscar por número (1-12) en la URL, por ejemplo: <code>?mes=3</code>.</p>
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

                    <th class="text-left px-4 py-3">
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
                    </th>

                    <th class="text-left px-4 py-3">Código</th>
                    <th class="text-left px-4 py-3">Cliente Potencial</th>
                    <th class="text-left px-4 py-3">Régimen</th>
                    <th class="text-right px-4 py-3">Valor Total</th>
                    <th class="text-left px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($cobros as $cobro)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $cobro->fecha_arriendo ? \Carbon\Carbon::parse($cobro->fecha_arriendo)->format('d/m/Y') : '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ $cobro->codigo ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cobro->nombre ?: 'Sin nombre' }}</td>
                        <td class="px-4 py-3">{{ $cobro->regimen ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">${{ number_format((float) ($cobro->valor_total ?? 0), 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('cobros.show', $cobro->id_cobro) }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay cobros disponibles para los filtros seleccionados.</td>
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
@endsection
