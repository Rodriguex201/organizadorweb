<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProformasService
{
    public const ESTADOS = [
        2 => 'Generada',
        4 => 'Pagada',
        6 => 'Facturada',
    ];

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

    public function paginateProformas(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('sg_proform as p')
            ->select([
                'p.id',
                'p.nro_prof',
                'p.emp',
                'p.nit',
                'p.emisora',
                'p.mes',
                'p.anio',
                'p.vtotal',
                'p.estado',
                'p.rpdf',
                'p.npdf',
            ]);

        $nroProf = trim((string) ($filters['nro_prof'] ?? ''));
        $nit = trim((string) ($filters['nit'] ?? ''));
        $empresa = trim((string) ($filters['empresa'] ?? ''));
        $emisora = trim((string) ($filters['emisora'] ?? ''));
        $estado = $this->normalizarEntero($filters['estado'] ?? null);
        $anio = $this->normalizarEntero($filters['anio'] ?? null);
        $mes = $this->normalizarMes($filters['mes'] ?? null);

        return $query
            ->when($nroProf !== '', fn ($q) => $q->where('p.nro_prof', 'like', "%{$nroProf}%"))
            ->when($nit !== '', fn ($q) => $q->where('p.nit', 'like', "%{$nit}%"))
            ->when($empresa !== '', fn ($q) => $q->where('p.emp', 'like', "%{$empresa}%"))
            ->when($emisora !== '', fn ($q) => $q->where('p.emisora', $emisora))
            ->when($estado !== null, fn ($q) => $q->where('p.estado', $estado))
            ->when($anio !== null, fn ($q) => $q->where('p.anio', $anio))
            ->when($mes !== null, fn ($q) => $q->where('p.mes', $mes))
            ->orderByDesc('p.anio')
            ->orderByDesc('p.mes')
            ->orderByDesc('p.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function estadoLabel(null|string|int $estado): string
    {
        $estadoInt = $this->normalizarEntero($estado);

        if ($estadoInt === null) {
            return 'N/D';
        }

        return self::ESTADOS[$estadoInt] ?? "Estado {$estadoInt}";
    }

    public function monthLabel(null|string|int $mes): string
    {
        $mesInt = $this->normalizarMes($mes);

        if ($mesInt === null) {
            return 'N/D';
        }

        return ucfirst(self::MESES[$mesInt] ?? (string) $mesInt);
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

    private function normalizarMes(null|string|int $mes): ?int
    {
        if ($mes === null) {
            return null;
        }

        $mesTexto = mb_strtolower(trim((string) $mes));
        if ($mesTexto === '') {
            return null;
        }

        if (ctype_digit($mesTexto)) {
            $mesInt = (int) $mesTexto;

            return ($mesInt >= 1 && $mesInt <= 12) ? $mesInt : null;
        }

        $mesInt = array_search($mesTexto, self::MESES, true);

        return $mesInt !== false ? (int) $mesInt : null;
    }
}
