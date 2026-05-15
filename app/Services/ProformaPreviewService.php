<?php

namespace App\Services;

use App\DataTransferObjects\LineaProforma;

class ProformaPreviewService
{
    private const CODIGO_MENSUALIDAD = '0010';
    private const CODIGO_TERMINALES_EXTRA = '0011';
    private const CODIGO_NOMINA = '0099';
    private const CODIGO_FACTURACION = '0081';
    private const CODIGO_RECEPCION = '0101';
    private const CODIGO_SOPORTE = '0102';
    private const CODIGO_EXTRA_MANUAL = 'EXTRA';

    public function __construct(
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
        private readonly ConceptosCatalogService $conceptosCatalogService,
    ) {
    }

    /**
     * Construye una proforma en memoria desde un cobro.
     * TODO (fase siguiente): persistir cabecera en sg_proform y lineas en sg_proford.
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
                'anio' => (string) ($cobro->{'año'} ?? $cobro->{'año'} ?? ''),
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
        $catalogoConceptos = $this->conceptosCatalogService->findByCodes([
            self::CODIGO_MENSUALIDAD,
            self::CODIGO_TERMINALES_EXTRA,
            self::CODIGO_NOMINA,
            self::CODIGO_FACTURACION,
            self::CODIGO_RECEPCION,
            self::CODIGO_SOPORTE,
            self::CODIGO_EXTRA_MANUAL,
        ]);
        $revision = $this->revisarProformaCalculator->calculate($this->mapCobroToCalculationData($cobro));
        $valorMensualidad = $this->toFloat($revision['total_mensualidad'] ?? null);
        $valorNomina = $this->toFloat($revision['valor_nomina'] ?? ($cobro->vlrnomina ?? null));
        $numeroEquiposExtra = $this->toFloat($revision['numero_equipos_extra'] ?? null);
        $valorEquipoExtra = $this->toFloat($revision['valor_equipo_extra'] ?? null);
        $valorMensualidadBase = max($valorMensualidad - $valorNomina - ($numeroEquiposExtra * $valorEquipoExtra), 0);

        if ($valorMensualidadBase > 0) {
            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_MENSUALIDAD, $catalogoConceptos, 'Mensualidad SaaS', [
                'origen' => 'preview_mensualidad',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: 1,
                valorUnitario: $valorMensualidadBase,
            );
        }

        if ($valorNomina > 0) {
            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_NOMINA, $catalogoConceptos, 'Nomina electronica', [
                'origen' => 'preview_nomina',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: 1,
                valorUnitario: $valorNomina,
            );
        }

        if ($numeroEquiposExtra > 0 && $valorEquipoExtra > 0) {
            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_TERMINALES_EXTRA, $catalogoConceptos, null, [
                'origen' => 'preview_terminales_extra',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: $numeroEquiposExtra,
                valorUnitario: $valorEquipoExtra,
            );
        }

        $numeroFacturas = $this->toFloat($cobro->numero_facturas ?? null);
        if ($numeroFacturas > 0) {
            $cantidadFacturas = $numeroFacturas
                + $this->toFloat($cobro->numero_nota_debito ?? null)
                + $this->toFloat($cobro->numero_nota_credito ?? null);

            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_FACTURACION, $catalogoConceptos, 'Facturacion electronica', [
                'origen' => 'preview_facturacion',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: $cantidadFacturas,
                valorUnitario: $this->toFloat($cobro->cliente_vlrfactura ?? null),
                valorParcialOverride: $this->toFloat($cobro->valor_facturas ?? null),
            );
        }

        $numeroAcuse = $this->toFloat($cobro->numero_acuse ?? null);
        if ($numeroAcuse > 0) {
            $valorUnitarioAcuse = $this->toFloat($cobro->cliente_vlrecepcion ?? null);
            $valorAcuse = $this->toFloat($cobro->valor_acuse ?? null);

            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_RECEPCION, $catalogoConceptos, 'Recepcion compras', [
                'origen' => 'preview_recepcion',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: $numeroAcuse,
                valorUnitario: $valorUnitarioAcuse,
                valorParcialOverride: $valorAcuse > 0 ? $valorAcuse : ($numeroAcuse * $valorUnitarioAcuse),
            );
        }

        $numeroDocumentosSoporte = $this->toFloat($cobro->numero_documento_soporte ?? null);
        if ($numeroDocumentosSoporte > 0) {
            $valorUnitarioSoporte = $this->toFloat($cobro->cliente_vlrsoporte ?? null);
            $valorDocumentos = $this->toFloat($cobro->valor_documentos ?? null);

            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_SOPORTE, $catalogoConceptos, 'Soporte electronico', [
                'origen' => 'preview_soporte',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: $numeroDocumentosSoporte,
                valorUnitario: $valorUnitarioSoporte,
                valorParcialOverride: $valorDocumentos > 0 ? $valorDocumentos : ($numeroDocumentosSoporte * $valorUnitarioSoporte),
            );
        }

        $valorExtra = $this->toFloat($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? null);
        if ($valorExtra > 0) {
            $concepto = $this->resolverConceptoDesdeCatalogo(self::CODIGO_EXTRA_MANUAL, $catalogoConceptos, 'Cargo extra manual', [
                'origen' => 'preview_extra_manual',
                'id_cobro' => (int) ($cobro->id_cobro ?? 0),
            ]);
            $lineas[] = new LineaProforma(
                codigo: $concepto['codigo'],
                concepto: $concepto['nombre'],
                cantidad: 1,
                valorUnitario: $valorExtra,
            );
        }

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
        $revision = $this->revisarProformaCalculator->calculate($this->mapCobroToCalculationData($cobro));

        return $this->toFloat($revision['total_mensualidad'] ?? null)
            + $this->toFloat($cobro->valor_facturas ?? null)
            + $this->toFloat($cobro->valor_documentos ?? null)
            + $this->toFloat($cobro->valor_acuse ?? null)
            + $this->toFloat($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? null);
    }

    private function mapCobroToCalculationData(object $cobro): array
    {
        $existeRevisionGuardada = $this->existeRevisionGuardada($cobro);

        return [
            'numero_equipos' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->numero_equipos ?? null, $cobro->cliente_numequipos ?? null),
            'valor_principal' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_principal ?? null, $cobro->cliente_vlrprincipal ?? null),
            'valor_terminal' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_terminal ?? null, $cobro->cliente_vlrterminal ?? null),
            'numero_equipos_extra' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->numextra ?? null, $cobro->cliente_numextra ?? null),
            'valor_equipo_extra' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->vlrextrae ?? null, $cobro->cliente_vlrextrae ?? null),
            'empleados' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->empleados ?? null, $cobro->cliente_numero_empleados ?? null),
            'valor_nomina' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->vlrnomina ?? null, $cobro->cliente_vlrnomina ?? null),
            'numero_moviles' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->numero_moviles ?? null, $cobro->cliente_numeromoviles ?? null),
            'valor_movil' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_movil ?? null, $cobro->cliente_vlrmovil ?? null),
            'facturas' => (float) ($cobro->numero_facturas ?? 0),
            'nota_debito' => (float) ($cobro->numero_nota_debito ?? 0),
            'nota_credito' => (float) ($cobro->numero_nota_credito ?? 0),
            'soporte' => (float) ($cobro->numero_documento_soporte ?? 0),
            'nota_ajuste' => (float) ($cobro->numero_nota_ajuste ?? 0),
            'acuse' => (float) ($cobro->numero_acuse ?? 0),
            'otro_valor_extra' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->otro_valor_extra ?? null, $cobro->cliente_vlrextra ?? null),
            'valor_terminal_recepcion' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_terminal_recepcion ?? null, $cobro->cliente_vlrextra2 ?? null),
            'precio_factura' => (float) ($cobro->cliente_vlrfactura ?? 0),
            'precio_soporte' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->precio_soporte ?? null, $cobro->cliente_vlrsoporte ?? null),
            'precio_acuse' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->precio_acuse ?? null, $cobro->cliente_vlrecepcion ?? null),
        ];
    }

    private function existeRevisionGuardada(object $cobro): bool
    {
        $indicadores = [
            $cobro->precio_soporte ?? null,
            $cobro->precio_acuse ?? null,
            $cobro->total_facturas ?? null,
            $cobro->total_documentos ?? null,
            $cobro->valor_terminal_recepcion ?? null,
            $cobro->otro_valor_extra ?? null,
            $cobro->numextra ?? null,
            $cobro->vlrextrae ?? null,
        ];

        foreach ($indicadores as $valor) {
            if ($valor !== null) {
                return true;
            }
        }

        return false;
    }

    private function valorRevisionOBase(bool $existeRevisionGuardada, mixed $valorRevision, mixed $valorBase): float
    {
        if ($existeRevisionGuardada && $valorRevision !== null) {
            return (float) $valorRevision;
        }

        if (!$existeRevisionGuardada && $valorBase !== null) {
            return (float) $valorBase;
        }

        if ($valorRevision !== null) {
            return (float) $valorRevision;
        }

        if ($valorBase !== null) {
            return (float) $valorBase;
        }

        return 0.0;
    }

    private function toFloat(mixed $valor): float
    {
        return (float) ($valor ?? 0);
    }

    /**
     * @param array<string, array{codigo:string,nombre:string,cuenta:mixed,activo:mixed}> $catalogoConceptos
     * @param array<string, mixed> $context
     * @return array{codigo:string,nombre:string,cuenta:mixed,activo:mixed,exists:bool}
     */
    private function resolverConceptoDesdeCatalogo(string $codigo, array $catalogoConceptos, ?string $fallbackNombre, array $context): array
    {
        if (isset($catalogoConceptos[$codigo])) {
            return $catalogoConceptos[$codigo] + ['exists' => true];
        }

        return $this->conceptosCatalogService->resolve($codigo, $fallbackNombre, $context);
    }
}
