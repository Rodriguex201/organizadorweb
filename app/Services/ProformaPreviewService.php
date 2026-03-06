<?php

namespace App\Services;

use App\DataTransferObjects\LineaProforma;

class ProformaPreviewService
{
    /**
     * Construye una proforma en memoria desde un cobro.
     * TODO (fase siguiente): persistir cabecera en sg_proform y líneas en sg_proford.
     */
    public function buildFromCobro(object $cobro): array
    {
        $lineas = $this->construirLineas($cobro);
        $lineasArray = array_map(fn (LineaProforma $linea) => $linea->toArray(), $lineas);

        $totalLineas = array_reduce($lineas, fn (float $acc, LineaProforma $linea) => $acc + $linea->valorParcial(), 0.0);
        $valorTotalCobro = (float) ($cobro->valor_total ?? 0);

        return [
            'cabecera' => [
                'id_cobro' => (int) $cobro->id_cobro,
                'mes' => (string) ($cobro->mes ?? ''),
                'anio' => (string) ($cobro->año ?? ''),
                'proforma_actual' => (int) ($cobro->Proforma ?? 0),
                'empresa_emisora' => config('app.name', 'Organizadorweb'),
                'cliente' => [
                    'id' => $cobro->cliente_id ?? null,
                    'empresa' => $this->resolveEmpresaCliente($cobro),
                    'nombre' => $cobro->cliente_nombre ?? null,
                    'contacto' => $cobro->cliente_contacto ?? null,
                    'nit' => $cobro->cliente_nit ?? null,
                    'codigo' => $cobro->cliente_codigo ?? null,
                    'email' => $cobro->cliente_email ?? null,
                    'direccion' => $cobro->cliente_direccion ?? null,
                    'regimen' => $cobro->cliente_regimen ?? null,
                    'modalidad' => $cobro->cliente_modalidad ?? null,
                    'categoria' => $cobro->cliente_categoria ?? null,
                ],
            ],
            'detalle' => [
                'lineas' => $lineasArray,
                'total_calculado' => $totalLineas,
                'total_cobro' => $valorTotalCobro,
                'total_preview' => $valorTotalCobro > 0 ? $valorTotalCobro : $totalLineas,
            ],
        ];
    }

    /**
     * @return array<int, LineaProforma>
     */
    private function construirLineas(object $cobro): array
    {
        $lineas = [];
        $camposMonetarios = [
            'valor_total',
            'valor',
            'subtotal',
            'impuesto',
            'iva',
            'descuento',
        ];

        foreach ($camposMonetarios as $campo) {
            if (!property_exists($cobro, $campo)) {
                continue;
            }

            $valor = (float) $cobro->{$campo};
            if ($valor <= 0) {
                continue;
            }

            $lineas[] = new LineaProforma(
                concepto: $this->normalizarConcepto($campo, $cobro),
                cantidad: 1,
                valorUnitario: $valor,
            );
        }

        if ($lineas !== []) {
            return $lineas;
        }

        return [
            new LineaProforma(
                concepto: $this->normalizarConcepto('valor_total', $cobro),
                cantidad: 1,
                valorUnitario: (float) ($cobro->valor_total ?? 0),
            ),
        ];
    }

    private function normalizarConcepto(string $campo, object $cobro): string
    {
        if ($campo === 'valor_total') {
            $mes = ucfirst((string) ($cobro->mes ?? ''));
            $anio = (string) ($cobro->año ?? '');

            return trim("Servicio cobrado {$mes} {$anio}");
        }

        return ucfirst(str_replace('_', ' ', $campo));
    }

    private function resolveEmpresaCliente(object $cobro): ?string
    {
        $empresa = trim((string) ($cobro->cliente_empresa ?? ''));
        if ($empresa !== '') {
            return $empresa;
        }

        $nombre = trim((string) ($cobro->cliente_nombre ?? ''));

        return $nombre !== '' ? $nombre : null;
    }
}
