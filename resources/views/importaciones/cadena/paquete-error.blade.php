@extends('layouts.admin')
@section('title', 'Cadena — Paquete no generado')
@section('content')
<section class="space-y-5 rounded bg-white p-5 shadow">
    <h1 class="text-xl font-bold">No se generó el paquete completo</h1>
    <p role="alert" class="text-amber-800">{{ $message }}</p>
    <p>No se entregó un ZIP parcial. Puedes intentar las descargas individuales mientras sus tokens sigan vigentes, o preparar una nueva vista previa.</p>
    <div class="flex flex-wrap gap-3">
        @foreach($tokens as $filename => $token)
            <form method="POST" action="{{ route('configuracion.importaciones.cadena.download') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="archivo" value="{{ $filename }}">
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="anio" value="{{ $anio }}">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white">{{ $filename }}</button>
            </form>
        @endforeach
    </div>
    <a class="underline" href="{{ route('configuracion.importaciones.cadena.index') }}">Volver a cargar originales</a>
</section>
@endsection
