<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisión manual de proforma - Cobro #{{ $cobro->id_cobro }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Módulo Cobros</p>
            <h1 class="text-2xl font-bold">Revisión manual de proforma</h1>
            <p class="text-sm text-slate-600">Cobro #{{ $cobro->id_cobro }} · {{ ucfirst((string) ($cobro->mes ?? '')) }} {{ $cobro->año ?? '' }}</p>
        </div>
        <a href="{{ route('cobros.show', $cobro->id_cobro) }}" class="inline-flex items-center rounded bg-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-300">
            Volver al detalle
        </a>
    </div>

    @if (session('status'))
        <div class="rounded border px-4 py-3 text-sm {{ session('status_type') === 'warning' ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-emerald-300 bg-emerald-50 text-emerald-700' }}">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold mb-1">Hay errores de validación:</p>
            <ul class="list-disc ml-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('cobros.revisar.guardar', $cobro->id_cobro) }}" class="space-y-6">
        @csrf

        <section class="bg-white rounded-lg shadow p-5">
            <h2 class="text-lg font-semibold mb-4">a) Datos del cliente</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-slate-500">Código</dt><dd class="font-medium">{{ $cobro->cliente_codigo ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $cobro->cliente_nombre ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Correo</dt><dd class="font-medium">{{ $cobro->cliente_email ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Régimen</dt><dd class="font-medium">{{ $cobro->cliente_regimen ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">{{ $cobro->cliente_empresa ?? $cobro->cliente_nombre ?? 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Celular</dt><dd class="font-medium">{{ $cobro->cliente_celular1 ?? $cobro->cliente_celular2 ?? 'N/D' }}</dd></div>
                <div class="md:col-span-2 lg:col-span-3"><dt class="text-slate-500">Notas</dt><dd class="font-medium">{{ $cobro->notas ?? 'Sin notas registradas' }}</dd></div>
            </dl>
        </section>

        <section class="bg-white rounded-lg shadow p-5 space-y-5">
            <h2 class="text-lg font-semibold">b) Valores de la proforma</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                @foreach([
                    'numero_equipos' => 'Número equipos',
                    'valor_principal' => 'Valor principal',
                    'valor_terminal' => 'Valor terminal',
                    'empleados' => 'Empleados',
                    'valor_nomina' => 'Valor nómina',
                    'numero_moviles' => 'Número móviles',
                    'valor_movil' => 'Valor móvil',
                    'facturas' => 'Facturas',
                    'nota_debito' => 'Nota débito',
                    'nota_credito' => 'Nota crédito',
                    'soporte' => 'Soporte',
                    'nota_ajuste' => 'Nota ajuste',
                    'acuse' => 'Acuse',
                    'otro_valor_extra' => 'Otro valor extra',
                    'valor_terminal_recepcion' => 'Valor terminal recepción',
                    'precio_factura' => 'Precio factura',
                    'precio_soporte' => 'Precio soporte',
                    'precio_acuse' => 'Precio acuse',
                ] as $key => $label)
                    <label class="block">
                        <span class="text-slate-500">{{ $label }}</span>
                        <input type="number" step="0.01" min="0" name="{{ $key }}" value="{{ old($key, $formData[$key] ?? 0) }}"
                               class="mt-1 w-full rounded border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                @endforeach
            </div>

            <div class="border-t pt-4">
                <h3 class="font-semibold mb-3">Campos calculados (solo lectura)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    @foreach([
                        'total_facturas' => 'Total facturas',
                        'valor_facturas' => 'Valor facturas',
                        'total_documentos' => 'Total documentos',
                        'valor_documentos' => 'Valor soporte / documentos',
                        'valor_acuse' => 'Valor acuse',
                        'total_mensualidad' => 'Total mensualidad',
                        'valor_total_proforma' => 'Valor total proforma',
                    ] as $key => $label)
                        <label class="block">
                            <span class="text-slate-500">{{ $label }}</span>
                            <input type="number" step="0.01" value="{{ $formData[$key] ?? 0 }}" readonly
                                   class="mt-1 w-full rounded border-slate-200 bg-slate-100 text-slate-700">
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pt-2 flex flex-wrap gap-3">
                <button type="submit" name="accion" value="recalcular" class="inline-flex items-center rounded bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Recalcular
                </button>
                <button type="submit" name="accion" value="guardar" class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar revisión
                </button>
                <button type="submit" name="accion" value="generar" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Generar proforma
                </button>
                <span class="inline-flex items-center rounded bg-amber-100 px-3 py-2 text-xs text-amber-700">Envío por correo: pendiente para siguiente fase.</span>
            </div>
        </section>
    </form>
</div>
</body>
</html>
