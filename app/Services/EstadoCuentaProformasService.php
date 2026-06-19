<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EstadoCuentaProformasService
{
    private const ESTADO_FILTER_DEFAULT = 'default';
    private const ESTADO_FILTER_TODAS = 'todas';

    public function __construct(
        private readonly ProformasService $proformasService,
        private readonly ProformaPdfService $proformaPdfService,
    ) {
    }

    public function defaultFilters(): array
    {
        return [
            'busqueda' => null,
            'nit' => null,
            'estado' => self::ESTADO_FILTER_DEFAULT,
        ];
    }

    public function estadoFilterOptions(): array
    {
        return [
            self::ESTADO_FILTER_DEFAULT => 'Generadas, Enviadas y Facturadas',
            (string) ProformasService::ESTADO_GENERADA => $this->proformasService->estadoLabel(ProformasService::ESTADO_GENERADA),
            (string) ProformasService::ESTADO_ENVIADA => $this->proformasService->estadoLabel(ProformasService::ESTADO_ENVIADA),
            (string) ProformasService::ESTADO_PAGADA => $this->proformasService->estadoLabel(ProformasService::ESTADO_PAGADA),
            (string) ProformasService::ESTADO_FACTURADA => $this->proformasService->estadoLabel(ProformasService::ESTADO_FACTURADA),
            self::ESTADO_FILTER_TODAS => 'Todas',
        ];
    }

    public function hasSearchCriteria(array $filters): bool
    {
        return trim((string) ($filters['busqueda'] ?? '')) !== ''
            || trim((string) ($filters['nit'] ?? '')) !== '';
    }

    public function searchProformas(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        if (!$this->hasSearchCriteria($filters)) {
            return $this->emptyPaginator($perPage);
        }

        $query = $this->baseQuery();
        $busqueda = trim((string) ($filters['busqueda'] ?? ''));
        $nit = trim((string) ($filters['nit'] ?? ''));

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';

            $query->where(function ($innerQuery) use ($like): void {
                $innerQuery
                    ->where('p.emp', 'like', $like)
                    ->orWhere('cp.codigo', 'like', $like)
                    ->orWhere('cp.nombre', 'like', $like)
                    ->orWhere('cp.empresa', 'like', $like);
            });
        }

        if ($nit !== '') {
            $query->where('p.nit', 'like', '%'.$nit.'%');
        }

        $this->applyEstadoFilter($query, $filters['estado'] ?? null);

        $paginator = $query
            ->orderBy('p.nit')
            ->orderBy('p.anio')
            ->orderBy('p.mes')
            ->orderBy('p.id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $this->decorateProformas($paginator->getCollection())
        );

        return $paginator;
    }

    public function findSelectedProformas(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return collect();
        }

        return $this->decorateProformas(
            $this->baseQuery()
                ->whereIn('p.id', $ids)
                ->orderBy('p.anio')
                ->orderBy('p.mes')
                ->orderBy('p.id')
                ->get()
        );
    }

    public function validateSelection(Collection $proformas): array
    {
        if ($proformas->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Debe seleccionar al menos una proforma.',
            ];
        }

        $nits = $proformas
            ->map(fn (object $proforma) => trim((string) ($proforma->nit ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($nits->count() !== 1) {
            return [
                'ok' => false,
                'message' => 'Solo puede generar un estado de cuenta con proformas del mismo NIT.',
            ];
        }

        return [
            'ok' => true,
            'message' => null,
        ];
    }

    public function depurateSelection(Collection $proformas): array
    {
        $validation = $this->validateSelection($proformas);

        if (!($validation['ok'] ?? false)) {
            return $validation + [
                'proformas' => collect(),
                'warnings' => [],
                'excluded_count' => 0,
            ];
        }

        $warnings = [];
        $kept = collect();
        $excludedCount = 0;

        $grouped = $proformas->groupBy(fn (object $proforma) => $this->dedupeGroupKey($proforma));

        foreach ($grouped as $groupKey => $items) {
            $items = $items->values();

            if ($items->count() === 1) {
                $kept->push($items->first());
                continue;
            }

            $corruptMessage = $this->resolveCorruptGroupMessage($items);
            if ($corruptMessage !== null) {
                return [
                    'ok' => false,
                    'message' => $corruptMessage,
                    'proformas' => collect(),
                    'warnings' => [],
                    'excluded_count' => 0,
                ];
            }

            $sorted = $items
                ->sort(function (object $left, object $right): int {
                    $leftCreatedAt = Carbon::parse((string) $left->creado_en);
                    $rightCreatedAt = Carbon::parse((string) $right->creado_en);

                    if ($leftCreatedAt->equalTo($rightCreatedAt)) {
                        return ((int) ($right->id ?? 0)) <=> ((int) ($left->id ?? 0));
                    }

                    return $rightCreatedAt->getTimestamp() <=> $leftCreatedAt->getTimestamp();
                })
                ->values();

            $keptProforma = $sorted->first();
            $excluded = $sorted->slice(1)->values();

            $kept->push($keptProforma);
            $excludedCount += $excluded->count();

            $warning = [
                'nit' => trim((string) ($keptProforma->nit ?? '')),
                'periodo' => $this->periodLabel((int) ($keptProforma->mes ?? 0), (int) ($keptProforma->anio ?? 0)),
                'emisora' => strtoupper(trim((string) ($keptProforma->emisora ?? 'N/D'))),
                'conservada' => $this->warningRowData($keptProforma),
                'excluidas' => $excluded->map(fn (object $proforma) => $this->warningRowData($proforma))->values()->all(),
            ];

            $warnings[] = $warning;
            $this->logDepuration($warning);
        }

        $kept = $kept
            ->sortBy(fn (object $proforma) => sprintf(
                '%04d-%02d-%010d',
                (int) ($proforma->anio ?? 0),
                (int) ($proforma->mes ?? 0),
                (int) ($proforma->id ?? 0),
            ))
            ->values();

        return [
            'ok' => true,
            'message' => null,
            'proformas' => $kept,
            'warnings' => $warnings,
            'excluded_count' => $excludedCount,
        ];
    }

    public function buildEstadoCuentaPayload(Collection $proformas): array
    {
        $sorted = $proformas
            ->sortBy(fn (object $proforma) => sprintf(
                '%04d-%02d-%010d',
                (int) ($proforma->anio ?? 0),
                (int) ($proforma->mes ?? 0),
                (int) ($proforma->id ?? 0),
            ))
            ->values();

        /** @var object|null $primera */
        $primera = $sorted->first();
        /** @var object|null $ultima */
        $ultima = $sorted->last();

        return [
            'empresa' => trim((string) ($primera->empresa_resuelta ?? $primera->emp ?? 'N/D')) ?: 'N/D',
            'nit' => trim((string) ($primera->nit ?? 'N/D')) ?: 'N/D',
            'correo' => $this->resolveClientValue([
                $primera->cliente_email_raw ?? null,
            ]),
            'telefono' => $this->resolveClientValue([
                $primera->cliente_celular1_raw ?? null,
                $primera->cliente_celular2_raw ?? null,
            ]),
            'fecha_generacion' => now(),
            'cantidad_proformas' => $sorted->count(),
            'total_acumulado' => (float) $sorted->sum(fn (object $proforma) => (float) ($proforma->vtotal ?? 0)),
            'periodo_antiguo' => $primera ? $this->periodLabel((int) ($primera->mes ?? 0), (int) ($primera->anio ?? 0)) : 'N/D',
            'periodo_reciente' => $ultima ? $this->periodLabel((int) ($ultima->mes ?? 0), (int) ($ultima->anio ?? 0)) : 'N/D',
            'emisoras' => $this->buildIssuerContext($sorted),
            'proformas' => $sorted->map(function (object $proforma): array {
                return [
                    'id' => (int) ($proforma->id ?? 0),
                    'nro_prof' => (string) ($proforma->nro_prof ?: '#'.$proforma->id),
                    'periodo' => $this->periodLabel((int) ($proforma->mes ?? 0), (int) ($proforma->anio ?? 0)),
                    'estado' => $this->proformasService->estadoLabel($proforma->estado ?? null),
                    'valor' => (float) ($proforma->vtotal ?? 0),
                    'emisora' => strtoupper(trim((string) ($proforma->emisora ?? 'N/D'))),
                ];
            })->values()->all(),
        ];
    }

    public function browserFilename(array $payload): string
    {
        $empresa = preg_replace('/[^A-Za-z0-9]+/', '_', $this->toAsciiUpper((string) ($payload['empresa'] ?? 'SIN_EMPRESA'))) ?: 'SIN_EMPRESA';
        $nit = preg_replace('/\D+/', '', (string) ($payload['nit'] ?? '')) ?: 'SIN_NIT';

        return 'ESTADO_CUENTA_CONSOLIDADO_'.$empresa.'_'.$nit.'.pdf';
    }

    public function resolveDefaultDestinatarios(?LengthAwarePaginator $proformas): string
    {
        if (!$proformas) {
            return '';
        }

        foreach ($proformas->items() as $proforma) {
            $email = trim((string) ($proforma->cliente_email_raw ?? ''));

            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    private function baseQuery()
    {
        return DB::table('sg_proform as p')
            ->leftJoin('clientes_potenciales as cp', function ($join): void {
                $join->whereRaw('BINARY cp.nit = BINARY p.nit');
            })
            ->select([
                'p.id',
                'p.id_cobro',
                'p.nro_prof',
                'p.emp',
                'p.nit',
                'p.emisora',
                'p.mes',
                'p.anio',
                'p.vtotal',
                'p.estado',
                'p.creado_en',
                'cp.idclientes_potenciales as id_cliente',
                'cp.codigo as codigo',
                'cp.nombre as cliente_nombre',
                'cp.empresa as cliente_empresa',
                'cp.email as cliente_email_raw',
                'cp.celular1 as cliente_celular1_raw',
                'cp.celular2 as cliente_celular2_raw',
            ]);
    }

    private function applyEstadoFilter($query, mixed $estadoFilter): void
    {
        $estadoFilter = trim((string) ($estadoFilter ?? self::ESTADO_FILTER_DEFAULT));

        if ($estadoFilter === '' || $estadoFilter === self::ESTADO_FILTER_DEFAULT) {
            $query->whereIn('p.estado', [
                ProformasService::ESTADO_GENERADA,
                ProformasService::ESTADO_ENVIADA,
                ProformasService::ESTADO_FACTURADA,
            ]);

            return;
        }

        if ($estadoFilter === self::ESTADO_FILTER_TODAS) {
            return;
        }

        $query->where('p.estado', (int) $estadoFilter);
    }

    private function decorateProformas(Collection $proformas): Collection
    {
        return $proformas->map(function (object $proforma): object {
            $proforma->estado_label = $this->proformasService->estadoLabel($proforma->estado ?? null);
            $proforma->periodo_label = $this->periodLabel((int) ($proforma->mes ?? 0), (int) ($proforma->anio ?? 0));
            $proforma->empresa_resuelta = $this->resolveEmpresa($proforma);
            $proforma->creado_en = $proforma->creado_en ?? null;

            return $proforma;
        });
    }

    private function periodLabel(int $mes, int $anio): string
    {
        return $this->proformasService->monthLabel($mes).' '.$anio;
    }

    private function resolveEmpresa(object $proforma): string
    {
        foreach ([
            $proforma->cliente_empresa ?? null,
            $proforma->emp ?? null,
            $proforma->cliente_nombre ?? null,
        ] as $candidate) {
            $value = trim((string) ($candidate ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return 'N/D';
    }

    private function logicalPeriodKey(object $proforma): string
    {
        return implode('|', [
            trim((string) ($proforma->nit ?? '')),
            (int) ($proforma->mes ?? 0),
            (int) ($proforma->anio ?? 0),
            strtoupper(trim((string) ($proforma->emisora ?? ''))),
        ]);
    }

    private function dedupeGroupKey(object $proforma): string
    {
        $idCobro = (int) ($proforma->id_cobro ?? 0);

        if ($idCobro > 0) {
            return 'id_cobro:'.$idCobro;
        }

        return 'logical:'.$this->logicalPeriodKey($proforma);
    }

    private function resolveCorruptGroupMessage(Collection $items): ?string
    {
        foreach ($items as $proforma) {
            if (trim((string) ($proforma->nit ?? '')) === '') {
                return 'Se encontraron proformas inconsistentes sin NIT. No es posible depurarlas automáticamente.';
            }

            if ((int) ($proforma->id_cobro ?? 0) <= 0) {
                if ((int) ($proforma->mes ?? 0) <= 0 || (int) ($proforma->anio ?? 0) <= 0 || trim((string) ($proforma->emisora ?? '')) === '') {
                    return 'Se encontraron proformas inconsistentes sin periodo lógico completo. No es posible depurarlas automáticamente.';
                }
            }

            if (trim((string) ($proforma->creado_en ?? '')) === '') {
                return 'Se encontraron proformas duplicadas sin fecha de creación. No es posible determinar automáticamente cuál es la vigente.';
            }
        }

        return null;
    }

    private function warningRowData(object $proforma): array
    {
        return [
            'id' => (int) ($proforma->id ?? 0),
            'nro_prof' => (string) ($proforma->nro_prof ?: '#'.$proforma->id),
            'valor' => (float) ($proforma->vtotal ?? 0),
            'creado_en' => trim((string) ($proforma->creado_en ?? '')),
        ];
    }

    private function logDepuration(array $warning): void
    {
        $user = Auth::user();
        $usuario = $user?->name
            ?? $user?->email
            ?? session('usuario')
            ?? session('email')
            ?? 'desconocido';

        Log::info('estado_cuenta.depuration', [
            'nit' => $warning['nit'] ?? null,
            'periodo' => $warning['periodo'] ?? null,
            'emisora' => $warning['emisora'] ?? null,
            'proforma_conservada' => $warning['conservada'] ?? null,
            'proformas_excluidas' => $warning['excluidas'] ?? [],
            'usuario' => $usuario,
            'fecha_hora' => now()->toDateTimeString(),
        ]);
    }

    private function toAsciiUpper(string $value): string
    {
        $value = \Illuminate\Support\Str::ascii(trim($value));
        $value = preg_replace('/\s+/', '_', $value) ?? $value;

        return trim(mb_strtoupper($value), '_');
    }

    private function resolveClientValue(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return 'N/D';
    }

    private function buildIssuerContext(Collection $proformas): array
    {
        $codigos = $proformas
            ->map(fn (object $proforma) => strtoupper(trim((string) ($proforma->emisora ?? ''))))
            ->filter()
            ->unique()
            ->values();

        $resolved = $codigos
            ->map(fn (string $codigo): array => $this->proformaPdfService->resolveIssuerData($codigo))
            ->values()
            ->all();

        return [
            'mode' => count($resolved) > 1 ? 'multiple' : 'single',
            'codes' => $codigos->all(),
            'items' => $resolved,
            'primary' => $resolved[0] ?? $this->proformaPdfService->resolveIssuerData('SAS'),
        ];
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new Paginator(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: Paginator::resolveCurrentPage(),
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }
}
