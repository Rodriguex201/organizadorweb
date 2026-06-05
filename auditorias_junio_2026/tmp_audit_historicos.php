<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ImportacionesService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$files = [
    'facturas_file' => new UploadedFile(storage_path('app/tmp_ResumenCombinado_junio_2026.xlsx'), 'ResumenCombinado.xlsx', null, null, true),
    'soporte_file' => new UploadedFile(storage_path('app/tmp_ResumenDocumentoSoporte_junio_2026.xlsx'), 'ResumenDocumentoSoporte.xlsx', null, null, true),
    'recepcion_file' => new UploadedFile(storage_path('app/tmp_ResumenEventos_junio_2026.xlsx'), 'ResumenEventos.xlsx', null, null, true),
];

$service = app(ImportacionesService::class);
$batch = $service->buildBatchFromUploads($files, 'junio', 2026);
$preview = $service->buildPreview($batch);

$yearColumn = 'año';
foreach (Schema::getColumnListing('valores_externos') as $candidate) {
    if (in_array($candidate, ['año', 'aÃ±o', 'aÃƒÂ±o', 'aÃƒÆ’Ã‚Â±o'], true)) {
        $yearColumn = $candidate;
        break;
    }
}

$targets = [
    ['codigo' => 'A217', 'id_cobro' => 14884],
    ['codigo' => 'A061', 'id_cobro' => 14949],
    ['codigo' => 'B531', 'id_cobro' => 15039],
];

$targetRows = DB::table('valores_externos as ve')
    ->leftJoin('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', 've.id_cliente')
    ->whereIn('ve.id_cobro', array_column($targets, 'id_cobro'))
    ->select([
        've.id_cobro',
        've.id_cliente',
        've.mes',
        DB::raw("ve.`{$yearColumn}` as anio"),
        've.numero_facturas',
        've.numero_nota_credito',
        've.numero_documento_soporte',
        've.numero_acuse',
        've.valor_facturas',
        've.valor_documentos',
        've.valor_acuse',
        've.valor_total',
        'cp.codigo',
        'cp.nombre',
        'cp.empresa',
        'cp.nit',
        'cp.dv',
        'cp.fecha_retiro',
        'cp.retiro',
    ])
    ->get()
    ->keyBy('id_cobro');

$previewByCobro = [];
foreach ($preview['rows'] as $row) {
    $idCobro = (int) ($row['id_cobro'] ?? 0);
    if ($idCobro <= 0) {
        continue;
    }
    $previewByCobro[$idCobro] ??= [];
    $previewByCobro[$idCobro][] = $row;
}

$duplicateGroups = [
    '161379896' => [14884, 14886], // A217/A223
    '750364327' => [14949, 14950], // A061/A062
    '421257480' => [14812, 15039], // B340/B531
];

$groupAudit = [];
foreach ($duplicateGroups as $nit => $cobros) {
    $rows = DB::table('valores_externos as ve')
        ->leftJoin('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', 've.id_cliente')
        ->whereIn('ve.id_cobro', $cobros)
        ->select([
            've.id_cobro',
            've.id_cliente',
            've.numero_facturas',
            've.numero_nota_credito',
            've.numero_documento_soporte',
            've.numero_acuse',
            've.valor_facturas',
            've.valor_documentos',
            've.valor_acuse',
            've.valor_total',
            'cp.codigo',
            'cp.nombre',
            'cp.empresa',
            'cp.nit',
            'cp.dv',
            'cp.fecha_retiro',
            'cp.retiro',
        ])
        ->orderBy('ve.id_cobro')
        ->get();

    $participating = [];
    foreach ($previewByCobro as $idCobro => $rowsPreview) {
        if (!in_array($idCobro, $cobros, true)) {
            continue;
        }
        $participating[$idCobro] = $rowsPreview;
    }

    $groupAudit[$nit] = [
        'candidates' => $rows,
        'participating_preview_rows' => $participating,
    ];
}

echo json_encode([
    'summary' => [
        'preview_total' => $preview['summary'] ?? [],
        'targets' => $targets,
    ],
    'target_rows' => $targetRows,
    'group_audit' => $groupAudit,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
