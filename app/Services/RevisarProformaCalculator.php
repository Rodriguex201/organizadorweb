<?php

namespace App\Services;

class RevisarProformaCalculator
{
    public function calculate(array $input): array
    {
        $data = $this->normalize($input);

        $data['total_facturas'] = $data['facturas'] + $data['nota_debito'] + $data['nota_credito'];
        $data['valor_facturas'] = $data['facturas'] * $data['precio_factura'];

        $data['total_documentos'] = $data['soporte'] + $data['nota_ajuste'];
        $data['valor_documentos'] = $data['soporte'] * $data['precio_soporte'];

        $data['valor_acuse'] = $data['acuse'] * $data['precio_acuse'];

        $equiposAdicionales = max($data['numero_equipos'] - 1, 0);

        $data['total_mensualidad'] = $data['valor_principal']
            + ($data['valor_terminal'] * $equiposAdicionales)
            + ($data['valor_equipo_extra'] * $data['numero_equipos_extra'])
            + $data['valor_nomina'];

        $data['valor_total_proforma'] = $data['total_mensualidad']
            + $data['valor_facturas']
            + $data['valor_documentos']
            + $data['valor_acuse']
            + $data['otro_valor_extra']
            + $data['valor_terminal_recepcion'];

        return $data;
    }

    private function normalize(array $input): array
    {
        $keys = [
            'numero_equipos',
            'valor_principal',
            'valor_terminal',
            'numero_equipos_extra',
            'valor_equipo_extra',
            'empleados',
            'valor_nomina',
            'numero_moviles',
            'valor_movil',
            'facturas',
            'nota_debito',
            'nota_credito',
            'soporte',
            'nota_ajuste',
            'acuse',
            'otro_valor_extra',
            'valor_terminal_recepcion',
            'precio_factura',
            'precio_soporte',
            'precio_acuse',
        ];

        $normalized = [];

        foreach ($keys as $key) {
            $normalized[$key] = (float) ($input[$key] ?? 0);
        }

        return $normalized;
    }
}
