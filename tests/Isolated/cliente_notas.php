<?php

// Ejecutar con `php tests/Isolated/cliente_notas.php`.
// No arranca Laravel, no carga .env y sustituye DB antes de cargar el servicio.
require __DIR__.'/../../vendor/autoload.php';

use App\Services\ClienteNotasService;
use App\Services\ProformasService;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

$container = new class extends Container
{
    public function abort($code, $message = '', array $headers = []): never
    {
        throw new RuntimeException($message, $code);
    }
};
Container::setInstance($container);
Facade::setFacadeApplication($container);
DB::swap(new class
{
    public function transaction(callable $callback): mixed { return $callback(); }
    public function __call($name, $arguments): never
    {
        throw new LogicException('Acceso a BD prohibido en esta prueba: '.$name);
    }
});
Crypt::swap(new Encrypter(str_repeat('x', 32), 'AES-256-CBC'));

// Dobles de persistencia: no se carga ningún modelo Eloquent ni conexión.
$model = Mockery::mock('alias:App\Models\ClienteNota');
$clients = Mockery::mock('alias:App\Models\ClientePotencial');
$client = new App\Models\ClientePotencial;
$clients->shouldReceive('findOrFail')->with(7)->andReturn($client);
$resolver = Mockery::mock(ProformasService::class);
$container->instance(ProformasService::class, $resolver);
$service = new ClienteNotasService;
$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function rejects(callable $callback, int $code): void
{
    try { $callback(); } catch (RuntimeException $exception) {
        check($exception->getCode() === $code, 'Código de rechazo incorrecto');
        return;
    }
    throw new RuntimeException('Se esperaba rechazo');
}

$context = ClienteNotasService::contexto('proforma', 99, 7);
$resolver->shouldReceive('clientesParaNotas')->with(99)->andReturn([7], [7, 8], [], [8]);
check($service->verificarContexto(7, $context) === $client, 'Cliente inequívoco');
rejects(fn () => $service->verificarContexto(7, $context), 409);
rejects(fn () => $service->verificarContexto(7, $context), 409);
rejects(fn () => $service->verificarContexto(7, $context), 409);
rejects(fn () => $service->verificarContexto(8, $context), 422);
rejects(fn () => $service->verificarContexto(7, 'manipulado'), 422);
rejects(fn () => $service->verificarContexto(7, null), 422);

$note = $model;
$note->tipo = 'CHECKLIST';
$note->completada = false;
$note->completada_en = null;
$note->created_at = '2026-10-01 08:00:00';
$note->shouldReceive('save')->times(2)->andReturn(true);
$query = Mockery::mock();
$model->shouldReceive('where')->with('cliente_id', 7)->andReturn($query);
$query->shouldReceive('whereKey')->with('12')->andReturnSelf();
$query->shouldReceive('lockForUpdate')->andReturnSelf();
$query->shouldReceive('firstOrFail')->andReturn($note);

$service->completar(7, '12', true);
$completedAt = $note->completada_en;
check($note->completada && $completedAt !== null, 'Completar registra fecha');
$service->completar(7, '12', true);
check($note->completada_en === $completedAt, 'Repetir completado conserva fecha');
$service->completar(7, '12', false);
check(!$note->completada && $note->completada_en === null, 'Reabrir limpia fecha');
check($note->created_at === '2026-10-01 08:00:00', 'Conserva creación');
$note->tipo = 'GENERAL';
rejects(fn () => $service->completar(7, '12', true), 422);

$foreignQuery = Mockery::mock();
$model->shouldReceive('where')->with('cliente_id', 8)->andReturn($foreignQuery);
$foreignQuery->shouldReceive('whereKey')->with('12')->andReturnSelf();
$foreignQuery->shouldReceive('lockForUpdate')->andReturnSelf();
$foreignQuery->shouldReceive('firstOrFail')->andThrow(new RuntimeException('No pertenece al cliente', 404));
rejects(fn () => $service->eliminar(8, '12'), 404);
$note->shouldReceive('delete')->once()->andReturn(true);
$service->eliminar(7, '12');

Mockery::close();
echo "OK: {$checks} comprobaciones aisladas, sin base de datos.\n";
