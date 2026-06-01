@extends('layouts.admin')

@section('title', 'Crear usuario')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Crear usuario</h1>
        <p class="text-sm text-slate-600">Alta de acceso en la tabla <code>usuarios</code>.</p>
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

        <form method="POST" action="{{ route('configuracion.usuarios.store') }}">
            @csrf

            @include('configuracion.usuarios.partials.form', [
                'usuario' => null,
                'roles' => $roles,
                'isEdit' => false,
            ])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar usuario</button>
                <a href="{{ route('configuracion.usuarios.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
