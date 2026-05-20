<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProformaCarteraService
{
    private ?bool $sgProformHasIdCobroColumn = null;

    public function __construct(
        private readonly ProformasService $proformasService,
    ) {
    }

    public function resolveFilters(array $input = []): array
    {
        $estado = $this->normalizarEntero($input['estado'] ?? null);

        if (!in_array($estado, [
            ProformasService::ESTADO_GENERADA,
            ProformasService::ESTADO_ENVIADA,
        ], true)) {
            $estado = null;
        }

        return [
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'empresa' => trim((string) ($input['empresa'] ?? '')),
            'nit' => trim((string) ($input['nit'] ?? '')),
            'fecha_desde' => $this->normalizeDateFilter($input['fecha_desde'] ?? null),
            'fecha_hasta' => $this->normalizeDateFilter($input['fecha_hasta'] ?? null),
            'estado' => $estado,
            'solo_acumuladas' => $this->toBooleanFlag($input['solo_acumuladas'] ?? null),
        ];
    }

    public function paginateCartera(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildCarteraQuery($filters)
            ->orderByDesc('dias_vencido')
            ->orderByDesc('valor_total_deuda')
            ->orderBy('empresa')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getSummary(array $filters = []): array
    {
        $summary = DB::query()
            ->fromSub($this->buildCarteraQuery($filters), 'cartera')
            ->selectRaw('COUNT(*) as empresas_con_deuda')
            ->selectRaw('COALESCE(SUM(valor_total_deuda), 0) as total_cartera')
            ->selectRaw('COALESCE(AVG(valor_total_deuda), 0) as promedio_deuda')
            ->selectRaw('COALESCE(SUM(cantidad_proformas), 0) as cantidad_proformas_pendientes')
            ->first();

        return [
            'empresas_con_deuda' => (int) ($summary->empresas_con_deuda ?? 0),
            'total_cartera' => (float) ($summary->total_cartera ?? 0),
            'promedio_deuda' => (float) ($summary->promedio_deuda ?? 0),
            'cantidad_proformas_pendientes' => (int) ($summary->cantidad_proformas_pendientes ?? 0),
        ];
    }

    public function getRowsForExport(array $filters = []): Collection
    {
        return $this->buildCarteraQuery($filters)
            ->orderByDesc('dias_vencido')
            ->orderByDesc('valor_total_deuda')
            ->orderBy('empresa')
            ->get();
    }

    public function estadoLabel(null|string|int $estado): string
    {
        return $this->proformasService->estadoLabel($estado);
    }

    public function estadoBadgeStyle(null|string|int $estado): string
    {
        return $this->proformasService->estadoBadgeStyle($estado);
    }

    private function buildCarteraQuery(array $filters): Builder
    {
        $aggregated = $this->buildAggregatedPendingSubquery($filters);
        $latest = $this->buildLatestPendingSubquery($filters);

        $query = DB::query()
            ->fromSub($aggregated, 'agg')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('latest.nit', '=', 'agg.nit')
                    ->on('latest.latest_sort_key', '=', 'agg.latest_sort_key');
            })
            ->join('sg_proform as lp', function ($join): void {
                $join->whereRaw('TRIM(lp.nit) = latest.nit')
                    ->whereRaw($this->pendingSortKeySql('lp').' = latest.latest_sort_key');
            });

        $this->applyClienteJoins($query, 'lp');

        $query->select([
            'agg.nit',
            'agg.meses_pendientes',
            'agg.cantidad_proformas',
            'agg.valor_total_deuda',
            'agg.dias_vencido',
            'lp.id as ultima_proforma_id',
            'lp.nro_prof as ultima_proforma_numero',
            'lp.mes as ultima_proforma_mes',
            'lp.anio as ultima_proforma_anio',
            'lp.estado as estado_actual',
        ])->selectRaw("COALESCE(".$this->joinedClienteFieldExpression('codigo').", '') as codigo")
            ->selectRaw("COALESCE(NULLIF(TRIM(".$this->joinedClienteFieldExpression('empresa')."), ''), NULLIF(TRIM(".$this->joinedClienteFieldExpression('nombre')."), ''), NULLIF(TRIM(lp.emp), ''), 'N/D') as empresa")
            ->selectRaw("COALESCE(NULLIF(TRIM(".$this->joinedClienteFieldExpression('email')."), ''), 'N/D') as email")
            ->selectRaw("COALESCE(NULLIF(TRIM(".$this->joinedClienteFieldExpression('celular1')."), ''), NULLIF(TRIM(".$this->joinedClienteFieldExpression('celular2')."), ''), 'N/D') as celular")
            ->selectRaw("COALESCE(NULLIF(TRIM(".$this->joinedClienteFieldExpression('nota_cobro')."), ''), 'N/D') as nota");

        $this->applyOuterFilters($query, $filters);

        return $query;
    }

    private function buildAggregatedPendingSubquery(array $filters): Builder
    {
        $query = DB::table('sg_proform as p')
            ->whereIn('p.estado', [
                ProformasService::ESTADO_GENERADA,
                ProformasService::ESTADO_ENVIADA,
            ])
            ->whereRaw("TRIM(COALESCE(p.nit, '')) <> ''");

        $this->applyPendingFilters($query, $filters);

        return $query
            ->selectRaw('TRIM(p.nit) as nit')
            ->selectRaw('COUNT(*) as cantidad_proformas')
            ->selectRaw("COUNT(DISTINCT CONCAT(p.anio, '-', LPAD(p.mes, 2, '0'))) as meses_pendientes")
            ->selectRaw('COALESCE(SUM(p.vtotal), 0) as valor_total_deuda')
            ->selectRaw('MIN(CONCAT(LPAD(p.anio, 4, "0"), LPAD(p.mes, 2, "0"))) as oldest_period_key')
            ->selectRaw('MAX('.$this->pendingSortKeySql('p').') as latest_sort_key')
            ->selectRaw("GREATEST(DATEDIFF(CURDATE(), LAST_DAY(STR_TO_DATE(CONCAT(MIN(CONCAT(LPAD(p.anio, 4, '0'), LPAD(p.mes, 2, '0'))), '01'), '%Y%m%d'))), 0) as dias_vencido")
            ->groupByRaw('TRIM(p.nit)');
    }

    private function buildLatestPendingSubquery(array $filters): Builder
    {
        $query = DB::table('sg_proform as p')
            ->whereIn('p.estado', [
                ProformasService::ESTADO_GENERADA,
                ProformasService::ESTADO_ENVIADA,
            ])
            ->whereRaw("TRIM(COALESCE(p.nit, '')) <> ''");

        $this->applyPendingFilters($query, $filters);

        return $query
            ->selectRaw('TRIM(p.nit) as nit')
            ->selectRaw('MAX('.$this->pendingSortKeySql('p').') as latest_sort_key')
            ->groupByRaw('TRIM(p.nit)');
    }

    private function applyPendingFilters(Builder $query, array $filters): void
    {
        $estado = $this->normalizarEntero($filters['estado'] ?? null);
        $fechaDesde = $this->normalizeDateFilter($filters['fecha_desde'] ?? null);
        $fechaHasta = $this->normalizeDateFilter($filters['fecha_hasta'] ?? null);

        if ($estado !== null) {
            $query->where('p.estado', $estado);
        }

        if ($fechaDesde !== null && $fechaHasta !== null) {
            $fromDate = min($fechaDesde, $fechaHasta);
            $toDate = max($fechaDesde, $fechaHasta);
            $query->whereDate('p.creado_en', '>=', $fromDate)
                ->whereDate('p.creado_en', '<=', $toDate);

            return;
        }

        if ($fechaDesde !== null) {
            $query->whereDate('p.creado_en', '>=', $fechaDesde);
        }

        if ($fechaHasta !== null) {
            $query->whereDate('p.creado_en', '<=', $fechaHasta);
        }
    }

    private function applyOuterFilters(Builder $query, array $filters): void
    {
        $codigo = $this->normalizeTextFilter($filters['codigo'] ?? '');
        $empresa = $this->normalizeTextFilter($filters['empresa'] ?? '');
        $nit = trim((string) ($filters['nit'] ?? ''));
        $soloAcumuladas = $this->toBooleanFlag($filters['solo_acumuladas'] ?? false);

        if ($codigo !== '') {
            $query->whereRaw(
                $this->normalizedSqlExpression($this->joinedClienteFieldExpression('codigo')).' LIKE ?',
                ['%'.$codigo.'%']
            );
        }

        if ($empresa !== '') {
            $empresaLike = '%'.$empresa.'%';

            $query->where(function (Builder $empresaQuery) use ($empresaLike): void {
                $empresaQuery
                    ->whereRaw($this->normalizedSqlExpression('lp.emp').' LIKE ?', [$empresaLike])
                    ->orWhereRaw($this->normalizedSqlExpression($this->joinedClienteFieldExpression('nombre')).' LIKE ?', [$empresaLike])
                    ->orWhereRaw($this->normalizedSqlExpression($this->joinedClienteFieldExpression('empresa')).' LIKE ?', [$empresaLike]);
            });
        }

        if ($nit !== '') {
            $query->where('agg.nit', 'like', '%'.$nit.'%');
        }

        if ($soloAcumuladas) {
            $query->where('agg.meses_pendientes', '>', 1);
        }
    }

    private function applyClienteJoins(Builder $query, string $proformaAlias): void
    {
        if ($this->hasSgProformIdCobroColumn()) {
            $query->leftJoinSub($this->buildClienteJoinByCobroSubquery(), 've_cobro_match', function ($join) use ($proformaAlias): void {
                $join->on('ve_cobro_match.id_cobro', '=', "{$proformaAlias}.id_cobro");
            });
        } else {
            $query->leftJoinSub($this->buildEmptyClienteJoinSubquery(), 've_cobro_match', function ($join): void {
                $join->whereRaw('1 = 0');
            });
        }

        $query->leftJoin('clientes_potenciales as cp_cobro', 'cp_cobro.idclientes_potenciales', '=', 've_cobro_match.id_cliente');

        $query->leftJoinSub($this->buildClienteJoinFallbackSubquery(), 've_fallback_match', function ($join) use ($proformaAlias): void {
            if ($this->hasSgProformIdCobroColumn()) {
                $join->whereRaw("({$proformaAlias}.id_cobro IS NULL OR {$proformaAlias}.id_cobro = 0)");
            }

            $join->whereRaw("BINARY ve_fallback_match.nit_normalized = BINARY TRIM({$proformaAlias}.nit)")
                ->whereRaw('BINARY ve_fallback_match.mes_normalized = BINARY '.$this->proformaMesTextoSql("{$proformaAlias}.mes"))
                ->whereRaw("ve_fallback_match.anio = {$proformaAlias}.anio")
                ->whereRaw('BINARY ve_fallback_match.emisora_normalized = BINARY '.$this->normalizedEmisoraSql("{$proformaAlias}.emisora"));
        });

        $query->leftJoin('clientes_potenciales as cp_fallback', 'cp_fallback.idclientes_potenciales', '=', 've_fallback_match.id_cliente');
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

    private function joinedClienteFieldExpression(string $field): string
    {
        return "COALESCE(cp_cobro.{$field}, cp_fallback.{$field})";
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

    private function pendingSortKeySql(string $alias): string
    {
        return "CONCAT(LPAD({$alias}.anio, 4, '0'), LPAD({$alias}.mes, 2, '0'), LPAD({$alias}.id, 10, '0'))";
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

    private function normalizeTextFilter(null|string|int $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return mb_strtolower($normalized ?? '');
    }

    private function hasSgProformIdCobroColumn(): bool
    {
        if ($this->sgProformHasIdCobroColumn !== null) {
            return $this->sgProformHasIdCobroColumn;
        }

        return $this->sgProformHasIdCobroColumn = Schema::hasColumn('sg_proform', 'id_cobro');
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

    private function normalizeDateFilter(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return null;
        }

        try {
            return Carbon::parse($string)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toBooleanFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
