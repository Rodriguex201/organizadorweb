<?php

// Sin bootstrap de Laravel ni conexiones: persistencia y almacenamiento con dobles.
require __DIR__.'/../../vendor/autoload.php';

use App\Services\ComprobantePagoService;
use App\Services\ProformasService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$app = new Container(); Container::setInstance($app); Facade::setFacadeApplication($app);
$cliente = (object) ['idclientes_potenciales'=>556, 'codigo'=>' B543 ', 'empresa'=>' GRUPO QUATRO SAS ', 'nombre'=>'Nombre anterior'];
$cases = [
    [$cliente, 'B543_GRUPO_QUATRO_SAS_10_2026.pdf'],
    [(object) ['codigo'=>' B543 ', 'empresa'=>'   ', 'nombre'=>' Juan Pérez '], 'B543_Juan_Perez_10_2026.pdf'],
    [(object) ['codigo'=>'B543', 'empresa'=>null, 'nombre'=>' '], 'B543_10_2026.pdf'],
    [(object) ['idclientes_potenciales'=>556, 'codigo'=>null, 'empresa'=>'Empresa'], 'CLIENTE_556_10_2026.pdf'],
    [null, 'PROFORMA_3669_10_2026.pdf'],
    [(object) ['codigo'=>'B/543', 'empresa'=>" Ácme: S.A./Norte\\Sur<>\"|?*\n "], 'B_543_Acme_S_A_Norte_Sur_10_2026.pdf'],
];
foreach ($cases as [$client, $expected]) {
    verify(ComprobantePagoService::nombre(3669, 10, 2026, $client, 'PDF') === $expected, $expected);
}
verify(ComprobantePagoService::nombre(3669, 2, 2026, $cliente, 'JPEG') === 'B543_GRUPO_QUATRO_SAS_02_2026.jpeg', 'Extensión original y mes con dos dígitos');

