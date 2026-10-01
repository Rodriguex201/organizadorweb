<?php
// Pure tests: vendor only, no Laravel bootstrap, .env, PDO, database or application endpoint.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaExportService;
use App\Services\Cadena\CadenaDescargaService;
use App\Services\ImportacionesService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\UploadedFile;

$checks = 0;
$check = function ($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException($message); } $checks++; };
$reject = function ($fn) use ($check) { try { $fn(); } catch (\Throwable) { $check(true, 'Token rechazado'); return; } $check(false, 'Token debió rechazarse'); };
$exporter = new CadenaExportService();
$downloads = new CadenaDescargaService();
$files = [
    ['categoria' => 'facturas', 'hash' => 'f1', 'archivo' => 'facturas1.xlsx', 'hojas' => []],
    ['categoria' => 'facturas', 'hash' => 'f2', 'archivo' => 'facturas2.xlsx', 'hojas' => []],
    ['categoria' => 'soporte', 'hash' => 'shared', 'archivo' => 'REPORTES.xlsx', 'hojas' => [
        ['hoja' => 'Sheet', 'filas' => [['fila' => 2, 'valores' => ['nit' => '900123456', 'nombre' => 'SOPORTE', 'tipo' => '05']]]]]],
    ['categoria' => 'eventos', 'hash' => 'shared', 'archivo' => 'REPORTES.xlsx', 'hojas' => [
        ['hoja' => 'Sheet', 'filas' => [['fila' => 2, 'valores' => ['nit' => '900123456', 'nombre' => 'EVENTO', 'tipo' => '032']]]]]],
];
$preview = ['archivos' => array_map(fn ($f) => $f + ['estado' => 'Leído'], $files),
    'clientes' => [['nit_base' => '900123456', 'dv' => '8', 'dv_valido' => true, 'cliente_id' => 1, 'cliente' => 'SAS', 'candidatos' => [1], 'estado' => 'Reconocido',
        'cantidades' => ['01' => 12, '91' => 1, '92' => 0, '05' => 2, '95' => 0, '032' => 3, '030' => 4, '031' => 5, '033' => 6, '034' => 7]]], 'auditoria' => []];
