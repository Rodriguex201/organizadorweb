<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProformaStoreService
{

    private const CODIGOS_CONCEPTO_OFICIALES = ['0010', '0099', '0081', '0101', '0102', 'EXTRA'];


    private const MESES_ES = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    public function __construct(
        private readonly ProformaPreviewService $proformaPreviewService,
    ) {
    }


    public function findExistingProformaIdFromCobro(object $cobro): ?int
    {
        $nit = trim((string) ($cobro->cliente_nit ?? ''));
        $mesTexto = trim((string) ($cobro->mes ?? ''));
        $mes = $this->normalizarMesParaProforma($mesTexto);
        $anio = (int) ($cobro->año ?? 0);
        $emisora = $this->resolverEmpresaEmisoraDesdeRegimen($cobro);

        $proforma = DB::table('sg_proform')
            ->select('id')
            ->where('nit', $nit)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->where('emisora', $emisora)
            ->first();

        return $proforma ? (int) $proforma->id : null;
    }

    public function storeFromCobro(object $cobro): array
    {
        return DB::transaction(function () use ($cobro) {
            $preview = $this->proformaPreviewService->buildFromCobro($cobro);

            $nit = trim((string) ($cobro->cliente_nit ?? ''));
            $mesTexto = trim((string) ($cobro->mes ?? ''));
            $mes = $this->normalizarMesParaProforma($mesTexto);
            $anio = (int) ($cobro->año ?? 0);
            $emisora = (string) ($preview['cabecera']['empresa_emisora'] ?? 'SAS');

            $proformaExistente = DB::table('sg_proform')
                ->where('nit', $nit)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->where('emisora', $emisora)
                ->first();

            if ($proformaExistente !== null) {
                $lineas = $preview['detalle']['lineas'] ?? [];
                $this->actualizarCabeceraProformaExistente((int) $proformaExistente->id, $cobro, $preview);
                $this->reemplazarDetalleProforma((int) $proformaExistente->id, $lineas);
                $this->marcarCobroComoProformaGenerada((int) $cobro->id_cobro);

                return [
                    'created' => false,
                    'duplicated' => true,
                    'proforma_id' => $proformaExistente->id ?? null,
                    'message' => 'La proforma ya existía para NIT, mes, año y emisora. Se actualizó cabecera y detalle con los valores vigentes.',
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

            $detalleRows = $this->construirDetalleRows($proformaId, $lineas);

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



    private function resolverEmpresaEmisoraDesdeRegimen(object $cobro): string
    {
        $regimen = strtoupper(trim((string) ($cobro->cliente_regimen ?? '')));

        return match ($regimen) {
            'PCS' => 'PCS',
            'SMP' => 'SMP',
            default => 'SAS',
        };
    }


    private function normalizarMesParaProforma(null|string|int $mes): ?int
    {
        if ($mes === null) {
            return null;
        }

        $valor = trim((string) $mes);
        if ($valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $mesNumero = (int) $valor;

            return ($mesNumero >= 1 && $mesNumero <= 12) ? $mesNumero : null;
        }

        $mesNumero = self::MESES_ES[mb_strtolower($valor)] ?? null;

        return $mesNumero;
    }

    private function actualizarCabeceraProformaExistente(int $proformaId, object $cobro, array $preview): void
    {
        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update([
                'emp' => $this->resolveEmpresaCliente($cobro),
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
            ]);
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     */
    private function reemplazarDetalleProforma(int $proformaId, array $lineas): void
    {
        DB::table('sg_proford')
            ->where('proforma_id', $proformaId)
            ->delete();

        $detalleRows = $this->construirDetalleRows($proformaId, $lineas);
        if ($detalleRows !== []) {
            DB::table('sg_proford')->insert($detalleRows);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lineas
     * @return array<int, array<string, mixed>>
     */
    private function construirDetalleRows(int $proformaId, array $lineas): array
    {
        $catalogoConceptos = $this->obtenerCatalogoConceptos();
        $detalleRows = [];
        foreach ($lineas as $index => $linea) {
            $codigoLinea = (string) ($linea['codigo'] ?? '');

            // Código de prueba deshabilitado temporalmente (sin código oficial aún en tabla conceptos).
            if ($codigoLinea === '01') {
                continue;
            }

            $concepto = $this->resolverConceptoDesdeCatalogo(
                $codigoLinea,
                (string) ($linea['concepto'] ?? ''),
                $catalogoConceptos,
            );

            $detalleRows[] = [
                'proforma_id' => $proformaId,
                'ref_codigo' => $concepto['codigo'],
                'descripcion' => $concepto['nombre'],
                'cantidad' => (float) ($linea['cantidad'] ?? 0),
                'vr_unidad' => (float) ($linea['valor_unitario'] ?? 0),
                'vr_parcial' => (float) ($linea['valor_parcial'] ?? 0),
                'orden' => $index + 1,
                'moneda' => 'COP',
            ];
        }

        return $detalleRows;
    }


    /**
     * @return array<string, object>
     */
    private function obtenerCatalogoConceptos(): array
    {
        return DB::table('conceptos')
            ->select('codigo', 'nombre')
            ->whereIn('codigo', self::CODIGOS_CONCEPTO_OFICIALES)
            ->get()
            ->mapWithKeys(fn ($concepto) => [(string) $concepto->codigo => $concepto])
            ->all();
    }

    /**
     * @param array<string, object> $catalogoConceptos
     * @return array{codigo:string,nombre:string}
     */
    private function resolverConceptoDesdeCatalogo(string $codigo, string $descripcionFallback, array $catalogoConceptos): array
    {
        if (isset($catalogoConceptos[$codigo])) {
            return [
                'codigo' => (string) $catalogoConceptos[$codigo]->codigo,
                'nombre' => (string) $catalogoConceptos[$codigo]->nombre,
            ];
        }

        Log::warning('Concepto no encontrado en catálogo oficial para detalle de proforma, usando fallback.', [
            'codigo' => $codigo,
            'descripcion_fallback' => $descripcionFallback,
        ]);

        return [
            'codigo' => $codigo,
            'nombre' => $descripcionFallback,
        ];
    }

}
