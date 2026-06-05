<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$yearColumn = Schema::getColumnListing('valores_externos')[3] ?? 'año';

$targets = [
    '161379896' => ['nit' => '16137989', 'dv' => '6'],
    '750364327' => ['nit' => '75036432', 'dv' => '7'],
    '421257480' => ['nit' => '42125748', 'dv' => '0'],
];

$out = [];
foreach ($targets as $key => $target) {
    $out[$key] = DB::table('valores_externos as ve')
        ->leftJoin('clientes_potenciales as cp', 'cp.idclientes_potenciales', '=', 've.id_cliente')
        ->whereRaw('LOWER(TRIM(ve.mes)) = ?', ['junio'])
        ->whereRaw('ve.`' . $yearColumn . '` = ?', [2026])
        ->where('cp.nit', $target['nit'])
        ->where('cp.dv', $target['dv'])
        ->select([
            've.id_cobro',
            've.id_cliente',
            'cp.codigo',
            'cp.nombre',
            'cp.empresa',
            'cp.nit',
            'cp.dv',
            've.numero_facturas',
            've.numero_nota_credito',
            've.numero_documento_soporte',
            've.numero_acuse',
            've.valor_facturas',
            've.valor_documentos',
            've.valor_acuse',
            've.valor_total',
        ])
        ->orderBy('ve.id_cobro')
        ->get();
}

echo json_encode([
    'year_column' => $yearColumn,
    'groups' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
