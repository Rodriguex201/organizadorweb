<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
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
        $resolved = $this->resolveExistingProforma($cobro);

        if (($resolved['status'] ?? null) === 'found') {
            return isset($resolved['proforma']->id) ? (int) $resolved['proforma']->id : null;
        }

        if (($resolved['status'] ?? null) === 'multiple_legacy') {
            return null;
        }

        $nit = trim((string) ($cobro->cliente_nit ?? ''));
        $mesTexto = trim((string) ($cobro->mes ?? ''));
        $mes = $this->normalizarMesParaProforma($mesTexto);
        $anio = (int) ($cobro->año ?? 0);
        $emisora = $this->resolverEmpresaEmisoraDesdeRegimen($cobro);
        $idCobro = (int) ($cobro->id_cobro ?? 0);

        $query = DB::table('sg_proform')
            ->select('id')
            ->where('nit', $nit)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->where('emisora', $emisora);

        if (Schema::hasColumn('sg_proform', 'id_cobro') && $idCobro > 0) {
            $query->where('id_cobro', $idCobro);
        }

        $proforma = $query->first();

        return $proforma ? (int) $proforma->id : null;
    }

    public function storeFromCobro(
        object $cobro,
        array $extraConcepto = [],
        bool $preserveExistingEstado = false,
        bool $protectProcessedProforma = false
    ): array
    {
        $startedAt = microtime(true);
        $idCobro = (int) ($cobro->id_cobro ?? 0);

        if ($validationError = $this->validateCobroReference($cobro)) {
            Log::info('Proforma storeFromCobro: validacion bloqueante.', [
                'id_cobro' => $idCobro,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'resultado' => $validationError,
            ]);

            return $validationError;
        }

        $result = DB::transaction(function () use ($cobro, $extraConcepto, $preserveExistingEstado, $protectProcessedProforma) {
            $preview = $this->proformaPreviewService->buildFromCobro($cobro);
            $revision = $this->revisarProformaCalculator->calculate($this->mapCobroToCalculationData($cobro));

            $nit = trim((string) ($cobro->cliente_nit ?? ''));
            $mesTexto = trim((string) ($cobro->mes ?? ''));
            $mes = $this->normalizarMesParaProforma($mesTexto);
            $anio = (int) ($cobro->año ?? 0);
            $emisora = (string) ($preview['cabecera']['empresa_emisora'] ?? 'SAS');
            $idCobro = (int) ($cobro->id_cobro ?? 0);
            $proformaExistente = null;
            $resolved = $this->resolveExistingProforma($cobro, $emisora);

            if (($resolved['status'] ?? null) === 'multiple_legacy') {
                $legacyMatches = collect($resolved['legacy_matches'] ?? [])
                    ->map(fn (object $proforma) => sprintf('#%s (id %s)', (string) ($proforma->nro_prof ?? 'N/D'), (string) ($proforma->id ?? 'N/D')))
                    ->implode(', ');

                return [
                    'created' => false,
                    'duplicated' => false,
                    'blocked' => true,
                    'proforma_id' => null,
                    'message' => sprintf(
                        'Se encontraron múltiples proformas legacy para NIT %s, %s %s y emisora %s. Requiere revisión manual. Coincidencias: %s.',
                        $nit,
                        $this->nombreMesParaMensaje($mes),
                        $anio,
                        $emisora,
                        $legacyMatches,
                    ),
                ];
            }

            if (($resolved['status'] ?? null) === 'found') {
                $proformaExistente = $resolved['proforma'] ?? null;
            }

            if ($proformaExistente === null) {
                $proformaExistenteQuery = DB::table('sg_proform')
                    ->where('nit', $nit)
                    ->where('mes', $mes)
                    ->where('anio', $anio)
                    ->where('emisora', $emisora);

                if (Schema::hasColumn('sg_proform', 'id_cobro') && $idCobro > 0) {
                    $proformaExistenteQuery->where('id_cobro', $idCobro);
                }

                $proformaExistente = $proformaExistenteQuery->first();
            }

            if ($proformaExistente !== null) {
                if ($protectProcessedProforma && $this->shouldProtectExistingProforma($proformaExistente)) {
                    $motivo = $this->buildProtectedProformaReason($proformaExistente);

                    Log::info('Proforma existente protegida omitida en generacion masiva.', [
                        'id_cobro' => $idCobro,
                        'proforma_id' => (int) ($proformaExistente->id ?? 0),
                        'nro_prof' => $proformaExistente->nro_prof ?? null,
                        'estado' => (int) ($proformaExistente->estado ?? 0),
                        'enviado' => (int) ($proformaExistente->enviado ?? 0),
                        'motivo' => $motivo,
                    ]);

                    return [
                        'created' => false,
                        'duplicated' => false,
                        'blocked' => false,
                        'protected' => true,
                        'omitted' => true,
                        'proforma_id' => $proformaExistente->id ?? null,
                        'message' => 'Proforma ya enviada/facturada. Se omite de la generación masiva.',
                    ];
                }

                $extraConcepto = $this->completarConceptoExtraDesdeProformaExistente(
                    (int) $proformaExistente->id,
                    $cobro,
                    $extraConcepto,
                );

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
                $estadoAConservar = $preserveExistingEstado
                    ? (int) ($proformaExistente->estado ?? 2)
                    : null;
                $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro, $estadoAConservar);

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
                'vlr_nom' => (float) ($revision['valor_nomina'] ?? 0),
                'vlr_fe' => (float) ($revision['valor_facturas'] ?? 0),
                'vlr_rec' => (float) ($cobro->valor_acuse ?? 0),
                'vlr_sop' => (float) ($cobro->valor_documentos ?? 0),
                'vext1' => (float) ($revision['otro_valor_extra'] ?? 0),
                'vext2' => (float) ($revision['otro_valor_extra_2'] ?? 0),
                'vtotal' => $totalPreview,
                'cfe' => (float) ($cobro->numero_facturas ?? 0),
                'csop' => (float) ($cobro->numero_documento_soporte ?? 0),
                'crec' => (float) ($cobro->numero_acuse ?? 0),
                'cnom' => (float) (($revision['valor_nomina'] ?? 0) > 0 ? 1 : 0),
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

        Log::info('Proforma storeFromCobro: finalizado.', [
            'id_cobro' => $idCobro,
            'proforma_id' => (int) ($result['proforma_id'] ?? 0),
            'created' => (bool) ($result['created'] ?? false),
            'duplicated' => (bool) ($result['duplicated'] ?? false),
            'protected' => (bool) ($result['protected'] ?? false),
            'omitted' => (bool) ($result['omitted'] ?? false),
            'blocked' => (bool) ($result['blocked'] ?? false),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $result;
    }

    public function regenerateFromCobro(object $cobro, array $extraConcepto = []): array
    {
        $resultado = $this->storeFromCobro($cobro, $extraConcepto, true);

        return $resultado + [
            'regenerated' => true,
        ];
    }

    private function validateCobroReference(object $cobro): ?array
    {
        if (!Schema::hasColumn('sg_proform', 'id_cobro')) {
            return null;
        }

        $idCobro = (int) ($cobro->id_cobro ?? 0);

        if ($idCobro > 0) {
            return null;
        }

        return [
            'created' => false,
            'duplicated' => false,
            'blocked' => true,
            'proforma_id' => null,
            'message' => 'No se puede generar la proforma porque el cobro no tiene un id_cobro valido.',
        ];
    }

    private function shouldProtectExistingProforma(object $proforma): bool
    {
        $estado = (int) ($proforma->estado ?? 0);
        $enviado = (int) ($proforma->enviado ?? 0);

        return $enviado === 1 || $estado === 4 || $estado === 6;
    }

    private function buildProtectedProformaReason(object $proforma): string
    {
        $estado = (int) ($proforma->estado ?? 0);
        $enviado = (int) ($proforma->enviado ?? 0);

        if ($enviado === 1) {
            return 'Proforma ya enviada.';
        }

        if ($estado === 6) {
            return 'Proforma ya facturada.';
        }

        if ($estado === 4) {
            return 'Proforma ya pagada.';
        }

        return 'Proforma protegida.';
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

    private function marcarCobroComoProformaGenerada(int $idCobro, ?int $estadoProforma = null): void
    {
        DB::table('valores_externos')
            ->where('id_cobro', $idCobro)
            ->update([
                'Proforma' => $estadoProforma ?? 2,
                'valor_extra' => 0,
                'valor_extra2' => 0,
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
            'vlr_nom' => (float) ($revision['valor_nomina'] ?? 0),
            'vlr_fe' => (float) ($revision['valor_facturas'] ?? 0),
            'vlr_rec' => (float) ($cobro->valor_acuse ?? 0),
            'vlr_sop' => (float) ($cobro->valor_documentos ?? 0),
            'vext1' => (float) ($revision['otro_valor_extra'] ?? 0),
            'vext2' => (float) ($revision['otro_valor_extra_2'] ?? 0),
            'vtotal' => (float) ($preview['detalle']['total_preview'] ?? 0),
            'cfe' => (float) ($cobro->numero_facturas ?? 0),
            'csop' => (float) ($cobro->numero_documento_soporte ?? 0),
            'crec' => (float) ($cobro->numero_acuse ?? 0),
            'cnom' => (float) (($revision['valor_nomina'] ?? 0) > 0 ? 1 : 0),
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

    private function completarConceptoExtraDesdeProformaExistente(int $proformaId, object $cobro, array $extraConcepto): array
    {
        $codigoActual = trim((string) ($extraConcepto['codigo_concepto_extra'] ?? ''));
        $descripcionActual = trim((string) ($extraConcepto['descripcion_concepto_extra'] ?? ''));
        $valorExtra = (float) ($cobro->valor_extra ?? $cobro->cliente_vlrextra ?? 0);

        if (($codigoActual !== '' && $descripcionActual !== '') || $valorExtra <= 0) {
            return $extraConcepto;
        }

        $detalle = DB::table('sg_proford')
            ->where('proforma_id', $proformaId)
            ->orderBy('orden')
            ->get();

        $candidatos = $detalle
            ->filter(function (object $linea) use ($valorExtra) {
                return (float) ($linea->cantidad ?? 0) === 1.0
                    && (float) ($linea->vr_unidad ?? 0) === $valorExtra
                    && (float) ($linea->vr_parcial ?? 0) === $valorExtra;
            })
            ->values();

        if ($candidatos->count() > 1) {
            $candidatos = $candidatos
                ->filter(fn (object $linea) => !in_array((string) ($linea->ref_codigo ?? ''), self::CODIGOS_CONCEPTO_OFICIALES, true))
                ->values();
        }

        if ($candidatos->isEmpty()) {
            $candidatos = $detalle
                ->filter(fn (object $linea) => stripos((string) ($linea->descripcion ?? ''), 'extra') !== false)
                ->values();
        }

        $lineaExtra = $candidatos->first();

        if (!$lineaExtra) {
            return $extraConcepto;
        }

        if ($codigoActual === '') {
            $extraConcepto['codigo_concepto_extra'] = trim((string) ($lineaExtra->ref_codigo ?? ''));
        }

        if ($descripcionActual === '') {
            $extraConcepto['descripcion_concepto_extra'] = trim((string) ($lineaExtra->descripcion ?? ''));
        }

        return $extraConcepto;
    }

    /**
     * @return array{status:'found'|'none'|'multiple_legacy',proforma?:object|null,legacy_matches?:array<int,object>}
     */
    private function resolveExistingProforma(object $cobro, ?string $emisoraOverride = null): array
    {
        $nit = trim((string) ($cobro->cliente_nit ?? ''));
        $mesTexto = trim((string) ($cobro->mes ?? ''));
        $mes = $this->normalizarMesParaProforma($mesTexto);
        $anio = (int) ($cobro->aÃ±o ?? 0);
        $emisora = $emisoraOverride ?: $this->resolverEmpresaEmisoraDesdeRegimen($cobro);
        $idCobro = (int) ($cobro->id_cobro ?? 0);

        if ($nit === '' || $mes === null || $anio <= 0 || trim($emisora) === '') {
            return ['status' => 'none'];
        }

        if (Schema::hasColumn('sg_proform', 'id_cobro') && $idCobro > 0) {
            $proforma = DB::table('sg_proform')
                ->where('id_cobro', $idCobro)
                ->first();

            if ($proforma !== null) {
                return [
                    'status' => 'found',
                    'proforma' => $proforma,
                ];
            }

            $legacyMatches = DB::table('sg_proform')
                ->where('nit', $nit)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->where('emisora', $emisora)
                ->where(function ($query): void {
                    $query->whereNull('id_cobro')->orWhere('id_cobro', 0);
                })
                ->orderByDesc('id')
                ->get();

            if ($legacyMatches->count() === 1) {
                $legacy = $legacyMatches->first();

                DB::table('sg_proform')
                    ->where('id', $legacy->id)
                    ->update([
                        'id_cobro' => $idCobro,
                    ]);

                $linked = DB::table('sg_proform')
                    ->where('id', $legacy->id)
                    ->first();

                Log::info('Proforma legacy vinculada automáticamente a id_cobro.', [
                    'proforma_id' => $linked->id ?? null,
                    'nro_prof' => $linked->nro_prof ?? null,
                    'id_cobro_asignado' => $idCobro,
                    'nit' => $nit,
                    'mes' => $mes,
                    'anio' => $anio,
                    'emisora' => $emisora,
                    'usuario' => $this->resolveActor(),
                ]);

                return [
                    'status' => 'found',
                    'proforma' => $linked,
                ];
            }

            if ($legacyMatches->count() > 1) {
                Log::warning('Múltiples proformas legacy candidatas para asignar id_cobro; creación bloqueada.', [
                    'id_cobro' => $idCobro,
                    'nit' => $nit,
                    'mes' => $mes,
                    'anio' => $anio,
                    'emisora' => $emisora,
                    'legacy_matches' => $legacyMatches->map(fn (object $proforma) => [
                        'id' => $proforma->id ?? null,
                        'nro_prof' => $proforma->nro_prof ?? null,
                    ])->values()->all(),
                    'usuario' => $this->resolveActor(),
                ]);

                return [
                    'status' => 'multiple_legacy',
                    'legacy_matches' => $legacyMatches->all(),
                ];
            }

            return ['status' => 'none'];
        }

        $proforma = DB::table('sg_proform')
            ->where('nit', $nit)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->where('emisora', $emisora)
            ->first();

        return $proforma !== null
            ? ['status' => 'found', 'proforma' => $proforma]
            : ['status' => 'none'];
    }

    private function nombreMesParaMensaje(?int $mes): string
    {
        if ($mes === null) {
            return 'mes';
        }

        return ucfirst(self::MESES_ES[array_search($mes, self::MESES_ES, true)] ?? (string) $mes);
    }

    private function resolveActor(): string
    {
        $user = Auth::user();

        if ($user) {
            return trim((string) ($user->name ?? $user->email ?? 'usuario'));
        }

        return trim((string) (session('usuario') ?? session('email') ?? 'desconocido'));
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
            'otro_valor_extra' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->otro_valor_extra ?? $cobro->valor_extra ?? null, $cobro->cliente_vlrextra ?? null),
            'otro_valor_extra_2' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->otro_valor_extra_2 ?? $cobro->valor_extra2 ?? $cobro->valor_terminal_recepcion ?? null, $cobro->cliente_vlrextra2 ?? null),
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
            $cobro->otro_valor_extra_2 ?? $cobro->valor_extra2 ?? $cobro->valor_terminal_recepcion ?? null,
            $cobro->otro_valor_extra ?? $cobro->valor_extra ?? null,
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
