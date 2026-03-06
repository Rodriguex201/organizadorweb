<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

class CobrosService
{
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
                    DB::raw('ve.`año` as anio'),
                    've.id_cliente_potencial',
                    've.total',
                    'cp.nombre as cliente_nombre',
                    'cp.apellido as cliente_apellido',
                    'cp.razon_social',
                ]);

            $mesNormalizado = $this->normalizarMes($filters['mes'] ?? null);

            $query
                ->when($mesNormalizado, fn ($q, $mes) => $q->whereRaw('LOWER(TRIM(ve.mes)) = ?', [$mes]))
                ->when($filters['anio'] ?? null, fn ($q, $anio) => $q->where('ve.año', (int) $anio))
                ->when($filters['proforma'] ?? null, fn ($q, $proforma) => $q->where('ve.proforma', 'like', '%' . trim($proforma) . '%'));

            $ordenMes = "CASE LOWER(TRIM(ve.mes))\n"
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

            return $query
                ->orderByDesc('ve.año')
                ->orderByRaw($ordenMes . ' DESC')
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
}
