<?php

// No application bootstrap, .env, PDO, DB connection, migrations or PHPUnit.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaExportService;
use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaValidacionService;
use App\Services\ImportacionesService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

$checks = 0;
$check = function ($condition, $message) use (&$checks) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$validation = new CadenaValidacionService();
$reader = new CadenaSpreadsheetReader();
$summary = new CadenaResumenService($validation);
$exporter = new CadenaExportService();
$client = $validation->nit('900123456') + ['cliente_id' => 1, 'nombre' => '=HYPERLINK("bad")'];
$files = [];
foreach (['facturas' => 'nota_debito_92.csv', 'soporte' => 'nota_ajuste_95.csv', 'eventos' => 'eventos_cinco_codigos.csv'] as $category => $name) {
    $path = dirname(__DIR__).'/fixtures/cadena/'.$name;
    $files[] = ['archivo' => $name, 'categoria' => $category, 'hash' => hash_file('sha256', $path)] + $reader->read($path, 'csv', $category);
}
$preview = $summary->build($files, [$client], []);
$sameBook = $files;
$sameBook[2]['hash'] = $sameBook[1]['hash'];
$sameBookPreview = $summary->build($sameBook, [$client], []);
$check($sameBookPreview['clientes'][0]['cantidades']['032'] === 3, 'Mismo libro puede aportar soporte y eventos');
$sameCategory = $summary->build([$files[1], $files[1]], [$client], []);
$check($sameCategory['archivos'][1]['estado'] === 'Duplicado excluido', 'Mismo hash/categoría sigue excluido');
$exports = $exporter->prepare($preview, $files);
$tables = $exports['tables'];
$baseHeaders = ['Integracion', 'Canal', 'Identificación emisor', 'Emisor'];
foreach ([
    'Resumen.xlsx' => ['Facturas de venta', 'Nota debito', 'Nota credito'],
    'ResumenCombinado.xlsx' => ['Facturas de venta', 'Nota debito', 'Nota credito'],
    'ResumenDocumentoSoporte.xlsx' => ['Documento soporte adquisiciones', 'Nota de ajuste documento soporte adquisiciones'],
    'ResumenEventos.xlsx' => ['Acuse', 'Recibo', 'Aceptación expresa', 'Aceptación tácita', 'Reclamo'],
] as $filename => $columns) {
    $expected = array_merge($baseHeaders, $columns);
    $check($tables[$filename]['headers'] === $expected, 'Encabezados históricos exactos: '.$filename);
    $check(count($tables[$filename]['rows'][0]) === count($expected), 'Sin metadatos adicionales en filas: '.$filename);
}
$check($tables['Resumen.xlsx']['rows'][0][5] === 2, '92 deduplicado en F');
$check($tables['Resumen.xlsx']['rows'][0][4] === null && $tables['Resumen.xlsx']['rows'][0][6] === null, '01/91 ausentes no inventan ceros');
$check($tables['ResumenDocumentoSoporte.xlsx']['rows'][0][5] === 1 && $tables['ResumenDocumentoSoporte.xlsx']['rows'][0][4] === null, '95 solo F soporte');
$check(array_slice($tables['ResumenEventos.xlsx']['rows'][0], 4, 5) === [3, 1, 4, 5, 2], 'Eventos orden legado 032/030/033/034/031');
$check($tables['ResumenCombinado.xlsx'] === $tables['Resumen.xlsx'], 'Un original también ofrece el mismo combinado, sin otro combinador');
$check($tables['Resumen.xlsx']['rows'][0][2] === $client['nit_base'].'-'.$client['dv'], 'NIT base y DV sin pérdida');

// Invoke the REAL legacy parser only, not buildPreview/apply. Unbootstrapped Excel facade
// falls back to its existing PhpSpreadsheet reader. Those parsing methods have no DB use.
$legacy = (new ReflectionClass(ImportacionesService::class))->newInstanceWithoutConstructor();
$paths = $uploads = [];
try {
    foreach (['facturas_file' => 'Resumen.xlsx', 'soporte_file' => 'ResumenDocumentoSoporte.xlsx', 'recepcion_file' => 'ResumenEventos.xlsx'] as $input => $name) {
        $path = dirname(__DIR__).'/fixtures/cadena/export-'.bin2hex(random_bytes(6)).'.xlsx';
        $paths[] = $path;
        $exporter->write($tables[$name], 'Septiembre 2026', $path);
        $reopened = IOFactory::load($path);
        $check($reopened->getActiveSheet()->getTitle() === 'Resumen', 'Primera hoja Resumen');
        $check(in_array($reopened->getActiveSheet()->getCell('C2')->getDataType(), ['s', 'inlineStr'], true), 'NIT texto en XLSX');
        $check($reopened->getProperties()->getTitle() === 'Septiembre 2026', 'Periodo en metadatos');
        $check($reopened->getActiveSheet()->getStyle('A1')->getFont()->getBold(), 'Encabezado conserva estilo en escritura por flujo');
        if ($input !== 'facturas_file') {
            $check(in_array($reopened->getActiveSheet()->getCell('D2')->getDataType(), ['s', 'inlineStr'], true), 'Nombre con = nunca fórmula');
        }
        $reopened->disconnectWorksheets();
        $uploads[$input] = new UploadedFile($path, $name, null, null, true);
    }
    $batch = $legacy->buildBatchFromUploads($uploads, 'septiembre', 2026);
    $check($batch['errors'] === [], 'Los tres XLSX pasan parser real sin errores');
    $check(count($batch['entries']) >= 1, 'Importador reconoce registros');
    $sum = fn ($key) => array_sum(array_column($batch['entries'], $key));
    $check($sum('nota_debito') === 2.0 && $sum('nota_credito') === 0.0 && $sum('facturas') === 0.0, 'Parser real 92 aislado');
    $check($sum('nota_ajuste') === 1.0 && $sum('soporte') === 0.0, 'Parser real 95 aislado');
    $check($sum('acuse') === 3.0, 'Parser real recibe solo 032, no los otros cuatro');
    $check($batch['entries'][0]['nit'] === $client['nit_base'].$client['dv'], 'Legacy normaliza NIT-DV');
    $auditPath = dirname(__DIR__).'/fixtures/cadena/export-'.bin2hex(random_bytes(6)).'.xlsx';
    $paths[] = $auditPath;
    $large = $tables['AuditoriaDocumentoSoporte.xlsx'];
    $large['rows'] = array_fill(0, 36000, ['prueba.csv', 2, '900123456', 'SOLO TEST', '', '', '05', 'No', 'Fila excluida de prueba']);
    $before = memory_get_usage(true);
    $exporter->write($large, 'Septiembre 2026', $auditPath);
    $check(is_file($auditPath) && filesize($auditPath) > 0, 'Auditoría 36.000 filas generada sin cuadrícula en memoria');
    $check(memory_get_peak_usage(true) - $before < 64 * 1024 * 1024, 'Escritor grande con incremento pico inferior a 64 MiB');
    echo 'Auditoría sintética: 36000 filas; pico proceso '.round(memory_get_peak_usage(true) / 1048576, 1)." MiB.\n";
} finally {
    foreach ($paths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

$only = $exporter->prepare($summary->build([$files[0]], [$client], []), [$files[0]]);
$check(!isset($only['tables']['ResumenEventos.xlsx'], $only['tables']['ResumenDocumentoSoporte.xlsx']), 'Categorias ausentes sin archivos');
$duplicate = $files[0];
$duplicate['archivo'] = 'otro-original.csv';
$duplicate['hash'] = 'distinto';
$multi = $exporter->prepare($summary->build([$files[0], $duplicate], [$client], []), [$files[0], $duplicate]);
$check($multi['tables']['ResumenCombinado.xlsx'] === $multi['tables']['Resumen.xlsx'], 'Combinado mismo esquema/total');
$check($multi['tables']['ResumenCombinado.xlsx']['rows'][0][5] === 2, 'Documentos repetidos entre archivos no se duplican');
$ambiguous = $summary->build($files, [$client, array_replace($client, ['cliente_id' => 2])], []);
$ambiguousExports = $exporter->prepare($ambiguous, $files);
$check(count($ambiguousExports['tables']['Resumen.xlsx']['rows']) === 1, 'Factura ambigua exporta NIT, no asigna cliente');
$check(count($ambiguousExports['tables']['ResumenDocumentoSoporte.xlsx']['rows']) === 0, 'Soporte ambiguo solo en auditoría');
$check($ambiguousExports['omitidos'][0][0] === 'soporte', 'Solo soporte pendiente se omite del resumen');
$check($ambiguous['clientes'][0]['cliente_id'] === null, 'No asignar automáticamente cliente ambiguo');
$unknown = $exporter->prepare($summary->build($files, [], []), $files);
$check(count($unknown['tables']['Resumen.xlsx']['rows']) === 1, 'Facturas sin cliente incluidas');
$check(count($unknown['tables']['ResumenDocumentoSoporte.xlsx']['rows']) === 0, 'Soporte sin cliente solo en auditoría');
$check($unknown['tables']['Resumen.xlsx']['rows'] === $ambiguousExports['tables']['Resumen.xlsx']['rows'], 'Mismos NIT, nombre observado y cantidades sin cliente o ambiguo');
$check(count($unknown['tables']['ResumenEventos.xlsx']['rows']) === 1, 'Evento pendiente conserva NIT para resolución legacy');
$check(count($unknown['tables']['AuditoriaDocumentoSoporte.xlsx']['rows']) === count($tables['AuditoriaDocumentoSoporte.xlsx']['rows']), 'Soporte pendiente conserva todas sus filas en auditoría');
// Resolved, unknown, ambiguous and invalid-DV groups share a batch. Counts stay intact.
$mixed = $preview;
$mixed['clientes'][0]['cantidades'] = ['05' => 2];
foreach (['Sin cliente', 'NIT ambiguo', 'DV inválido'] as $i => $state) {
    $mixed['clientes'][] = array_replace($mixed['clientes'][0], ['nit_base' => '80000000'.$i,
        'cliente_id' => null, 'dv_valido' => $i !== 2, 'estado' => $state, 'cantidades' => ['05' => 31, '95' => 1]]);
}
$mixedExports = $exporter->prepare($mixed, $files);
$supportRows = $mixedExports['tables']['ResumenDocumentoSoporte.xlsx']['rows'];
$check(count($supportRows) === 1 && $supportRows[0][4] === 2, 'Soporte solo cliente válido: 1 fila / total 2');
$check(count($mixedExports['omitidos']) === 3, 'Tres exclusiones de soporte registradas');
$check(array_sum(array_map(fn ($g) => $g['cantidades']['05'], $mixed['clientes'])) === 95, 'No se alteran los 95 documentos observados');
$check(count($mixedExports['tables']['AuditoriaCadena.xlsx']['rows']) === count($mixed['auditoria']) + 3, 'Exclusiones comerciales explícitas en auditoría');
$mixed['consulta_disponible'] = true;
$check((new App\Services\Cadena\CadenaGeneracionService())->issues($mixed, $mixedExports, 'soporte') === [], 'Exclusiones no bloquean el resumen válido');
$zero = $preview;
$zero['clientes'][0]['cantidades']['032'] = 0;
$check($exporter->prepare($zero, $files)['tables']['ResumenEventos.xlsx']['rows'][0][4] === 0, 'Cero explícito conservado como número');
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
foreach (['index', 'preview'] as $view) {
    token_get_all($compiler->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/importaciones/cadena/'.$view.'.blade.php')), TOKEN_PARSE);
    $check(true, 'Blade compila sin bootstrap ni BD: '.$view);
}
echo "OK: {$checks} comprobaciones de exportación y lectura legacy; sin BD.\n";
