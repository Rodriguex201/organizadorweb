<?php

namespace App\Services;

use App\Models\ClientePotencial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;


class CobrosService
{
    public function __construct(
        private readonly ClienteRetiradoService $clienteRetiradoService,
    ) {
    }

    public const MESES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    private const REVISION_VALORES_EXTERNOS_MAP = [
        'facturas' => 'numero_facturas',
        'nota_debito' => 'numero_nota_debito',
        'nota_credito' => 'numero_nota_credito',
        'soporte' => 'numero_documento_soporte',
        'nota_ajuste' => 'numero_nota_ajuste',
        'acuse' => 'numero_acuse',
        'otro_valor_extra' => 'valor_extra',
        'otro_valor_extra_2' => 'valor_extra2',
        'valor_facturas' => 'valor_facturas',
        'valor_documentos' => 'valor_documentos',
        'valor_acuse' => 'valor_acuse',
        'total_mensualidad' => 'valor_mensualidad',
        'valor_total_proforma' => 'valor_total',
    ];

    private const REVISION_CLIENTES_MAP = [
        'numero_equipos' => 'numequipos',
        'valor_principal' => 'vlrprincipal',
        'valor_terminal' => 'vlrterminal',
        'numero_equipos_extra' => 'numextra',
        'valor_equipo_extra' => 'vlrextrae',
        'empleados' => 'numero_empleados',
        'valor_nomina' => 'vlrnomina',
        'numero_moviles' => 'numeromoviles',
        'valor_movil' => 'vlrmovil',
        'otro_valor_extra' => 'vlrextra',
        'otro_valor_extra_2' => 'vlrextra2',
        'precio_factura' => 'vlrfactura',
        'precio_soporte' => 'vlrsoporte',
        'precio_acuse' => 'vlrecepcion',
    ];


    public function paginateCobros(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        try {

            $query = $this->buildCobrosQuery($filters);
            $this->logCobrosDebug($query);

            $ordenFecha = $this->normalizarOrdenFecha($filters['orden_fecha'] ?? null);

return $query
    ->when(
        $ordenFecha,
        fn ($q, $direccion) => $q->orderBy('cp.fecha_arriendo', $direccion),
        fn ($q) => $q
            ->orderByRaw($this->valoresExternosYearSql().' DESC')
            ->orderByRaw($this->ordenMesSql() . ' DESC')
            ->orderByDesc('ve.id_cobro'),
    )
    ->paginate($perPage)
    ->withQueryString();
        } catch (QueryException) {
            return new Paginator(
                items: [],
                total: 0,
                perPage: $perPage,
                currentPage: $page,
                options: [
                    'path' => Paginator::resolveCurrentPath(),
                    'query' => request()->query(),
                ],
            );
        }
    }

