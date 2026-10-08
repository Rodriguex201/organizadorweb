<?php
// Sin bootstrap de Laravel. Consultas simuladas; archivos y ZIP reales en directorio temporal propio.
require __DIR__.'/../../vendor/autoload.php';

use App\Services\ComprobantesZipService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function checkZip(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$app = new Container(); Container::setInstance($app); Facade::setFacadeApplication($app);
$service = new ComprobantesZipService();
$connection = new Illuminate\Database\Connection(fn () => throw new LogicException('Acceso a BD prohibido'));
$query = Mockery::mock(Illuminate\Database\Query\Builder::class, [$connection])->makePartial();
$query->from('sg_proform');
$rows = [(object) ['id'=>1,'nro_prof'=>100,'estado'=>4,'comprobante_pago'=>'a/estandar.pdf'],
    (object) ['id'=>2,'nro_prof'=>101,'estado'=>5,'comprobante_pago'=>'b/estandar.pdf'],
    (object) ['id'=>3,'nro_prof'=>102,'estado'=>4,'comprobante_pago'=>'a/319d6a62-e601-4622-a6f3-330f9d92f0a6.jpg']];
$query->shouldReceive('cursor')->once()->andReturn($rows);
$db = Mockery::mock(); DB::swap($db);
$db->shouldReceive('table')->with('sg_proform')->once()->andReturn($query);
$selected = $service->registros(10, 2026);
checkZip($query->getBindings() === [10,2026], 'Período exacto');
checkZip(!str_contains($query->toSql(), 'estado') && !str_contains($query->toSql(), 'fpag'), 'Incluye Pagada y Facturada por período de proforma');
checkZip(str_contains($query->toSql(), 'TRIM(comprobante_pago)'), 'Excluye rutas vacías');
Mockery::close();

$root = sys_get_temp_dir().'/comprobantes-zip-test-'.bin2hex(random_bytes(6));
mkdir($root); mkdir($root.'/a'); mkdir($root.'/b'); mkdir($root.'/tmp');
file_put_contents($root.'/a/estandar.pdf', 'pago');
file_put_contents($root.'/b/estandar.pdf', 'facturada');
file_put_contents($root.'/a/319d6a62-e601-4622-a6f3-330f9d92f0a6.jpg', 'antiguo');
$missing = (object) ['id'=>4,'nro_prof'=>103,'comprobante_pago'=>'ausente.pdf'];
try {
    $result = $service->generar([...$selected, $missing], $root, $root.'/tmp');
    checkZip($result['incluidos'] === 3 && $result['faltantes'] === 1, 'Cuenta incluidos y faltantes');
    $zip = new ZipArchive(); checkZip($zip->open($result['path']) === true, 'ZIP válido');
    checkZip($zip->getFromName('estandar.pdf') === 'pago', 'Comprobante pagado');
    checkZip($zip->getFromName('estandar_PROFORMA_2.pdf') === 'facturada', 'Duplicado de Facturada sin sobrescritura');
    checkZip($zip->getFromName('319d6a62-e601-4622-a6f3-330f9d92f0a6.jpg') === 'antiguo', 'UUID conservado');
    checkZip(str_contains($zip->getFromName('incidencias.txt'), 'ID 4 / número 103: ausente.pdf'), 'Incidencia trazable');
    $zip->close();
    $response = new BinaryFileResponse($result['path']);
    $response->deleteFileAfterSend(true);
    ob_start(); $response->sendContent(); ob_end_clean();
    checkZip(!is_file($result['path']), 'Temporal eliminado después de enviar');
    foreach ([[], [$missing]] as $emptyRows) {
        try { $service->generar($emptyRows, $root, $root.'/tmp'); throw new LogicException('Debió rechazar ZIP vacío'); }
        catch (RuntimeException $error) { checkZip(str_contains($error->getMessage(), $emptyRows ? 'ninguno' : 'No hay'), 'Mensaje vacío/todos faltantes'); }
        checkZip(glob($root.'/tmp/*') === [], 'No crea ZIP vacío');
    }
    // Fuerza un fallo de generación con un doble de ZipArchive; ejecuta el mismo servicio.
    $source = file_get_contents(__DIR__.'/../../app/Services/ComprobantesZipService.php');
    $source = str_replace(['namespace App\\Services;', 'use ZipArchive;', 'class ComprobantesZipService'],
        ['namespace ZipFailureTest;', 'use ZipFailureTest\\FailingZip as ZipArchive;', 'class FailingService'], $source);
    eval('namespace ZipFailureTest; class FailingZip { const OVERWRITE=8; public function open($path,$flags){return true;} public function addFile($path,$name){return false;} public function close(){return true;} }');
    eval(substr($source, 5));
    try { (new ZipFailureTest\FailingService())->generar($rows, $root, $root.'/tmp'); throw new LogicException('Debió fallar'); }
    catch (RuntimeException $error) { checkZip(str_contains($error->getMessage(), 'incluir'), 'Fallo de generación controlado'); }
    checkZip(glob($root.'/tmp/*') === [], 'Temporal eliminado al fallar generación');
    echo "OK: período, Pagada/Facturada, UUID, faltantes, todos faltantes, duplicados, vacío y limpieza tras descarga/fallo; sin BD.\n";
} finally {
    // Solo elimina archivos del directorio aleatorio creado por esta prueba.
    foreach (['a','b','tmp'] as $folder) {
        foreach (glob($root.'/'.$folder.'/*') as $file) unlink($file);
        rmdir($root.'/'.$folder);
    }
    rmdir($root);
}
