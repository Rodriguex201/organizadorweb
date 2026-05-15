<?php

namespace App\Services;

use App\Exports\ProformasDashboardExcelExport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse as DownloadResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformaDashboardExportService
{
    public const EXPORT_MODE_SUMMARY = 'summary';
    public const EXPORT_MODE_DETAILED = 'detailed';
    public const SCOPE_CURRENT_FILTERS = 'current_filters';
    public const SCOPE_CURRENT_MONTH = 'current_month';
    public const SCOPE_FULL_YEAR = 'full_year';
    public const SCOPE_MONTHLY_RANGE = 'monthly_range';
    public const FORMAT_XLSX = 'xlsx';
    public const TEXT_PLACEHOLDER = 'N/D';
    private const TEMP_EXPORT_CACHE_PREFIX = 'proformas_dashboard_export:';
    private const TEMP_EXPORT_TTL_SECONDS = 600;

    private ?bool $sgProformHasIdCobroColumn = null;

    public function __construct(
        private readonly ProformasService $proformasService,
    ) {
    }

    public function getModalOptions(array $dashboardFilters = []): array
    {
        $definitions = $this->columnDefinitions();

        return [
            'column_groups' => [
                [
                    'key' => 'cliente',
                    'label' => 'Datos cliente',
                    'columns' => $this->columnsForGroup($definitions, 'cliente'),
                ],
                [
                    'key' => 'cliente_valores',
                    'label' => 'Valores cliente',
                    'columns' => $this->columnsForGroup($definitions, 'cliente_valores'),
                ],
                [
                    'key' => 'proforma',
                    'label' => 'Datos proforma',
                    'columns' => $this->columnsForGroup($definitions, 'proforma'),
                ],
            ],
            'defaults' => [
                self::EXPORT_MODE_SUMMARY => $this->defaultColumnsFor(self::EXPORT_MODE_SUMMARY),
                self::EXPORT_MODE_DETAILED => $this->defaultColumnsFor(self::EXPORT_MODE_DETAILED),
            ],
            'filters' => [
                'mes' => $dashboardFilters['mes'] ?? (int) now()->format('n'),
                'anio' => $dashboardFilters['anio'] ?? (int) now()->format('Y'),
                'estado' => $dashboardFilters['estado'] ?? null,
            ],
            'scopes' => [
                ['value' => self::SCOPE_CURRENT_FILTERS, 'label' => 'Respetar filtros actuales'],
                ['value' => self::SCOPE_CURRENT_MONTH, 'label' => 'Solo mes actual'],
                ['value' => self::SCOPE_FULL_YEAR, 'label' => 'Todo el año'],
                ['value' => self::SCOPE_MONTHLY_RANGE, 'label' => 'Rango mensual'],
            ],
            'modes' => [
                ['value' => self::EXPORT_MODE_SUMMARY, 'label' => 'Resumen'],
                ['value' => self::EXPORT_MODE_DETAILED, 'label' => 'Detallado'],
            ],
            'formats' => [
                ['value' => self::FORMAT_XLSX, 'label' => '.xlsx', 'enabled' => true],
                ['value' => 'pdf', 'label' => 'PDF', 'enabled' => false],
                ['value' => 'csv', 'label' => 'CSV', 'enabled' => false],
                ['value' => 'google_sheets', 'label' => 'Google Sheets', 'enabled' => false],
            ],
        ];
    }

    public function defaultColumnsFor(string $mode): array
    {
        $allColumns = array_keys($this->columnDefinitions());

        if ($mode === self::EXPORT_MODE_DETAILED) {
            return $allColumns;
        }

        return [
            'cliente_codigo',
            'cliente_nombre',
            'cliente_empresa',
            'cliente_tipo_cliente',
            'cliente_valor_principal',
            'cliente_valor_nomina',
            'cliente_valor_factura',
            'cliente_valor_soporte',
            'cliente_valor_total',
            'proforma_numero',
            'proforma_estado',
            'proforma_mes',
            'proforma_anio',
            'proforma_valor_total',
            'proforma_fecha_generacion',
        ];
    }

    public function download(array $filters, array $columns, string $mode = self::EXPORT_MODE_DETAILED, string $format = self::FORMAT_XLSX): BinaryFileResponse
    {
        if ($format !== self::FORMAT_XLSX) {
            throw new InvalidArgumentException('Formato de exportación no soportado todavía.');
        }

        $selectedColumns = $this->sanitizeSelectedColumns($columns, $mode);
        $dataset = $this->buildDataset($filters, $selectedColumns);
        $export = new ProformasDashboardExcelExport(
            $dataset['headings'],
            $dataset['rows'],
            $dataset['currency_column_indexes'],
            $dataset['totals_row_index'],
        );

        $response = Excel::download($export, $this->buildFilename($filters, $mode, $format));
        $response->headers->set('X-Export-Records', (string) ($dataset['record_count'] ?? 0));

        return $response;
    }

    public function prepareTemporaryDownload(array $filters, array $columns, string $mode = self::EXPORT_MODE_DETAILED, string $format = self::FORMAT_XLSX): array
    {
        if ($format !== self::FORMAT_XLSX) {
            throw new InvalidArgumentException('Formato de exportación no soportado todavía.');
        }

        $selectedColumns = $this->sanitizeSelectedColumns($columns, $mode);
        $dataset = $this->buildDataset($filters, $selectedColumns);
        $filename = $this->buildFilename($filters, $mode, $format);
        $token = (string) Str::uuid();
        $relativePath = 'exports/dashboard/'.$token.'-'.$filename;
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($relativePath);
        $export = new ProformasDashboardExcelExport(
            $dataset['headings'],
            $dataset['rows'],
            $dataset['currency_column_indexes'],
            $dataset['totals_row_index'],
        );

        $startedAt = microtime(true);
        Excel::store($export, $relativePath, 'local');

        clearstatcache(true, $absolutePath);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Cache::put(self::TEMP_EXPORT_CACHE_PREFIX.$token, [
            'relative_path' => $relativePath,
            'filename' => $filename,
            'record_count' => $dataset['record_count'] ?? 0,
            'created_at' => now()->toIso8601String(),
        ], now()->addSeconds(self::TEMP_EXPORT_TTL_SECONDS));

        return [
            'token' => $token,
            'filename' => $filename,
            'record_count' => $dataset['record_count'] ?? 0,
            'duration_ms' => $durationMs,
        ];
    }

    public function downloadTemporaryFile(string $token): DownloadResponse
    {
        $meta = Cache::get(self::TEMP_EXPORT_CACHE_PREFIX.$token);

        if (!is_array($meta)) {
            throw new InvalidArgumentException('La exportación ya no está disponible.');
        }

        $relativePath = (string) ($meta['relative_path'] ?? '');
        $filename = (string) ($meta['filename'] ?? 'proformas.xlsx');
        $recordCount = (int) ($meta['record_count'] ?? 0);

        if ($relativePath === '' || !Storage::disk('local')->exists($relativePath)) {
            Cache::forget(self::TEMP_EXPORT_CACHE_PREFIX.$token);

            throw new InvalidArgumentException('No se encontró el archivo exportado.');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $response = response()->download($absolutePath, $filename)->deleteFileAfterSend(true);
        $response->headers->set('X-Export-Records', (string) $recordCount);
        Cache::forget(self::TEMP_EXPORT_CACHE_PREFIX.$token);

        return $response;
    }

    public function resolveFilters(array $input, array $dashboardFilters = []): array
    {
        $scope = $input['scope'] ?? self::SCOPE_CURRENT_FILTERS;
        $dashboardMes = $this->normalizarMes($dashboardFilters['mes'] ?? null) ?? (int) now()->format('n');
        $dashboardAnio = $this->normalizarEntero($dashboardFilters['anio'] ?? null) ?? (int) now()->format('Y');
        $dashboardEstado = $this->normalizarEntero($dashboardFilters['estado'] ?? null);
        $anio = $this->normalizarEntero($input['anio'] ?? null) ?? $dashboardAnio;
        $estado = array_key_exists('estado', $input)
            ? $this->normalizarEntero($input['estado'])
            : $dashboardEstado;

        $filters = [
            'scope' => $scope,
            'anio' => $anio,
            'estado' => $estado,
            'mes' => $dashboardMes,
            'mes_desde' => $dashboardMes,
            'mes_hasta' => $dashboardMes,
        ];

        return match ($scope) {
            self::SCOPE_CURRENT_MONTH => array_merge($filters, [
                'mes' => (int) now()->format('n'),
                'anio' => (int) now()->format('Y'),
                'mes_desde' => (int) now()->format('n'),
                'mes_hasta' => (int) now()->format('n'),
            ]),
            self::SCOPE_FULL_YEAR => array_merge($filters, [
                'mes' => null,
                'mes_desde' => 1,
                'mes_hasta' => 12,
            ]),
            self::SCOPE_MONTHLY_RANGE => array_merge($filters, [
                'mes' => null,
                'mes_desde' => $this->normalizarMes($input['mes_desde'] ?? null) ?? $dashboardMes,
                'mes_hasta' => $this->normalizarMes($input['mes_hasta'] ?? null) ?? $dashboardMes,
            ]),
            default => array_merge($filters, [
                'mes' => $dashboardMes,
                'anio' => $dashboardAnio,
                'mes_desde' => $dashboardMes,
                'mes_hasta' => $dashboardMes,
            ]),
        };
    }

    public function supportedColumnKeys(): array
    {
        return array_keys($this->columnDefinitions());
    }

    private function buildDataset(array $filters, array $selectedColumns): array
    {
        $definitions = $this->columnDefinitions();
        $query = DB::table('sg_proform as p');

        if ($this->requiresClienteJoins($selectedColumns)) {
            $this->applyClienteJoins($query);
        }

        foreach ($selectedColumns as $key) {
            ($definitions[$key]['select'])($query);
        }

        $this->applyFilters($query, $filters);
        $orderedQuery = $query
            ->orderByDesc('p.anio')
            ->orderByDesc('p.mes')
            ->orderByDesc('p.id');

        $rows = $orderedQuery->get();

        $headings = [];
        $formattedRows = [];
        $totals = [
            'cliente_valor_principal' => 0.0,
            'cliente_valor_nomina' => 0.0,
            'cliente_valor_factura' => 0.0,
            'cliente_valor_soporte' => 0.0,
            'proforma_valor_total' => 0.0,
        ];
        $currencyColumnIndexes = [];

        foreach ($selectedColumns as $index => $key) {
            $headings[] = $definitions[$key]['label'];

            if (($definitions[$key]['type'] ?? null) === 'currency') {
                $currencyColumnIndexes[] = $index + 1;
            }
        }

        foreach ($rows as $row) {
            $formattedRow = [];

            foreach ($selectedColumns as $key) {
                $formattedRow[] = ($definitions[$key]['value'])($row);

                if (array_key_exists($key, $totals)) {
                    $totals[$key] += $this->toFloat($row->{$key} ?? null);
                }
            }

            $formattedRows[] = $formattedRow;
        }

        $totalsRow = array_fill(0, count($selectedColumns), '');
        if ($totalsRow !== []) {
            $totalsRow[0] = 'Totales';
        }

        foreach ($selectedColumns as $index => $key) {
            if (array_key_exists($key, $totals)) {
                $totalsRow[$index] = $totals[$key];
            }
        }

        $formattedRows[] = $totalsRow;

        return [
            'headings' => $headings,
            'rows' => $formattedRows,
            'currency_column_indexes' => $currencyColumnIndexes,
            'totals_row_index' => count($formattedRows) + 1,
            'record_count' => $rows->count(),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $estado = $this->normalizarEntero($filters['estado'] ?? null);
        $anio = $this->normalizarEntero($filters['anio'] ?? null);
        $mes = $this->normalizarMes($filters['mes'] ?? null);
        $mesDesde = $this->normalizarMes($filters['mes_desde'] ?? null);
        $mesHasta = $this->normalizarMes($filters['mes_hasta'] ?? null);
        $scope = $filters['scope'] ?? self::SCOPE_CURRENT_FILTERS;

        if ($anio !== null) {
            $query->where('p.anio', $anio);
        }

        if ($estado !== null) {
            $query->where('p.estado', $estado);
        }

        if ($scope === self::SCOPE_MONTHLY_RANGE && $mesDesde !== null && $mesHasta !== null) {
            $query->whereBetween('p.mes', [min($mesDesde, $mesHasta), max($mesDesde, $mesHasta)]);

            return;
        }

        if ($scope !== self::SCOPE_FULL_YEAR && $mes !== null) {
            $query->where('p.mes', $mes);
        }
    }

    private function columnsForGroup(array $definitions, string $group): array
    {
        return collect($definitions)
            ->filter(fn (array $definition) => $definition['group'] === $group)
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
            ])
            ->values()
            ->all();
    }

    private function sanitizeSelectedColumns(array $columns, string $mode): array
    {
        $allowed = $this->supportedColumnKeys();
        $selected = collect($columns)
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return $selected !== [] ? $selected : $this->defaultColumnsFor($mode);
    }

    private function buildFilename(array $filters, string $mode, string $format): string
    {
        $scope = $filters['scope'] ?? self::SCOPE_CURRENT_FILTERS;
        $anio = $filters['anio'] ?? now()->format('Y');

        $periodLabel = match ($scope) {
            self::SCOPE_FULL_YEAR => "anio-{$anio}",
            self::SCOPE_MONTHLY_RANGE => sprintf(
                'meses-%02d-a-%02d-%s',
                $filters['mes_desde'] ?? 1,
                $filters['mes_hasta'] ?? 12,
                $anio
            ),
            default => sprintf('mes-%02d-%s', $filters['mes'] ?? now()->format('n'), $anio),
        };

        return "proformas-{$mode}-{$periodLabel}.{$format}";
    }

    private function columnDefinitions(): array
    {
        return [
            'cliente_codigo' => $this->subqueryColumn('cliente_codigo', 'cliente', 'codigo', 'Código'),
            'cliente_nombre' => $this->subqueryColumn('cliente_nombre', 'cliente', 'nombre', 'Nombre'),
            'cliente_empresa' => $this->subqueryColumn('cliente_empresa', 'cliente', 'empresa', 'Empresa'),
            'cliente_email' => $this->subqueryColumn('cliente_email', 'cliente', 'email', 'Email'),
            'cliente_celular' => $this->subqueryColumn('cliente_celular', 'cliente', 'celular1', 'Celular'),
            'cliente_direccion' => $this->subqueryColumn('cliente_direccion', 'cliente', 'direccion', 'Dirección'),
            'cliente_departamento_ciudad' => $this->subqueryColumn('cliente_departamento_ciudad', 'cliente', 'departamento', 'Departamento/Ciudad'),
            'cliente_tipo_cliente' => [
                'group' => 'cliente',
                'label' => 'Tipo cliente',
                'type' => 'text',
                'select' => fn (Builder $query) => $this->addJoinedTipoClienteSelect($query, 'cliente_tipo_cliente'),
                'value' => fn (object $row) => $this->displayTextValue($row->cliente_tipo_cliente ?? null),
            ],
            'cliente_clase' => $this->subqueryColumn('cliente_clase', 'cliente', 'clase', 'Clase'),
            'cliente_modalidad' => $this->subqueryColumn('cliente_modalidad', 'cliente', 'modalidad', 'Modalidad'),
            'cliente_como_llego' => $this->subqueryColumn('cliente_como_llego', 'cliente', 'llego', 'Cómo llegó'),
            'cliente_contacto' => $this->subqueryColumn('cliente_contacto', 'cliente', 'contacto', 'Contacto'),
            'cliente_fecha_arriendo' => $this->subqueryDateColumn('cliente_fecha_arriendo', 'cliente', 'fecha_arriendo', 'Fecha arriendo'),
            'cliente_fecha_inicio' => $this->subqueryDateColumn('cliente_fecha_inicio', 'cliente', 'fecha_llegada', 'Fecha inicio'),

            'cliente_valor_principal' => $this->subqueryCurrencyColumn('cliente_valor_principal', 'cliente_valores', 'vlrprincipal', 'Valor principal'),
            'cliente_numero_equipos' => $this->subqueryNumericColumn('cliente_numero_equipos', 'cliente_valores', 'numequipos', 'Número equipos'),
            'cliente_valor_terminal' => $this->subqueryCurrencyColumn('cliente_valor_terminal', 'cliente_valores', 'vlrterminal', 'Valor terminal'),
            'cliente_numero_equipos_extra' => $this->subqueryNumericColumn('cliente_numero_equipos_extra', 'cliente_valores', 'numextra', 'Número equipos extra'),
            'cliente_valor_equipo_extra' => $this->subqueryCurrencyColumn('cliente_valor_equipo_extra', 'cliente_valores', 'vlrextrae', 'Valor equipo extra'),
            'cliente_valor_nomina' => $this->subqueryCurrencyColumn('cliente_valor_nomina', 'cliente_valores', 'vlrnomina', 'Valor nómina'),
            'cliente_valor_recepcion' => $this->subqueryCurrencyColumn('cliente_valor_recepcion', 'cliente_valores', 'vlrecepcion', 'Valor recepción'),
            'cliente_valor_factura' => $this->subqueryCurrencyColumn('cliente_valor_factura', 'cliente_valores', 'vlrfactura', 'Valor factura'),
            'cliente_valor_soporte' => $this->subqueryCurrencyColumn('cliente_valor_soporte', 'cliente_valores', 'vlrsoporte', 'Valor soporte'),
            'cliente_numero_moviles' => $this->subqueryNumericColumn('cliente_numero_moviles', 'cliente_valores', 'numeromoviles', 'Número móviles'),
            'cliente_valor_movil' => $this->subqueryCurrencyColumn('cliente_valor_movil', 'cliente_valores', 'vlrmovil', 'Valor móvil'),
            'cliente_valor_total' => $this->subqueryCurrencyColumn('cliente_valor_total', 'cliente_valores', 'valor_total', 'Valor total cliente'),

            'proforma_numero' => $this->directTextColumn('p.nro_prof', 'proforma', 'Número proforma', 'proforma_numero'),
            'proforma_estado' => [
                'group' => 'proforma',
                'label' => 'Estado',
                'type' => 'text',
                'select' => fn (Builder $query) => $query->addSelect('p.estado as proforma_estado'),
                'value' => fn (object $row) => $this->displayTextValue($this->proformasService->estadoLabel($row->proforma_estado ?? null)),
            ],
            'proforma_mes' => [
                'group' => 'proforma',
                'label' => 'Mes',
                'type' => 'text',
                'select' => fn (Builder $query) => $query->addSelect('p.mes as proforma_mes'),
                'value' => fn (object $row) => $this->displayTextValue($this->proformasService->monthLabel($row->proforma_mes ?? null)),
            ],
            'proforma_anio' => $this->directNumericColumn('p.anio', 'proforma', 'Año', 'proforma_anio'),
            'proforma_valor_total' => $this->directCurrencyColumn('p.vtotal', 'proforma', 'Valor total proforma', 'proforma_valor_total'),
            'proforma_fecha_generacion' => $this->directDateColumn('p.creado_en', 'proforma', 'Fecha generación', 'proforma_fecha_generacion'),
            'proforma_fecha_envio' => $this->directDateColumn('p.fecha_envio', 'proforma', 'Fecha envío', 'proforma_fecha_envio'),
            'proforma_fecha_pago' => [
                'group' => 'proforma',
                'label' => 'Fecha pago',
                'type' => 'date',
                'select' => fn (Builder $query) => $query->selectRaw("COALESCE(p.fpag, p.fpago) as proforma_fecha_pago"),
                'value' => fn (object $row) => $this->displayDateValue($row->proforma_fecha_pago ?? null),
            ],
            'proforma_fecha_facturacion' => $this->directDateColumn('p.ffac', 'proforma', 'Fecha facturación', 'proforma_fecha_facturacion'),
        ];
    }

    private function subqueryColumn(string $alias, string $group, string $field, string $label): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'text',
            'select' => fn (Builder $query) => $this->addClienteFieldSelect($query, $field, $alias),
            'value' => fn (object $row) => $this->displayTextValue($row->{$alias} ?? null),
        ];
    }

    private function subqueryDateColumn(string $alias, string $group, string $field, string $label): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'date',
            'select' => fn (Builder $query) => $this->addClienteFieldSelect($query, $field, $alias),
            'value' => fn (object $row) => $this->displayDateValue($row->{$alias} ?? null),
        ];
    }

    private function subqueryCurrencyColumn(string $alias, string $group, string $field, string $label): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'currency',
            'select' => fn (Builder $query) => $this->addClienteFieldSelect($query, $field, $alias),
            'value' => fn (object $row) => $this->toFloatOrNull($row->{$alias} ?? null),
        ];
    }

    private function subqueryNumericColumn(string $alias, string $group, string $field, string $label): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'number',
            'select' => fn (Builder $query) => $this->addClienteFieldSelect($query, $field, $alias),
            'value' => fn (object $row) => $this->toFloatOrNull($row->{$alias} ?? null),
        ];
    }

    private function directTextColumn(string $column, string $group, string $label, string $alias): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'text',
            'select' => fn (Builder $query) => $query->addSelect(DB::raw("{$column} as {$alias}")),
            'value' => fn (object $row) => $this->displayTextValue($row->{$alias} ?? null),
        ];
    }

    private function directNumericColumn(string $column, string $group, string $label, string $alias): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'number',
            'select' => fn (Builder $query) => $query->addSelect(DB::raw("{$column} as {$alias}")),
            'value' => fn (object $row) => $this->toFloatOrNull($row->{$alias} ?? null),
        ];
    }

    private function directCurrencyColumn(string $column, string $group, string $label, string $alias): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'currency',
            'select' => fn (Builder $query) => $query->addSelect(DB::raw("{$column} as {$alias}")),
            'value' => fn (object $row) => $this->toFloatOrNull($row->{$alias} ?? null),
        ];
    }

    private function directDateColumn(string $column, string $group, string $label, string $alias): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'type' => 'date',
            'select' => fn (Builder $query) => $query->addSelect(DB::raw("{$column} as {$alias}")),
            'value' => fn (object $row) => $this->displayDateValue($row->{$alias} ?? null),
        ];
    }

    private function addClienteFieldSelect(Builder $query, string $field, string $alias): void
    {
        if (!Schema::hasColumn('clientes_potenciales', $field)) {
            $query->selectRaw("NULL as {$alias}");

            return;
        }

        $query->addSelect(DB::raw($this->joinedClienteFieldExpression($field)." as {$alias}"));
    }

    private function addJoinedTipoClienteSelect(Builder $query, string $alias): void
    {
        if (!Schema::hasTable('tipos_cliente')) {
            $query->selectRaw("NULL as {$alias}");

            return;
        }

        $query->addSelect(DB::raw($this->joinedTipoClienteExpression()." as {$alias}"));
    }

    private function buildClienteFieldSubquery(string $field): Builder
    {
        return $this->buildClienteBaseSubquery()
            ->select("cp.{$field}")
            ->limit(1);
    }

    private function buildTipoClienteNombreSubquery(): Builder
    {
        $query = $this->buildClienteBaseSubquery();

        if (!Schema::hasTable('tipos_cliente')) {
            return $query->selectRaw('NULL')->limit(1);
        }

        return $query
            ->leftJoin('tipos_cliente as tc', 'tc.id', '=', 'cp.tipo_cliente_id')
            ->select('tc.nombre')
            ->limit(1);
    }

    private function buildClienteBaseSubquery(): Builder
    {
        return DB::table('clientes_potenciales as cp')
            ->where(function (Builder $query): void {
                $query->whereExists($this->buildClienteValoresExistsSubquery())
                    ->orWhereRaw('BINARY TRIM(cp.nit) = BINARY TRIM(p.nit)');
            })
            ->orderByRaw("CASE WHEN BINARY TRIM(cp.nit) = BINARY TRIM(p.nit) THEN 0 ELSE 1 END")
            ->orderByDesc('cp.idclientes_potenciales');
    }

    private function buildClienteValoresExistsSubquery(): Builder
    {
        return DB::table('valores_externos as ve')
            ->whereRaw("TRIM(COALESCE(ve.id_cliente, '')) <> ''")
            ->whereRaw('cp.idclientes_potenciales = CAST(TRIM(ve.id_cliente) AS UNSIGNED)')
            ->where(function (Builder $query): void {
                if ($this->hasSgProformIdCobroColumn()) {
                    $query
                        ->where(function (Builder $exact): void {
                            $exact
                                ->whereRaw('p.id_cobro IS NOT NULL')
                                ->whereRaw('p.id_cobro > 0')
                                ->whereRaw('ve.id_cobro = p.id_cobro');
                        })
                        ->orWhere(function (Builder $fallback): void {
                            $fallback
                                ->where(function (Builder $missingCobro): void {
                                    $missingCobro
                                        ->whereRaw('p.id_cobro IS NULL')
                                        ->orWhereRaw('p.id_cobro = 0');
                                })
                                ->whereRaw('BINARY TRIM(cp.nit) = BINARY TRIM(p.nit)')
                                ->whereRaw('BINARY LOWER(TRIM(ve.mes)) = BINARY '.$this->proformaMesTextoSql('p.mes'))
                                ->whereRaw('ve.`año` = p.anio')
                                ->whereRaw($this->regimenMatchSql('cp.regimen', 'p.emisora'));
                        });

                    return;
                }

                $query
                    ->whereRaw('BINARY TRIM(cp.nit) = BINARY TRIM(p.nit)')
                    ->whereRaw('BINARY LOWER(TRIM(ve.mes)) = BINARY '.$this->proformaMesTextoSql('p.mes'))
                    ->whereRaw('ve.`año` = p.anio')
                    ->whereRaw($this->regimenMatchSql('cp.regimen', 'p.emisora'));
            });
    }

    private function applyClienteJoins(Builder $query): void
    {
        if ($this->hasSgProformIdCobroColumn()) {
            $query->leftJoinSub($this->buildClienteJoinByCobroSubquery(), 've_cobro_match', function ($join): void {
                $join->on('ve_cobro_match.id_cobro', '=', 'p.id_cobro');
            });
        } else {
            $query->leftJoinSub($this->buildEmptyClienteJoinSubquery(), 've_cobro_match', function ($join): void {
                $join->whereRaw('1 = 0');
            });
        }

        $query->leftJoin('clientes_potenciales as cp_cobro', 'cp_cobro.idclientes_potenciales', '=', 've_cobro_match.id_cliente');

        if (Schema::hasTable('tipos_cliente')) {
            $query->leftJoin('tipos_cliente as tc_cobro', 'tc_cobro.id', '=', 'cp_cobro.tipo_cliente_id');
        }

        $query->leftJoinSub($this->buildClienteJoinFallbackSubquery(), 've_fallback_match', function ($join): void {
            if ($this->hasSgProformIdCobroColumn()) {
                $join->whereRaw('(p.id_cobro IS NULL OR p.id_cobro = 0)');
            }

            $join->whereRaw('BINARY ve_fallback_match.nit_normalized = BINARY TRIM(p.nit)')
                ->whereRaw('BINARY ve_fallback_match.mes_normalized = BINARY '.$this->proformaMesTextoSql('p.mes'))
                ->whereRaw('ve_fallback_match.anio = p.anio')
                ->whereRaw('BINARY ve_fallback_match.emisora_normalized = BINARY '.$this->normalizedEmisoraSql('p.emisora'));
        });

        $query->leftJoin('clientes_potenciales as cp_fallback', 'cp_fallback.idclientes_potenciales', '=', 've_fallback_match.id_cliente');

        if (Schema::hasTable('tipos_cliente')) {
            $query->leftJoin('tipos_cliente as tc_fallback', 'tc_fallback.id', '=', 'cp_fallback.tipo_cliente_id');
        }
    }

    private function buildClienteJoinByCobroSubquery(): Builder
    {
        return DB::table('valores_externos as ve')
            ->selectRaw('ve.id_cobro, MAX(CAST(TRIM(ve.id_cliente) AS UNSIGNED)) as id_cliente')
            ->whereNotNull('ve.id_cobro')
            ->whereRaw('ve.id_cobro > 0')
            ->whereRaw("TRIM(COALESCE(ve.id_cliente, '')) <> ''")
            ->groupBy('ve.id_cobro');
    }

    private function buildEmptyClienteJoinSubquery(): Builder
    {
        return DB::query()
            ->selectRaw('NULL as id_cobro, NULL as id_cliente')
            ->whereRaw('1 = 0');
    }

    private function buildClienteJoinFallbackSubquery(): Builder
    {
        return DB::table('valores_externos as ve')
            ->join('clientes_potenciales as cp_lookup', 'cp_lookup.idclientes_potenciales', '=', DB::raw('CAST(TRIM(ve.id_cliente) AS UNSIGNED)'))
            ->selectRaw('TRIM(cp_lookup.nit) as nit_normalized')
            ->selectRaw('LOWER(TRIM(ve.mes)) as mes_normalized')
            ->selectRaw('ve.`año` as anio')
            ->selectRaw($this->normalizedRegimenSql('cp_lookup.regimen').' as emisora_normalized')
            ->selectRaw('MAX(cp_lookup.idclientes_potenciales) as id_cliente')
            ->whereRaw("TRIM(COALESCE(ve.id_cliente, '')) <> ''")
            ->groupByRaw('TRIM(cp_lookup.nit), LOWER(TRIM(ve.mes)), ve.`año`, '.$this->normalizedRegimenSql('cp_lookup.regimen'));
    }

    private function requiresClienteJoins(array $selectedColumns): bool
    {
        foreach ($selectedColumns as $column) {
            if (str_starts_with($column, 'cliente_')) {
                return true;
            }
        }

        return false;
    }

    private function joinedClienteFieldExpression(string $field): string
    {
        return "COALESCE(cp_cobro.{$field}, cp_fallback.{$field})";
    }

    private function joinedTipoClienteExpression(): string
    {
        return 'COALESCE(tc_cobro.nombre, tc_fallback.nombre)';
    }

    private function normalizedRegimenSql(string $regimenColumn): string
    {
        return "CASE UPPER(TRIM(COALESCE({$regimenColumn}, '')))
            WHEN 'PCS' THEN 'PCS'
            WHEN 'SMP' THEN 'SMP'
            ELSE 'SAS'
        END";
    }

    private function normalizedEmisoraSql(string $emisoraColumn): string
    {
        return "UPPER(TRIM(COALESCE({$emisoraColumn}, 'SAS')))";
    }

    private function regimenMatchSql(string $regimenColumn, string $emisoraColumn): string
    {
        return 'BINARY '.$this->normalizedRegimenSql($regimenColumn).' = BINARY '.$this->normalizedEmisoraSql($emisoraColumn);
    }

    private function hasSgProformIdCobroColumn(): bool
    {
        if ($this->sgProformHasIdCobroColumn !== null) {
            return $this->sgProformHasIdCobroColumn;
        }

        return $this->sgProformHasIdCobroColumn = Schema::hasColumn('sg_proform', 'id_cobro');
    }

    private function proformaMesTextoSql(string $mesColumn): string
    {
        return "CASE {$mesColumn}
            WHEN 1 THEN 'enero'
            WHEN 2 THEN 'febrero'
            WHEN 3 THEN 'marzo'
            WHEN 4 THEN 'abril'
            WHEN 5 THEN 'mayo'
            WHEN 6 THEN 'junio'
            WHEN 7 THEN 'julio'
            WHEN 8 THEN 'agosto'
            WHEN 9 THEN 'septiembre'
            WHEN 10 THEN 'octubre'
            WHEN 11 THEN 'noviembre'
            WHEN 12 THEN 'diciembre'
            ELSE ''
        END";
    }

    private function normalizarEntero(null|string|int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' || !ctype_digit($string)) {
            return null;
        }

        return (int) $string;
    }

    private function normalizarMes(null|string|int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $mes = mb_strtolower(trim((string) $value));

        if ($mes === '') {
            return null;
        }

        if (ctype_digit($mes)) {
            $mesInt = (int) $mes;

            return ($mesInt >= 1 && $mesInt <= 12) ? $mesInt : null;
        }

        $mesInt = array_search($mes, ProformasService::MESES, true);

        return $mesInt !== false ? (int) $mesInt : null;
    }

    private function stringValue(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }

    private function displayTextValue(mixed $value): string
    {
        return $this->stringValue($value) ?? self::TEXT_PLACEHOLDER;
    }

    private function formatDate(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($string)->format('d/m/Y');
        } catch (\Throwable) {
            return $string;
        }
    }

    private function displayDateValue(mixed $value): string
    {
        return $this->formatDate($value) ?? self::TEXT_PLACEHOLDER;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

}
