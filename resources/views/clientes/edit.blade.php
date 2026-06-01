@extends('layouts.admin')

@section('title', 'Editar cliente')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold">Editar cliente / empresa</h1>
                @if($cliente->esta_retirado)
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Retirado</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Activo</span>
                @endif
            </div>
            <p class="text-sm text-slate-600">Ajuste de datos en <code>clientes_potenciales</code>.</p>
            @if(!empty($cliente->motivo_retiro_nombre))
                <p class="mt-2 text-sm text-rose-700">Motivo de retiro: <span class="font-medium">{{ $cliente->motivo_retiro_nombre }}</span></p>
            @endif
        </div>

        @if(!$cliente->esta_retirado)
            <a href="{{ route('cobros.extraordinario.create', ['cliente_id' => $clienteId]) }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Generar cobro extraordinario
            </a>
        @endif

        @if($cliente->esta_retirado)
            <button
                type="button"
                data-reactivar-url="{{ route('clientes.reactivar', $clienteId) }}"
                data-reactivar-id="{{ $clienteId }}"
                data-reactivar-nombre="{{ $cliente->empresa ?: ($cliente->nombre ?: 'este cliente') }}"
                class="inline-flex items-center rounded bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-200"
            >
                Reactivar cliente
            </button>
        @else
            <button
                type="button"
                data-retirar-url="{{ route('clientes.retirar', $clienteId) }}"
                data-retirar-id="{{ $clienteId }}"
                data-retirar-nombre="{{ $cliente->empresa ?: ($cliente->nombre ?: 'este cliente') }}"
                class="inline-flex items-center rounded bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-200"
            >
                Retirar cliente
            </button>
        @endif
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
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

@include('clientes.partials.reactivar-modal')
@include('clientes.partials.retirar-modal')
@endsection
