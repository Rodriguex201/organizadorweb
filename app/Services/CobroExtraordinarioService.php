<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CobroExtraordinarioService
{
    public function __construct(
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
        private readonly ClienteRetiradoService $clienteRetiradoService,
    ) {
    }

    public function getClientes(): Collection
    {
        $select = [
            'idclientes_potenciales as id',
            'codigo',
            'nombre',
            'empresa',
            'nit',
            'regimen',
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
            'fecha_arriendo',
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

        return $query
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get();
    }

    public function getRetiradosSearchCandidates(): Collection
    {
        $select = [
            'idclientes_potenciales as id',
            'codigo',
            'nombre',
            'empresa',
        ];

        $select = $this->clienteRetiradoService->addSelectColumns($select, null, 'fecha_retiro', 'retirado');

        $query = DB::table('clientes_potenciales')
            ->select($select);

        $query->where(function ($builder): void {
            $fechaRetiroColumn = Schema::hasColumn('clientes_potenciales', 'fecha_retiro') ? 'fecha_retiro' : null;
            $retiroFlagColumn = null;

            foreach (['retiro', 'retirado'] as $column) {
                if (Schema::hasColumn('clientes_potenciales', $column)) {
                    $retiroFlagColumn = $column;
                    break;
                }
            }

            if ($fechaRetiroColumn !== null) {
                $builder->whereNotNull($fechaRetiroColumn)
                    ->whereRaw("TRIM(COALESCE({$fechaRetiroColumn}, '')) <> ''");
            }

            if ($retiroFlagColumn !== null) {
                $method = $fechaRetiroColumn !== null ? 'orWhereRaw' : 'whereRaw';
                $builder->{$method}("COALESCE({$retiroFlagColumn}, 0) = 1");
            }
        });

        return $query
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get();
    }

    public function findCliente(int $clienteId): ?object
    {
        $select = [
            'idclientes_potenciales as id',
            'codigo',
            'nombre',
            'empresa',
            'nit',
            'regimen',
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
            'fecha_arriendo',
        ];

        if (Schema::hasColumn('clientes_potenciales', 'numextra')) {
            $select[] = 'numextra';
        }

        if (Schema::hasColumn('clientes_potenciales', 'vlrextrae')) {
            $select[] = 'vlrextrae';
        }

        $select = $this->clienteRetiradoService->addSelectColumns($select, null, 'fecha_retiro', 'retirado');

        return DB::table('clientes_potenciales')
            ->select($select)
            ->where('idclientes_potenciales', $clienteId)
            ->first();
    }

    public function findExistingCobro(int $clienteId, string $mes, int $anio): ?object
    {
        return DB::table('valores_externos')
            ->select(['id_cobro', 'id_cliente', 'mes', 'año', 'valor_total', 'Proforma'])
            ->whereRaw('CAST(TRIM(id_cliente) AS UNSIGNED) = ?', [$clienteId])
            ->whereRaw('LOWER(TRIM(mes)) = ?', [mb_strtolower(trim($mes))])
            ->where('año', $anio)
            ->first();
    }

    public function buildPreview(object $cliente, array $input): array
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
            'facturas' => (float) ($input['numero_facturas'] ?? 0),
            'nota_debito' => 0,
            'nota_credito' => 0,
            'soporte' => (float) ($input['numero_documento_soporte'] ?? 0),
            'nota_ajuste' => 0,
            'acuse' => (float) ($input['numero_acuse'] ?? 0),
            'otro_valor_extra' => (float) ($input['valor_extra'] ?? ($cliente->vlrextra ?? 0)),
            'otro_valor_extra_2' => (float) ($input['valor_extra2'] ?? ($cliente->vlrextra2 ?? 0)),
            'precio_factura' => (float) ($cliente->vlrfactura ?? 0),
            'precio_soporte' => (float) ($cliente->vlrsoporte ?? 0),
            'precio_acuse' => (float) ($cliente->vlrecepcion ?? 0),
        ]);
    }

    public function createCobro(object $cliente, array $input): array
    {
        if ($this->clienteRetiradoService->estaRetirado($cliente)) {
            return [
                'created' => false,
                'duplicated' => false,
                'blocked' => true,
                'message' => 'No es posible generar cobros extraordinarios para clientes retirados.',
            ];
        }

        $mes = mb_strtolower(trim((string) ($input['mes'] ?? '')));
        $anio = (int) ($input['anio'] ?? 0);
        $clienteId = (int) ($cliente->id ?? 0);

        return DB::transaction(function () use ($cliente, $input, $mes, $anio, $clienteId) {
            $existente = $this->findExistingCobro($clienteId, $mes, $anio);

            if ($existente) {
                return [
                    'created' => false,
                    'duplicated' => true,
                    'id_cobro' => (int) ($existente->id_cobro ?? 0),
                    'message' => 'Ya existe un cobro para ese cliente y periodo.',
                ];
            }

            $preview = $this->buildPreview($cliente, $input);
            $nextIdCobro = ((int) DB::table('valores_externos')->lockForUpdate()->max('id_cobro')) + 1;

            DB::table('valores_externos')->insert([
                'id_cobro' => $nextIdCobro,
                'id_cliente' => (string) $clienteId,
                'mes' => $mes,
                'año' => $anio,
                'numero_facturas' => (int) ($input['numero_facturas'] ?? 0),
                'numero_nota_debito' => 0,
                'numero_nota_credito' => 0,
                'numero_documento_soporte' => (int) ($input['numero_documento_soporte'] ?? 0),
                'numero_nota_ajuste' => 0,
                'numero_acuse' => (int) ($input['numero_acuse'] ?? 0),
                'valor_extra' => (float) ($input['valor_extra'] ?? 0),
                'valor_extra2' => (float) ($input['valor_extra2'] ?? 0),
                'valor_facturas' => (float) ($preview['valor_facturas'] ?? 0),
                'valor_documentos' => (float) ($preview['valor_documentos'] ?? 0),
                'valor_acuse' => (float) ($preview['valor_acuse'] ?? 0),
                'valor_mensualidad' => (float) ($preview['total_mensualidad'] ?? 0),
                'valor_total' => (float) ($preview['valor_total_proforma'] ?? 0),
                'Proforma' => 0,
            ]);

            return [
                'created' => true,
                'duplicated' => false,
                'id_cobro' => $nextIdCobro,
                'message' => 'Cobro extraordinario creado correctamente.',
            ];
        });
    }
}
