<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProformasService
{
    public const ESTADO_GENERADA = 2;
    public const ESTADO_ENVIADA = 3;
    public const ESTADO_PAGADA = 4;
    public const ESTADO_FACTURADA = 6;

    public const ESTADOS = [
        self::ESTADO_GENERADA => 'Generada',
        self::ESTADO_ENVIADA => 'Enviada',
        self::ESTADO_PAGADA => 'Pagada',
        self::ESTADO_FACTURADA => 'Facturada',
    ];

    public const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const TRANSICIONES_VALIDAS = [
        self::ESTADO_GENERADA => [self::ESTADO_ENVIADA, self::ESTADO_PAGADA],
        self::ESTADO_ENVIADA => [self::ESTADO_PAGADA],
        self::ESTADO_PAGADA => [self::ESTADO_FACTURADA],
        self::ESTADO_FACTURADA => [],
    ];

    public function __construct(private readonly EstadoProformaConfigService $estadoConfigService)
    {
    }

    public function paginateProformas(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('sg_proform as p')
            ->select(['p.id', 'p.nro_prof', 'p.emp', 'p.nit', 'p.emisora', 'p.mes', 'p.anio', 'p.vtotal', 'p.estado', 'p.rpdf', 'p.npdf', 'p.hpdf', 'p.enviado', 'p.fecha_envio', 'p.intentos_envio'])
            ->selectSub(function ($subquery) {
                $subquery
                    ->from('clientes_potenciales as cp')
                    ->select('cp.codigo')
                    ->whereRaw('BINARY cp.nit = BINARY p.nit')
                    ->limit(1);
            }, 'codigo');
        $nroProf = trim((string) ($filters['nro_prof'] ?? ''));
        $codigo = trim((string) ($filters['codigo'] ?? ''));
        $nit = trim((string) ($filters['nit'] ?? ''));
        $empresa = trim((string) ($filters['empresa'] ?? ''));
        $emisora = trim((string) ($filters['emisora'] ?? ''));
        $estado = $this->normalizarEntero($filters['estado'] ?? null);
        $anio = $this->normalizarEntero($filters['anio'] ?? null);
        $mes = $this->normalizarMes($filters['mes'] ?? null);

        return $query
            ->when($nroProf !== '', fn ($q) => $q->where('p.nro_prof', 'like', "%{$nroProf}%"))
            ->when($codigo !== '', function ($q) use ($codigo) {
                $q->whereExists(function ($subquery) use ($codigo) {
                    $subquery
                        ->select(DB::raw(1))
                        ->from('clientes_potenciales as cp')
                        ->whereRaw('BINARY cp.nit = BINARY p.nit')
                        ->where('cp.codigo', 'like', "%{$codigo}%");
                });
            })
            ->when($nit !== '', fn ($q) => $q->where('p.nit', 'like', "%{$nit}%"))
            ->when($empresa !== '', fn ($q) => $q->where('p.emp', 'like', "%{$empresa}%"))
            ->when($emisora !== '', fn ($q) => $q->where('p.emisora', $emisora))
            ->when($estado !== null, fn ($q) => $q->where('p.estado', $estado))
            ->when($anio !== null, fn ($q) => $q->where('p.anio', $anio))
            ->when($mes !== null, fn ($q) => $q->where('p.mes', $mes))
            ->orderByDesc('p.anio')->orderByDesc('p.mes')->orderByDesc('p.id')
            ->paginate($perPage)->withQueryString();
    }

    public function getDashboardData(int $mes, int $anio): array
    {
        $basePeriodo = DB::table('sg_proform as p')->where('p.mes', $mes)->where('p.anio', $anio);
        $totalProformas = (clone $basePeriodo)->count();
        $sumaTotal = (float) ((clone $basePeriodo)->sum('p.vtotal') ?? 0);

        $totalesPorEstado = [];
        foreach (self::ESTADOS as $estadoCodigo => $estadoLabel) {
            $totalesPorEstado[$estadoCodigo] = [
                'label' => $this->estadoLabel($estadoCodigo),
                'cantidad' => (clone $basePeriodo)->where('p.estado', $estadoCodigo)->count(),
                'total' => (float) ((clone $basePeriodo)->where('p.estado', $estadoCodigo)->sum('p.vtotal') ?? 0),
            ];
        }

        $ultimasProformas = (clone $basePeriodo)
            ->select(['p.id', 'p.nro_prof', 'p.emp', 'p.nit', 'p.emisora', 'p.mes', 'p.anio', 'p.vtotal', 'p.estado'])
            ->orderByDesc('p.id')->limit(15)->get();

        return [
            'periodo' => ['mes' => $mes, 'anio' => $anio],
            'total_proformas' => $totalProformas,
            'total_generadas' => $totalesPorEstado[self::ESTADO_GENERADA]['cantidad'] ?? 0,
            'total_enviadas' => $totalesPorEstado[self::ESTADO_ENVIADA]['cantidad'] ?? 0,
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
        return ['mes' => $this->normalizarMes($mes) ?? (int) now()->format('n'), 'anio' => $this->normalizarEntero($anio) ?? (int) now()->format('Y')];
    }

    public function findProformaById(int $id): ?object
    {
        return DB::table('sg_proform as p')->select(['p.id', 'p.nro_prof', 'p.emp', 'p.nit', 'p.emisora', 'p.mes', 'p.anio', 'p.vtotal', 'p.estado', 'p.rpdf', 'p.npdf', 'p.hpdf', 'p.enviado', 'p.fecha_envio', 'p.intentos_envio'])->where('p.id', $id)->first();
    }

    public function canSendProforma(null|object $proforma): bool
    {
        if (!$proforma) return false;
        return trim((string) ($proforma->nro_prof ?? '')) !== '' && trim((string) ($proforma->rpdf ?? '')) !== '' && trim((string) ($proforma->npdf ?? '')) !== '' && (int) ($proforma->estado ?? 0) >= self::ESTADO_GENERADA;
    }

    public function registrarEnvioExitoso(int $proformaId): void
    {
        DB::table('sg_proform')->where('id', $proformaId)->update(['enviado' => 1, 'fecha_envio' => now(), 'estado' => self::ESTADO_ENVIADA, 'intentos_envio' => DB::raw('COALESCE(intentos_envio, 0) + 1')]);
        $this->syncEstadoEnValoresExternos($proformaId, self::ESTADO_ENVIADA);
    }

    public function registrarIntentoFallido(int $proformaId): void
    {
        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update(['intentos_envio' => DB::raw('COALESCE(intentos_envio, 0) + 1')]);
    }

    public function buildBatchEnvioResumen(int $grupoFecha): array
    {
        $proformas = $this->queryProformasByGrupoFecha($grupoFecha)->get();

        $omitidasPorMotivo = [
            'sin_correo' => 0,
            'sin_pdf' => 0,
            'ya_enviadas' => 0,
            'no_generadas' => 0,
        ];

        $validas = [];
        $omitidasDetalle = [];

        foreach ($proformas as $proforma) {
            $motivo = $this->resolveInvalidReasonForBatch($proforma);

            if ($motivo === null) {
                $validas[] = $proforma;

                continue;
            }

            $omitidasPorMotivo[$motivo]++;
            $omitidasDetalle[] = [
                'id' => $proforma->id,
                'nro_prof' => $proforma->nro_prof,
                'empresa' => $proforma->emp,
                'motivo' => $motivo,
            ];
        }

        return [
            'grupo' => $grupoFecha,
            'total_encontradas' => $proformas->count(),
            'validas' => collect($validas),
            'validas_count' => count($validas),
            'omitidas_count' => count($omitidasDetalle),
            'omitidas_por_motivo' => $omitidasPorMotivo,
            'omitidas_detalle' => $omitidasDetalle,
        ];
    }

    public function findBatchCandidatesByIds(int $grupoFecha, array $ids): Collection
    {
        return $this->queryProformasByGrupoFecha($grupoFecha)
            ->whereIn('p.id', $ids)
            ->get();
    }

    public function envioLabel(null|string|int $enviado): string
    {
        return ((int) ($enviado ?? 0)) === 1 ? 'Enviada' : 'No enviada';
    }

    public function envioBadgeClass(null|string|int $enviado): string
    {
        return ((int) ($enviado ?? 0)) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700';
    }

    public function updateEstado(int $proformaId, int $nuevoEstado): array
    {
        $proforma = DB::table('sg_proform')->select(['id', 'estado'])->where('id', $proformaId)->first();
        if (!$proforma) return ['ok' => false, 'message' => 'La proforma no existe.', 'from' => 0, 'to' => $nuevoEstado];
        $estadoActual = (int) ($proforma->estado ?? 0);
        if (!isset(self::ESTADOS[$nuevoEstado])) return ['ok' => false, 'message' => 'Estado destino inválido.', 'from' => $estadoActual, 'to' => $nuevoEstado];
        if ($estadoActual === $nuevoEstado) return ['ok' => false, 'message' => 'La proforma ya tiene ese estado.', 'from' => $estadoActual, 'to' => $nuevoEstado];
        if (!$this->canTransition($estadoActual, $nuevoEstado)) return ['ok' => false, 'message' => 'Transición de estado no permitida.', 'from' => $estadoActual, 'to' => $nuevoEstado];

        DB::transaction(function () use ($proformaId, $nuevoEstado) {
            $updatePayload = ['estado' => $nuevoEstado];
            $fechaActual = now()->toDateString();

            if ($nuevoEstado === self::ESTADO_PAGADA) {
                $updatePayload['fpag'] = $fechaActual;
            }

            if ($nuevoEstado === self::ESTADO_FACTURADA) {
                $updatePayload['ffac'] = $fechaActual;
            }

            DB::table('sg_proform')->where('id', $proformaId)->update($updatePayload);
            $this->syncEstadoEnValoresExternos($proformaId, $nuevoEstado);
        });

        return ['ok' => true, 'message' => sprintf('Estado actualizado de %s a %s.', $this->estadoLabel($estadoActual), $this->estadoLabel($nuevoEstado)), 'from' => $estadoActual, 'to' => $nuevoEstado];
    }

    private function syncEstadoEnValoresExternos(int $proformaId, int $nuevoEstado): int
    {
        $select = ['id', 'nit', 'mes', 'anio', 'emisora'];
        $hasCobroReference = Schema::hasColumn('sg_proform', 'id_cobro');

        if ($hasCobroReference) {
            $select[] = 'id_cobro';
        }

        $proforma = DB::table('sg_proform')
            ->select($select)
            ->where('id', $proformaId)
            ->first();

        if (!$proforma) {
            return 0;
        }

        if ($hasCobroReference && isset($proforma->id_cobro) && (int) $proforma->id_cobro > 0) {
            return DB::table('valores_externos')
                ->where('id_cobro', (int) $proforma->id_cobro)
                ->update(['Proforma' => $nuevoEstado]);
        }

        $mesTexto = self::MESES[(int) ($proforma->mes ?? 0)] ?? null;

        if ($mesTexto === null) {
            return 0;
        }

        $idsCobro = DB::table('valores_externos as ve')
            ->leftJoin('clientes_potenciales as cp', DB::raw('cp.idclientes_potenciales'), '=', DB::raw('CAST(ve.id_cliente AS UNSIGNED)'))
            ->where('cp.nit', trim((string) ($proforma->nit ?? '')))
            ->whereRaw('LOWER(TRIM(ve.mes)) = ?', [mb_strtolower($mesTexto)])
            ->whereRaw('ve.`año` = ?', [(int) ($proforma->anio ?? 0)])
            ->whereRaw(
                "CASE UPPER(TRIM(cp.regimen))
                    WHEN 'PCS' THEN 'PCS'
                    WHEN 'SMP' THEN 'SMP'
                    ELSE 'SAS'
                 END = ?",
                [strtoupper(trim((string) ($proforma->emisora ?? 'SAS')))],
            )
            ->pluck('ve.id_cobro');

        if ($idsCobro->isEmpty()) {
            return 0;
        }

        return DB::table('valores_externos')
            ->whereIn('id_cobro', $idsCobro->all())
            ->update(['Proforma' => $nuevoEstado]);
    }

    public function canTransition(null|string|int $estadoActual, null|string|int $estadoDestino): bool
    {
        $origen = $this->normalizarEntero($estadoActual);
        $destino = $this->normalizarEntero($estadoDestino);
        if ($origen === null || $destino === null) return false;
        return in_array($destino, self::TRANSICIONES_VALIDAS[$origen] ?? [], true);
    }

    public function estadoLabel(null|string|int $estado): string
    {
        $estadoInt = $this->normalizarEntero($estado);
        if ($estadoInt === null) return 'N/D';
        $config = $this->estadoConfigService->getMap()[$estadoInt] ?? null;
        return $config['estado_nombre'] ?? (self::ESTADOS[$estadoInt] ?? "Estado {$estadoInt}");
    }

    public function estadoBadgeStyle(null|string|int $estado): string
    {
        $estadoInt = $this->normalizarEntero($estado);
        $config = $estadoInt !== null ? ($this->estadoConfigService->getMap()[$estadoInt] ?? null) : null;
        $fondo = $config['color_fondo'] ?? '#E2E8F0';
        $texto = $config['color_texto'] ?? '#334155';

        return "background-color: {$fondo}; color: {$texto};";
    }

    public function monthLabel(null|string|int $mes): string
    {
        $mesInt = $this->normalizarMes($mes);
        return $mesInt === null ? 'N/D' : ucfirst(self::MESES[$mesInt] ?? (string) $mesInt);
    }

    private function normalizarEntero(null|string|int $valor): ?int
    {
        if ($valor === null) return null;
        $string = trim((string) $valor);
        if ($string === '' || !ctype_digit($string)) return null;
        return (int) $string;
    }

    private function normalizarMes(null|string|int $mes): ?int
    {
        if ($mes === null) return null;
        $mesTexto = mb_strtolower(trim((string) $mes));
        if ($mesTexto === '') return null;
        if (ctype_digit($mesTexto)) {
            $mesInt = (int) $mesTexto;
            return ($mesInt >= 1 && $mesInt <= 12) ? $mesInt : null;
        }
        $mesInt = array_search($mesTexto, self::MESES, true);
        return $mesInt !== false ? (int) $mesInt : null;
    }

    private function queryProformasByGrupoFecha(int $grupoFecha)
    {
        $diaArriendo = "CAST(SUBSTRING_INDEX(cp.fecha_arriendo, '-', 1) AS UNSIGNED)";

        return DB::table('sg_proform as p')
            ->leftJoin('clientes_potenciales as cp', 'cp.nit', '=', 'p.nit')
            ->select([
                'p.id',
                'p.nro_prof',
                'p.emp',
                'p.nit',
                'p.estado',
                'p.enviado',
                'p.rpdf',
                'p.npdf',
                'cp.email as cliente_email',
                'cp.fecha_arriendo as cliente_fecha_arriendo',
            ])
            ->where(function ($query) use ($grupoFecha, $diaArriendo) {
                if ($grupoFecha === 7) {
                    $query->whereRaw("{$diaArriendo} BETWEEN 1 AND 12");

                    return;
                }

                $query->whereRaw("{$diaArriendo} BETWEEN 22 AND 31");
            });
    }

    private function resolveInvalidReasonForBatch(object $proforma): ?string
    {
        if ((int) ($proforma->estado ?? 0) !== self::ESTADO_GENERADA) {
            return 'no_generadas';
        }

        if ((int) ($proforma->enviado ?? 0) === 1) {
            return 'ya_enviadas';
        }

        $email = trim((string) ($proforma->cliente_email ?? ''));
        if ($email === '') {
            return 'sin_correo';
        }

        $rutaPdf = trim((string) ($proforma->rpdf ?? ''));
        $nombrePdf = trim((string) ($proforma->npdf ?? ''));

        if ($rutaPdf === '' || $nombrePdf === '') {
            return 'sin_pdf';
        }

        $relativePath = trim($rutaPdf, '/').'/'.ltrim($nombrePdf, '/');

        if (!Storage::disk('local')->exists($relativePath)) {
            return 'sin_pdf';
        }

        return null;
    }
}
