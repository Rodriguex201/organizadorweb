<?php

// Pure fixture checks: no Laravel bootstrap, .env, PDO, PHPUnit or database.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\Cadena\CadenaValidacionService;
use App\Services\ClienteValorTotalCalculator;
use App\Services\RevisarProformaCalculator;

$checks = 0;
$check = function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FALLO: '.$label);
    }
    $checks++;
};
$reader = new CadenaSpreadsheetReader();
$validation = new CadenaValidacionService();
$summary = new CadenaResumenService($validation);
$client = $validation->nit('900123456') + ['cliente_id' => 1, 'nombre' => 'CLIENTE SINTETICO SOLO TESTS'];
$read = function (string $name, string $category) use ($reader): array {
    $path = dirname(__DIR__).'/fixtures/cadena/'.$name;

    return ['archivo' => $name, 'categoria' => $category, 'hash' => hash_file('sha256', $path)]
        + $reader->read($path, 'csv', $category);
};
$build = fn (array $file) => $summary->build([$file], [$client], []);

$debit = $read('nota_debito_92.csv', 'facturas');
$check(count($debit['hojas'][0]['filas']) === 3, '92: lector reconoce tres filas reales del fixture');
$one = $debit;
$one['hojas'][0]['filas'] = array_slice($one['hojas'][0]['filas'], 0, 1);
$result = $build($one);
$check($result['clientes'][0]['cantidades'] === [92 => 1], 'Una nota incrementa solo numero_nota_debito (92), nunca 01/91');
$two = $debit;
$two['hojas'][0]['filas'] = array_slice($two['hojas'][0]['filas'], 0, 2);
$check($build($two)['clientes'][0]['cantidades'] === [92 => 2], 'Dos identidades diferentes cuentan dos notas débito');
$result = $build($debit);
$check($result['clientes'][0]['cantidades'] === [92 => 2], '92 repetido no incrementa el contador');
$check(count(array_filter($result['auditoria'], fn ($row) => $row['estado'] === 'Duplicado')) === 1, '92 repetido visible en auditoría');

$adjustment = $build($read('nota_ajuste_95.csv', 'soporte'));
$check($adjustment['clientes'][0]['cantidades'] === [95 => 1], '95 incrementa solo numero_nota_ajuste, no numero_documento_soporte');
$check(str_contains($adjustment['auditoria'][0]['detalle'], '95: 1'), '95 conservado en auditoría');
$calculator = new RevisarProformaCalculator(new ClienteValorTotalCalculator());
$base = $calculator->calculate(['soporte' => 2, 'precio_soporte' => 100]);
$withAdjustment = $calculator->calculate(['soporte' => 2, 'nota_ajuste' => $adjustment['clientes'][0]['cantidades'][95], 'precio_soporte' => 100]);
$onlyAdjustment = $calculator->calculate(['nota_ajuste' => 1, 'precio_soporte' => 100]);
$check($base['valor_documentos'] === 200.0 && $withAdjustment['valor_documentos'] === 200.0, 'La fórmula real permanece numero_documento_soporte × precio_soporte');
$check($onlyAdjustment['valor_documentos'] === 0.0, '95 solo no genera cargo de soporte');

$tacit = $build($read('aceptacion_tacita_034.csv', 'eventos'));
$check(CadenaValidacionService::EVENTOS['034'] === 'Aceptación tácita', 'Etiqueta 034 correcta');
$check($tacit['clientes'][0]['cantidades'] === ['034' => 7], '034 conserva siete y no incrementa 032 ni otros códigos');
$check(str_contains($tacit['auditoria'][0]['detalle'], '034: 7'), '034 visible en auditoría');
$check(!isset($tacit['clientes'][0]['cantidades']['032']), '034 no altera contador comercial heredado 032; ausente sigue CONSERVAR');

$five = $build($read('eventos_cinco_codigos.csv', 'eventos'));
$expected = ['030' => 1, '031' => 2, '032' => 3, '033' => 4, '034' => 5];
$check($five['clientes'][0]['cantidades'] === $expected, 'Cinco cantidades en sus categorías exactas');
$check(array_keys(CadenaValidacionService::EVENTOS) === array_keys($expected), 'Catálogo conserva los cinco códigos');
foreach ($expected as $code => $quantity) {
    $check(count(array_filter($five['auditoria'], fn ($row) => str_contains($row['detalle'], $code.': '.$quantity))) === 1, 'Auditoría individual de '.$code);
}
$check($five['clientes'][0]['cantidades']['032'] === 3, 'Contador comercial heredado es tres, no la suma quince');
echo "OK: {$checks} comprobaciones con fixtures sintéticos, sin Laravel ni base de datos.\n";
