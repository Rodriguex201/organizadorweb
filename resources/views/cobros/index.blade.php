<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobros</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Módulo Cobros</h1>
        <p class="text-sm text-slate-600">Listado inicial desde <code>valores_externos</code> con datos de clientes potenciales.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="{{ route('cobros.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="mes" class="block text-sm font-medium mb-1">Mes</label>
                <input id="mes" name="mes" type="number" min="1" max="12" value="{{ $filters['mes'] ?? '' }}"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="ano" class="block text-sm font-medium mb-1">Año</label>
                <input id="ano" name="ano" type="number" min="1900" max="9999" value="{{ $filters['ano'] ?? '' }}"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label for="proforma" class="block text-sm font-medium mb-1">Proforma</label>
                <input id="proforma" name="proforma" type="text" value="{{ $filters['proforma'] ?? '' }}"
                       class="w-full border border-slate-300 rounded px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('cobros.index') }}" class="bg-slate-200 text-slate-700 px-4 py-2 rounded hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                <tr>
                    <th class="text-left px-4 py-3">ID Cobro</th>
                    <th class="text-left px-4 py-3">Proforma</th>
                    <th class="text-left px-4 py-3">Mes</th>
                    <th class="text-left px-4 py-3">Año</th>
                    <th class="text-left px-4 py-3">Cliente potencial</th>
                    <th class="text-right px-4 py-3">Total</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($cobros as $cobro)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $cobro->id_cobro }}</td>
                        <td class="px-4 py-3 font-medium">{{ $cobro->proforma }}</td>
                        <td class="px-4 py-3">{{ $cobro->mes }}</td>
                        <td class="px-4 py-3">{{ $cobro->ano }}</td>
                        <td class="px-4 py-3">{{ trim(($cobro->cliente_nombre ?? '') . ' ' . ($cobro->cliente_apellido ?? '')) ?: ($cobro->razon_social ?? 'Sin nombre') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) ($cobro->total ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay cobros disponibles para los filtros seleccionados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200">
            {{ $cobros->links() }}
        </div>
    </div>
</div>
</body>
</html>
