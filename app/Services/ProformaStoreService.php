<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProformaStoreService
{
    public function __construct(
        private readonly ProformaPreviewService $proformaPreviewService,
    ) {
    }

    public function storeFromCobro(object $cobro): array
    {
        return DB::transaction(function () use ($cobro) {
            $preview = $this->proformaPreviewService->buildFromCobro($cobro);

            $nit = trim((string) ($cobro->cliente_nit ?? ''));
            $mes = trim((string) ($cobro->mes ?? ''));
            $anio = (int) ($cobro->año ?? 0);
            $emisora = (string) ($preview['cabecera']['empresa_emisora'] ?? 'SAS');

            $proformaExistente = DB::table('sg_proform')
                ->where('nit', $nit)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->where('emisora', $emisora)
                ->first();

            if ($proformaExistente !== null) {
                $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro);

                return [
                    'created' => false,
                    'duplicated' => true,
                    'proforma_id' => $proformaExistente->id ?? null,
                    'message' => 'La proforma ya existía para NIT, mes, año y emisora. No se duplicó la cabecera ni el detalle.',
                ];
            }

            $nroProf = $this->resolverNumeroProforma($emisora, $anio);
            $lineas = $preview['detalle']['lineas'] ?? [];

            $cabecera = [
                'nit' => $nit,
                'emp' => $this->resolveEmpresaCliente($cobro),
                'emisora' => $emisora,
                'fpago' => null,
                'mes' => $mes,
                'anio' => $anio,
                'nro_prof' => $nroProf,
                'estado' => 2,
                'vlr_mens' => (float) ($cobro->valor_mensualidad ?? 0),
                'vlr_nom' => (float) ($cobro->vlrnomina ?? 0),
                'vlr_fe' => (float) ($cobro->valor_facturas ?? 0),
                'vlr_rec' => (float) ($cobro->valor_acuse ?? 0),
                'vlr_sop' => (float) ($cobro->valor_documentos ?? 0),
                'vext1' => (float) ($cobro->cliente_vlrextra ?? 0),
                'vext2' => (float) ($cobro->cliente_vlrextra2 ?? 0),
                'vtotal' => (float) ($preview['detalle']['total_preview'] ?? 0),
                'cfe' => (float) ($cobro->numero_facturas ?? 0),
                'csop' => (float) ($cobro->numero_documento_soporte ?? 0),
                'crec' => (float) ($cobro->numero_acuse ?? 0),
                'cnom' => (float) (($cobro->vlrnomina ?? 0) > 0 ? 1 : 0),
                // Punto de integración futura de PDF/hash
                'rpdf' => null,
                'npdf' => null,
                'hpdf' => null,
            ];

            $proformaId = (int) DB::table('sg_proform')->insertGetId($cabecera);

            $detalleRows = [];
            foreach ($lineas as $index => $linea) {
                $detalleRows[] = [
                    'proforma_id' => $proformaId,
                    'ref_codigo' => (string) ($linea['codigo'] ?? ''),
                    'descripcion' => (string) ($linea['concepto'] ?? ''),
                    'cantidad' => (float) ($linea['cantidad'] ?? 0),
                    'vr_unidad' => (float) ($linea['valor_unitario'] ?? 0),
                    'vr_parcial' => (float) ($linea['valor_parcial'] ?? 0),
                    'orden' => $index + 1,
                    'moneda' => 'COP',
                ];
            }

            if ($detalleRows !== []) {
                DB::table('sg_proford')->insert($detalleRows);
            }

            $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro);

            return [
                'created' => true,
                'duplicated' => false,
                'proforma_id' => $proformaId,
                'message' => 'Proforma guardada correctamente en sg_proform y sg_proford.',
            ];
        });
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

    private function marcarCobroComoProformaGenerada(int $idCobro): void
    {
        DB::table('valores_externos')
            ->where('id_cobro', $idCobro)
            ->update(['Proforma' => 2]);
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
}