for ($i = 0; $i < 72074; $i++) {
    $preview['auditoria'][] = ['categoria' => $i === 0 ? 'soporte' : 'eventos', 'hash' => 'shared', 'archivo' => 'REPORTES.xlsx',
        'hoja' => 'Sheet', 'fila' => $i === 1 ? 2 : $i + 2, 'estado' => 'Contada', 'detalle' => 'registro-'.$i];
}
$exports = $exporter->prepare($preview, $files);
$check($exports['errores'] === [], '72074 filas ya no bloquean descargas');
$check(count($exports['tables']['AuditoriaCadena_001.xlsx']['rows']) === 40000, 'Primera parte 40000');
$check(count($exports['tables']['AuditoriaCadena_002.xlsx']['rows']) === 32074, 'Segunda parte 32074');
$joined = array_merge($exports['tables']['AuditoriaCadena_001.xlsx']['rows'], $exports['tables']['AuditoriaCadena_002.xlsx']['rows']);
$check(array_column($joined, 4) === array_column($preview['auditoria'], 'detalle'), 'Mismo orden y registros sin pérdida ni duplicación');
unset($joined);
$support = $exports['tables']['AuditoriaDocumentoSoporte.xlsx']['rows'];
$check(count($support) === 1 && $support[0][3] === 'SOPORTE' && $support[0][6] === '05', 'Categoría separada aun con mismo nombre/hash/hoja/fila');
$check($exports['tables']['Resumen.xlsx'] === $exports['tables']['ResumenCombinado.xlsx'], 'Combinado no altera cantidades');
$cipher = new Encrypter(random_bytes(32), 'AES-256-CBC');
$prepared = $downloads->prepare($exports, 'septiembre', 2026, 'test-session', $cipher);
$check(count($prepared['tokens']) === 7 && $prepared['errores'] === [], 'Cuatro comerciales y tres auditorías, tokens independientes');
$check(count(array_unique($prepared['tokens'])) === 7, 'No se comparte un token entre archivos');
$legacy = (new ReflectionClass(ImportacionesService::class))->newInstanceWithoutConstructor();
$reader = new ReflectionMethod($legacy, 'readSpreadsheetFallback');
$paths = [];
try {
    foreach ($prepared['tokens'] as $name => $token) {
        $payload = $downloads->decode($token, $name, 'septiembre', 2026, 'test-session', $cipher);
        $check($payload['table'] === $exports['tables'][$name], 'Token contiene solo tabla exacta de '.$name);
        $path = dirname(__DIR__).'/fixtures/cadena/download-'.bin2hex(random_bytes(6)).'.xlsx';
        $paths[] = $path;
        $exporter->write($payload['table'], 'Septiembre 2026', $path);
        $upload = new UploadedFile($path, $name, null, null, true);
        $rows = $reader->invoke($legacy, $upload);
        $check(count($rows) === count($payload['table']['rows']) + 1 && $rows[0] === $payload['table']['headers'], 'XLSX reabre completo con lector real de Importaciones: '.$name);
        unset($rows);
        if (!str_starts_with($name, 'Auditoria')) {
            $input = match ($name) { 'ResumenDocumentoSoporte.xlsx' => 'soporte_file', 'ResumenEventos.xlsx' => 'recepcion_file', default => 'facturas_file' };
            $batch = $legacy->buildBatchFromUploads([$input => $upload], 'septiembre', 2026);
            $check(!$batch['blocked'] && $batch['errors'] === [] && count($batch['entries']) === 1, 'Resumen aceptado por parser comercial: '.$name);
        }
    }
} finally { foreach ($paths as $path) { if (is_file($path)) { unlink($path); } } }
$token = $prepared['tokens']['Resumen.xlsx'];
$reject(fn () => $downloads->decode($token, 'ResumenCombinado.xlsx', 'septiembre', 2026, 'test-session', $cipher));
$reject(fn () => $downloads->decode($token, 'Resumen.xlsx', 'octubre', 2026, 'test-session', $cipher));
$reject(fn () => $downloads->decode($token, 'Resumen.xlsx', 'septiembre', 2026, 'other-session', $cipher));
$reject(fn () => $downloads->decode($token.'x', 'Resumen.xlsx', 'septiembre', 2026, 'test-session', $cipher));
$payload = $downloads->decode($token, 'Resumen.xlsx', 'septiembre', 2026, 'test-session', $cipher);
$payload['expires'] = time() - 1;
$expired = $cipher->encryptString(base64_encode(gzencode(json_encode($payload))));
$reject(fn () => $downloads->decode($expired, 'Resumen.xlsx', 'septiembre', 2026, 'test-session', $cipher));
// Deliberate failure of just one part; the second part and commercial files survive.
$preview['auditoria'][0]['detalle'] = str_repeat('x', 32768);
$broken = $exporter->prepare($preview, $files);
$check(isset($broken['errores']['AuditoriaCadena_001.xlsx']) && isset($broken['tables']['AuditoriaCadena_002.xlsx']), 'Error aislado por parte');
foreach (['Resumen.xlsx', 'ResumenCombinado.xlsx', 'ResumenDocumentoSoporte.xlsx', 'ResumenEventos.xlsx'] as $name) {
    $check($broken['tables'][$name] === $exports['tables'][$name], 'Fallo de auditoría no cambia '.$name);
}
$check(str_contains($broken['errores']['AuditoriaCadena_001.xlsx'], '40000 filas') && str_contains($broken['errores']['AuditoriaCadena_001.xlsx'], '32.767'), 'Error indica archivo, filas y límite');
$badEncoding = $exports;
$badEncoding['tables']['AuditoriaDocumentoSoporte.xlsx']['rows'][0][0] = "\xB1";
$tokens = $downloads->prepare($badEncoding, 'septiembre', 2026, 'test-session', $cipher);
$check(count($tokens['tokens']) === 6 && isset($tokens['errores']['AuditoriaDocumentoSoporte.xlsx']), 'Fallo de codificación no impide otras descargas');
foreach ([40000 => [40000], 40001 => [40000, 1], 80000 => [40000, 40000], 80001 => [40000, 40000, 1]] as $size => $expected) {
    $boundary = $preview;
    $boundary['auditoria'] = array_fill(0, $size, ['categoria' => 'eventos', 'hash' => 'shared', 'archivo' => 'REPORTES.xlsx',
        'hoja' => 'Sheet', 'fila' => 2, 'estado' => 'Contada', 'detalle' => 'prueba límite']);
    $parts = $exporter->prepare($boundary, $files);
    $lengths = [];
    foreach ($parts['tables'] as $name => $table) {
        if (str_starts_with($name, 'AuditoriaCadena')) { $lengths[] = count($table['rows']); }
    }
    $check($lengths === $expected && $parts['errores'] === [], 'Límite exacto sin parte vacía: '.$size);
    unset($boundary, $parts);
}
echo "OK: {$checks} comprobaciones de descargas; sin BD ni bootstrap.\n";
