<?php

// Run directly: php tests/Standalone/CadenaPreviewTest.php
// No Laravel bootstrap, PHPUnit, PDO, database connections or .env loading.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaValidacionService;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function rejects(callable $action, string $message): void
{
    try {
        $action();
    } catch (InvalidArgumentException) {
        check(true, $message);
        return;
    }
    check(false, $message);
}
function source(string $name, string $category, array $rows, ?string $hash = null): array
{
    return ['archivo' => $name, 'categoria' => $category, 'hash' => $hash ?? hash('sha256', $name), 'ignoradas' => [],
        'hojas' => [['hoja' => 'Datos', 'filas' => array_map(fn ($values, $i) => ['fila' => $i + 2, 'valores' => $values], $rows, array_keys($rows))]]];
}

$v = new CadenaValidacionService();
$s = new CadenaResumenService($v);
$r = new CadenaSpreadsheetReader();
$nit = $v->nit('900.123.456');
check($nit['nit_base'] === '900123456' && $nit['dv'] === '8', 'NIT base and known DV');
check($v->nit('900.123.456-8')['dv_valido'], 'Explicit DV');
check(!$v->nit('900123456-9')['dv_valido'], 'Incorrect DV remains pending');
check($v->nit('9001234568')['nit_base'] === '9001234568', 'No guessed concatenated DV');
rejects(fn () => $v->nit('9.00123E+8'), 'Scientific notation rejected');
rejects(fn () => $v->nit('900123456-8', '9'), 'Contradictory DV rejected');
$client = $nit + ['cliente_id' => 1, 'nombre' => 'Cliente de prueba'];
check($v->match($nit, [$client])['cliente_id'] === 1, 'Unique client');
check($v->match($nit, [$client, array_replace($client, ['cliente_id' => 2])])['cliente_id'] === null, 'Ambiguous NIT');
check($v->match($nit, [])['cliente_id'] === null, 'Unknown NIT');
check($v->match($v->nit('900123456-9'), [$client])['cliente_id'] === null, 'DV conflict not auto matched');
check($v->eventos('32', '0') === ['032' => 0], 'Explicit zero preserved');
check($v->eventos('030--031,032;033 034', '5') === array_fill_keys(['030', '031', '032', '033', '034'], 1), 'Five event types');
check($v->eventos('032', '7') === ['032' => 7], 'Aggregated events');
check($v->eventos('032', null) === ['032' => 1], 'Missing quantity column counts row');
rejects(fn () => $v->eventos('030 032', '7'), 'Ambiguous event allocation');
rejects(fn () => $v->eventos('035', '1'), 'Unknown event');
rejects(fn () => $v->eventos('032', ''), 'Blank quantity is not zero');
rejects(fn () => $v->eventos('032', '-1'), 'Negative quantity');
$doc = ['nit' => '900123456', 'nombre' => 'Prueba', 'tipo' => '01', 'prefijo' => 'FE', 'numero' => '1'];
$files = [source('a', 'facturas', [$doc, $doc, array_replace($doc, ['tipo' => '91']), array_replace($doc, ['tipo' => '92'])]),
    source('same-file', 'facturas', [$doc], hash('sha256', 'a')),
    source('b', 'facturas', [$doc, array_replace($doc, ['prefijo' => 'AB'])]),
    source('c', 'soporte', [array_replace($doc, ['tipo' => '05']), array_replace($doc, ['tipo' => '95'])]),
    source('d', 'eventos', [['receptor' => '900123456', 'evento' => '032', 'cantidad' => '0']])];
