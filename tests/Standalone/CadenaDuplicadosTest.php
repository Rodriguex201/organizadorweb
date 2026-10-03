<?php
// Pure services only: no Laravel bootstrap, database or endpoints.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaValidacionService;
use App\Services\Cadena\CadenaGeneracionService;

$reader = new CadenaSpreadsheetReader();
$summary = new CadenaResumenService(new CadenaValidacionService());
$generation = new CadenaGeneracionService();
$checks = 0;
$check = function ($ok) use (&$checks) { if (!$ok) throw new RuntimeException('Falló comprobación '.($checks + 1)); $checks++; };
foreach (['facturas'=>'nota_debito_92.csv', 'soporte'=>'nota_ajuste_95.csv', 'eventos'=>'eventos_cinco_codigos.csv'] as $category=>$fixture) {
    $path = dirname(__DIR__).'/fixtures/cadena/'.$fixture;
    $file = ['categoria'=>$category, 'archivo'=>$fixture, 'hash'=>hash_file('sha256', $path)] + $reader->read($path, 'csv', $category);
    $copyPath = tempnam(sys_get_temp_dir(), 'cadena-duplicate-');
    try {
        copy($path, $copyPath);
        $copy = array_replace($file, ['archivo'=>'copia.csv', 'hash'=>hash_file('sha256', $copyPath)]);
        $single = $summary->build([$file], [], []);
        $both = $summary->build([$file, $copy], [], []);
        $check($both['archivos'][1]['estado'] === 'Duplicado excluido');
        $check($both['clientes'] === $single['clientes']);
        $warnings = $generation->duplicateWarnings($both);
        $check(count($warnings) === 1 && str_contains($warnings[0], '"copia.csv"') && str_contains($warnings[0], 'fue omitido'));
        $other = array_replace($copy, ['categoria'=>$category === 'facturas' ? 'soporte' : 'facturas']);
        $cross = $summary->build([$file, $other], [], []);
        $check($cross['archivos'][1]['estado'] === 'Leído');
        $check($generation->duplicateWarnings($cross) === []);
    } finally { unlink($copyPath); }
}
echo "OK: $checks comprobaciones SHA-256 por categoría y avisos; sin BD.\n";
