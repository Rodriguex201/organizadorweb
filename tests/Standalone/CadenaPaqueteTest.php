<?php
// Standalone: no Laravel bootstrap, .env, PDO or database.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaDescargaService;
use App\Services\Cadena\CadenaExportService;
use App\Services\Cadena\CadenaPaqueteService;
use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaValidacionService;
use Illuminate\Encryption\Encrypter;
use PhpOffice\PhpSpreadsheet\IOFactory;

$checks = 0;
$check = function ($ok, $message) use (&$checks) { if (!$ok) throw new RuntimeException($message); $checks++; };
$reject = function ($fn, $message) use ($check) {
    try { $fn(); } catch (RuntimeException $e) { $check(str_contains($e->getMessage(), $message), $e->getMessage()); return; }
    throw new RuntimeException('Se esperaba rechazo: '.$message);
};
$reader = new CadenaSpreadsheetReader();
$validation = new CadenaValidacionService();
$exporter = new CadenaExportService();
$files = [];
foreach (['facturas' => 'nota_debito_92.csv', 'soporte' => 'nota_ajuste_95.csv', 'eventos' => 'eventos_cinco_codigos.csv'] as $category => $file) {
    $path = dirname(__DIR__).'/fixtures/cadena/'.$file;
    $files[] = ['categoria' => $category, 'archivo' => $file, 'hash' => hash_file('sha256', $path)] + $reader->read($path, 'csv', $category);
}
$preview = (new CadenaResumenService($validation))->build($files, [$validation->nit('900123456') + ['cliente_id' => 1, 'nombre' => 'Prueba']], []);
$exports = $exporter->prepare($preview, $files);
$cipher = new Encrypter(random_bytes(32), 'AES-256-CBC');
$downloads = new CadenaDescargaService();
$package = new CadenaPaqueteService();
$prepared = $downloads->prepare($exports, 'septiembre', 2026, 'session', $cipher);
$check($prepared['paquete_errores'] === [], 'Tres categorías habilitan ZIP');
$tokens = array_intersect_key($prepared['tokens'], CadenaPaqueteService::FILES);
$make = fn ($t, $e = null) => $package->create($t, 'septiembre', 2026, 'session', $cipher, $e ?? $exporter);
$path = $make($tokens);
$zip = new ZipArchive();
$tmp = null;
try {
    $check($zip->open($path, ZipArchive::CHECKCONS) === true, 'ZIP válido');
    $check($zip->numFiles === 3, 'Solo tres comerciales, sin alias duplicado');
    foreach (CadenaPaqueteService::FILES as $name => $category) {
        $bytes = $zip->getFromName($name);
        $check(is_string($bytes) && strlen($bytes) > 0, 'XLSX presente: '.$name);
        $tmp = tempnam(sys_get_temp_dir(), 'cadena-check-');
        file_put_contents($tmp, $bytes);
        $book = IOFactory::load($tmp);
        $rows = $book->getSheet(0)->toArray(null, false, false, false);
        $book->disconnectWorksheets();
        $check($rows === array_merge([$exports['tables'][$name]['headers']], $exports['tables'][$name]['rows']), 'Igualdad exacta con tabla individual: '.$name);
        unlink($tmp); $tmp = null;
    }
} finally { $zip->close(); unlink($path); if ($tmp !== null) unlink($tmp); }
$missing = $tokens; unset($missing['ResumenEventos.xlsx']);
$reject(fn () => $make($missing), 'Eventos');
$second = $downloads->prepare($exports, 'septiembre', 2026, 'session', $cipher);
$mixed = $tokens; $mixed['ResumenEventos.xlsx'] = $second['tokens']['ResumenEventos.xlsx'];
$reject(fn () => $make($mixed), 'distintas vistas previas');
$reject(fn () => $package->create($tokens, 'octubre', 2026, 'session', $cipher, $exporter), 'Facturas/notas');
$bad = $exports; $bad['paquete_errores'] = ['Documento soporte: original ilegible'];
$badTokens = $downloads->prepare($bad, 'septiembre', 2026, 'session', $cipher);
$check(isset($badTokens['tokens']['ResumenEventos.xlsx']), 'Fallo no elimina descargas individuales');
$reject(fn () => $make($badTokens['tokens']), 'Documento soporte: original ilegible');
$before = glob(sys_get_temp_dir().'/cadena-part-*');
$beforeZip = glob(sys_get_temp_dir().'/cadena-package-*');
$failing = new class extends CadenaExportService {
    private int $calls = 0;
    public function write(array $table, string $period, string $destination): void {
        if (++$this->calls === 2) throw new RuntimeException('Fallo simulado');
        parent::write($table, $period, $destination);
    }
};
$reject(fn () => $make($tokens, $failing), 'Documento soporte: Fallo simulado');
$check(glob(sys_get_temp_dir().'/cadena-part-*') === $before && glob(sys_get_temp_dir().'/cadena-package-*') === $beforeZip, 'Fallo elimina temporales y no entrega ZIP parcial');
echo "OK: $checks comprobaciones ZIP, sin BD.\n";
