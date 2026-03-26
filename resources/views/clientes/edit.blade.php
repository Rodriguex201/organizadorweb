@extends('layouts.admin')

@section('title', 'Editar cliente')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Editar cliente / empresa</h1>
            <p class="text-sm text-slate-600">Ajuste de datos en <code>clientes_potenciales</code>.</p>
        </div>

        <form method="POST" action="{{ route('clientes.retirar', $clienteId) }}" onsubmit="return confirm('¿Marcar este cliente como retirado?');">
            @csrf
            @method('PATCH')
            <button type="submit" class="inline-flex items-center rounded bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200">Retirar cliente</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('clientes.update', $clienteId) }}">
            @csrf
            @method('PUT')

            @include('clientes.partials.form', ['cliente' => $cliente])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Actualizar</button>
                <a href="{{ route('clientes.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection
