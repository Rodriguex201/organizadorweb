@extends('layouts.admin')

@section('title', 'Configuración de estados de proforma')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Configuración de estados de proforma</h1>
            <p class="text-sm text-slate-600">Administra colores de badge para cada estado.</p>
        </div>

        @if(session('status'))
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach($estadosConfig as $estado)
                <div class="rounded-lg bg-white p-5 shadow">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">{{ $estado->estado_nombre }}</h2>
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" style="background-color: {{ $estado->color_fondo }}; color: {{ $estado->color_texto }};">
                            Preview
                        </span>
                    </div>

                    <form method="POST" action="{{ route('configuracion.estados-proforma.update', $estado->estado_codigo) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="color_fondo_{{ $estado->estado_codigo }}">Color fondo</label>
                            <input id="color_fondo_{{ $estado->estado_codigo }}" type="color" name="color_fondo" value="{{ $estado->color_fondo }}" class="h-10 w-full rounded border border-slate-300 p-1">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="color_texto_{{ $estado->estado_codigo }}">Color texto</label>
                            <input id="color_texto_{{ $estado->estado_codigo }}" type="color" name="color_texto" value="{{ $estado->color_texto }}" class="h-10 w-full rounded border border-slate-300 p-1">
                        </div>
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection
