<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CobrosService;
use App\Services\ImportacionesService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$files = [
    'facturas_file' => new UploadedFile(storage_path('app/tmp_ResumenCombinado_junio_2026.xlsx'), 'ResumenCombinado.xlsx', null, null, true),
    'soporte_file' => new UploadedFile(storage_path('app/tmp_ResumenDocumentoSoporte_junio_2026.xlsx'), 'ResumenDocumentoSoporte.xlsx', null, null, true),
    'recepcion_file' => new UploadedFile(storage_path('app/tmp_ResumenEventos_junio_2026.xlsx'), 'ResumenEventos.xlsx', null, null, true),
];

/** @var ImportacionesService $service */
$service = app(ImportacionesService::class);
/** @var CobrosService $cobrosService */
$cobrosService = app(CobrosService::class);
/** @var RevisarProformaCalculator $calculator */
$calculator = app(RevisarProformaCalculator::class);

$singleFacturas = $service->buildBatchFromUploads([
    'facturas_file' => $files['facturas_file'],
    'soporte_file' => null,
    'recepcion_file' => null,
], 'junio', 2026);

$singleSoporte = $service->buildBatchFromUploads([
    'facturas_file' => null,
    'soporte_file' => $files['soporte_file'],
    'recepcion_file' => null,
], 'junio', 2026);

$singleEventos = $service->buildBatchFromUploads([
    'facturas_file' => null,
    'soporte_file' => null,
    'recepcion_file' => $files['recepcion_file'],
], 'junio', 2026);

$sumEntries = static function (array $entries, string $field): float {
    return array_reduce($entries, static function (float $carry, array $entry) use ($field): float {
        return $carry + (float) ($entry[$field] ?? 0);
    }, 0.0);
};

$totalsExcel = [
    'facturas' => [
        'numero_facturas' => $sumEntries($singleFacturas['entries'], 'facturas'),
        'numero_nota_debito' => $sumEntries($singleFacturas['entries'], 'nota_debito'),
        'numero_nota_credito' => $sumEntries($singleFacturas['entries'], 'nota_credito'),
    ],
    'soporte' => [
        'numero_documento_soporte' => $sumEntries($singleSoporte['entries'], 'soporte'),
        'numero_nota_ajuste' => $sumEntries($singleSoporte['entries'], 'nota_ajuste'),
    ],
    'eventos' => [
        'numero_acuse' => $sumEntries($singleEventos['entries'], 'acuse'),
    ],
];

$fullBatch = $service->buildBatchFromUploads($files, 'junio', 2026);
$preview = $service->buildPreview($fullBatch);
$readyRows = array_values(array_filter(
    $preview['rows'],
    static fn (array $row): bool => ($row['status'] ?? null) === 'ready'
));

$consolidated = [];
foreach ($readyRows as $row) {
    $idCobro = (int) ($row['id_cobro'] ?? 0);
    if ($idCobro <= 0) {
        continue;
    }

    if (!isset($consolidated[$idCobro])) {
        $consolidated[$idCobro] = $row;
        continue;
    }

    foreach (['facturas', 'nota_debito', 'nota_credito', 'soporte', 'nota_ajuste', 'acuse'] as $field) {
        $consolidated[$idCobro]['imported'][$field] = (float) ($consolidated[$idCobro]['imported'][$field] ?? 0)
            + (float) ($row['imported'][$field] ?? 0);
    }

    $consolidated[$idCobro]['sources'] = array_values(array_unique(array_merge(
        (array) ($consolidated[$idCobro]['sources'] ?? []),
        (array) ($row['sources'] ?? []),
    )));
    $consolidated[$idCobro]['rows'] = array_values(array_unique(array_merge(
        array_map('strval', (array) ($consolidated[$idCobro]['rows'] ?? [])),
        array_map('strval', (array) ($row['rows'] ?? [])),
    )));
}

foreach ($consolidated as $idCobro => &$row) {
    $cobro = $cobrosService->findCobroById((int) $idCobro);
    if (!$cobro) {
        continue;
    }

    $input = $cobrosService->mapCobroToRevisionValues($cobro);
    $input['facturas'] = (float) ($row['imported']['facturas'] ?? 0);
    $input['nota_debito'] = (float) ($row['imported']['nota_debito'] ?? 0);
    $input['nota_credito'] = (float) ($row['imported']['nota_credito'] ?? 0);
    $input['soporte'] = (float) ($row['imported']['soporte'] ?? 0);
    $input['nota_ajuste'] = (float) ($row['imported']['nota_ajuste'] ?? 0);
    $input['acuse'] = (float) ($row['imported']['acuse'] ?? 0);

    $calculated = $calculator->calculate($input);
    $row['persist_payload'] = [
        'numero_facturas' => (float) ($row['imported']['facturas'] ?? 0),
        'numero_nota_debito' => (float) ($row['imported']['nota_debito'] ?? 0),
        'numero_nota_credito' => (float) ($row['imported']['nota_credito'] ?? 0),
        'numero_documento_soporte' => (float) ($row['imported']['soporte'] ?? 0),
        'numero_nota_ajuste' => (float) ($row['imported']['nota_ajuste'] ?? 0),
        'numero_acuse' => (float) ($row['imported']['acuse'] ?? 0),
        'valor_facturas' => (float) ($calculated['valor_facturas'] ?? 0),
        'valor_documentos' => (float) ($calculated['valor_documentos'] ?? 0),
        'valor_acuse' => (float) ($calculated['valor_acuse'] ?? 0),
        'valor_mensualidad' => (float) ($calculated['total_mensualidad'] ?? 0),
        'valor_total' => (float) ($calculated['valor_total_proforma'] ?? 0),
    ];
}
unset($row);

$expectedTotals = [
    'numero_facturas' => 0.0,
    'numero_nota_debito' => 0.0,
    'numero_nota_credito' => 0.0,
    'numero_documento_soporte' => 0.0,
    'numero_nota_ajuste' => 0.0,
    'numero_acuse' => 0.0,
    'valor_facturas' => 0.0,
    'valor_documentos' => 0.0,
    'valor_acuse' => 0.0,
    'valor_mensualidad' => 0.0,
    'valor_total' => 0.0,
];

foreach ($consolidated as $row) {
    foreach ($expectedTotals as $field => $_) {
        $expectedTotals[$field] += (float) ($row['persist_payload'][$field] ?? 0);
    }
}

$yearColumn = null;
foreach (Schema::getColumnListing('valores_externos') as $column) {
    if (in_array($column, ['año', 'aÃ±o', 'aÃƒÂ±o', 'aÃƒÆ’Ã‚Â±o'], true)) {
        $yearColumn = $column;
        break;
    }
}
$yearColumn ??= 'año';

$baseRows = DB::table('valores_externos as ve')
    ->leftJoin('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', 've.id_cliente')
    ->whereRaw('LOWER(TRIM(ve.mes)) = ?', ['junio'])
    ->where("ve.{$yearColumn}", 2026)
    ->select([
        've.id_cobro',
        've.id_cliente',
        've.numero_facturas',
        've.numero_nota_debito',
        've.numero_nota_credito',
        've.numero_documento_soporte',
        've.numero_nota_ajuste',
        've.numero_acuse',
        've.valor_facturas',
        've.valor_documentos',
        've.valor_acuse',
        've.valor_mensualidad',
        've.valor_total',
        'cp.codigo',
        'cp.nombre',
    ])
    ->get()
    ->keyBy('id_cobro');

$baseTotals = [
    'numero_facturas' => 0.0,
    'numero_nota_debito' => 0.0,
    'numero_nota_credito' => 0.0,
    'numero_documento_soporte' => 0.0,
    'numero_nota_ajuste' => 0.0,
    'numero_acuse' => 0.0,
    'valor_facturas' => 0.0,
    'valor_documentos' => 0.0,
    'valor_acuse' => 0.0,
    'valor_mensualidad' => 0.0,
    'valor_total' => 0.0,
];

foreach ($baseRows as $row) {
    foreach ($baseTotals as $field => $_) {
        $baseTotals[$field] += (float) ($row->{$field} ?? 0);
    }
}

$diffRows = [];
foreach ($consolidated as $idCobro => $row) {
    $base = $baseRows[$idCobro] ?? null;
    if ($base === null) {
        $diffRows[] = [
            'id_cobro' => (int) $idCobro,
            'id_cliente' => (int) ($row['id_cliente'] ?? 0),
            'codigo' => $row['selected_codigo'] ?? '',
            'cliente' => $row['cliente'] ?? '',
            'expected' => $row['persist_payload'],
            'base' => null,
        ];
        continue;
    }

    $fields = [
        'numero_facturas',
        'numero_nota_debito',
        'numero_nota_credito',
        'numero_documento_soporte',
        'numero_nota_ajuste',
        'numero_acuse',
        'valor_facturas',
        'valor_documentos',
        'valor_acuse',
        'valor_mensualidad',
        'valor_total',
    ];

    $hasDifference = false;
    foreach ($fields as $field) {
        if ((float) ($row['persist_payload'][$field] ?? 0) !== (float) ($base->{$field} ?? 0)) {
            $hasDifference = true;
            break;
        }
    }

    if ($hasDifference) {
        $diffRows[] = [
            'id_cobro' => (int) $idCobro,
            'id_cliente' => (int) ($base->id_cliente ?? 0),
            'codigo' => $base->codigo ?? '',
            'cliente' => $base->nombre ?? '',
            'expected' => $row['persist_payload'],
            'base' => [
                'numero_facturas' => (float) ($base->numero_facturas ?? 0),
                'numero_nota_debito' => (float) ($base->numero_nota_debito ?? 0),
                'numero_nota_credito' => (float) ($base->numero_nota_credito ?? 0),
                'numero_documento_soporte' => (float) ($base->numero_documento_soporte ?? 0),
                'numero_nota_ajuste' => (float) ($base->numero_nota_ajuste ?? 0),
                'numero_acuse' => (float) ($base->numero_acuse ?? 0),
                'valor_facturas' => (float) ($base->valor_facturas ?? 0),
                'valor_documentos' => (float) ($base->valor_documentos ?? 0),
                'valor_acuse' => (float) ($base->valor_acuse ?? 0),
                'valor_mensualidad' => (float) ($base->valor_mensualidad ?? 0),
                'valor_total' => (float) ($base->valor_total ?? 0),
            ],
        ];
    }
}

$nonParticipatingRows = [];
foreach ($baseRows as $idCobro => $base) {
    if (isset($consolidated[(int) $idCobro])) {
        continue;
    }

    $hasValues = false;
    foreach ([
        'numero_facturas',
        'numero_nota_debito',
        'numero_nota_credito',
        'numero_documento_soporte',
        'numero_nota_ajuste',
        'numero_acuse',
        'valor_facturas',
        'valor_documentos',
        'valor_acuse',
    ] as $field) {
        if ((float) ($base->{$field} ?? 0) !== 0.0) {
            $hasValues = true;
            break;
        }
    }

    if (!$hasValues) {
        continue;
    }

    $nonParticipatingRows[] = [
        'id_cobro' => (int) $base->id_cobro,
        'id_cliente' => (int) ($base->id_cliente ?? 0),
        'codigo' => $base->codigo ?? '',
        'cliente' => $base->nombre ?? '',
        'base' => [
            'numero_facturas' => (float) ($base->numero_facturas ?? 0),
            'numero_nota_debito' => (float) ($base->numero_nota_debito ?? 0),
            'numero_nota_credito' => (float) ($base->numero_nota_credito ?? 0),
            'numero_documento_soporte' => (float) ($base->numero_documento_soporte ?? 0),
            'numero_nota_ajuste' => (float) ($base->numero_nota_ajuste ?? 0),
            'numero_acuse' => (float) ($base->numero_acuse ?? 0),
            'valor_facturas' => (float) ($base->valor_facturas ?? 0),
            'valor_documentos' => (float) ($base->valor_documentos ?? 0),
            'valor_acuse' => (float) ($base->valor_acuse ?? 0),
            'valor_mensualidad' => (float) ($base->valor_mensualidad ?? 0),
            'valor_total' => (float) ($base->valor_total ?? 0),
        ],
    ];
}

