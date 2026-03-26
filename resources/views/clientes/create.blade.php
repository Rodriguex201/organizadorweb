@extends('layouts.admin')

@section('title', 'Crear cliente')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Crear cliente / empresa</h1>
        <p class="text-sm text-slate-600">Registro inicial en <code>clientes_potenciales</code>.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('clientes.store') }}">
            @csrf

            @include('clientes.partials.form')

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar</button>
                <a href="{{ route('clientes.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
