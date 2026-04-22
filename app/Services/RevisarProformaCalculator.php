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
        $data['valor_documentos'] = $data['total_documentos'] * $data['precio_soporte'];

        $data['valor_acuse'] = $data['acuse'] * $data['precio_acuse'];

        $data['total_mensualidad'] = ($data['numero_equipos'] * $data['valor_principal'])
            + ($data['numero_moviles'] * $data['valor_movil'])
            + ($data['empleados'] * $data['valor_nomina'])
            + $data['valor_terminal']
            + $data['valor_terminal_recepcion'];

        $data['valor_total_proforma'] = $data['total_mensualidad']
            + $data['valor_facturas']
            + $data['valor_documentos']
            + $data['valor_acuse']
            + $data['otro_valor_extra'];

        return $data;
    }

    private function normalize(array $input): array
    {
        $keys = [
            'numero_equipos',
            'valor_principal',
            'valor_terminal',
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
