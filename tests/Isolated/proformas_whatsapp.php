<?php

// No arranca Laravel ni carga .env. Todas las operaciones persistentes son dobles.
require __DIR__.'/../../vendor/autoload.php';

use App\Services\ClienteProformaWhatsappService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

$container = new class extends Container {
    public function abort($code, $message = '', array $headers = []): never { throw new RuntimeException($message, $code); }
};
Container::setInstance($container);
Facade::setFacadeApplication($container);
$db = Mockery::mock();
DB::swap($db);
// Alias antes de cargar los modelos: imposible abrir una conexión real.
$clientes = Mockery::mock('alias:App\Models\ClientePotencial');
$config = Mockery::mock('alias:App\Models\ClienteProformaWhatsapp');
$validator = new Factory(new Translator(new ArrayLoader(), 'es'));
$container->instance('validator', $validator);
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$valid = ['grupo_fecha' => 7, 'telefono_fuente' => 'ALTERNATIVO', 'whatsapp_alternativo' => '+573001234567'];
check($validator->make($valid, ClienteProformaWhatsappService::reglas())->passes(), 'Alternativo válido');
foreach ([['grupo_fecha'=>10], ['grupo_fecha'=>20], ['telefono_fuente'=>'OTRO'], ['whatsapp_alternativo'=>null], ['whatsapp_alternativo'=>'3001234567'], ['whatsapp_alternativo'=>'+570000000000000000']] as $change) {
    check($validator->make(array_replace($valid, $change), ClienteProformaWhatsappService::reglas())->fails(), 'Debe rechazar datos inválidos');
}
check($validator->make(['grupo_fecha'=>27, 'telefono_fuente'=>'CELULAR2', 'whatsapp_alternativo'=>null], ClienteProformaWhatsappService::reglas())->passes(), 'Celular 2 sin alternativo');
check(ClienteProformaWhatsappService::normalizarAlternativo('+57 (300) 123-4567') === '+573001234567', 'Normaliza formato');
$cliente = (object) ['celular1'=>'3001234567', 'celular2'=>''];
check(ClienteProformaWhatsappService::telefono($cliente, 'CELULAR1', null)['whatsapp'] === '+573001234567', 'Prefijo colombiano');
check(!ClienteProformaWhatsappService::telefono($cliente, 'CELULAR2', null)['telefono_valido'], 'No sustituye celular vacío por el otro');
check(ClienteProformaWhatsappService::telefono($cliente, 'ALTERNATIVO', '+14155550123')['whatsapp'] === '+14155550123', 'Alternativo internacional');

$service = new ClienteProformaWhatsappService();
check($service->buscar('a') === [], 'Búsqueda corta sin consulta');
$db->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
$locked = Mockery::mock();
$clientes->shouldReceive('whereKey')->with(556)->once()->andReturn($locked);
$locked->shouldReceive('lockForUpdate')->once()->andReturnSelf();
$locked->shouldReceive('firstOrFail')->once()->andReturn((object) ['idclientes_potenciales'=>556]);
$existing = Mockery::mock();
$config->shouldReceive('whereKey')->with(556)->once()->andReturn($existing);
$existing->shouldReceive('exists')->once()->andReturn(true);
try { $service->agregar(556, $valid); throw new RuntimeException('No rechazó duplicado'); }
catch (RuntimeException $e) { check($e->getCode() === 409, 'Duplicado rechazado antes de guardar'); }
$item = Mockery::mock();
$clientes->shouldReceive('findOrFail')->with(556)->once()->andReturn($cliente);
$config->shouldReceive('findOrFail')->with(556)->times(2)->andReturn($item);
$item->shouldReceive('fill')->with($valid)->once()->andReturnSelf();
$item->shouldReceive('save')->once()->andReturn(true);
$item->shouldReceive('delete')->once()->andReturn(true);
$service->editar(556, $valid);
$service->eliminar(556);
try {
    $service->validarFuente($cliente, ['telefono_fuente'=>'CELULAR2']);
    throw new RuntimeException('Permitió guardar celular vacío');
} catch (Illuminate\Validation\ValidationException $error) {
    check(isset($error->errors()['telefono_fuente']), 'Error de fuente vacía');
}
$service->validarFuente($cliente, ['telefono_fuente'=>'CELULAR1']);
Mockery::close();
echo "OK: validaciones, teléfonos vacíos, duplicado, edición y eliminación; sin BD.\n";
