<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista previa proforma - Cobro #{{ $cobro->id_cobro }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-500">Módulo Cobros</p>
            <h1 class="text-2xl font-bold">Vista previa de proforma</h1>
            <p class="text-sm text-slate-600">Cobro #{{ $proforma['cabecera']['id_cobro'] }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cobros.show', $cobro->id_cobro) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Volver a detalle
            </a>
            <a href="{{ route('cobros.index') }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
                Ir al listado
            </a>
        </div>
    </div>

    <section class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-4">Cabecera proforma (en memoria)</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">Empresa emisora</dt>
                <dd class="font-medium">{{ $proforma['cabecera']['empresa_emisora'] }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Mes / Año</dt>
                <dd class="font-medium">{{ ucfirst($proforma['cabecera']['mes']) }} {{ $proforma['cabecera']['anio'] }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Estado Proforma actual</dt>
                <dd>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ (int) $proforma['cabecera']['proforma_actual'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ (int) $proforma['cabecera']['proforma_actual'] === 1 ? 'Generada' : 'Pendiente' }}
                    </span>
                </dd>
            </div>
        </dl>
    </section>

    <section class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-4">Datos del cliente</h2>
        @php
            $cliente = $proforma['cabecera']['cliente'];
            $empresaCliente = trim((string) ($cliente['empresa'] ?? ''));
            $nombreCliente = trim((string) ($cliente['nombre'] ?? ''));
            $contactoCliente = trim((string) ($cliente['contacto'] ?? ''));
        @endphp

        <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-slate-500">ID Cliente</dt>
                <dd class="font-medium">{{ $cliente['id'] ?? 'N/D' }}</dd>
            </div>
            @if($empresaCliente !== '')
                <div>
                    <dt class="text-slate-500">Empresa</dt>
                    <dd class="font-medium">{{ $empresaCliente }}</dd>
                </div>
            @endif
            @if($nombreCliente !== '' && strcasecmp($nombreCliente, $empresaCliente) !== 0)
                <div>
                    <dt class="text-slate-500">Nombre</dt>
                    <dd>{{ $nombreCliente }}</dd>
                </div>
            @endif
            @if($contactoCliente !== '')
                <div>
                    <dt class="text-slate-500">Contacto</dt>
                    <dd>{{ $contactoCliente }}</dd>
                </div>
            @endif

            @foreach(['nit' => 'NIT', 'codigo' => 'Código', 'email' => 'Email', 'direccion' => 'Dirección', 'regimen' => 'Régimen', 'modalidad' => 'Modalidad', 'categoria' => 'Categoría'] as $key => $label)
                @if(!empty($cliente[$key]))
                    <div>
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd>{{ $cliente[$key] }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </section>

    <section class="bg-white rounded-lg shadow p-5">
        <h2 class="text-lg font-semibold mb-4">Detalle de líneas de proforma</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                <tr>
                    <th class="text-left px-3 py-2">Concepto</th>
                    <th class="text-right px-3 py-2">Cantidad</th>
                    <th class="text-right px-3 py-2">Valor unitario</th>
                    <th class="text-right px-3 py-2">Valor parcial</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($proforma['detalle']['lineas'] as $linea)
                    <tr>
                        <td class="px-3 py-2">{{ $linea['concepto'] }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linea['cantidad'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linea['valor_unitario'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $linea['valor_parcial'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-slate-50">
                <tr>
                    <td colspan="3" class="px-3 py-2 text-right font-semibold">Total líneas</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $proforma['detalle']['total_calculado'], 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="px-3 py-2 text-right text-slate-600">Total cobro origen</td>
                    <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) $proforma['detalle']['total_cobro'], 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="px-3 py-2 text-right font-bold">Total proforma vista previa</td>
                    <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $proforma['detalle']['total_preview'], 2, ',', '.') }}</td>
                </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Esta vista solo construye la proforma en memoria. La persistencia en <code>sg_proform</code> y <code>sg_proford</code> queda para la siguiente fase.
        </p>
    </section>
</div>
</body>
</html>