// Ejecuta el constructor de consulta real; get() devuelve filas simuladas.
// La conexión lanza una excepción si se intenta ejecutar cualquier consulta.
$connection = new Illuminate\Database\Connection(fn () => throw new LogicException('Conexión prohibida'));
foreach ([[$cliente], [$cliente, (object) ['idclientes_potenciales'=>999]], [(object) ['idclientes_potenciales'=>null]], []] as $candidates) {
    $db = Mockery::mock(); DB::swap($db);
    $db->shouldReceive('raw')->andReturnUsing(fn ($value) => new Illuminate\Database\Query\Expression($value));
    $relation = new Illuminate\Database\Query\Builder($connection);
    $relation->from('valores_externos as ve');
    $db->shouldReceive('table')->with('valores_externos as ve')->once()->andReturn($relation);
    $query = Mockery::mock(Illuminate\Database\Query\Builder::class, [$connection])->makePartial();
    $query->from('sg_proform as p');
    $db->shouldReceive('table')->with('sg_proform as p')->once()->andReturn($query);
    $query->shouldReceive('get')->once()->andReturn(collect($candidates));
    $service = (new ReflectionClass(ProformasService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($service, 'sgProformHasIdCobroColumn'))->setValue($service, true);
    $context = $service->contextoComprobante(3669);
    verify($context === null ? $candidates === [] : ($context['cliente'] !== null) === ($candidates === [$cliente]), 'Solo acepta cliente único');
    verify($query->lock === true, 'Bloqueo para reemplazos concurrentes');
    verify(in_array('cliente_comprobante.empresa', $query->columns) && !in_array('p.emp', $query->columns), 'Usa empresa real del cliente');
    Mockery::close();
}

$temp = tempnam(sys_get_temp_dir(), 'receipt-test-');
file_put_contents($temp, '%PDF-1.4 test');
try {
    $upload = new UploadedFile($temp, 'original.PDF', 'application/pdf', null, true);
    foreach (['replace', 'rejected', 'rollback', 'new'] as $scenario) {
        $db = Mockery::mock(); DB::swap($db);
        $disk = Mockery::mock(); $storage = Mockery::mock(); Storage::swap($storage);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);
        $committed = false; $path = null;
        $old = 'proformas/comprobantes/3669/old.pdf';
        $disk->shouldReceive('exists')->once()->andReturn(false);
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) use (&$committed) {
            $result = $callback(); $committed = true; return $result;
        });
        $proformas = Mockery::mock(ProformasService::class);
        $proformas->shouldReceive('contextoComprobante')->with(3669)->once()->andReturn([
            'proforma'=>(object) ['estado'=>$scenario === 'replace' ? ProformasService::ESTADO_PAGADA : ProformasService::ESTADO_ENVIADA, 'mes'=>10, 'anio'=>2026, 'comprobante_pago'=>$old],
            'cliente'=>$cliente,
        ]);
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use (&$path, $upload) {
            verify($file === $upload, 'Archivo original');
            verify($directory === 'proformas/comprobantes/3669', 'Carpeta directa de la proforma sin UUID');
            verify($name === 'B543_GRUPO_QUATRO_SAS_10_2026.pdf', 'Nombre físico estándar');
            return $path = $directory.'/'.$name;
        });
        if ($scenario === 'replace') {
            $query = Mockery::mock();
            $db->shouldReceive('table')->with('sg_proform')->once()->andReturn($query);
            $query->shouldReceive('where')->with('id',3669)->once()->andReturnSelf();
            $query->shouldReceive('update')->once()->with(Mockery::on(function ($data) use (&$path) { return $data === ['comprobante_pago'=>$path, 'fpago'=>'TRANSFERENCIA']; }))->andReturn(1);
        } else {
            $expectation = $proformas->shouldReceive('updateEstado')->once()->with(3669, ProformasService::ESTADO_PAGADA, 'TRANSFERENCIA', Mockery::type('string'));
            if ($scenario === 'rollback') $expectation->andThrow(new RuntimeException('Fallo de persistencia'));
            else $expectation->andReturn(['ok'=>$scenario === 'new', 'message'=>'Resultado']);
        }
        $disk->shouldReceive('delete')->once()->andReturnUsing(function ($deleted) use (&$path, &$committed, $old, $scenario) {
            $success = in_array($scenario, ['replace','new'], true);
            verify($deleted === ($success ? $old : $path), 'Elimina solo el archivo adecuado');
            verify(!$success || $committed, 'Nunca elimina el anterior antes del commit');
            return true;
        });
        try {
            $result = (new ComprobantePagoService($proformas))->registrar(3669,'TRANSFERENCIA',$upload);
            verify($scenario !== 'rollback', 'Debía propagar el fallo');
            if ($result['ok']) verify($result['comprobante_pago'] === $path, 'Ruta devuelta consistente');
        } catch (RuntimeException $error) {
            verify($scenario === 'rollback' && $error->getMessage() === 'Fallo de persistencia', 'Error inesperado');
        }
        Mockery::close();
    }

    // Reemplazo en la misma ruta: comprueba contenido y recuperación, no solo llamadas.
    foreach (['success', 'rejected', 'commit_failure', 'write_failure'] as $scenario) {
        $path = 'proformas/comprobantes/3669/B543_GRUPO_QUATRO_SAS_10_2026.pdf';
        $files = [$path => 'original'];
        $committed = false;
        $db = Mockery::mock(); DB::swap($db);
        $disk = Mockery::mock(); $storage = Mockery::mock(); Storage::swap($storage);
        $storage->shouldReceive('disk')->with('local')->andReturn($disk);
        $disk->shouldReceive('exists')->with($path)->once()->andReturn(true);
        $disk->shouldReceive('copy')->andReturnUsing(function ($from, $to) use (&$files) {
            verify(isset($files[$from]), 'Existe el origen de respaldo/restauración');
            $files[$to] = $files[$from]; return true;
        });
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use (&$files, $path, $scenario) {
            verify($directory.'/'.$name === $path, 'Reemplazo sin subcarpeta UUID');
            verify(count($files) === 2, 'Original respaldado antes de sobrescribir');
            $files[$path] = 'nuevo';
            return $scenario === 'write_failure' ? false : $path;
        });
        $disk->shouldReceive('delete')->andReturnUsing(function ($deleted) use (&$files, &$committed, $scenario, $path) {
            if ($scenario === 'success') {
                verify($committed && $deleted !== $path, 'Solo elimina respaldo después de persistir');
            }
            unset($files[$deleted]); return true;
        });
        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) use (&$committed, $scenario) {
            $result = $callback();
            if ($scenario === 'commit_failure') throw new RuntimeException('Fallo de commit');
            $committed = true; return $result;
        });
        $proformas = Mockery::mock(ProformasService::class);
        $proformas->shouldReceive('contextoComprobante')->with(3669)->once()->andReturn([
            'proforma'=>(object) ['estado'=>$scenario === 'rejected' ? ProformasService::ESTADO_ENVIADA : ProformasService::ESTADO_PAGADA, 'mes'=>10, 'anio'=>2026, 'comprobante_pago'=>$path],
            'cliente'=>$cliente,
        ]);
        if ($scenario === 'rejected') {
            // Una transición rechazada también debe recuperar los bytes originales.
            $proformas->shouldReceive('updateEstado')->once()->andReturn(['ok'=>false, 'message'=>'Rechazada']);
        } elseif ($scenario !== 'write_failure') {
            $query = Mockery::mock();
            $db->shouldReceive('table')->with('sg_proform')->once()->andReturn($query);
            $query->shouldReceive('where')->with('id',3669)->once()->andReturnSelf();
            $query->shouldReceive('update')->with(['comprobante_pago'=>$path, 'fpago'=>'TRANSFERENCIA'])->once()->andReturn(1);
        }
        try {
            $result = (new ComprobantePagoService($proformas))->registrar(3669,'TRANSFERENCIA',$upload);
            verify(in_array($scenario, ['success', 'rejected'], true), 'Debe propagar fallo de escritura/commit');
            verify($result['ok'] === ($scenario === 'success'), 'Resultado esperado');
        } catch (RuntimeException $error) {
            verify(($scenario === 'commit_failure' && $error->getMessage() === 'Fallo de commit')
                || ($scenario === 'write_failure' && $error->getMessage() === 'No fue posible almacenar el comprobante de pago.'), 'Fallo esperado');
        }
        verify($files === [$path => $scenario === 'success' ? 'nuevo' : 'original'], 'Contenido correcto, sin respaldo residual');
        Mockery::close();
    }

    // Invoca el endpoint real, sustituyendo solo lectura del registro y disco.
    $disk = Mockery::mock(); $storage = Mockery::mock(); Storage::swap($storage);
    $storage->shouldReceive('disk')->with('local')->andReturn($disk);
    $path = 'proformas/comprobantes/3669/B543_GRUPO_QUATRO_SAS_10_2026.pdf';
    $disk->shouldReceive('exists')->with($path)->andReturn(true);
    $disk->shouldReceive('mimeType')->with($path)->andReturn('application/pdf');
    $disk->shouldReceive('path')->with($path)->andReturn($temp);
    $proformas = Mockery::mock(ProformasService::class);
    $proformas->shouldReceive('findComprobantePagoById')->with(3669)->andReturn((object) ['comprobante_pago'=>$path]);
    $factory = Mockery::mock(Illuminate\Contracts\Routing\ResponseFactory::class);
    $factory->shouldReceive('file')->once()->andReturnUsing(fn ($file,$headers) => new BinaryFileResponse($file,200,$headers));
    $app->instance(Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
    $controller = (new ReflectionClass(App\Http\Controllers\ProformasController::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($controller, 'proformasService'))->setValue($controller, $proformas);
    $response = $controller->showComprobantePago(3669);
    verify(str_contains($response->headers->get('Content-Disposition'), 'filename="B543_GRUPO_QUATRO_SAS_10_2026.pdf"'), 'Nombre estándar presentado por endpoint');
    verify(str_contains($response->headers->get('Cache-Control'), 'private'), 'Respuesta privada');
    Mockery::close();
    echo "OK: fallbacks, sanitización, extensión, almacenamiento, reemplazo, rollback y endpoint; sin BD.\n";
} finally { unlink($temp); }
