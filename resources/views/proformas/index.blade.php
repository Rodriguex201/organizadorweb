<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Proformas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Listado de Proformas</h1>
            <p class="text-sm text-slate-600">Consulta administrativa sobre <code>sg_proform</code>.</p>
        </div>

        <div class="flex gap-2">
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


    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <form method="GET" action="{{ route('proformas.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4 xl:grid-cols-8">
            <div>
                <label for="nro_prof" class="mb-1 block text-sm font-medium">Número</label>
                <input id="nro_prof" name="nro_prof" value="{{ $filters['nro_prof'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="nit" class="mb-1 block text-sm font-medium">NIT</label>
                <input id="nit" name="nit" value="{{ $filters['nit'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="empresa" class="mb-1 block text-sm font-medium">Empresa</label>
                <input id="empresa" name="empresa" value="{{ $filters['empresa'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="emisora" class="mb-1 block text-sm font-medium">Emisora</label>
                <input id="emisora" name="emisora" value="{{ $filters['emisora'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="mes" class="mb-1 block text-sm font-medium">Mes</label>
                <select id="mes" name="mes" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($meses as $mesNumero => $mesNombre)
                        <option value="{{ $mesNumero }}" @selected((string) ($filters['mes'] ?? '') === (string) $mesNumero || (string) ($filters['mes'] ?? '') === $mesNombre)>
                            {{ ucfirst($mesNombre) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="anio" class="mb-1 block text-sm font-medium">Año</label>
                <input id="anio" name="anio" type="number" min="1900" max="9999" value="{{ $filters['anio'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="estado" class="mb-1 block text-sm font-medium">Estado</label>
                <select id="estado" name="estado" class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach($estados as $estadoCodigo => $estadoLabel)
                        <option value="{{ $estadoCodigo }}" @selected((string) ($filters['estado'] ?? '') === (string) $estadoCodigo)>{{ $estadoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
                <a href="{{ route('proformas.index') }}" class="rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
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
                    <th class="px-4 py-3">Envío</th>
                    <th class="px-4 py-3">Fecha / ID</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($proformas as $proforma)
                    @php
                        $estado = $proformasService->estadoLabel($proforma->estado);
                        $estadoClasses = $proformasService->estadoBadgeClass($proforma->estado);
                        $envioEstado = $proformasService->envioLabel($proforma->enviado ?? 0);
                        $envioClasses = $proformasService->envioBadgeClass($proforma->enviado ?? 0);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $proforma->nro_prof ?: ('#'.$proforma->id) }}</td>
                        <td class="px-4 py-3">{{ $proforma->emp ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->nit ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proforma->emisora ?: 'N/D' }}</td>
                        <td class="px-4 py-3">{{ $proformasService->monthLabel($proforma->mes) }}</td>
                        <td class="px-4 py-3">{{ $proforma->anio ?: 'N/D' }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) ($proforma->vtotal ?? 0), 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $estadoClasses }}">{{ $estado }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $envioClasses }}">{{ $envioEstado }}</span>
                            <p class="mt-1 text-xs text-slate-500">Intentos: {{ (int) ($proforma->intentos_envio ?? 0) }}</p>
                            <p class="text-xs text-slate-500">Último: {{ $proforma->fecha_envio ? \Illuminate\Support\Carbon::parse($proforma->fecha_envio)->format('Y-m-d H:i') : "N/D" }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">ID: {{ $proforma->id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('proformas.pdf.show', $proforma->id) }}" target="_blank" class="inline-flex items-center rounded bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">Ver PDF</a>
                                <a href="{{ route('proformas.pdf.download', $proforma->id) }}" class="inline-flex items-center rounded bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200">Descargar PDF</a>
                                <form method="POST" action="{{ route('proformas.enviar', $proforma->id) }}">
                                    @csrf

                                    <button type="submit" class="inline-flex items-center rounded bg-cyan-100 px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-200">{{ ((int) ($proforma->enviado ?? 0)) === 1 ? "Reenviar" : "Enviar" }} por correo</button>

                                </form>
                                <a href="{{ route('proformas.show', $proforma->id) }}" class="inline-flex items-center rounded bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ver detalle</a>


                                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_PAGADA))
                                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_PAGADA }}">
                                        <input type="hidden" name="redirect_to" value="index">
                                        <button type="submit" class="inline-flex items-center rounded bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200">Marcar pagada</button>
                                    </form>
                                @endif

                                @if($proformasService->canTransition($proforma->estado, \App\Services\ProformasService::ESTADO_FACTURADA))
                                    <form method="POST" action="{{ route('proformas.estado.update', $proforma->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ \App\Services\ProformasService::ESTADO_FACTURADA }}">
                                        <input type="hidden" name="redirect_to" value="index">
                                        <button type="submit" class="inline-flex items-center rounded bg-purple-100 px-3 py-1.5 text-xs font-medium text-purple-700 hover:bg-purple-200">Marcar facturada</button>
                                    </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-8 text-center text-slate-500">No hay proformas para los filtros seleccionados.</td>
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
</body>
</html>
