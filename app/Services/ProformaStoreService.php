<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProformaStoreService
{

    private const CODIGOS_CONCEPTO_OFICIALES = ['0010', '0011', '0099', '0081', '0101', '0102', 'EXTRA'];


    private const MESES_ES = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    public function __construct(
        private readonly ProformaPreviewService $proformaPreviewService,
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
        private readonly ConceptosCatalogService $conceptosCatalogService,
    ) {
    }


    public function findExistingProformaIdFromCobro(object $cobro): ?int
    {
        $nit = trim((string) ($cobro->cliente_nit ?? ''));
        $mesTexto = trim((string) ($cobro->mes ?? ''));
        $mes = $this->normalizarMesParaProforma($mesTexto);
        $anio = (int) ($cobro->año ?? 0);
        $emisora = $this->resolverEmpresaEmisoraDesdeRegimen($cobro);

        $proforma = DB::table('sg_proform')
            ->select('id')
            ->where('nit', $nit)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->where('emisora', $emisora)
            ->first();

        return $proforma ? (int) $proforma->id : null;
    }

    public function storeFromCobro(object $cobro, array $extraConcepto = []): array
    {
        return DB::transaction(function () use ($cobro, $extraConcepto) {
            $preview = $this->proformaPreviewService->buildFromCobro($cobro);
            $revision = $this->revisarProformaCalculator->calculate($this->mapCobroToCalculationData($cobro));

            $nit = trim((string) ($cobro->cliente_nit ?? ''));
            $mesTexto = trim((string) ($cobro->mes ?? ''));
            $mes = $this->normalizarMesParaProforma($mesTexto);
            $anio = (int) ($cobro->año ?? 0);
            $emisora = (string) ($preview['cabecera']['empresa_emisora'] ?? 'SAS');

            $proformaExistente = DB::table('sg_proform')
                ->where('nit', $nit)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->where('emisora', $emisora)
                ->first();

            if ($proformaExistente !== null) {

                $lineas = $this->garantizarLineaValorExtra(
                    $preview['detalle']['lineas'] ?? [],
                    $cobro,
                    $extraConcepto,
                );
                $totalPreview = $this->calcularTotalDesdeLineas($lineas);

                $this->actualizarCabeceraProformaExistente((int) $proformaExistente->id, $cobro, $preview, $revision);
                $this->actualizarTotalCabecera((int) $proformaExistente->id, $totalPreview);
                $this->actualizarValoresExternosDesdeRevision($cobro, $revision);
                $this->reemplazarDetalleProforma((int) $proformaExistente->id, $lineas);
                $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro);

                return [
                    'created' => false,
                    'duplicated' => true,
                    'proforma_id' => $proformaExistente->id ?? null,
                    'message' => 'La proforma ya existía para NIT, mes, año y emisora. Se actualizó cabecera y detalle con los valores vigentes.',
                ];
            }

            $nroProf = $this->resolverNumeroProforma($emisora, $anio);

            $lineas = $this->garantizarLineaValorExtra(
                $preview['detalle']['lineas'] ?? [],
                $cobro,
                $extraConcepto,
            );
            $totalPreview = $this->calcularTotalDesdeLineas($lineas);


            $cabecera = [
                'nit' => $nit,
                'emp' => $this->resolveEmpresaCliente($cobro),
                'emisora' => $emisora,
                'fpago' => null,
                'mes' => $mes,
                'anio' => $anio,
                'nro_prof' => $nroProf,
                'estado' => 2,
                'vlr_mens' => (float) ($revision['total_mensualidad'] ?? 0),
                'vlr_nom' => (float) ($cobro->vlrnomina ?? 0),
                'vlr_fe' => (float) ($cobro->valor_facturas ?? 0),
                'vlr_rec' => (float) ($cobro->valor_acuse ?? 0),
                'vlr_sop' => (float) ($cobro->valor_documentos ?? 0),
                'vext1' => (float) ($cobro->cliente_vlrextra ?? 0),
                'vext2' => (float) ($cobro->cliente_vlrextra2 ?? 0),
                'vtotal' => $totalPreview,
                'cfe' => (float) ($cobro->numero_facturas ?? 0),
                'csop' => (float) ($cobro->numero_documento_soporte ?? 0),
                'crec' => (float) ($cobro->numero_acuse ?? 0),
                'cnom' => (float) (($cobro->vlrnomina ?? 0) > 0 ? 1 : 0),
                // Punto de integración futura de PDF/hash
                'rpdf' => null,
                'npdf' => null,
                'hpdf' => null,
            ];

            if (Schema::hasColumn('sg_proform', 'id_cobro')) {
                $cabecera['id_cobro'] = (int) ($cobro->id_cobro ?? 0) ?: null;
            }

            $proformaId = (int) DB::table('sg_proform')->insertGetId($cabecera);

            $detalleRows = $this->construirDetalleRows($proformaId, $lineas);

            if ($detalleRows !== []) {
                DB::table('sg_proford')->insert($detalleRows);
            }

            $this->actualizarValoresExternosDesdeRevision($cobro, $revision);
            $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro);

            return [
                'created' => true,
                'duplicated' => false,
                'proforma_id' => $proformaId,
                'message' => 'Proforma guardada correctamente en sg_proform y sg_proford.',
            ];
        });
    }

    /**
     * Implementación aislada de consecutivo. Si luego se descubre la lógica exacta del Java,
     * ajustar aquí sin impactar el resto del flujo.
     */
    private function resolverNumeroProforma(string $emisora, int $anio): int
    {
        $max = DB::table('sg_proform')
            ->where('emisora', $emisora)
            ->where('anio', $anio)
            ->max('nro_prof');

        return ((int) $max) + 1;
    }

    private function marcarCobroComoProformaGenerada(int $idCobro): void
    {
        DB::table('valores_externos')
            ->where('id_cobro', $idCobro)
            ->update([
                'Proforma' => 2,
                'valor_extra' => 0,
            ]);
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



    private function resolverEmpresaEmisoraDesdeRegimen(object $cobro): string
    {
        $regimen = strtoupper(trim((string) ($cobro->cliente_regimen ?? '')));

        return match ($regimen) {
            'PCS' => 'PCS',
            'SMP' => 'SMP',
            default => 'SAS',
        };
    }


    private function normalizarMesParaProforma(null|string|int $mes): ?int
    {
        if ($mes === null) {
            return null;
        }

        $valor = trim((string) $mes);
        if ($valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $mesNumero = (int) $valor;

            return ($mesNumero >= 1 && $mesNumero <= 12) ? $mesNumero : null;
        }

        $mesNumero = self::MESES_ES[mb_strtolower($valor)] ?? null;

        return $mesNumero;
    }

    private function actualizarCabeceraProformaExistente(int $proformaId, object $cobro, array $preview, array $revision): void
    {
        $payload = [
            'emp' => $this->resolveEmpresaCliente($cobro),
            'vlr_mens' => (float) ($revision['total_mensualidad'] ?? 0),
            'vlr_nom' => (float) ($cobro->vlrnomina ?? 0),
            'vlr_fe' => (float) ($cobro->valor_facturas ?? 0),
            'vlr_rec' => (float) ($cobro->valor_acuse ?? 0),
            'vlr_sop' => (float) ($cobro->valor_documentos ?? 0),
            'vext1' => (float) ($cobro->cliente_vlrextra ?? 0),
            'vext2' => (float) ($cobro->cliente_vlrextra2 ?? 0),
            'vtotal' => (float) ($preview['detalle']['total_preview'] ?? 0),
            'cfe' => (float) ($cobro->numero_facturas ?? 0),
            'csop' => (float) ($cobro->numero_documento_soporte ?? 0),
            'crec' => (float) ($cobro->numero_acuse ?? 0),
            'cnom' => (float) (($cobro->vlrnomina ?? 0) > 0 ? 1 : 0),
        ];

        if (Schema::hasColumn('sg_proform', 'id_cobro')) {
            $payload['id_cobro'] = (int) ($cobro->id_cobro ?? 0) ?: null;
        }

        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update($payload);
    }

    private function actualizarTotalCabecera(int $proformaId, float $totalPreview): void
    {
        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update([
                'vtotal' => $totalPreview,
            ]);
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     */
    private function reemplazarDetalleProforma(int $proformaId, array $lineas): void
    {
        DB::table('sg_proford')
            ->where('proforma_id', $proformaId)
            ->delete();

        $detalleRows = $this->construirDetalleRows($proformaId, $lineas);
        if ($detalleRows !== []) {
            DB::table('sg_proford')->insert($detalleRows);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     * @return array<int, array<string, mixed>>
     */
    private function construirDetalleRows(int $proformaId, array $lineas): array
    {
        $catalogoConceptos = $this->obtenerCatalogoConceptos();
        $detalleRows = [];
        foreach ($lineas as $index => $linea) {
            $codigoLinea = (string) ($linea['codigo'] ?? '');

            // Código de prueba deshabilitado temporalmente (sin código oficial aún en tabla conceptos).
            if ($codigoLinea === '01') {
                continue;
            }

            $concepto = $this->resolverConceptoDesdeCatalogo(
                $codigoLinea,
                (string) ($linea['concepto'] ?? ''),
                $catalogoConceptos,
            );

            $detalleRows[] = [
                'proforma_id' => $proformaId,
                'ref_codigo' => trim((string) ($linea['codigo_mostrado'] ?? '')) !== ''
                    ? trim((string) $linea['codigo_mostrado'])
                    : $concepto['codigo'],
                'descripcion' => trim((string) ($linea['descripcion_mostrada'] ?? '')) !== ''
                    ? trim((string) $linea['descripcion_mostrada'])
                    : $concepto['nombre'],
                'cantidad' => (float) ($linea['cantidad'] ?? 0),
                'vr_unidad' => (float) ($linea['valor_unitario'] ?? 0),
                'vr_parcial' => (float) ($linea['valor_parcial'] ?? 0),
                'orden' => $index + 1,
                'moneda' => 'COP',
            ];
        }

        return $detalleRows;
    }


    /**
     * @return array<string, object>
     */
    private function obtenerCatalogoConceptos(): array
    {
        return array_map(
            fn (array $concepto) => (object) $concepto,
            $this->conceptosCatalogService->findByCodes(self::CODIGOS_CONCEPTO_OFICIALES),
        );
    }

    /**
     * @param array<string, object> $catalogoConceptos
     * @return array{codigo:string,nombre:string}
     */
    private function resolverConceptoDesdeCatalogo(string $codigo, string $descripcionFallback, array $catalogoConceptos): array
    {
        if (isset($catalogoConceptos[$codigo])) {
            return [
                'codigo' => (string) $catalogoConceptos[$codigo]->codigo,
                'nombre' => (string) $catalogoConceptos[$codigo]->nombre,
            ];
        }

        Log::warning('Concepto no encontrado en catálogo oficial para detalle de proforma, usando fallback.', [
            'codigo' => $codigo,
            'descripcion_fallback' => $descripcionFallback,
        ]);

        return [
            'codigo' => $codigo,
            'nombre' => $descripcionFallback,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     * @param array<string, mixed> $extraConcepto
     * @return array<int, array<string, mixed>>
     */

    private function garantizarLineaValorExtra(array $lineas, object $cobro, array $extraConcepto): array
    {
        $valorExtra = (float) ($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? 0);
        $codigoExtra = trim((string) ($extraConcepto['codigo_concepto_extra'] ?? ''));

        $descripcionExtra = strtoupper(trim((string) ($extraConcepto['descripcion_concepto_extra'] ?? '')));


        if ($valorExtra <= 0) {
            return $lineas;
        }

        $indexLineaExtra = null;
        foreach ($lineas as $index => &$linea) {

            if ((string) ($linea['codigo'] ?? '') !== 'EXTRA') {
                continue;
            }


            $indexLineaExtra = $index;
            $linea['cantidad'] = 1;
            $linea['valor_unitario'] = $valorExtra;
            $linea['valor_parcial'] = $valorExtra;

            break;
        }
        unset($linea);


        if ($indexLineaExtra === null) {
            $lineas[] = [
                'codigo' => 'EXTRA',
                'concepto' => 'Cargo extra manual',
                'cantidad' => 1,
                'valor_unitario' => $valorExtra,
                'valor_parcial' => $valorExtra,
            ];
        }

        $ultimaPosicion = array_key_last($lineas);
        if ($ultimaPosicion !== null && (string) ($lineas[$ultimaPosicion]['codigo'] ?? '') === 'EXTRA' && $indexLineaExtra === null) {
            $indexLineaExtra = $ultimaPosicion;
        }

        if ($indexLineaExtra !== null) {
            if ($codigoExtra !== '') {
                $lineas[$indexLineaExtra]['codigo_mostrado'] = $codigoExtra;
            }
            if ($descripcionExtra !== '') {
                $lineas[$indexLineaExtra]['descripcion_mostrada'] = $descripcionExtra;
            }
        }

        return $lineas;
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     */
    private function calcularTotalDesdeLineas(array $lineas): float
    {
        return (float) array_reduce(
            $lineas,
            fn (float $acumulado, array $linea) => $acumulado + (float) ($linea['valor_parcial'] ?? 0),
            0.0,
        );
    }

    private function actualizarValoresExternosDesdeRevision(object $cobro, array $revision): void
    {
        $idCobro = (int) ($cobro->id_cobro ?? 0);

        if ($idCobro <= 0) {
            return;
        }

        $payload = [];

        foreach ([
            'numextra' => 'numero_equipos_extra',
            'vlrextrae' => 'valor_equipo_extra',
            'valor_mensualidad' => 'total_mensualidad',
            'valor_total' => 'valor_total_proforma',
        ] as $column => $key) {
            if (!Schema::hasColumn('valores_externos', $column)) {
                continue;
            }

            $payload[$column] = (float) ($revision[$key] ?? 0);
        }

        if ($payload === []) {
            return;
        }

        DB::table('valores_externos')
            ->where('id_cobro', $idCobro)
            ->update($payload);
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
        foreach ([
            $cobro->precio_soporte ?? null,
            $cobro->precio_acuse ?? null,
            $cobro->total_facturas ?? null,
            $cobro->total_documentos ?? null,
            $cobro->valor_terminal_recepcion ?? null,
            $cobro->otro_valor_extra ?? null,
            $cobro->numextra ?? null,
            $cobro->vlrextrae ?? null,
        ] as $valor) {
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


}
