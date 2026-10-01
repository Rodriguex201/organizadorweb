<?php
// No bootstrap, PDO, database or application endpoints.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use App\Services\Cadena\CadenaGeneracionService;

$checks = 0;
$check = function ($ok) use (&$checks) { if (!$ok) throw new RuntimeException('Falló comprobación '.($checks + 1)); $checks++; };
$service = new CadenaGeneracionService();
$preview = ['consulta_disponible' => true, 'archivos' => [], 'auditoria' => [], 'clientes' => [], 'descargas' => ['paquete_errores' => []]];
$exports = ['tables' => [], 'omitidos' => [], 'errores' => []];
foreach (CadenaGeneracionService::FILES as $category => $file) { $exports['tables'][$file] = ['rows' => [['datos']]]; }
foreach (['facturas', 'soporte', 'eventos', 'paquete'] as $action) { $check($service->issues($preview, $exports, $action) === []); }
$bad = $preview; $bad['consulta_disponible'] = false;
$check($service->issues($bad, $exports, 'facturas') !== []);
$bad = $preview; $bad['clientes'][] = ['cliente_id' => null, 'dv_valido' => true, 'nit_base' => '900123456', 'dv' => '8', 'estado' => 'Ambiguo'];
$check($service->issues($bad, $exports, 'eventos') === []);
$check($service->pendingCount($bad) === 1);
$bad['clientes'][0]['estado'] = 'Sin coincidencia';
$check($service->issues($bad, $exports, 'soporte') === []);
$check($service->issues($bad, $exports, 'paquete') === []);
$bad['clientes'][0]['cliente_id'] = 1; $bad['clientes'][0]['dv_valido'] = false;
$check($service->issues($bad, $exports, 'soporte') !== []);
$bad = $preview; $bad['auditoria'][] = ['estado' => 'Error', 'categoria' => 'soporte', 'archivo' => 'a.xlsx', 'hoja' => 'Hoja1', 'fila' => 2, 'detalle' => 'Identidad contradictoria'];
$check(str_contains(implode(' ', $service->issues($bad, $exports, 'soporte')), 'fila 2'));
$bad['auditoria'][0]['estado'] = 'Duplicado';
$check($service->issues($bad, $exports, 'soporte') === []);
$bad['auditoria'][0]['estado'] = 'Excluida';
$check($service->issues($bad, $exports, 'soporte') === []);
$badExports = $exports; unset($badExports['tables']['ResumenEventos.xlsx']);
$check($service->issues($preview, $badExports, 'paquete') !== []);
$check($service->issues($preview, $badExports, 'facturas') === []);
$badExports = $exports; $badExports['tables']['ResumenEventos.xlsx']['rows'] = [];
$check($service->issues($preview, $badExports, 'eventos') !== []);
$badExports = $exports; $badExports['omitidos'][] = ['soporte', 'NIT', 'Pendiente'];
$check($service->issues($preview, $badExports, 'soporte') === []);
$bad = $preview;
$bad['clientes'][] = ['cliente_id' => null, 'dv_valido' => false, 'nit_base' => '900123456', 'dv' => '0', 'estado' => 'Pendiente', 'cantidades' => ['05' => 2, '95' => 1]];
$check($service->issues($bad, $badExports, 'soporte') === []);
$check($service->issues($bad, $badExports, 'paquete') === []);
$bad['clientes'][0]['cantidades']['032'] = 1;
$check($service->issues($bad, $badExports, 'paquete') !== []);
$bad['clientes'][0]['cantidades'] = ['01' => 1];
$check($service->issues($bad, $badExports, 'facturas') !== []);
$check($service->issues($preview, null, 'paquete') !== []);
echo "OK: $checks comprobaciones de bloqueo de generación directa, sin BD.\n";
