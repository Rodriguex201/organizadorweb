<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProformasService
{

    public const ESTADO_GENERADA = 2;
    public const ESTADO_PAGADA = 4;
    public const ESTADO_FACTURADA = 6;

    public const ESTADOS = [
        self::ESTADO_GENERADA => 'Generada',
        self::ESTADO_PAGADA => 'Pagada',
        self::ESTADO_FACTURADA => 'Facturada',

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


    private const TRANSICIONES_VALIDAS = [
        self::ESTADO_GENERADA => [self::ESTADO_PAGADA],
        self::ESTADO_PAGADA => [self::ESTADO_FACTURADA],
        self::ESTADO_FACTURADA => [],
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
                'p.enviado',
                'p.fecha_envio',
                'p.intentos_envio',
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


    public function getDashboardData(int $mes, int $anio): array
    {
        $basePeriodo = DB::table('sg_proform as p')
            ->where('p.mes', $mes)
            ->where('p.anio', $anio);

        $totalProformas = (clone $basePeriodo)->count();
        $sumaTotal = (float) ((clone $basePeriodo)->sum('p.vtotal') ?? 0);

        $totalesPorEstado = [];
        foreach (self::ESTADOS as $estadoCodigo => $estadoLabel) {
            $totalesPorEstado[$estadoCodigo] = [
                'label' => $estadoLabel,
                'cantidad' => (clone $basePeriodo)->where('p.estado', $estadoCodigo)->count(),
                'total' => (float) ((clone $basePeriodo)->where('p.estado', $estadoCodigo)->sum('p.vtotal') ?? 0),
            ];
        }

        $ultimasProformas = (clone $basePeriodo)
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
            ])
            ->orderByDesc('p.id')
            ->limit(15)
            ->get();

        return [
            'periodo' => ['mes' => $mes, 'anio' => $anio],
            'total_proformas' => $totalProformas,
            'total_generadas' => $totalesPorEstado[self::ESTADO_GENERADA]['cantidad'] ?? 0,
            'total_pagadas' => $totalesPorEstado[self::ESTADO_PAGADA]['cantidad'] ?? 0,
            'total_facturadas' => $totalesPorEstado[self::ESTADO_FACTURADA]['cantidad'] ?? 0,
            'suma_total_vtotal' => $sumaTotal,
            'suma_total_por_estado' => $totalesPorEstado,
            'total_periodo_filtrado' => $totalProformas,
            'ultimas_proformas' => $ultimasProformas,
        ];
    }

    public function normalizePeriodoFilters(null|string|int $mes, null|string|int $anio): array
    {
        $mesNormalizado = $this->normalizarMes($mes) ?? (int) now()->format('n');
        $anioNormalizado = $this->normalizarEntero($anio) ?? (int) now()->format('Y');

        return [
            'mes' => $mesNormalizado,
            'anio' => $anioNormalizado,
        ];
    }


    public function findProformaById(int $id): ?object
    {
        return DB::table('sg_proform as p')
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
                'p.enviado',
                'p.fecha_envio',
                'p.intentos_envio',
            ])
            ->where('p.id', $id)
            ->first();
    }


    public function canSendProforma(null|object $proforma): bool
    {
        if (!$proforma) {
            return false;
        }

        $nroProf = trim((string) ($proforma->nro_prof ?? ''));
        $rutaPdf = trim((string) ($proforma->rpdf ?? ''));
        $nombrePdf = trim((string) ($proforma->npdf ?? ''));
        $estado = (int) ($proforma->estado ?? 0);

        return $nroProf !== ''
            && $rutaPdf !== ''
            && $nombrePdf !== ''
            && $estado >= self::ESTADO_GENERADA;
    }

    public function registrarEnvioExitoso(int $proformaId): void
    {
        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update([
                'enviado' => 1,
                'fecha_envio' => now(),
                'intentos_envio' => DB::raw('COALESCE(intentos_envio, 0) + 1'),
            ]);
    }

    public function envioLabel(null|string|int $enviado): string
    {
        return ((int) ($enviado ?? 0)) === 1 ? 'Enviada' : 'No enviada';
    }

    public function envioBadgeClass(null|string|int $enviado): string
    {
        return ((int) ($enviado ?? 0)) === 1
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-slate-100 text-slate-700';
    }

    /**
     * @return array{ok:bool,message:string,from:int,to:int}
     */
    public function updateEstado(int $proformaId, int $nuevoEstado): array
    {
        $proforma = DB::table('sg_proform')
            ->select(['id', 'estado'])
            ->where('id', $proformaId)
            ->first();

        if (!$proforma) {
            return [
                'ok' => false,
                'message' => 'La proforma no existe.',
                'from' => 0,
                'to' => $nuevoEstado,
            ];
        }

        $estadoActual = (int) ($proforma->estado ?? 0);

        if (!isset(self::ESTADOS[$nuevoEstado])) {
            return [
                'ok' => false,
                'message' => 'Estado destino inválido.',
                'from' => $estadoActual,
                'to' => $nuevoEstado,
            ];
        }

        if ($estadoActual === $nuevoEstado) {
            return [
                'ok' => false,
                'message' => 'La proforma ya tiene ese estado.',
                'from' => $estadoActual,
                'to' => $nuevoEstado,
            ];
        }

        if (!$this->canTransition($estadoActual, $nuevoEstado)) {
            return [
                'ok' => false,
                'message' => 'Transición de estado no permitida.',
                'from' => $estadoActual,
                'to' => $nuevoEstado,
            ];
        }

        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update(['estado' => $nuevoEstado]);

        return [
            'ok' => true,
            'message' => sprintf(
                'Estado actualizado de %s a %s.',
                $this->estadoLabel($estadoActual),
                $this->estadoLabel($nuevoEstado),
            ),
            'from' => $estadoActual,
            'to' => $nuevoEstado,
        ];
    }

    public function canTransition(null|string|int $estadoActual, null|string|int $estadoDestino): bool
    {
        $origen = $this->normalizarEntero($estadoActual);
        $destino = $this->normalizarEntero($estadoDestino);

        if ($origen === null || $destino === null) {
            return false;
        }

        $permitidos = self::TRANSICIONES_VALIDAS[$origen] ?? [];

        return in_array($destino, $permitidos, true);
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


    public function estadoBadgeClass(null|string|int $estado): string
    {
        return match ((int) ($this->normalizarEntero($estado) ?? 0)) {
            self::ESTADO_GENERADA => 'bg-blue-100 text-blue-700',
            self::ESTADO_PAGADA => 'bg-emerald-100 text-emerald-700',
            self::ESTADO_FACTURADA => 'bg-purple-100 text-purple-700',
            default => 'bg-slate-100 text-slate-700',
        };
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
