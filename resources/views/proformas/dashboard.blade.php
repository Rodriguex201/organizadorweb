<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Proformas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Dashboard de Proformas</h1>
            <p class="text-sm text-slate-600">Resumen general por periodo de <code>sg_proform</code>.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('proformas.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Ir al listado</a>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('proformas.dashboard') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((int) $filters['mes'] === (int) $mesNumero)>{{ ucfirst($mesNombre) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Aplicar filtro</button>
                <a href="{{ route('proformas.dashboard') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Periodo actual</a>
            </div>
        </form>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Total proformas</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format((int) $dashboard['total_proformas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Generadas</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format((int) $dashboard['total_generadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Pagadas</p>
            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format((int) $dashboard['total_pagadas'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-xs uppercase text-slate-500">Facturadas</p>
            <p class="mt-1 text-2xl font-bold text-purple-700">{{ number_format((int) $dashboard['total_facturadas'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-semibold uppercase text-slate-600">Suma total del periodo</h2>
            <p class="mt-2 text-2xl font-bold">$ {{ number_format((float) $dashboard['suma_total_vtotal'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Total del periodo filtrado: {{ number_format((int) $dashboard['total_periodo_filtrado'], 0, ',', '.') }}</p>
        </div>

        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-sm font-semibold uppercase text-slate-600">Suma total por estado</h2>
            <div class="mt-3 space-y-2 text-sm">
                @foreach($dashboard['suma_total_por_estado'] as $estadoCodigo => $datosEstado)
                    <div class="flex items-center justify-between">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proformasService->estadoBadgeClass($estadoCodigo) }}">{{ $datosEstado['label'] }}</span>
                        <span class="font-medium">{{ number_format((int) $datosEstado['cantidad'], 0, ',', '.') }} / $ {{ number_format((float) $datosEstado['total'], 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold">Últimas proformas del periodo</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Número</th>
                    <th class="px-4 py-3">Empresa</th>
                    <th class="px-4 py-3">NIT</th>
                    <th class="px-4 py-3">Emisora</th>
                    <th class="px-4 py-3">Mes</th>
                    <th class="px-4 py-3">Año</th>
                    <th class="px-4 py-3 text-right">Valor total</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($dashboard['ultimas_proformas'] as $proforma)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</td>
                        <td class="px-4 py-3">{{ $proforma->emp ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->nit ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->emisora ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proformasService->monthLabel($proforma->mes) }}</td>
                        <td class="px-4 py-3">{{ $proforma->anio ?: 'N/D' }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proformasService->estadoBadgeClass($proforma->estado) }}">{{ $proformasService->estadoLabel($proforma->estado) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('proformas.pdf.show', $proforma->id) }}" target="_blank" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Ver PDF</a>
                                <a href="{{ route('proformas.show', $proforma->id) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver detalle</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-slate-500">No hay proformas para el periodo seleccionado.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