$result = $s->build($files, [$client], [1 => [['id_cobro' => 7, 'proformas' => [], 'protecciones' => ['pagada']]]]);
$counts = $result['clientes'][0]['cantidades'];
check($counts['01'] === 2 && $counts['91'] === 1 && $counts['92'] === 1, 'Composite identity deduplicates within/across files, preserves prefixes/types');
check($counts['05'] === 1 && $counts['95'] === 1, 'Support and adjustments independent');
check($counts['032'] === 0 && !isset($counts['030']), 'Explicit event zero versus absent');
check($result['archivos'][1]['estado'] === 'Duplicado excluido', 'SHA256 duplicate excluded');
check($result['clientes'][0]['destinos'][0]['protecciones'] === ['pagada'], 'Protected destination propagated');
$partial = $s->build([source('partial', 'soporte', [['nit' => '900123456', 'tipo' => '05']])], [$client], []);
check($partial['alcance']['facturas'] === 'CONSERVAR' && $partial['alcance']['eventos'] === 'CONSERVAR', 'Absent categories conserve');
check(!isset($partial['clientes'][0]['cantidades']['95']), 'Absent adjustment is not zero');
check(str_contains($partial['auditoria'][0]['detalle'], 'no verificable'), 'Missing identity visibly diagnosed');
$conflict = $s->build([source('events', 'eventos', [
    ['receptor' => '900123456', 'evento' => '032', 'cantidad' => '3', 'evento_id' => 'x'],
    ['receptor' => '900123456', 'evento' => '032', 'cantidad' => '5', 'evento_id' => 'x'],
])], [$client], []);
check($conflict['clientes'][0]['cantidades']['032'] === 3 && $conflict['auditoria'][1]['estado'] === 'Error', 'Repeated event identity with conflicting totals');
$split = $s->build([source('split', 'eventos', [
    ['receptor' => '900123456', 'evento' => '030 032', 'cantidad' => '2', 'evento_id' => 'x'],
    ['receptor' => '900123456', 'evento' => '032', 'cantidad' => '1', 'evento_id' => 'x'],
])], [$client], []);
check($split['clientes'][0]['cantidades'] === ['030' => 1, '032' => 1], 'Combined/split events deduplicated per code');
check($v->protecciones(['estado' => 3]) === ['enviada'], 'Sent state');
check($v->protecciones(['estado' => 4, 'enviado' => 1]) === ['enviada', 'pagada'], 'Paid and sent');
check($v->protecciones(['estado' => 6]) === ['facturada'], 'Invoiced');
$headers = ['Número Documento', 'Razón Social Emisor', 'Sub Producto', 'Tipo Documento', 'Nit Emisor', 'Prefijo'];
$values = ['0001', 'Cliente', 'RM', '01', '900123456', 'FE'];
$recognized = $r->recognize([['Título'], $headers, $values], 'facturas');
check($recognized['filas'][0]['fila'] === 3 && $recognized['filas'][0]['valores']['numero'] === '0001', 'Reordered accented headers and preamble');
check($r->recognize([['irreconocible']], 'soporte') === null, 'Unknown headers');
$temp = sys_get_temp_dir().'/cadena-test-'.bin2hex(random_bytes(6));
mkdir($temp);
try {
    foreach ([';' => 'semicolon', ',' => 'comma', "\t" => 'tab'] as $delimiter => $name) {
        $path = $temp.'/'.$name.'.csv';
        $handle = fopen($path, 'wb');
        fputcsv($handle, $headers, $delimiter, '"', '');
        fputcsv($handle, $values, $delimiter, '"', '');
        fclose($handle);
        $read = $r->read($path, 'csv', 'facturas');
        check($read['hojas'][0]['filas'][0]['valores']['nit'] === '900123456', 'CSV delimiter '.$name);
    }
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $book->getActiveSheet()->setTitle('Notas')->setCellValue('A1', 'Sin datos de Cadena');
    $book->createSheet()->setTitle('ETV')->fromArray([['receiverNit', 'eventCode', 'transactionsCount'], ['900123456', '032', 2]]);
    foreach (['Xlsx' => 'xlsx', 'Xls' => 'xls'] as $format => $extension) {
        $path = $temp.'/events.'.$extension;
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, $format)->save($path);
        $read = $r->read($path, $extension, 'eventos');
        check($read['ignoradas'] === ['Notas'] && $read['hojas'][0]['hoja'] === 'ETV', 'Multiple sheets '.$format);
    }
    $book->disconnectWorksheets();
    $inflated = $temp.'/inflated.xlsx';
    copy($temp.'/events.xlsx', $inflated);
    $zip = new ZipArchive();
    $zip->open($inflated);
    $xml = $zip->getFromName('xl/worksheets/sheet2.xml');
    $xml = preg_replace('/<dimension[^>]*\/>/', '<dimension ref="A1:XFD50000"/>', $xml);
    $xml = preg_replace('/(<row\b[^>]*r="1"[^>]*>)/', '$1<c r="XFD1" t="inlineStr"><is><t>Columna16361</t></is></c>', $xml);
    $xml = str_replace('</sheetData>', '<row r="50000"><c r="XFD50000" s="0"/></row></sheetData>', $xml);
    $zip->addFromString('xl/worksheets/sheet2.xml', $xml);
    $zip->close();
    $read = $r->read($inflated, 'xlsx', 'eventos');
    check(count($read['hojas'][0]['filas']) === 1, 'Inflated XFD range and empty placeholder columns ignored');
    check($read['hojas'][0]['filas'][0]['fila'] === 2, 'Sparse row numbers preserved');
    $zip->open($inflated);
    $wide = '';
    for ($column = 4; $column <= 154; $column++) {
        $wide .= '<c r="'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column).'3"><v>1</v></c>';
    }
    $zip->addFromString('xl/worksheets/sheet2.xml', str_replace('<row r="50000">', '<row r="3">'.$wide.'</row><row r="50000">', $xml));
    $zip->close();
    try {
        $r->read($inflated, 'xlsx', 'eventos');
        check(false, 'Real wide content must be rejected');
    } catch (RuntimeException $exception) {
        check(str_contains($exception->getMessage(), '150 columnas'), 'Effective column limit enforced');
    }
    $zip->open($inflated);
    $zip->addFromString('xl/worksheets/sheet2.xml', '<worksheet><broken></worksheet>');
    $zip->close();
    try {
        $r->read($inflated, 'xlsx', 'eventos');
        check(false, 'Malformed XML must be rejected');
    } catch (RuntimeException $exception) {
        check(str_contains($exception->getMessage(), 'XML malformado'), 'Malformed XML rejected');
    }
    $compiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), $temp);
    foreach (['index', 'preview'] as $view) {
        $compiled = $compiler->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/importaciones/cadena/'.$view.'.blade.php'));
        $compiledPath = $temp.'/'.$view.'.php';
        file_put_contents($compiledPath, $compiled);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($compiledPath), $output, $status);
        check($status === 0, 'Compiled Blade syntax '.$view.': '.implode("\n", $output));
    }
} finally {
    foreach (glob($temp.'/*') as $file) {
        unlink($file);
    }
    rmdir($temp);
}
echo "OK: {$checks} comprobaciones aisladas, sin Laravel ni base de datos.\n";
