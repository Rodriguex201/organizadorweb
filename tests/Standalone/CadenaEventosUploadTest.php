<?php
// No Laravel bootstrap, configuration, PDO or database access.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaUploadService;
use Illuminate\Validation\Factory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$checks = 0;
$check = function ($ok) use (&$checks) { if (!$ok) { throw new RuntimeException('Falló comprobación '.($checks + 1)); } $checks++; };
$factory = new Factory(new Translator(new ArrayLoader(), 'es'));
$path = tempnam(sys_get_temp_dir(), 'cadena-eventos-');
try {
    foreach ([10, 11, 20, 21] as $mib) {
        $handle = fopen($path, 'wb'); ftruncate($handle, $mib * 1048576); fclose($handle); clearstatcache(true, $path);
        $upload = new UploadedFile($path, 'prueba.xlsx', null, null, true);
        foreach (CadenaUploadService::MAX_KIB as $category => $limit) {
            $validator = $factory->make(['archivo' => $upload], ['archivo' => ['file', 'max:'.$limit]]);
            $check($validator->passes() === ($mib <= ($category === 'eventos' ? 20 : 10)));
        }
    }
    $book = new Spreadsheet();
    $book->getActiveSheet()->setTitle('Otra hoja')->fromArray([['receiverNit', 'eventCode', 'transactionsCount'], ['900123456', '032', 99]]);
    $events = $book->createSheet()->setTitle('Eventos-FE_Facturacio');
    $events->fromArray([['receiverNit', 'eventCode', 'transactionsCount'], ['900123456', '032', 3]]);
    $book->createSheet()->setTitle('Soporte')->fromArray([['Nit Emisor', 'Razón Social Emisor', 'Tipo Documento'], ['900123456', 'CLIENTE TEST', '05']]);
    (new Xlsx($book))->save($path);
    $reader = new CadenaSpreadsheetReader();
    $read = $reader->read($path, 'xlsx', 'eventos');
    $check(array_column($read['hojas'], 'hoja') === ['Eventos-FE_Facturacio']);
    $check($read['hojas'][0]['filas'][0]['valores']['cantidad'] === '3');
    $check(in_array('Otra hoja', $read['ignoradas'], true));
    $support = $reader->read($path, 'xlsx', 'soporte');
    $check(array_column($support['hojas'], 'hoja') === ['Soporte']);
    $events->setTitle('Nombre sin alias');
    (new Xlsx($book))->save($path);
    $check(count($reader->read($path, 'xlsx', 'eventos')['hojas']) === 2);
    $events->setTitle('EVENTOS FE FACTURACIO');
    (new Xlsx($book))->save($path);
    $check(count($reader->read($path, 'xlsx', 'eventos')['hojas']) === 1);
    $book->disconnectWorksheets();
} finally { unlink($path); }
echo "OK: $checks comprobaciones de límites y selección de hojas, sin BD.\n";
