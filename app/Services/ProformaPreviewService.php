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
        $totalPreview = $this->calcularTotalPreview($cobro);

        return [
            'cabecera' => [
                'id_cobro' => (int) $cobro->id_cobro,
                'mes' => (string) ($cobro->mes ?? ''),
                'anio' => (string) ($cobro->año ?? ''),
                'proforma_actual' => (int) ($cobro->Proforma ?? 0),
                'empresa_emisora' => $this->resolverEmpresaEmisora($cobro),
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
                'total_preview' => $totalPreview,
            ],
        ];
    }

    /**
     * @return array<int, LineaProforma>
     */
    private function construirLineas(object $cobro): array
    {
        $lineas = [];
        $valorMensualidad = $this->toFloat($cobro->valor_mensualidad ?? null);
        $valorNomina = $this->toFloat($cobro->vlrnomina ?? null);

        if ($valorMensualidad > 0) {
            $lineas[] = new LineaProforma(
                codigo: '0010',
                concepto: 'Mensualidad SaaS',
                cantidad: 1,
                valorUnitario: $valorMensualidad - $valorNomina,
            );
        }

        if ($valorNomina > 0) {
            $lineas[] = new LineaProforma(
                codigo: '0099',
                concepto: 'Nómina electrónica',
                cantidad: 1,
                valorUnitario: $valorNomina,
            );
        }

        $numeroFacturas = $this->toFloat($cobro->numero_facturas ?? null);
        if ($numeroFacturas > 0) {
            $cantidadFacturas = $numeroFacturas
                + $this->toFloat($cobro->numero_nota_debito ?? null)
                + $this->toFloat($cobro->numero_nota_credito ?? null);

            $lineas[] = new LineaProforma(
                codigo: '0081',
                concepto: 'Facturación electrónica',
                cantidad: $cantidadFacturas,
                valorUnitario: $this->toFloat($cobro->cliente_vlrfactura ?? null),
                valorParcialOverride: $this->toFloat($cobro->valor_facturas ?? null),
            );
        }

        $numeroAcuse = $this->toFloat($cobro->numero_acuse ?? null);
        if ($numeroAcuse > 0) {
            $valorUnitarioAcuse = $this->toFloat($cobro->cliente_vlrecepcion ?? null);
            $valorAcuse = $this->toFloat($cobro->valor_acuse ?? null);

            $lineas[] = new LineaProforma(
                codigo: '0101',
                concepto: 'Recepción compras',
                cantidad: $numeroAcuse,
                valorUnitario: $valorUnitarioAcuse,
                valorParcialOverride: $valorAcuse > 0 ? $valorAcuse : ($numeroAcuse * $valorUnitarioAcuse),
            );
        }

        $numeroDocumentosSoporte = $this->toFloat($cobro->numero_documento_soporte ?? null);
        if ($numeroDocumentosSoporte > 0) {
            $valorUnitarioSoporte = $this->toFloat($cobro->cliente_vlrsoporte ?? null);
            $valorDocumentos = $this->toFloat($cobro->valor_documentos ?? null);

            $lineas[] = new LineaProforma(
                codigo: '0102',
                concepto: 'Soporte electrónico',
                cantidad: $numeroDocumentosSoporte,
                valorUnitario: $valorUnitarioSoporte,
                valorParcialOverride: $valorDocumentos > 0 ? $valorDocumentos : ($numeroDocumentosSoporte * $valorUnitarioSoporte),
            );
        }

        $valorExtra = $this->toFloat($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? null);
        if ($valorExtra > 0) {
            $lineas[] = new LineaProforma(
                codigo: 'EXTRA',
                concepto: 'Cargo extra manual',
                cantidad: 1,
                valorUnitario: $valorExtra,
            );
        }

        // Línea temporalmente desactivada: pendiente código oficial en tabla conceptos.
        // $valorTerminalRecepcion = $this->toFloat($cobro->cliente_vlrextra2 ?? null);
        // if ($valorTerminalRecepcion > 0) {
        //     $lineas[] = new LineaProforma(
        //         codigo: '01',
        //         concepto: 'VALOR TERMINAL RECEPCIÓN',
        //         cantidad: 1,
        //         valorUnitario: $valorTerminalRecepcion,
        //     );
        // }

        return $lineas;
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

    private function resolverEmpresaEmisora(object $cobro): string
    {
        $regimen = strtoupper(trim((string) ($cobro->cliente_regimen ?? '')));

        return match ($regimen) {
            'PCS' => 'PCS',
            'SMP' => 'SMP',
            default => 'SAS',
        };
    }

    private function calcularTotalPreview(object $cobro): float
    {
        return $this->toFloat($cobro->valor_mensualidad ?? null)
            + $this->toFloat($cobro->valor_facturas ?? null)
            + $this->toFloat($cobro->valor_documentos ?? null)
            + $this->toFloat($cobro->valor_acuse ?? null)
            + $this->toFloat($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? null);
            // + $this->toFloat($cobro->cliente_vlrextra2 ?? null); // Pendiente código oficial.
    }

    private function toFloat(mixed $valor): float
    {
        return (float) ($valor ?? 0);
    }
}
