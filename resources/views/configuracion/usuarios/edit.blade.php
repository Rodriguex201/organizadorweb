@extends('layouts.admin')

@section('title', 'Editar usuario')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold">Editar usuario</h1>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) $usuario->estado === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    {{ (int) $usuario->estado === 1 ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
            <p class="text-sm text-slate-600">Actualización del acceso <code>{{ $usuario->nombre }}</code>.</p>
        </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
        @if($errors->any())
            <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('configuracion.usuarios.update', $usuario) }}">
            @csrf
            @method('PUT')

            @include('configuracion.usuarios.partials.form', [
                'usuario' => $usuario,
                'roles' => $roles,
                'isEdit' => true,
            ])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Actualizar usuario</button>
                <a href="{{ route('configuracion.usuarios.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection
