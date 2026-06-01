@extends('layouts.admin')

@section('title', 'Configuración de usuarios')

@section('content')
<div class="w-full min-w-0 px-2 py-6 md:px-4 md:py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Configuración de usuarios</h1>
            <p class="text-sm text-slate-600">Gestión de accesos desde las tablas <code>usuarios</code> y <code>roles</code>.</p>
        </div>
        <a href="{{ route('configuracion.usuarios.create') }}" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Nuevo usuario
        </a>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type', 'success') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('configuracion.usuarios.index') }}" class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
            <div>
                <label for="nombre" class="mb-1 block text-sm font-medium">Nombre</label>
                <input
                    id="nombre"
                    name="nombre"
                    type="text"
                    value="{{ $filters['nombre'] }}"
                    placeholder="Buscar usuario"
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label for="rol" class="mb-1 block text-sm font-medium">Rol</label>
                <select id="rol" name="rol" class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol->idroles }}" @selected((string) $filters['rol'] === (string) $rol->idroles)>{{ ucfirst(strtolower((string) $rol->rol)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="1" @selected($filters['estado'] === '1')>Activo</option>
                    <option value="0" @selected($filters['estado'] === '0')>Inactivo</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('configuracion.usuarios.index') }}" class="rounded bg-slate-200 px-4 py-2 text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="min-w-0 overflow-hidden rounded-lg bg-white shadow">
        <div class="min-w-0 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-3 text-left whitespace-nowrap">ID</th>
                    <th class="px-3 py-3 text-left">Usuario</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Rol</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Estado</th>
                    <th class="px-3 py-3 text-left whitespace-nowrap">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($usuarios as $usuario)
                    @php
                        $rolNombre = strtolower(trim((string) ($usuario->rol->rol ?? 'sin rol')));
                        $esAdmin = (int) $usuario->roles_idroles === 1;
                        $estaActivo = (int) $usuario->estado === 1;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3 align-top whitespace-nowrap font-medium text-slate-700">{{ $usuario->idusuario }}</td>
                        <td class="px-3 py-3 align-top">
                            <div class="space-y-1">
                                <p class="font-semibold text-slate-900">{{ $usuario->nombre }}</p>
                                @if((int) session('idusuario') === (int) $usuario->idusuario)
                                    <p class="text-xs text-slate-500">Sesión actual</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $esAdmin ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-700' }}">
                                {{ $esAdmin ? 'Administrador' : 'Usuario' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $estaActivo ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $estaActivo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">
                            <a href="{{ route('configuracion.usuarios.edit', $usuario) }}" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay usuarios para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $usuarios->links() }}
        </div>
    </div>
</div>
@endsection
