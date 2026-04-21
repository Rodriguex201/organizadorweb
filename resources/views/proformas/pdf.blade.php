<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma #{{ $cabecera->nro_prof ?? $cabecera->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }

        .header { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header td { vertical-align: top; border: none; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 8px; }
        .subtle { color: #6b7280; }
        .section-title { font-size: 13px; font-weight: bold; letter-spacing: .3px; margin: 14px 0 6px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .card { border: 1px solid #e5e7eb; border-radius: 4px; }
        .card td, .card th { border: 1px solid #e5e7eb; padding: 7px 8px; }
        .card th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .money { text-align: right; white-space: nowrap; }
        .total-box { margin-top: 14px; border: 1px solid #d1d5db; background: #f9fafb; padding: 10px 12px; }
        .total-title { font-size: 12px; font-weight: bold; text-transform: uppercase; color: #374151; }
        .total-value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 4px; }
        .footer-note { margin-top: 14px; font-size: 10px; color: #4b5563; }
        .payment-note { margin-top: 10px; font-size: 10px; color: #374151; line-height: 1.4; }
    </style>
</head>
<body>
@php
    $valorTotal = (float) ($cabecera->vtotal ?? 0);
    $clientePdf = $cliente_pdf ?? ['direccion' => 'N/D', 'telefono' => 'N/D', 'correo' => 'N/D'];
    $emisoraPdf = strtoupper(trim((string) ($cabecera->emisora ?? 'SAS')));
$mensajesPagoPorEmisora = [
    'SAS' => '
        Favor consignar o transferir a nombre de: 
        <strong>RM SOFT Casa de Software SAS</strong><br>
        NIT: <strong>900770401-8</strong><br>
        Banco: <strong>Bancolombia</strong><br>
        Cuenta Ahorros: <strong># 85131975584</strong><br>
        Envíe soporte a: <strong>cartera.rmsoft1@gmail.com</strong><br>
        con los datos del cliente y su valor abonado.<br>
        Confirmado el pago, a vuelta.<br>
        Impreso por el Software CAO 6.0 (www.rmsoft.com.co).
    ',

    'PCS' => '
        Favor consignar o transferir a nombre de: 
        <strong>Maria Edilma Carranza Leon</strong><br>
        NIT: <strong>1004994836-0</strong><br>
        Banco: <strong>Bancolombia</strong><br>
        Cuenta Ahorros: <strong>851-0000-4419</strong><br>
        Envíe soporte a: <strong>cartera.rmsoft1@gmail.com</strong><br>
        con los datos del cliente, facturas y su valor abonado.<br>
        Impreso por el Software CAO 6.0 (www.rmsoft.com.co).
    ',

    'SMP' => '
        Favor consignar o transferir a nombre de: 
        <strong>Raúl Osvaldo Ramos M.</strong><br>
        NIT: <strong>75036432-7</strong><br>
        Banco: <strong>Bancolombia</strong><br>
        Cuenta Ahorros: <strong>851-0000-2888</strong><br>
        Envíe soporte a: <strong>cartera.rmsoft1@gmail.com</strong><br>
        con los datos del cliente, facturas y su valor abonado.
    ',
];
    $mensajePago = $mensajesPagoPorEmisora[$emisoraPdf] ?? $mensajesPagoPorEmisora['SAS'];
@endphp

<table class="header">
    <tr>
        <td style="width: 60%;">
            <div class="title">PROFORMA #{{ $cabecera->nro_prof ?? $cabecera->id }}</div>
            <div><strong>Emisora:</strong> {{ $cabecera->emisora ?? 'N/D' }}</div>
            <div><strong>Fecha:</strong> {{ $fecha_emision }}</div>
            <div><strong>Mes/Año:</strong> {{ ucfirst((string) $mes_nombre) }} / {{ $cabecera->anio ?? 'N/D' }}</div>
            <div><strong>Forma de pago:</strong> {{ $cabecera->fpago ?? 'N/D' }}</div>
        </td>
        <td style="width: 40%; text-align: right;">
            @if($logo_path)
                <img src="{{ $logo_path }}" alt="Logo {{ $cabecera->emisora }}" style="max-height: 74px;">
            @else
                <div class="subtle">Logo {{ $cabecera->emisora ?? 'N/D' }} pendiente</div>

            @endif
        </td>
    </tr>
</table>


<div class="section-title">Datos del cliente</div>
<table class="card">
    <tr>
        <th style="width: 20%;">Empresa</th>
        <td style="width: 30%;">{{ $cabecera->emp ?? 'N/D' }}</td>
        <th style="width: 20%;">NIT</th>
        <td style="width: 30%;">{{ $cabecera->nit ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Dirección</th>
        <td>{{ $clientePdf['direccion'] }}</td>
        <th>Teléfono</th>
        <td>{{ $clientePdf['telefono'] }}</td>
    </tr>
    <tr>
        <th>Correo</th>
        <td colspan="3">{{ $clientePdf['correo'] }}</td>

    </tr>
</table>

<div class="section-title">Detalle</div>

<table class="card">
    <thead>
    <tr>
        <th style="width: 14%;">Referencia</th>
        <th style="width: 42%;">Descripción</th>
        <th class="text-center" style="width: 10%;">Cantidad</th>
        <th class="text-right" style="width: 17%;">Vr Unidad</th>
        <th class="text-right" style="width: 17%;">Valor Parcial</th>

    </tr>
    </thead>
    <tbody>
    @forelse($detalle as $linea)
        <tr>
            <td>{{ $linea->ref_codigo }}</td>
            <td>{{ $linea->descripcion }}</td>

            <td class="text-center">{{ number_format((float) $linea->cantidad, 2, ',', '.') }}</td>
            <td class="money">{{ number_format((float) $linea->vr_unidad, 2, ',', '.') }}</td>
            <td class="money">{{ number_format((float) $linea->vr_parcial, 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="subtle">Sin líneas de detalle.</td>

        </tr>
    @endforelse
    </tbody>
</table>

<div class="payment-note">
   {!! $mensajePago !!}
</div>

<div class="section-title">Total en letras</div>
<div class="subtle">{{ $cabecera->total_letras ?? 'Pendiente de configuración.' }}</div>

<div class="total-box">
    <div class="total-title">Total Proforma</div>
    <div class="total-value">$ {{ number_format($valorTotal, 2, ',', '.') }}</div>
</div>

<div class="footer-note">
    Esta proforma debe pagarse en su totalidad para habilitar la prestación del servicio.
</div>

</body>
</html>
