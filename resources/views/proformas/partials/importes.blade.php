@php
    $snapshot = (object) $importes;
    $conDesglose = isset($snapshot->valor_original, $snapshot->retencion_porcentaje, $snapshot->retencion_valor);
@endphp
@if($conDesglose)
    <div>Valor original: <strong>{{ number_format((float) $snapshot->valor_original, 2, ',', '.') }}</strong></div>
    <div>Retención en la fuente ({{ number_format((float) $snapshot->retencion_porcentaje, 3, ',', '.') }} %):
        <strong>− {{ number_format((float) $snapshot->retencion_valor, 2, ',', '.') }}</strong>
    </div>
@else
    <div class="text-xs text-slate-500">Proforma histórica: sin desglose de retención registrado.</div>
@endif
<div class="importes-total">Total a pagar: <strong>{{ number_format((float) ($snapshot->vtotal ?? 0), 2, ',', '.') }}</strong></div>
