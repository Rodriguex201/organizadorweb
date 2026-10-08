<?php
// No arranca Laravel: DB, logs y sesión son dobles; no hay conexión real.
require __DIR__.'/../../vendor/autoload.php';
use App\Services\EmpresaActivacionService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
function verifyEvents($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$app = new Container(); Container::setInstance($app); Facade::setFacadeApplication($app);
class EventsOnlyService extends EmpresaActivacionService {
    public function obtenerDetalle(string $codigo): array { throw new LogicException('Activación normal prohibida'); }
    public function guardarActivacion(string $codigo, string $inicio, string $fin, string $usuario): array { throw new LogicException('Activación normal prohibida'); }
}
$service = new EventsOnlyService();
foreach (['found','missing','ambiguous','change','same','invalid','race'] as $case) {
    $db = Mockery::mock(); DB::swap($db); $log = Mockery::mock(); Log::swap($log);
    $row = (object) ['empresa'=>'B543', 'fecha_vencimiento'=>'2026-10-31'];
    if ($case !== 'invalid') {
        $db->shouldReceive('select')->once()->with(Mockery::on(fn($sql) => str_contains($sql, '`api`.`licencia`') && !str_contains($sql, 'LIMIT')), ['B543'])
            ->andReturn($case === 'missing' ? [] : ($case === 'ambiguous' ? [$row,$row] : [$row]));
    }
    if (in_array($case, ['change','same','ambiguous','race'])) $db->shouldReceive('transaction')->once()->andReturnUsing(fn($fn)=>$fn());
    if (in_array($case, ['change','race'])) {
        $db->shouldReceive('update')->once()->with(Mockery::on(fn($sql)=>str_starts_with($sql,'UPDATE `api`.`licencia` SET fecha_vencimiento = ?')), ['2026-11-30','B543'])->andReturn($case === 'race' ? 2 : 1);
        $log->shouldReceive($case === 'race' ? 'error' : 'info')->once()->with(Mockery::type('string'), Mockery::on(fn($data)=>$data['usuario']==='tester (1)' && $data['vencimiento_anterior_eventos']==='2026-10-31' && $data['vencimiento_nuevo_eventos']==='2026-11-30'));
    }
    try {
        $result = in_array($case, ['found','missing']) ? $service->detalleEventos('B543')
            : $service->actualizarLicenciaEventos(' B543 ', $case === 'invalid' ? '2026-02-30' : ($case === 'same' ? '2026-10-31' : '2026-11-30'), 'tester (1)');
        verifyEvents(!in_array($case,['ambiguous','invalid','race']), 'Debió bloquear');
        if ($case==='found') verifyEvents($result['fecha_vencimiento_actual']==='2026-10-31','Encontrada');
        if ($case==='missing') verifyEvents($result===null,'Inexistente');
        if ($case==='same') verifyEvents($result['sin_cambios']===true,'Sin cambios sin UPDATE');
        if ($case==='change') verifyEvents($result['fecha_vencimiento_nueva']==='2026-11-30','Nueva fecha');
    } catch (RuntimeException $e) {
        verifyEvents(in_array($case,['ambiguous','invalid','race']), $e->getMessage());
    }
    Mockery::close();
}
// Middleware real con sesión simulada; sin proveedores de Laravel.
$role = null;
function session($key) { return $GLOBALS['role']; }
function abort($status, $message) { throw new RuntimeException($message, $status); }
foreach (['admin','user','guest',null] as $role) {
    try {
        $response = (new App\Http\Middleware\RoleMiddleware())->handle(new Illuminate\Http\Request(), fn()=>new Symfony\Component\HttpFoundation\Response('ok'), 'admin','user');
        verifyEvents(in_array($role,['admin','user']), 'Rol no autorizado');
    } catch (RuntimeException $e) { verifyEvents(!in_array($role,['admin','user']) && $e->getCode()===403, 'Permisos'); }
}
echo "OK: encontrada, inexistente, ambigua, actualización, sin cambios, fecha inválida, permisos y sin Activación normal; sin BD.\n";
