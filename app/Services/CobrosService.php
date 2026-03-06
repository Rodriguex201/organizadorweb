<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class CobrosService
{
    public function paginateCobros(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        try {
            $query = DB::table('valores_externos as ve')
                ->leftJoin('clientes_potenciales as cp', 'cp.id_cliente_potencial', '=', 've.id_cliente_potencial')
                ->select([
                    've.id_cobro',
                    've.proforma',
                    've.mes',
                    've.ano',
                    've.id_cliente_potencial',
                    've.total',
                    'cp.nombre as cliente_nombre',
                    'cp.apellido as cliente_apellido',
                    'cp.razon_social',
                ]);

            $query
                ->when($filters['mes'] ?? null, fn ($q, $mes) => $q->where('ve.mes', (int) $mes))
                ->when($filters['ano'] ?? null, fn ($q, $ano) => $q->where('ve.ano', (int) $ano))
                ->when($filters['proforma'] ?? null, fn ($q, $proforma) => $q->where('ve.proforma', 'like', "%{$proforma}%"));

            return $query
                ->orderByDesc('ve.ano')
                ->orderByDesc('ve.mes')
                ->orderByDesc('ve.id_cobro')
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
}
