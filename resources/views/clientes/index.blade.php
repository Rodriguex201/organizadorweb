@extends('layouts.admin')

@section('title', 'Inicio | Clientes')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
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

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="{{ route('clientes.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label for="q" class="block text-sm font-medium mb-1">Búsqueda libre</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] }}" placeholder="Nombre, empresa, NIT, código, contacto o correo"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="contrato" class="block text-sm font-medium mb-1">Contrato / Modalidad</label>
                <select id="contrato" name="contrato" class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" @disabled(!$mapping['contrato'])>
                    <option value="">Todos</option>
                    @foreach($contratos as $valorContrato)
                        <option value="{{ $valorContrato }}" @selected($filters['contrato'] === $valorContrato)>{{ $valorContrato }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('clientes.index') }}" class="bg-slate-200 text-slate-700 px-4 py-2 rounded hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                <tr>
                    <th class="text-left px-4 py-3">NIT</th>
                    <th class="text-left px-4 py-3">DV</th>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Código</th>
                    <th class="text-left px-4 py-3">Empresa</th>
                    <th class="text-left px-4 py-3">Correo</th>
                    <th class="text-left px-4 py-3">Teléfono</th>
                    <th class="text-left px-4 py-3">Contacto</th>
                    <th class="text-left px-4 py-3">Fecha inicio</th>
                    <th class="text-left px-4 py-3">Fecha arriendo</th>
                    <th class="text-left px-4 py-3">Fecha cotización</th>
                    <th class="text-left px-4 py-3">Fecha retiro</th>
                    <th class="text-left px-4 py-3">Contrato / Modalidad</th>
                    <th class="text-left px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($clientes as $cliente)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $cliente->nit ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->dv ?: '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ $cliente->nombre ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->codigo ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->empresa ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->correo ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->telefono ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->contacto ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->fecha_inicio ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->fecha_arriendo ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->fecha_cotizacion ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->fecha_retiro ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $cliente->contrato ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                @if($cliente->id)
                                <a href="{{ route('clientes.show', $cliente->id) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver</a>
                                <a href="{{ route('clientes.edit', $cliente->id) }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Editar</a>
                                <form method="POST" action="{{ route('clientes.retirar', $cliente->id) }}" onsubmit="return confirm('¿Marcar este cliente como retirado?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center rounded bg-rose-100 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200">Retirar</button>
                                </form>
                                @else
                                    <span class="text-xs text-slate-400">Sin identificador</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="px-4 py-8 text-center text-slate-500">No hay clientes disponibles para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200">
            {{ $clientes->links() }}
        </div>
    </div>
</div>
@endsection
