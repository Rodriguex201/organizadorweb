<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma #{{ $cabecera->nro_prof ?? $cabecera->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .header { width: 100%; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 8px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        .text-right { text-align: right; }
        .section-title { font-size: 14px; font-weight: bold; margin: 14px 0 6px; }
        .totals td:first-child { width: 80%; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td style="width: 55%;">
            <div class="title">PROFORMA #{{ $cabecera->nro_prof ?? $cabecera->id }}</div>
            <div><strong>Emisora:</strong> {{ $cabecera->emisora ?? 'N/D' }}</div>
            <div><strong>Fecha:</strong> {{ $fecha_emision }}</div>
        </td>
        <td style="width: 45%; text-align: right;">
            @if($logo_path)
                <img src="{{ $logo_path }}" alt="Logo {{ $cabecera->emisora }}" style="max-height: 70px;">
            @else
                <div class="muted">Logo {{ $cabecera->emisora ?? 'N/D' }} pendiente</div>
            @endif
        </td>
    </tr>
</table>

<div class="section-title">Datos cliente</div>
<table>
    <tr>
        <td><strong>Cliente / Empresa</strong></td>
        <td>{{ $cabecera->emp ?? 'N/D' }}</td>
        <td><strong>NIT</strong></td>
        <td>{{ $cabecera->nit ?? 'N/D' }}</td>
    </tr>
    <tr>
        <td><strong>Mes / Año</strong></td>
        <td>{{ ucfirst((string) $mes_nombre) }} / {{ $cabecera->anio ?? 'N/D' }}</td>
        <td><strong>Forma de pago</strong></td>
        <td>{{ $cabecera->fpago ?? 'N/D' }}</td>
    </tr>
</table>

<div class="section-title">Detalle</div>
<table>
    <thead>
    <tr>
        <th>Ref</th>
        <th>Descripción</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Vr. unidad</th>
        <th class="text-right">Vr. parcial</th>
        <th>Moneda</th>
    </tr>
    </thead>
    <tbody>
    @forelse($detalle as $linea)
        <tr>
            <td>{{ $linea->ref_codigo }}</td>
            <td>{{ $linea->descripcion }}</td>
            <td class="text-right">{{ number_format((float) $linea->cantidad, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $linea->vr_unidad, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $linea->vr_parcial, 2, ',', '.') }}</td>
            <td>{{ $linea->moneda }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="muted">Sin líneas de detalle.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<div class="section-title">Totales</div>
<table class="totals">
    <tr><td>vlr_mens</td><td class="text-right">{{ number_format((float) ($cabecera->vlr_mens ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vlr_nom</td><td class="text-right">{{ number_format((float) ($cabecera->vlr_nom ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vlr_fe</td><td class="text-right">{{ number_format((float) ($cabecera->vlr_fe ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vlr_rec</td><td class="text-right">{{ number_format((float) ($cabecera->vlr_rec ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vlr_sop</td><td class="text-right">{{ number_format((float) ($cabecera->vlr_sop ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vext1</td><td class="text-right">{{ number_format((float) ($cabecera->vext1 ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td>vext2</td><td class="text-right">{{ number_format((float) ($cabecera->vext2 ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td><strong>vtotal</strong></td><td class="text-right"><strong>{{ number_format((float) ($cabecera->vtotal ?? 0), 2, ',', '.') }}</strong></td></tr>
</table>
</body>
</html>
