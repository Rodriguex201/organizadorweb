<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientePotencial extends Model
{
    public const ESTADO_FACTURACION_PENDIENTE = 'PENDIENTE';
    public const ESTADO_FACTURACION_ACTIVO = 'ACTIVO';

    protected $table = 'clientes_potenciales';

    protected $primaryKey = 'idclientes_potenciales';

    public $timestamps = false;

    /**
     * @return list<string>
     */
    public static function estadosFacturacion(): array
    {
        return [
            self::ESTADO_FACTURACION_PENDIENTE,
            self::ESTADO_FACTURACION_ACTIVO,
        ];
    }

    public static function normalizeEstadoFacturacion(?string $estado, string $default = self::ESTADO_FACTURACION_ACTIVO): string
    {
        $normalized = strtoupper(trim((string) $estado));

        return in_array($normalized, self::estadosFacturacion(), true)
            ? $normalized
            : $default;
    }

    public static function isPendiente(?string $estado): bool
    {
        return self::normalizeEstadoFacturacion($estado) === self::ESTADO_FACTURACION_PENDIENTE;
    }
}
