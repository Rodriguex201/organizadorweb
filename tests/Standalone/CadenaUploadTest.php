<?php
// Sin Laravel, .env, PDO ni consultas de BD.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$service = new CadenaUploadService();
$checks = 0;
$check = function (bool $condition) use (&$checks): void {
    if (!$condition) { throw new RuntimeException('Falló comprobación '.($checks + 1)); }
    $checks++;
};
$valid = new UploadedFile(__FILE__, 'primero.csv', 'text/csv', UPLOAD_ERR_OK, true);
foreach ([1 => 'INI_SIZE', 2 => 'FORM_SIZE', 3 => 'PARTIAL', 4 => 'NO_FILE', 6 => 'NO_TMP_DIR', 7 => 'CANT_WRITE', 8 => 'EXTENSION'] as $code => $symbol) {
    $invalid = new UploadedFile('', 'segundo.xlsx', null, $code, false);
    $issues = $service->inspect(['facturas' => [$valid, $invalid]], '128M');
    $check(array_keys($issues) === ['facturas.1']);
    $check($issues['facturas.1']['code'] === $code);
    $check($issues['facturas.1']['symbol'] === 'UPLOAD_ERR_'.$symbol);
    $check(str_contains($issues['facturas.1']['message'], 'segundo.xlsx'));
    $check(($code === 1) === str_contains($issues['facturas.1']['message'], 'upload_max_filesize=128M'));
}
$check($service->inspect(['facturas' => [$valid]], '128M') === []);
$notHttp = new UploadedFile(__FILE__, 'temporal.csv', 'text/csv', UPLOAD_ERR_OK, false);
$check($service->inspect(['soporte' => [$notHttp]], '128M')['soporte.0']['symbol'] === 'UPLOAD_ERR_OK');
$check($service->inspect(['eventos' => [new UploadedFile('', 'evento.csv', null, 7)]], '128M')['eventos.0']['code'] === 7);
$check($service->inspect(['facturas' => 'inválido'], '128M') === []);
echo "OK: {$checks} comprobaciones; sin Laravel ni BD.\n";
