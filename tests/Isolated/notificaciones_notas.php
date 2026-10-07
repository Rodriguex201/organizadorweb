<?php

// Sin Laravel, .env, SQL ni conexiones: los terminales del Query Builder son dobles.
require __DIR__.'/../../vendor/autoload.php';

use App\Services\ClienteNotaNotificacionesService;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

$container = new class extends Container {
    public function abort($code, $message = '', array $headers = []): never { throw new RuntimeException($message, $code); }
};
Container::setInstance($container);
Facade::setFacadeApplication($container);
$connection = new Connection(fn () => throw new LogicException('Conexión prohibida'));
function queryDouble(): Builder {
    global $connection;
    return Mockery::mock(Builder::class, [$connection, $connection->getQueryGrammar(), $connection->getPostProcessor()])->makePartial()->from('cliente_notas as n');
}
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function reject(callable $action, int $code): void {
    try { $action(); } catch (RuntimeException $error) { verify($error->getCode() === $code, 'Código inesperado'); return; }
    throw new RuntimeException('Falta rechazo');
}
$db = Mockery::mock();
DB::swap($db);
$query = queryDouble();
$db->shouldReceive('table')->with('cliente_notas as n')->once()->andReturn($query);
$query->shouldReceive('count')->once()->andReturn(21);
$query->shouldReceive('get')->once()->andReturn(collect([(object) [
    'id'=>30, 'tipo'=>'GENERAL', 'texto'=>str_repeat('Texto ', 80), 'created_at'=>'2026-10-07 10:00:00',
    'autor'=>'Usuario ejemplo', 'cliente'=>'Cliente ejemplo', 'empresa'=>'Empresa ejemplo',
]]));
$service = new ClienteNotaNotificacionesService;
$payload = $service->pendientes(7, 999);
verify($payload['pagina'] === 2 && $payload['paginas'] === 2 && $payload['no_leidas'] === 21, 'Paginación y contador');
verify(mb_strlen($payload['notificaciones'][0]['resumen']) <= 183, 'Resumen limitado');
verify($payload['notificaciones'][0]['autor'] === 'Usuario ejemplo', 'Autor');
verify($payload['notificaciones'][0]['cliente'] === 'Empresa ejemplo', 'Empresa');
verify(in_array(['type'=>'Null','column'=>'n.deleted_at','boolean'=>'and'], $query->wheres), 'Excluye eliminadas');
verify(in_array(['type'=>'NotNull','column'=>'n.creado_por','boolean'=>'and'], $query->wheres), 'Excluye antiguas sin autor');
verify(in_array(['type'=>'Basic','column'=>'n.creado_por','operator'=>'<>','value'=>7,'boolean'=>'and'], $query->wheres), 'Excluye propias');
verify(in_array(['type'=>'Null','column'=>'l.nota_id','boolean'=>'and'], $query->wheres), 'Solo no leídas');
verify($query->joins[0]->wheres[1]['value'] === 7, 'Lecturas aisladas por usuario');
verify($query->orders[0]['column'] === 'n.created_at', 'Orden por creación, no por actualización');

$read = Mockery::mock(Builder::class);
$db->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
foreach ([7, 8] as $user) {
    $eligible = queryDouble();
    $db->shouldReceive('table')->with('cliente_notas as n')->once()->andReturn($eligible);
    $eligible->shouldReceive('first')->once()->andReturn((object) ['id'=>30]);
    $db->shouldReceive('table')->with('cliente_nota_lecturas')->once()->andReturn($read);
    $read->shouldReceive('insertOrIgnore')->once()->with(Mockery::on(fn ($data) => $data['nota_id'] === 30 && $data['usuario_id'] === $user && isset($data['leida_en'])))->andReturn(1);
    $service->marcarLeida($user, '30');
}
$missing = queryDouble();
$db->shouldReceive('table')->with('cliente_notas as n')->once()->andReturn($missing);
$missing->shouldReceive('first')->once()->andReturn(null);
reject(fn () => $service->marcarLeida(7, '31'), 404);
reject(fn () => $service->pendientes(0), 401);
Mockery::close();
echo "OK: filtros, autor, resumen, paginación, aislamiento de lecturas y rechazo; sin BD.\n";
