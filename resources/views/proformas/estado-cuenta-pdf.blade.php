<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ESTADO DE CUENTA CONSOLIDADO</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        .header { margin-bottom: 16px; }
        .header td { vertical-align: top; border: none; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: .5px; margin: 2px 0 8px; }
        .subtle { color: #6b7280; }
        .section-title { font-size: 13px; font-weight: bold; letter-spacing: .3px; margin: 14px 0 6px; text-transform: uppercase; }
        .warning { margin-bottom: 16px; border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; padding: 10px 12px; font-size: 11px; line-height: 1.45; }
        .card { border: 1px solid #e5e7eb; border-radius: 4px; margin-bottom: 14px; }
        .card td, .card th { border: 1px solid #e5e7eb; padding: 7px 8px; }
        .card th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .detail th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .detail td { border: 1px solid #e5e7eb; padding: 8px; }
        .text-right { text-align: right; }
        .money { text-align: right; white-space: nowrap; }
        .total-box { margin-top: 14px; border: 1px solid #d1d5db; background: #f9fafb; padding: 12px 14px; }
        .total-title { font-size: 12px; font-weight: bold; text-transform: uppercase; color: #374151; }
        .total-value { font-size: 24px; font-weight: bold; color: #111827; margin-top: 6px; }
        .bank-box { margin-top: 12px; border: 1px solid #d1d5db; background: #fcfcfd; padding: 10px 12px; line-height: 1.5; }
        .bank-box + .bank-box { margin-top: 10px; }
        .footer { margin-top: 16px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
@php
    $emisoras = $estadoCuenta['emisoras'] ?? ['mode' => 'single', 'items' => [], 'primary' => null, 'codes' => []];
    $emisorPrincipal = $emisoras['primary'] ?? null;
    $esMultiEmisora = ($emisoras['mode'] ?? 'single') === 'multiple';
    $advertenciaFormal = 'Documento informativo generado a partir de proformas existentes. Este estado de cuenta consolidado no modifica estados operativos, no genera facturacion y no reemplaza los documentos originales.';
@endphp

    <table class="header">
        <tr>
            <td style="width: 68%;">
                <div class="subtle">{{ $emisorPrincipal['codigo'] ?? implode(', ', $emisoras['codes'] ?? []) }}</div>
                <div class="title">ESTADO DE CUENTA CONSOLIDADO</div>
                <div><strong>Fecha de generacion:</strong> {{ ($estadoCuenta['fecha_generacion'] ?? now())->format('d/m/Y H:i') }}</div>
                @if($esMultiEmisora)
                    <div><strong>Emisoras incluidas:</strong> {{ implode(', ', $emisoras['codes'] ?? []) }}</div>
                @else
                    <div><strong>{{ $emisorPrincipal['razon_social'] ?? 'N/D' }}</strong></div>
                    <div><strong>NIT:</strong> {{ $emisorPrincipal['nit'] ?? 'N/D' }}</div>
                @endif
            </td>
            <td style="width: 32%; text-align: right;">
                @if(!$esMultiEmisora && !empty($emisorPrincipal['logo_path']))
                    <img src="{{ $emisorPrincipal['logo_path'] }}" alt="Logo {{ $emisorPrincipal['codigo'] ?? 'emisora' }}" style="max-height: 74px;">
                @else
                    <div class="subtle">Consolidado {{ $esMultiEmisora ? 'multiemisora' : 'sin logo disponible' }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="warning">{{ $advertenciaFormal }}</div>

    <div class="section-title">Datos del cliente</div>
    <table class="card">
        <tr>
            <th style="width: 20%;">Empresa</th>
            <td style="width: 30%;">{{ $estadoCuenta['empresa'] ?? 'N/D' }}</td>
            <th style="width: 20%;">NIT</th>
            <td style="width: 30%;">{{ $estadoCuenta['nit'] ?? 'N/D' }}</td>
        </tr>
        <tr>
            <th>Correo</th>
            <td>{{ $estadoCuenta['correo'] ?? 'N/D' }}</td>
            <th>Telefono</th>
            <td>{{ $estadoCuenta['telefono'] ?? 'N/D' }}</td>
        </tr>
    </table>

    <div class="section-title">Resumen del estado de cuenta</div>
    <table class="card">
        <tr>
            <th style="width: 20%;">Periodo inicial</th>
            <td style="width: 30%;">{{ $estadoCuenta['periodo_antiguo'] ?? 'N/D' }}</td>
            <th style="width: 20%;">Periodo final</th>
            <td style="width: 30%;">{{ $estadoCuenta['periodo_reciente'] ?? 'N/D' }}</td>
        </tr>
        <tr>
            <th>Cantidad de proformas</th>
            <td>{{ $estadoCuenta['cantidad_proformas'] ?? 0 }}</td>
            <th>Periodo cubierto</th>
            <td>Desde {{ $estadoCuenta['periodo_antiguo'] ?? 'N/D' }} hasta {{ $estadoCuenta['periodo_reciente'] ?? 'N/D' }}</td>
        </tr>
    </table>

    <div class="section-title">Detalle</div>
    <table class="card detail">
        <thead>
            <tr>
                <th style="width: 22%;">Proforma</th>
                <th style="width: 24%;">Periodo</th>
                <th style="width: 24%;">Estado</th>
                <th style="width: 12%;">Emisora</th>
                <th style="width: 18%;" class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($estadoCuenta['proformas'] ?? []) as $proforma)
                <tr>
                    <td>{{ $proforma['nro_prof'] ?? 'N/D' }}</td>
                    <td>{{ $proforma['periodo'] ?? 'N/D' }}</td>
                    <td>{{ $proforma['estado'] ?? 'N/D' }}</td>
                    <td>{{ $proforma['emisora'] ?? 'N/D' }}</td>
                    <td class="money">$ {{ number_format((float) ($proforma['valor'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <div class="total-title">Total acumulado</div>
        <div class="total-value">$ {{ number_format((float) ($estadoCuenta['total_acumulado'] ?? 0), 2, ',', '.') }}</div>
    </div>

    <div class="section-title">Informacion bancaria</div>
    @foreach(($emisoras['items'] ?? []) as $emisor)
        <div class="bank-box">
            Favor consignar o transferir a nombre de:<br>
            <strong>{{ $emisor['razon_social'] ?? 'N/D' }}</strong><br>
            NIT: <strong>{{ $emisor['nit'] ?? 'N/D' }}</strong><br>
            Banco: <strong>{{ $emisor['banco'] ?? 'N/D' }}</strong><br>
            Cuenta: <strong>{{ ($emisor['cuenta_tipo'] ?? 'Cuenta').' '.($emisor['cuenta_numero'] ?? 'N/D') }}</strong><br>
            Enviar soporte a:<br>
            <strong>{{ $emisor['cartera_email'] ?? 'N/D' }}</strong>
        </div>
    @endforeach

    <div class="footer">
        Este documento se emite con fines informativos y se construye a partir de proformas ya existentes en el sistema.
    </div>
</body>
</html>
