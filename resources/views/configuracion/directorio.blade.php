@extends('layouts.admin')

@section('title', 'Configuración de directorio')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Configuración de directorio</h1>
            <p class="text-sm text-slate-600">Define la ruta UNC base donde se crearán las carpetas de empresas.</p>
        </div>

        @if(session('status'))
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg bg-white p-5 shadow">
            <form method="POST" action="{{ route('configuracion.directorio.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="ruta_clientes" class="mb-1 block text-sm font-medium">Ruta UNC base</label>
                    <input
                        id="ruta_clientes"
                        name="ruta_clientes"
                        type="text"
                        value="{{ old('ruta_clientes', $configuracion->ruta_clientes ?? '') }}"
                        placeholder="\\\\192.168.1.150\\00_Organizador_Empresas_Rm"
                        class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                    <p class="mt-2 text-xs text-slate-500">
                        Usa una ruta UNC. Ejemplo: \\\\192.168.1.150\\00_Organizador_Empresas_Rm
                    </p>
                </div>

                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar
                </button>
            </form>
        </div>
    </div>
@endsection
