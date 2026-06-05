<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CobrosBasePeriodoService
{
    public function __construct(
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
        private readonly ClienteRetiradoService $clienteRetiradoService,
    ) {
    }

    /**
     * @return array{created:int,skipped_existing:int,total_active_clients:int,periodo:array{mes:string,anio:int}}
     */
    public function generate(string $mes, int $anio): array
    {
        $mes = mb_strtolower(trim($mes));

        return DB::transaction(function () use ($mes, $anio): array {
            $clientes = $this->getClientesActivos();
            $yearColumn = $this->resolveYearColumn();
            $existingIds = DB::table('valores_externos')
                ->whereRaw('LOWER(TRIM(mes)) = ?', [$mes])
                ->where($yearColumn, $anio)
                ->pluck('id_cliente')
                ->map(fn ($idCliente) => (int) trim((string) $idCliente))
                ->filter(fn (int $idCliente) => $idCliente > 0)
                ->unique()
                ->values()
                ->all();

            $existingLookup = array_fill_keys($existingIds, true);
            $rowsToInsert = [];
            $nextIdCobro = ((int) DB::table('valores_externos')->lockForUpdate()->max('id_cobro')) + 1;

            foreach ($clientes as $cliente) {
                $clienteId = (int) ($cliente->id ?? 0);

                if ($clienteId <= 0 || isset($existingLookup[$clienteId])) {
                    continue;
                }

                $preview = $this->buildPreview($cliente);

                $rowsToInsert[] = [
                    'id_cobro' => $nextIdCobro++,
                    'id_cliente' => (string) $clienteId,
                    'mes' => $mes,
                    $yearColumn => $anio,
                    'numero_facturas' => 0,
                    'numero_nota_debito' => 0,
                    'numero_nota_credito' => 0,
                    'numero_documento_soporte' => 0,
                    'numero_nota_ajuste' => 0,
                    'numero_acuse' => 0,
                    'valor_extra' => (float) ($preview['otro_valor_extra'] ?? 0),
                    'valor_extra2' => (float) ($preview['otro_valor_extra_2'] ?? 0),
                    'valor_facturas' => (float) ($preview['valor_facturas'] ?? 0),
                    'valor_documentos' => (float) ($preview['valor_documentos'] ?? 0),
                    'valor_acuse' => (float) ($preview['valor_acuse'] ?? 0),
                    'valor_mensualidad' => (float) ($preview['total_mensualidad'] ?? 0),
                    'valor_total' => (float) ($preview['valor_total_proforma'] ?? 0),
                    'Proforma' => 0,
                ];
            }

            if ($rowsToInsert !== []) {
                DB::table('valores_externos')->insert($rowsToInsert);
            }

            return [
                'created' => count($rowsToInsert),
                'skipped_existing' => count($existingIds),
                'total_active_clients' => $clientes->count(),
                'periodo' => [
                    'mes' => $mes,
                    'anio' => $anio,
                ],
            ];
        });
    }

    private function getClientesActivos()
    {
        $select = [
            'idclientes_potenciales as id',
            'vlrprincipal',
            'numequipos',
            'vlrterminal',
            'vlrnomina',
            'numero_empleados',
            'numeromoviles',
            'vlrmovil',
            'vlrfactura',
            'vlrsoporte',
            'vlrecepcion',
            'vlrextra',
            'vlrextra2',
        ];

        if (Schema::hasColumn('clientes_potenciales', 'numextra')) {
            $select[] = 'numextra';
        }

        if (Schema::hasColumn('clientes_potenciales', 'vlrextrae')) {
            $select[] = 'vlrextrae';
        }

        $select = $this->clienteRetiradoService->addSelectColumns($select, null, 'fecha_retiro', 'retirado');

        $query = DB::table('clientes_potenciales')
            ->select($select);

        $this->clienteRetiradoService->applyNoRetiradosConstraint($query);

        return $query->orderBy('idclientes_potenciales')->get();
    }

    /**
     * @return array<string, float>
     */
    private function buildPreview(object $cliente): array
    {
        return $this->revisarProformaCalculator->calculate([
            'numero_equipos' => (float) ($cliente->numequipos ?? 0),
            'valor_principal' => (float) ($cliente->vlrprincipal ?? 0),
            'valor_terminal' => (float) ($cliente->vlrterminal ?? 0),
            'numero_equipos_extra' => (float) ($cliente->numextra ?? 0),
            'valor_equipo_extra' => (float) ($cliente->vlrextrae ?? 0),
            'empleados' => (float) ($cliente->numero_empleados ?? 0),
            'valor_nomina' => (float) ($cliente->vlrnomina ?? 0),
            'numero_moviles' => (float) ($cliente->numeromoviles ?? 0),
            'valor_movil' => (float) ($cliente->vlrmovil ?? 0),
            'facturas' => 0,
            'nota_debito' => 0,
            'nota_credito' => 0,
            'soporte' => 0,
            'nota_ajuste' => 0,
            'acuse' => 0,
            'otro_valor_extra' => (float) ($cliente->vlrextra ?? 0),
            'otro_valor_extra_2' => (float) ($cliente->vlrextra2 ?? 0),
            'precio_factura' => (float) ($cliente->vlrfactura ?? 0),
            'precio_soporte' => (float) ($cliente->vlrsoporte ?? 0),
            'precio_acuse' => (float) ($cliente->vlrecepcion ?? 0),
        ]);
    }

    private function resolveYearColumn(): string
    {
        if (Schema::hasColumn('valores_externos', 'año')) {
            return 'año';
        }

        if (Schema::hasColumn('valores_externos', 'aÃ±o')) {
            return 'aÃ±o';
        }

        if (Schema::hasColumn('valores_externos', 'aÃƒÂ±o')) {
            return 'aÃƒÂ±o';
        }

        return 'año';
    }
}
