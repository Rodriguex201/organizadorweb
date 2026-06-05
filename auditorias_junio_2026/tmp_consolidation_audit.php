<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ImportacionesService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

$files = [
    'facturas_file' => new UploadedFile(storage_path('app/tmp_ResumenCombinado_junio_2026.xlsx'), 'ResumenCombinado.xlsx', null, null, true),
    'soporte_file' => new UploadedFile(storage_path('app/tmp_ResumenDocumentoSoporte_junio_2026.xlsx'), 'ResumenDocumentoSoporte.xlsx', null, null, true),
    'recepcion_file' => new UploadedFile(storage_path('app/tmp_ResumenEventos_junio_2026.xlsx'), 'ResumenEventos.xlsx', null, null, true),
];

/** @var ImportacionesService $service */
$service = app(ImportacionesService::class);
/** @var RevisarProformaCalculator $calculator */
$calculator = app(RevisarProformaCalculator::class);

$preview = $service->buildPreview($service->buildBatchFromUploads($files, 'junio', 2026));
$readyRows = array_values(array_filter(
    $preview['rows'],
    static fn (array $row): bool => ($row['status'] ?? null) === 'ready'
));

$rowsByCobro = [];
foreach ($readyRows as $row) {
    $idCobro = (int) ($row['id_cobro'] ?? 0);
    if ($idCobro <= 0) {
        continue;
    }

    $rowsByCobro[$idCobro] ??= [];
    $rowsByCobro[$idCobro][] = $row;
}

$clienteRows = DB::table('valores_externos as ve')
    ->leftJoin('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', 've.id_cliente')
    ->whereIn('ve.id_cobro', array_keys($rowsByCobro))
    ->select([
        've.id_cobro',
        've.id_cliente',
        'cp.codigo',
        'cp.nombre',
        'cp.vlrprincipal',
        'cp.numequipos',
        'cp.vlrterminal',
        'cp.vlrnomina',
        'cp.numero_empleados',
        'cp.numeromoviles',
        'cp.vlrmovil',
        'cp.vlrfactura',
        'cp.vlrsoporte',
        'cp.vlrecepcion',
        'cp.vlrextra',
        'cp.vlrextra2',
    ])
    ->get()
    ->keyBy('id_cobro');

$before = [
    'facturas' => 0.0,
    'nota_credito' => 0.0,
    'soporte' => 0.0,
    'acuse' => 0.0,
    'valor_facturas' => 0.0,
    'valor_documentos' => 0.0,
    'valor_acuse' => 0.0,
    'valor_total' => 0.0,
];

foreach ($readyRows as $row) {
    $before['facturas'] += (float) ($row['imported']['facturas'] ?? 0);
    $before['nota_credito'] += (float) ($row['imported']['nota_credito'] ?? 0);
    $before['soporte'] += (float) ($row['imported']['soporte'] ?? 0);
    $before['acuse'] += (float) ($row['imported']['acuse'] ?? 0);
    $before['valor_facturas'] += (float) ($row['persist_payload']['valor_facturas'] ?? 0);
    $before['valor_documentos'] += (float) ($row['persist_payload']['valor_documentos'] ?? 0);
    $before['valor_acuse'] += (float) ($row['persist_payload']['valor_acuse'] ?? 0);
    $before['valor_total'] += (float) ($row['persist_payload']['valor_total'] ?? 0);
}

$after = [
    'facturas' => 0.0,
    'nota_credito' => 0.0,
    'soporte' => 0.0,
    'acuse' => 0.0,
    'valor_facturas' => 0.0,
    'valor_documentos' => 0.0,
    'valor_acuse' => 0.0,
    'valor_total' => 0.0,
];

$duplicates = [];
$repeatedReadyRows = 0;

