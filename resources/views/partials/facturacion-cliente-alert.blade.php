@php
    $facturacionCliente = $facturacionCliente ?? [];
    $estadoFacturacion = $facturacionCliente['estado'] ?? \App\Models\ClientePotencial::ESTADO_FACTURACION_ACTIVO;
    $esPendienteFacturacion = (bool) ($facturacionCliente['es_pendiente'] ?? false);
    $clienteFacturacionId = (int) ($facturacionCliente['cliente_id'] ?? 0);
    $fechaInicioFacturacion = $facturacionCliente['fecha_inicio'] ?? null;
@endphp

<div class="rounded-lg border {{ $esPendienteFacturacion ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} px-4 py-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold {{ $esPendienteFacturacion ? 'text-amber-800' : 'text-emerald-800' }}">
                Estado Facturacion:
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $esPendienteFacturacion ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $estadoFacturacion }}
                </span>
            </p>
            <p class="mt-1 text-sm {{ $esPendienteFacturacion ? 'text-amber-700' : 'text-emerald-700' }}">
                @if($esPendienteFacturacion)
                    Este cliente aun no ha iniciado facturacion.
                @else
                    Fecha inicio facturacion: {{ \Illuminate\Support\Carbon::make($fechaInicioFacturacion)?->format('d/m/Y') ?: 'No registrada' }}.
                @endif
            </p>
        </div>

        @if($esPendienteFacturacion && $clienteFacturacionId > 0)
            <form method="POST" action="{{ route('clientes.activar-facturacion', $clienteFacturacionId) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Activar cliente para facturacion
                </button>
            </form>
        @endif
    </div>
</div>
