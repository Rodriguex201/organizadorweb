@extends('layouts.admin')

@section('title', 'Listado de Proformas')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Listado de Proformas</h1>
            <p class="text-sm text-slate-600">Consulta administrativa sobre <code>sg_proform</code>.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 7, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-cyan-100 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-200">
                Enviar grupo 7
            </a>
            <a href="{{ route('proformas.envio-masivo.confirmar', ['grupo' => 27, 'mes' => $filters['mes'] ?? null, 'anio' => $filters['anio'] ?? null]) }}" class="inline-flex items-center rounded bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200">
                Enviar grupo 27
            </a>
            <a href="{{ route('proformas.dashboard') }}" class="inline-flex items-center rounded bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-200">
                Ver dashboard
            </a>
            <a href="{{ route('cobros.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Ir a Cobros
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ session('status_type') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('proformas.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-8">
            <div>
                <label for="nro_prof" class="mb-1 block text-sm font-medium">Número</label>
                <input id="nro_prof" name="nro_prof" value="{{ request('nro_prof', session('proformas.numero')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="nit" class="mb-1 block text-sm font-medium">NIT</label>
                <input id="nit" name="nit" value="{{ request('nit', session('proformas.nit')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="empresa" class="mb-1 block text-sm font-medium">Empresa</label>
                <input id="empresa" name="empresa" value="{{ request('empresa', session('proformas.empresa')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="emisora" class="mb-1 block text-sm font-medium">Emisora</label>
                <input id="emisora" name="emisora" value="{{ request('emisora', session('proformas.emisora')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((string) request('mes', session('proformas.mes')) === (string) $mesNumero || (string) request('mes', session('proformas.mes')) === $mesNombre)>
                            {{ ucfirst($mesNombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ request('anio', session('proformas.anio')) }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoLabel)
                        <option value="{{ $estadoCodigo }}" @selected((string) request('estado', session('proformas.estado')) === (string) $estadoCodigo)>{{ $estadoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('proformas.clear-filters') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2">Número</th>
                    <th class="px-3 py-2">Empresa</th>
                    <th class="px-3 py-2">Periodo</th>
                    <th class="px-3 py-2 text-right">Valor total</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Envío</th>
                    <th class="px-3 py-2 text-right">Acción</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($proformas as $proforma)
                    @php
                        $estado = $proformasService->estadoLabel($proforma->estado);
                        $envioEstado = $proformasService->envioLabel($proforma->enviado ?? 0);
                        $envioClasses = $proformasService->envioBadgeClass($proforma->enviado ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-800">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</p>
                            <p class="text-xs text-slate-500">ID {{ $proforma->id }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-slate-800">{{ $proforma->emp ?: 'N/D' }}</p>
                            <p class="text-xs text-slate-500">NIT: {{ $proforma->nit ?: 'N/D' }}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-700">{{ $proformasService->monthLabel($proforma->mes) }} {{ $proforma->anio ?: 'N/D' }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="{{ $proformasService->estadoBadgeStyle($proforma->estado) }}">{{ $estado }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $envioClasses }}">{{ $envioEstado }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">

                            <a href="{{ route('proformas.show', array_merge(['id' => $proforma->id], request()->query())) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver detalle</a>

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay proformas para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $proformas->links() }}
        </div>
    </div>
</div>
@endsection