foreach ($rowsByCobro as $idCobro => $rows) {
    $cliente = $clienteRows[$idCobro] ?? null;
    $sum = [
        'facturas' => 0.0,
        'nota_debito' => 0.0,
        'nota_credito' => 0.0,
        'soporte' => 0.0,
        'nota_ajuste' => 0.0,
        'acuse' => 0.0,
    ];
    $entries = [];

    foreach ($rows as $row) {
        $sum['facturas'] += (float) ($row['imported']['facturas'] ?? 0);
        $sum['nota_debito'] += (float) ($row['imported']['nota_debito'] ?? 0);
        $sum['nota_credito'] += (float) ($row['imported']['nota_credito'] ?? 0);
        $sum['soporte'] += (float) ($row['imported']['soporte'] ?? 0);
        $sum['nota_ajuste'] += (float) ($row['imported']['nota_ajuste'] ?? 0);
        $sum['acuse'] += (float) ($row['imported']['acuse'] ?? 0);

        $entries[] = [
            'entry_id' => $row['entry_id'] ?? null,
            'emisor' => $row['emisor'] ?? null,
            'sources' => $row['sources'] ?? [],
            'rows' => $row['rows'] ?? [],
            'imported' => $row['imported'] ?? [],
        ];
    }

    $calcData = [
        'numero_equipos' => (float) ($cliente->numequipos ?? 0),
        'valor_principal' => (float) ($cliente->vlrprincipal ?? 0),
        'valor_terminal' => (float) ($cliente->vlrterminal ?? 0),
        'numero_equipos_extra' => 0.0,
        'valor_equipo_extra' => 0.0,
        'empleados' => (float) ($cliente->numero_empleados ?? 0),
        'valor_nomina' => (float) ($cliente->vlrnomina ?? 0),
        'numero_moviles' => (float) ($cliente->numeromoviles ?? 0),
        'valor_movil' => (float) ($cliente->vlrmovil ?? 0),
        'facturas' => $sum['facturas'],
        'nota_debito' => $sum['nota_debito'],
        'nota_credito' => $sum['nota_credito'],
        'soporte' => $sum['soporte'],
        'nota_ajuste' => $sum['nota_ajuste'],
        'acuse' => $sum['acuse'],
        'otro_valor_extra' => (float) ($cliente->vlrextra ?? 0),
        'otro_valor_extra_2' => (float) ($cliente->vlrextra2 ?? 0),
        'precio_factura' => (float) ($cliente->vlrfactura ?? 0),
        'precio_soporte' => (float) ($cliente->vlrsoporte ?? 0),
        'precio_acuse' => (float) ($cliente->vlrecepcion ?? 0),
    ];

    $recalculated = $calculator->calculate($calcData);
    $persist = [
        'numero_facturas' => $sum['facturas'],
        'numero_nota_debito' => $sum['nota_debito'],
        'numero_nota_credito' => $sum['nota_credito'],
        'numero_documento_soporte' => $sum['soporte'],
        'numero_nota_ajuste' => $sum['nota_ajuste'],
        'numero_acuse' => $sum['acuse'],
        'valor_facturas' => (float) ($recalculated['valor_facturas'] ?? 0),
        'valor_documentos' => (float) ($recalculated['valor_documentos'] ?? 0),
        'valor_acuse' => (float) ($recalculated['valor_acuse'] ?? 0),
        'valor_mensualidad' => (float) ($recalculated['total_mensualidad'] ?? 0),
        'valor_total' => (float) ($recalculated['valor_total_proforma'] ?? 0),
    ];

    $after['facturas'] += $sum['facturas'];
    $after['nota_credito'] += $sum['nota_credito'];
    $after['soporte'] += $sum['soporte'];
    $after['acuse'] += $sum['acuse'];
    $after['valor_facturas'] += (float) $persist['valor_facturas'];
    $after['valor_documentos'] += (float) $persist['valor_documentos'];
    $after['valor_acuse'] += (float) $persist['valor_acuse'];
    $after['valor_total'] += (float) $persist['valor_total'];

    if (count($rows) <= 1) {
        continue;
    }

    $repeatedReadyRows += count($rows);
    $duplicates[] = [
        'id_cobro' => (int) $idCobro,
        'id_cliente' => (int) ($cliente->id_cliente ?? 0),
        'codigo' => $cliente->codigo ?? null,
        'nombre' => $cliente->nombre ?? null,
        'ready_rows' => count($rows),
        'entries' => $entries,
        'consolidated_imported' => $sum,
        'consolidated_persist_payload' => $persist,
    ];
}

echo json_encode([
    'summary' => [
        'ready_rows_total' => count($readyRows),
        'repeated_id_cobro_count' => count($duplicates),
        'ready_rows_with_repeated_id_cobro' => $repeatedReadyRows,
        'unique_id_cobro_after_consolidation' => count($rowsByCobro),
    ],
    'totals_before_consolidation' => $before,
    'totals_after_consolidation' => $after,
    'duplicates' => $duplicates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
