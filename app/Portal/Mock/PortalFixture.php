<?php

namespace App\Portal\Mock;

final class PortalFixture
{
    public static function proforma(): array
    {
        $lineas = [
            ['concepto' => 'Licencia de software', 'descripcion' => 'Mensualidad de tu servicio RM Soft', 'cantidad' => 1, 'unitario' => 120000],
            ['concepto' => 'Terminal adicional', 'descripcion' => 'Acceso para tu equipo de trabajo', 'cantidad' => 2, 'unitario' => 25000],
            ['concepto' => 'Facturación electrónica', 'descripcion' => 'Documentos del período', 'cantidad' => 100, 'unitario' => 150],
            ['concepto' => 'Servicio página web', 'descripcion' => 'Mensualidad de tu sitio web', 'cantidad' => 1, 'unitario' => 45000],
        ];
        foreach ($lineas as &$linea) {
            $linea['subtotal'] = $linea['cantidad'] * $linea['unitario'];
        }
        unset($linea);

        return [
            'numero' => 'PF-2026-001284',
            'documento' => '900123456',
            'empresa' => 'Comercializadora Horizonte',
            'contacto' => 'Cliente de demostración',
            'periodo' => 'Octubre de 2026',
            'emision' => '01 oct 2026',
            'vencimiento' => '31 oct 2026',
            'lineas' => $lineas,
            'total' => array_sum(array_column($lineas, 'subtotal')),
        ];
    }
}