    public function getPeriodSummary(array $filters = []): object
    {
        return $this->buildFilteredCobrosQuery($filters)
            ->selectRaw('
                COALESCE(SUM(ve.numero_facturas), 0) as total_facturas,
                COALESCE(SUM(ve.numero_nota_debito), 0) as total_notas_debito,
                COALESCE(SUM(ve.numero_nota_credito), 0) as total_notas_credito,
                COALESCE(SUM(ve.numero_documento_soporte), 0) as total_documentos_soporte,
                COALESCE(SUM(ve.numero_nota_ajuste), 0) as total_notas_ajuste,
                COALESCE(SUM(ve.numero_acuse), 0) as total_acuses,
                COALESCE(SUM(ve.valor_facturas), 0) as valor_facturas,
                COALESCE(SUM(ve.valor_documentos), 0) as valor_documentos,
                COALESCE(SUM(ve.valor_acuse), 0) as valor_acuse,
                COALESCE(SUM(ve.valor_mensualidad), 0) as valor_mensualidad,
                COALESCE(SUM(ve.valor_total), 0) as valor_total
            ')
            ->first() ?? (object) [
                'total_facturas' => 0,
                'total_notas_debito' => 0,
                'total_notas_credito' => 0,
                'total_documentos_soporte' => 0,
                'total_notas_ajuste' => 0,
                'total_acuses' => 0,
                'valor_facturas' => 0,
                'valor_documentos' => 0,
                'valor_acuse' => 0,
                'valor_mensualidad' => 0,
                'valor_total' => 0,
            ];
    }


    public function debugSnapshot(array $filters = []): array
    {
        $baseQuery = DB::table('valores_externos as ve');
        $filteredQuery = $this->buildCobrosQuery($filters);


        return [
            'connection' => DB::connection()->getName(),
            'database' => DB::connection()->getDatabaseName(),

            'base_count' => (clone $baseQuery)->count(),
            'first_record' => (clone $baseQuery)->first(),
            'sql' => $filteredQuery->toSql(),
            'bindings' => $filteredQuery->getBindings(),
            'filtered_count' => (clone $filteredQuery)->count(),
        ];
    }




    public function findCobroById(int $idCobro): ?object
    {
        $select = [
            've.*',
            'cp.idclientes_potenciales as cliente_id',
            'cp.nombre as cliente_nombre',
            'cp.empresa as cliente_empresa',
            'cp.nit as cliente_nit',
            'cp.codigo as cliente_codigo',
            'cp.contacto as cliente_contacto',
            'cp.celular1 as cliente_celular1',
            'cp.celular2 as cliente_celular2',
            'cp.email as cliente_email',
            'cp.direccion as cliente_direccion',
            'cp.regimen as cliente_regimen',
            'cp.modalidad as cliente_modalidad',
            'cp.categoria as cliente_categoria',
            'cp.vlrprincipal as cliente_vlrprincipal',
            'cp.numequipos as cliente_numequipos',
            'cp.vlrterminal as cliente_vlrterminal',
            'cp.vlrnomina as cliente_vlrnomina',
            'cp.numeromoviles as cliente_numeromoviles',
            'cp.vlrmovil as cliente_vlrmovil',
            'cp.numero_empleados as cliente_numero_empleados',
            'cp.vlrfactura as cliente_vlrfactura',
            'cp.vlrecepcion as cliente_vlrecepcion',
            'cp.vlrsoporte as cliente_vlrsoporte',
            'cp.nominaterminal as cliente_nominaterminal',
            'cp.vlrextra as cliente_vlrextra',
            'cp.vlrextra2 as cliente_vlrextra2',
        ];

        if (Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
            $select[] = 'cp.estado_facturacion as cliente_estado_facturacion';
        } else {
            $select[] = DB::raw("'".ClientePotencial::ESTADO_FACTURACION_ACTIVO."' as cliente_estado_facturacion");
        }

        if (Schema::hasColumn('clientes_potenciales', 'fecha_inicio_facturacion')) {
            $select[] = 'cp.fecha_inicio_facturacion as cliente_fecha_inicio_facturacion';
        } else {
            $select[] = DB::raw('NULL as cliente_fecha_inicio_facturacion');
        }

        if (Schema::hasColumn('clientes_potenciales', 'numextra')) {
            $select[] = 'cp.numextra as cliente_numextra';
        }

        if (Schema::hasColumn('clientes_potenciales', 'vlrextrae')) {
            $select[] = 'cp.vlrextrae as cliente_vlrextrae';
        }

        $select = $this->clienteRetiradoService->addSelectColumns(
            $select,
            'cp',
            'cliente_fecha_retiro',
            'cliente_retiro_flag',
        );

        return DB::table('valores_externos as ve')
            ->leftJoin('clientes_potenciales as cp', DB::raw('cp.idclientes_potenciales'), '=', DB::raw('CAST(ve.id_cliente AS UNSIGNED)'))
            ->select($select)
            ->where('ve.id_cobro', $idCobro)
            ->first();
    }


    public function updateCobroRevision(int $idCobro, array $data): bool
    {
        $payload = [];

        foreach (self::REVISION_VALORES_EXTERNOS_MAP as $inputKey => $column) {
            if (!array_key_exists($inputKey, $data)) {
                continue;
            }

            if (!Schema::hasColumn('valores_externos', $column)) {
                continue;
            }

            $payload[$column] = (float) $data[$inputKey];
        }

        if ($payload === []) {
            return false;
        }

        return DB::table('valores_externos')
            ->where('id_cobro', $idCobro)
            ->update($payload) > 0;
    }

    public function updateClienteRevision(int $idCliente, array $data): bool
    {
        $payload = [];

        foreach (self::REVISION_CLIENTES_MAP as $inputKey => $column) {
            if (!array_key_exists($inputKey, $data)) {
                continue;
            }

            if (!Schema::hasColumn('clientes_potenciales', $column)) {
                continue;
            }

            $payload[$column] = (float) $data[$inputKey];
        }

        if ($payload === []) {
            return false;
        }

        return DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', $idCliente)
            ->update($payload) > 0;
    }

    public function mapCobroToRevisionValues(object $cobro): array
    {
        return [
            'numero_equipos' => $this->revisionValue($cobro, ['numero_equipos', 'cliente_numequipos']),
            'valor_principal' => $this->revisionValue($cobro, ['valor_principal', 'cliente_vlrprincipal']),
            'valor_terminal' => $this->revisionValue($cobro, ['valor_terminal', 'cliente_vlrterminal']),
            'numero_equipos_extra' => $this->revisionValue($cobro, ['numextra', 'cliente_numextra']),
            'valor_equipo_extra' => $this->revisionValue($cobro, ['vlrextrae', 'cliente_vlrextrae']),
            'empleados' => $this->revisionValue($cobro, ['empleados', 'cliente_numero_empleados']),
            'valor_nomina' => $this->revisionValue($cobro, ['vlrnomina', 'cliente_vlrnomina']),
            'numero_moviles' => $this->revisionValue($cobro, ['numero_moviles', 'cliente_numeromoviles']),
            'valor_movil' => $this->revisionValue($cobro, ['valor_movil', 'cliente_vlrmovil']),
            'facturas' => $this->revisionValue($cobro, ['numero_facturas']),
            'nota_debito' => $this->revisionValue($cobro, ['numero_nota_debito']),
            'nota_credito' => $this->revisionValue($cobro, ['numero_nota_credito']),
            'soporte' => $this->revisionValue($cobro, ['numero_documento_soporte']),
            'nota_ajuste' => $this->revisionValue($cobro, ['numero_nota_ajuste']),
            'acuse' => $this->revisionValue($cobro, ['numero_acuse']),
            'otro_valor_extra' => $this->revisionValue($cobro, ['otro_valor_extra', 'valor_extra', 'cliente_vlrextra']),
            'otro_valor_extra_2' => $this->revisionValue($cobro, ['otro_valor_extra_2', 'valor_terminal_recepcion', 'valor_extra2', 'cliente_vlrextra2']),
            'precio_factura' => $this->revisionValue($cobro, ['precio_factura', 'cliente_vlrfactura']),
            'precio_soporte' => $this->revisionValue($cobro, ['precio_soporte', 'cliente_vlrsoporte']),
            'precio_acuse' => $this->revisionValue($cobro, ['precio_acuse', 'cliente_vlrecepcion']),
        ];
    }

    /**
     * TODO: Integrar lógica de persistencia para sg_proform y sg_proford.
     */
    public function buildProformaPayload(array $data): array
    {
        return [
            'sg_proform' => [
                'header' => $data,
            ],
            'sg_proford' => [
                'items' => $data['items'] ?? [],
            ],
        ];
    }

    private function revisionValue(object $cobro, array $sources): float
    {
        foreach ($sources as $source) {
            if (!isset($cobro->{$source}) || $cobro->{$source} === null || $cobro->{$source} === '') {
                continue;
            }

            return (float) $cobro->{$source};
        }

        return 0.0;
    }



    public function normalizePeriodoFilters(null|string|int $mes, null|string|int $anio): array
    {
        return [
            'mes' => $this->normalizarMes($mes) ?? self::MESES[(int) now()->format('n')],
            'anio' => $this->normalizarEntero($anio) ?? (int) now()->format('Y'),
        ];
    }

    public function findCobrosForMassGeneration(array $filters, int $grupoFecha): Collection
    {
        $filters['grupo_fecha'] = (string) $grupoFecha;
        $filters['exclude_retirados'] = true;
        $filters['only_facturacion_activa'] = true;

        $massDebug = $this->buildMassGenerationDebugSnapshot($filters, $grupoFecha);
        Log::info('Cobros masivos debug snapshot.', $massDebug);

        $query = $this->buildCobrosQuery($filters);
        $ordenFecha = $this->normalizarOrdenFecha($filters['orden_fecha'] ?? null);

        return $query
            ->when(
                $ordenFecha,
                fn ($q, $direccion) => $q->orderBy('cp.fecha_arriendo', $direccion),
                fn ($q) => $q
                    ->orderByRaw($this->valoresExternosYearSql().' DESC')
                    ->orderByRaw($this->ordenMesSql() . ' DESC')
                    ->orderByDesc('ve.id_cobro'),
            )
            ->pluck('ve.id_cobro')
            ->map(fn ($idCobro) => (int) $idCobro)
            ->filter(fn (int $idCobro) => $idCobro > 0)
            ->values();
    }

    public function findCobroCandidatesForMassGeneration(array $filters, int $grupoFecha): Collection
    {
        $filters['grupo_fecha'] = (string) $grupoFecha;
        unset($filters['exclude_retirados'], $filters['only_facturacion_activa']);

        $query = $this->buildCobrosQuery($filters);
        $ordenFecha = $this->normalizarOrdenFecha($filters['orden_fecha'] ?? null);

        return $query
            ->when(
                $ordenFecha,
                fn ($q, $direccion) => $q->orderBy('cp.fecha_arriendo', $direccion),
                fn ($q) => $q
                    ->orderByRaw($this->valoresExternosYearSql().' DESC')
                    ->orderByRaw($this->ordenMesSql() . ' DESC')
                    ->orderByDesc('ve.id_cobro'),
            )
            ->get();
    }

    public function buildMassGenerationDebugSnapshot(array $filters, int $grupoFecha): array
    {
        $normalizedFilters = $filters;
        $normalizedFilters['grupo_fecha'] = (string) $grupoFecha;

        $baseFilters = $normalizedFilters;
        unset($baseFilters['grupo_fecha'], $baseFilters['only_facturacion_activa'], $baseFilters['exclude_retirados']);

        $baseQuery = $this->buildCobrosQuery($baseFilters);

        $groupFilters = $baseFilters;
        $groupFilters['grupo_fecha'] = (string) $grupoFecha;
        $groupQuery = $this->buildCobrosQuery($groupFilters);

        $activeFilters = $groupFilters;
        $activeFilters['only_facturacion_activa'] = true;
        $activeQuery = $this->buildCobrosQuery($activeFilters);

        $finalFilters = $activeFilters;
        $finalFilters['exclude_retirados'] = true;
        $finalQuery = $this->buildCobrosQuery($finalFilters);

        $groupCount = (clone $groupQuery)->count();
        $activeCount = (clone $activeQuery)->count();
        $finalCount = (clone $finalQuery)->count();

        return [
            'grupo' => $grupoFecha,
            'filters' => $normalizedFilters,
            'database' => DB::connection()->getDatabaseName(),
            'valores_externos_encontrados' => (clone $baseQuery)->count(),
            'registros_grupo' => $groupCount,
            'descartados_por_estado_facturacion' => max(0, $groupCount - $activeCount),
            'descartados_por_retirado' => max(0, $activeCount - $finalCount),
            'llegan_a_generacion' => $finalCount,
            'muestra_ids_grupo' => (clone $groupQuery)->limit(10)->pluck('ve.id_cobro')->map(fn ($id) => (int) $id)->values()->all(),
            'muestra_ids_finales' => (clone $finalQuery)->limit(10)->pluck('ve.id_cobro')->map(fn ($id) => (int) $id)->values()->all(),
            'sql_final' => $finalQuery->toSql(),
            'bindings_final' => $finalQuery->getBindings(),
        ];
    }

private function buildCobrosQuery(array $filters)
{
    $select = $this->clienteRetiradoService->addSelectColumns(
        [
            've.id_cobro',
            've.id_cliente',
            've.valor_total',

            'cp.idclientes_potenciales as cliente_id',
            'cp.fecha_arriendo',
            'cp.codigo',
            'cp.nombre',
            'cp.empresa',
            'cp.regimen',
            'cp.nota_cobro',
        ],
        'cp',
        'cliente_fecha_retiro',
        'cliente_retiro_flag',
    );

    $query = $this->buildFilteredCobrosQuery($filters)
        ->select($select);

    if (Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
        $query->addSelect(DB::raw($this->billingStatusSql('cp.estado_facturacion').' as estado_facturacion'));
    } else {
        $query->addSelect(DB::raw("'".ClientePotencial::ESTADO_FACTURACION_ACTIVO."' as estado_facturacion"));
    }

    if (Schema::hasColumn('clientes_potenciales', 'fecha_inicio_facturacion')) {
        $query->addSelect('cp.fecha_inicio_facturacion');
    } else {
        $query->addSelect(DB::raw('NULL as fecha_inicio_facturacion'));
    }

    if (Schema::hasColumn('clientes_potenciales', 'fecha_llegada')) {
        $query->addSelect('cp.fecha_llegada as cliente_fecha_creacion');
    } elseif (Schema::hasColumn('clientes_potenciales', 'fecha_inicio')) {
        $query->addSelect('cp.fecha_inicio as cliente_fecha_creacion');
    } elseif (Schema::hasColumn('clientes_potenciales', 'fechainicio')) {
        $query->addSelect('cp.fechainicio as cliente_fecha_creacion');
    } elseif (Schema::hasColumn('clientes_potenciales', 'fecha_cotizacion')) {
        $query->addSelect('cp.fecha_cotizacion as cliente_fecha_creacion');
    } else {
        $query->addSelect(DB::raw('NULL as cliente_fecha_creacion'));
    }

    // 🔥 FILTRO MES
    return $query;

    if (!empty($filters['mes'])) {
        $query->whereRaw('LOWER(TRIM(ve.mes)) = ?', [strtolower(trim($filters['mes']))]);
    }

    // 🔥 FILTRO AÑO
if (!empty($filters['anio'])) {
    $query->whereRaw($this->valoresExternosYearSql().' = ?', [(string) $filters['anio']]);
}

    // 🔥 FILTRO PROFORMA
    if (!is_null($filters['proforma'] ?? null)) {
        $query->where('ve.Proforma', $filters['proforma']);
    }

    // 🔥 BUSCAR
    if ($codigo !== '') {
        $query->whereRaw(
            $this->normalizedSqlExpression('cp.codigo').' LIKE ?',
            ['%'.$codigo.'%']
        );
    }

    if ($buscar !== '') {
        foreach ($this->splitSearchTerms($buscar) as $term) {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($like) {
                $q->whereRaw($this->normalizedSqlExpression('cp.nombre').' LIKE ?', [$like])
                    ->orWhereRaw($this->normalizedSqlExpression('cp.empresa').' LIKE ?', [$like])
                    ->orWhereRaw($this->normalizedSqlExpression('cp.email').' LIKE ?', [$like])
                    ->orWhereRaw($this->normalizedSqlExpression('cp.codigo').' LIKE ?', [$like]);
            });
        }
    }

    // 🔥 GRUPO FECHA
    if ($grupoFecha !== null) {
        $query->whereRaw($this->arriendoCutDaySql('cp.fecha_arriendo').' = ?', [$grupoFecha]);
    }

    if (($filters['exclude_retirados'] ?? false) === true) {
        $this->clienteRetiradoService->applyNoRetiradosConstraint($query, 'cp');
    }

    if (($filters['only_facturacion_activa'] ?? false) === true && Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
        $query->whereRaw($this->billingStatusSql('cp.estado_facturacion').' = ?', [
            ClientePotencial::ESTADO_FACTURACION_ACTIVO,
        ]);
    }

    // 🔥 FILTRO NOTA
    if (!empty($filters['filtro_nota'])) {
        if ($filters['filtro_nota'] === 'con') {
            $query->whereNotNull('cp.nota_cobro');
        } elseif ($filters['filtro_nota'] === 'sin') {
            $query->whereNull('cp.nota_cobro');
        }
    }

    if (!empty($filters['filtro_envio'])) {
        if ($filters['filtro_envio'] === 'enviadas') {
            $query->whereExists(function ($subquery) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('sg_proform as sp')
                    ->whereRaw('BINARY sp.nit = BINARY cp.nit')
                    ->whereRaw('sp.mes = '.$this->ordenMesSql())
                    ->whereRaw('sp.anio = '.$this->valoresExternosYearSql())
                    ->whereRaw(
                        "sp.emisora = CASE UPPER(TRIM(cp.regimen))
                            WHEN 'PCS' THEN 'PCS'
                            WHEN 'SMP' THEN 'SMP'
                            ELSE 'SAS'
                        END"
                    )
                    ->where('sp.enviado', 1);
            });
        } elseif ($filters['filtro_envio'] === 'no_enviadas') {
            $query->whereNotExists(function ($subquery) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('sg_proform as sp')
                    ->whereRaw('BINARY sp.nit = BINARY cp.nit')
                    ->whereRaw('sp.mes = '.$this->ordenMesSql())
                    ->whereRaw('sp.anio = '.$this->valoresExternosYearSql())
                    ->whereRaw(
                        "sp.emisora = CASE UPPER(TRIM(cp.regimen))
                            WHEN 'PCS' THEN 'PCS'
                            WHEN 'SMP' THEN 'SMP'
                            ELSE 'SAS'
                        END"
                    )
                    ->where('sp.enviado', 1);
            });
        }
    }

    return $query;
}

    private function buildFilteredCobrosQuery(array $filters)
    {
        $filters = array_map(function ($value) {
            return $value === '' ? null : $value;
        }, $filters);
        $codigo = $this->normalizeTextFilter($filters['codigo'] ?? '');
        $buscar = $this->normalizeTextFilter($filters['buscar'] ?? '');
        $grupoFecha = $this->normalizarGrupoFecha($filters['grupo_fecha'] ?? null);

        $query = DB::table('valores_externos as ve')
            ->leftJoin(
                'clientes_potenciales as cp',
                've.id_cliente',
                '=',
                'cp.idclientes_potenciales'
            );

        if (!empty($filters['mes'])) {
            $query->whereRaw('LOWER(TRIM(ve.mes)) = ?', [strtolower(trim($filters['mes']))]);
        }

        if (!empty($filters['anio'])) {
            $query->whereRaw($this->valoresExternosYearSql().' = ?', [(string) $filters['anio']]);
        }

        if (!is_null($filters['proforma'] ?? null)) {
            $query->where('ve.Proforma', $filters['proforma']);
        }

        if ($codigo !== '') {
            $query->whereRaw(
                $this->normalizedSqlExpression('cp.codigo').' LIKE ?',
                ['%'.$codigo.'%']
            );
        }

        if ($buscar !== '') {
            foreach ($this->splitSearchTerms($buscar) as $term) {
                $like = '%'.$term.'%';

                $query->where(function ($q) use ($like) {
                    $q->whereRaw($this->normalizedSqlExpression('cp.nombre').' LIKE ?', [$like])
                        ->orWhereRaw($this->normalizedSqlExpression('cp.empresa').' LIKE ?', [$like])
                        ->orWhereRaw($this->normalizedSqlExpression('cp.email').' LIKE ?', [$like])
                        ->orWhereRaw($this->normalizedSqlExpression('cp.codigo').' LIKE ?', [$like]);
                });
            }
        }

        if ($grupoFecha !== null) {
            $query->whereRaw($this->arriendoCutDaySql('cp.fecha_arriendo').' = ?', [$grupoFecha]);
        }

        if (($filters['exclude_retirados'] ?? false) === true) {
            $this->clienteRetiradoService->applyNoRetiradosConstraint($query, 'cp');
        }

        if (($filters['only_facturacion_activa'] ?? false) === true && Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
            $query->whereRaw($this->billingStatusSql('cp.estado_facturacion').' = ?', [
                ClientePotencial::ESTADO_FACTURACION_ACTIVO,
            ]);
        }

        if (!empty($filters['filtro_nota'])) {
            if ($filters['filtro_nota'] === 'con') {
                $query->whereNotNull('cp.nota_cobro');
            } elseif ($filters['filtro_nota'] === 'sin') {
                $query->whereNull('cp.nota_cobro');
            }
        }

        if (!empty($filters['filtro_envio'])) {
            if ($filters['filtro_envio'] === 'enviadas') {
                $query->whereExists(function ($subquery) {
                    $subquery
                        ->select(DB::raw(1))
                        ->from('sg_proform as sp')
                        ->whereRaw('BINARY sp.nit = BINARY cp.nit')
                        ->whereRaw('sp.mes = '.$this->ordenMesSql())
                        ->whereRaw('sp.anio = '.$this->valoresExternosYearSql())
                        ->whereRaw(
                            "sp.emisora = CASE UPPER(TRIM(cp.regimen))
                                WHEN 'PCS' THEN 'PCS'
                                WHEN 'SMP' THEN 'SMP'
                                ELSE 'SAS'
                            END"
                        )
                        ->where('sp.enviado', 1);
                });
            } elseif ($filters['filtro_envio'] === 'no_enviadas') {
                $query->whereNotExists(function ($subquery) {
                    $subquery
                        ->select(DB::raw(1))
                        ->from('sg_proform as sp')
                        ->whereRaw('BINARY sp.nit = BINARY cp.nit')
                        ->whereRaw('sp.mes = '.$this->ordenMesSql())
                        ->whereRaw('sp.anio = '.$this->valoresExternosYearSql())
                        ->whereRaw(
                            "sp.emisora = CASE UPPER(TRIM(cp.regimen))
                                WHEN 'PCS' THEN 'PCS'
                                WHEN 'SMP' THEN 'SMP'
                                ELSE 'SAS'
                            END"
                        )
                        ->where('sp.enviado', 1);
                });
            }
        }

        return $query;
    }

    private function ordenMesSql(): string
    {
        return "CASE LOWER(TRIM(ve.mes))\n"
            . "WHEN 'enero' THEN 1\n"
            . "WHEN 'febrero' THEN 2\n"
            . "WHEN 'marzo' THEN 3\n"
            . "WHEN 'abril' THEN 4\n"
            . "WHEN 'mayo' THEN 5\n"
            . "WHEN 'junio' THEN 6\n"
            . "WHEN 'julio' THEN 7\n"
            . "WHEN 'agosto' THEN 8\n"
            . "WHEN 'septiembre' THEN 9\n"
            . "WHEN 'octubre' THEN 10\n"
            . "WHEN 'noviembre' THEN 11\n"
            . "WHEN 'diciembre' THEN 12\n"
            . "ELSE 0 END";
    }

    private function logCobrosDebug($query): void
    {
        Log::info('Cobros debug query', [
            'db_connection' => DB::connection()->getName(),
            'database_name' => DB::connection()->getDatabaseName(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);
    }


    private function normalizarMes(null|string|int $mes): ?string
    {
        if ($mes === null) {
            return null;
        }

        $valor = mb_strtolower(trim((string) $mes));

        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            $mesNumero = (int) $valor;

            return self::MESES[$mesNumero] ?? null;
        }

        if (in_array($valor, self::MESES, true)) {
            return $valor;
        }

        return null;
    }



    private function normalizarEntero(null|string|int $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $string = trim((string) $valor);

        if ($string === '' || !ctype_digit($string)) {
            return null;
        }

        return (int) $string;
    }

    private function normalizarBuscar(null|string $buscar): ?string
    {
        if ($buscar === null) {
            return null;
        }

        $valor = trim($buscar);

        return $valor === '' ? null : $valor;
    }

    private function normalizeTextFilter(null|string|int $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return mb_strtolower($normalized ?? '');
    }

    /**
     * @return list<string>
     */
    private function splitSearchTerms(string $value): array
    {
        $parts = preg_split('/\s+/u', trim($value)) ?: [];

        return array_values(array_filter(array_map(
            fn ($part) => $this->normalizeTextFilter($part),
            $parts
        ), fn ($part) => $part !== ''));
    }

    private function normalizedSqlExpression(string $column): string
    {
        $trimmed = "TRIM(COALESCE({$column}, ''))";
        $collapsed = $trimmed;

        for ($i = 0; $i < 5; $i++) {
            $collapsed = "REPLACE({$collapsed}, '  ', ' ')";
        }

        return "LOWER({$collapsed})";
    }

    private function billingStatusSql(string $column): string
    {
        return "CASE WHEN UPPER(TRIM(COALESCE({$column}, ''))) = 'PENDIENTE' THEN 'PENDIENTE' ELSE 'ACTIVO' END";
    }

    private function valoresExternosYearSql(string $alias = 've'): string
    {
        return "{$alias}.`año`";
    }

    private function arriendoCutDaySql(string $column): string
    {
        $firstSegment = "SUBSTRING_INDEX(TRIM(COALESCE({$column}, '')), '-', 1)";
        $lastSegment = "SUBSTRING_INDEX(TRIM(COALESCE({$column}, '')), '-', -1)";

        return "CASE
            WHEN CHAR_LENGTH({$firstSegment}) = 4
                THEN CAST({$lastSegment} AS UNSIGNED)
            ELSE CAST({$firstSegment} AS UNSIGNED)
        END";
    }

    private function normalizarOrdenFecha(null|string $orden): ?string
    {
        if ($orden === null) {
            return null;
        }

        $valor = mb_strtolower(trim($orden));

        return in_array($valor, ['asc', 'desc'], true) ? $valor : null;
    }

    private function normalizarGrupoFecha(null|string|int $grupoFecha): ?int
    {
        if ($grupoFecha === null) {
            return null;
        }

        $valor = trim((string) $grupoFecha);

        if (!in_array($valor, ['7', '27'], true)) {
            return null;
        }

        return (int) $valor;
    }

    private function normalizarProforma(null|string|int $proforma): ?int
    {
        if ($proforma === null) {
            return null;
        }

        $valor = trim((string) $proforma);

        if ($valor === '' || !ctype_digit($valor)) {
            return null;
        }

        return (int) $valor;
    }

    private function normalizarFiltroNota(null|string $filtroNota): ?string
    {
        if ($filtroNota === null) {
            return null;
        }

        $valor = mb_strtolower(trim($filtroNota));

        return in_array($valor, ['con', 'sin'], true) ? $valor : null;
    }

    private function normalizarFiltroEnvio(null|string $filtroEnvio): ?string
    {
        if ($filtroEnvio === null) {
            return null;
        }

        $valor = mb_strtolower(trim($filtroEnvio));

        return in_array($valor, ['enviadas', 'no_enviadas'], true) ? $valor : null;
    }

}
