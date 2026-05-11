@extends('layouts.admin')

@section('title', 'Inicio | Clientes')

@section('content')
<div class="w-full min-w-0 px-2 py-6 md:px-4 md:py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Inicio · Clientes / Empresas</h1>
            <p class="text-sm text-slate-600">Panel maestro desde <code>clientes_potenciales</code>.</p>
        </div>
        <a href="{{ route('clientes.create') }}" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Nuevo cliente
        </a>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if($errors->has('general'))
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first('general') }}</div>
    @endif

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('clientes.index') }}" class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="q" class="mb-1 block text-sm font-medium">Busqueda libre</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] }}" placeholder="Nombre, empresa, NIT, codigo, contacto o correo"
                       class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label for="contrato" class="mb-1 block text-sm font-medium">Contrato / Modalidad</label>
                <select id="contrato" name="contrato" class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" @disabled(!$mapping['contrato'])>
                    <option value="">Todos</option>
                    @foreach($contratos as $valorContrato)
                        <option value="{{ $valorContrato }}" @selected($filters['contrato'] === $valorContrato)>{{ $valorContrato }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('clientes.index') }}" class="rounded bg-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="min-w-0 overflow-hidden rounded-lg bg-white shadow">
        <div class="min-w-0 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-3 text-left">Cliente</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Estado</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Codigo</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Fecha inicio</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Fecha arriendo</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">IP empresa</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Fecha cotizacion</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Fecha retiro</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Fecha reactivacion</th>
                    <th class="px-3 py-3 text-left">Motivo reactivacion</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($clientes as $cliente)
                    @php
                        $empresaPrincipal = $cliente->empresa ?: ($cliente->nombre ?: '-');
                        $nombreSecundario = $cliente->empresa && $cliente->nombre && $cliente->empresa !== $cliente->nombre
                            ? $cliente->nombre
                            : null;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3 align-top">
                            <div class="min-w-[18rem] space-y-1 text-sm leading-tight text-slate-600">
                                <p class="font-semibold text-slate-900">{{ $empresaPrincipal }}</p>
                                @if($nombreSecundario)
                                    <p class="text-xs text-slate-500">{{ $nombreSecundario }}</p>
                                @endif
                                <p class="text-xs text-slate-500">NIT: {{ $cliente->nit ?: '-' }}</p>
                                <p class="text-xs text-slate-500">Contacto: {{ $cliente->contacto ?: '-' }}</p>
                                <p class="text-xs text-slate-500">Tel: {{ $cliente->telefono ?: '-' }}</p>
                                <p class="break-all text-xs text-slate-500">Correo: {{ $cliente->correo ?: '-' }}</p>
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">
                            @if($cliente->esta_retirado)
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Retirado</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->codigo ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->fecha_inicio ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ \Illuminate\Support\Carbon::make($cliente->fecha_arriendo)?->format('d-m-Y') ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->ip_empresa ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->fecha_cotizacion ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->fecha_retiro ?: '-' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap text-xs text-slate-700">{{ $cliente->fecha_reactivacion ?: '-' }}</td>
                        <td class="px-3 py-3 align-top text-xs leading-tight text-slate-700">{{ $cliente->motivo_reactivacion ?: '-' }}</td>
                        <td class="px-3 py-3 align-top">
                            <div class="flex flex-wrap gap-2">
                                @if($cliente->id)
                                    <a href="{{ route('clientes.show', $cliente->id) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver</a>
                                    <a href="{{ route('clientes.edit', $cliente->id) }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Editar</a>

                                    @if($cliente->esta_retirado)
                                        <button
                                            type="button"
                                            data-reactivar-url="{{ route('clientes.reactivar', $cliente->id) }}"
                                            data-reactivar-id="{{ $cliente->id }}"
                                            data-reactivar-nombre="{{ $cliente->empresa ?: ($cliente->nombre ?: 'este cliente') }}"
                                            class="inline-flex items-center rounded bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200"
                                        >
                                            Reactivar
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('clientes.retirar', $cliente->id) }}" onsubmit="return confirm('¿Marcar este cliente como retirado?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex items-center rounded bg-rose-100 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200">Retirar</button>
                                        </form>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-400">Sin identificador</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-500">No hay clientes disponibles para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $clientes->links() }}
        </div>
    </div>
</div>

@include('clientes.partials.reactivar-modal')
@endsection
