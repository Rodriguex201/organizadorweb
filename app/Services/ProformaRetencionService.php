<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProformaRetencionService
{
    public function porcentajeParaNuevaProforma(object $cobro): string
    {
        // Resolve by the exact client linked to the cobro, never by name or NIT.
        $cliente = DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', (int) ($cobro->cliente_id ?? 0))
            ->first(['retencion_fuente_id']);
        if (!$cliente) {
            throw ValidationException::withMessages(['retencion_fuente_id' => 'No se pudo verificar el cliente de la proforma.']);
        }
        if ($cliente->retencion_fuente_id === null) {
            return '0.000';
        }
        $retencion = DB::table('config_retenciones_fuente')->where('id', $cliente->retencion_fuente_id)->first();
        if (!$retencion || (int) $retencion->activo !== 1) {
            throw ValidationException::withMessages(['retencion_fuente_id' => 'La retención del cliente está inactiva o no existe. Corrige su configuración antes de generar la proforma.']);
        }
        return (string) $retencion->porcentaje;
    }
}