$table = [
    [
        'concepto' => 'Total Facturas',
        'excel' => $totalsExcel['facturas']['numero_facturas'],
        'base' => $baseTotals['numero_facturas'],
        'diferencia' => $baseTotals['numero_facturas'] - $totalsExcel['facturas']['numero_facturas'],
    ],
    [
        'concepto' => 'Total Notas Debito',
        'excel' => $totalsExcel['facturas']['numero_nota_debito'],
        'base' => $baseTotals['numero_nota_debito'],
        'diferencia' => $baseTotals['numero_nota_debito'] - $totalsExcel['facturas']['numero_nota_debito'],
    ],
    [
        'concepto' => 'Total Notas Credito',
        'excel' => $totalsExcel['facturas']['numero_nota_credito'],
        'base' => $baseTotals['numero_nota_credito'],
        'diferencia' => $baseTotals['numero_nota_credito'] - $totalsExcel['facturas']['numero_nota_credito'],
    ],
    [
        'concepto' => 'Valor Facturas',
        'excel' => $expectedTotals['valor_facturas'],
        'base' => $baseTotals['valor_facturas'],
        'diferencia' => $baseTotals['valor_facturas'] - $expectedTotals['valor_facturas'],
    ],
    [
        'concepto' => 'Total Documentos Soporte',
        'excel' => $totalsExcel['soporte']['numero_documento_soporte'],
        'base' => $baseTotals['numero_documento_soporte'],
        'diferencia' => $baseTotals['numero_documento_soporte'] - $totalsExcel['soporte']['numero_documento_soporte'],
    ],
    [
        'concepto' => 'Total Notas Ajuste',
        'excel' => $totalsExcel['soporte']['numero_nota_ajuste'],
        'base' => $baseTotals['numero_nota_ajuste'],
        'diferencia' => $baseTotals['numero_nota_ajuste'] - $totalsExcel['soporte']['numero_nota_ajuste'],
    ],
    [
        'concepto' => 'Valor Documentos',
        'excel' => $expectedTotals['valor_documentos'],
        'base' => $baseTotals['valor_documentos'],
        'diferencia' => $baseTotals['valor_documentos'] - $expectedTotals['valor_documentos'],
    ],
    [
        'concepto' => 'Total Acuses',
        'excel' => $totalsExcel['eventos']['numero_acuse'],
        'base' => $baseTotals['numero_acuse'],
        'diferencia' => $baseTotals['numero_acuse'] - $totalsExcel['eventos']['numero_acuse'],
    ],
    [
        'concepto' => 'Valor Acuses',
        'excel' => $expectedTotals['valor_acuse'],
        'base' => $baseTotals['valor_acuse'],
        'diferencia' => $baseTotals['valor_acuse'] - $expectedTotals['valor_acuse'],
    ],
];

echo json_encode([
    'database' => DB::connection()->getDatabaseName(),
    'host' => config('database.connections.mysql.host'),
    'periodo' => ['mes' => 'junio', 'anio' => 2026],
    'participating' => [
        'batch_entries_total' => count($fullBatch['entries'] ?? []),
        'preview_ready_rows' => count($readyRows),
        'consolidated_unique_id_cobro' => count($consolidated),
        'base_rows_period' => $baseRows->count(),
    ],
    'excel_totals' => $totalsExcel,
    'expected_persisted_totals' => $expectedTotals,
    'base_totals' => $baseTotals,
    'reconciliation' => $table,
    'all_differences_zero' => count(array_filter($table, static fn (array $item): bool => abs((float) $item['diferencia']) > 0.0001)) === 0,
    'different_id_cobro_count' => count($diffRows),
    'different_rows' => $diffRows,
    'non_participating_with_values_count' => count($nonParticipatingRows),
    'non_participating_with_values' => $nonParticipatingRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
