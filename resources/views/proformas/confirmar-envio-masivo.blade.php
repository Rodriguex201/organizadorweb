@extends('layouts.admin')

@section('title', 'Confirmar envío masivo de proformas')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Confirmación envío masivo grupo {{ $grupo }}</h1>
            <p class="mt-1 text-sm text-slate-600">Revisa el resumen antes de ejecutar el envío por correo.</p>
        </div>
        <a href="{{ route('proformas.index', ['mes' => $filtrosPeriodo['mes'] ?? null, 'anio' => $filtrosPeriodo['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Volver al listado</a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Total encontradas</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $resumen['total_encontradas'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Válidas para enviar</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $resumen['validas_count'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Omitidas</p>
            <p class="mt-1 text-2xl font-semibold text-amber-600">{{ $resumen['omitidas_count'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Grupo fecha arriendo</p>
            <p class="mt-1 text-2xl font-semibold text-cyan-700">{{ $grupo }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-5 shadow">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Detalle de omitidas</h2>
        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div class="rounded bg-slate-50 px-3 py-2">Sin correo: <strong>{{ $resumen['omitidas_por_motivo']['sin_correo'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">Sin PDF: <strong>{{ $resumen['omitidas_por_motivo']['sin_pdf'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">Ya enviadas: <strong>{{ $resumen['omitidas_por_motivo']['ya_enviadas'] }}</strong></div>
            <div class="rounded bg-slate-50 px-3 py-2">No generadas: <strong>{{ $resumen['omitidas_por_motivo']['no_generadas'] }}</strong></div>
        </dl>
    </div>

    @if($resumen['validas_count'] > 0)
        <div class="rounded-lg bg-white p-5 shadow">
            <form method="POST" action="{{ route('proformas.envio-masivo.enviar', ['grupo' => $grupo]) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="mes" value="{{ $filtrosPeriodo['mes'] ?? '' }}">
                <input type="hidden" name="anio" value="{{ $filtrosPeriodo['anio'] ?? '' }}">

                @foreach($resumen['validas'] as $proforma)
                    <input type="hidden" name="proformas[]" value="{{ $proforma->id }}">
                @endforeach

                <p class="text-sm text-slate-600">Se enviarán <strong>{{ $resumen['validas_count'] }}</strong> proformas una por una usando el servicio de correo existente.</p>

                <button type="submit" class="inline-flex items-center rounded bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">Confirmar y enviar grupo {{ $grupo }}</button>
            </form>
        </div>
    @else
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            No hay proformas válidas para envío en este grupo.
        </div>
    @endif
</div>
@endsection
