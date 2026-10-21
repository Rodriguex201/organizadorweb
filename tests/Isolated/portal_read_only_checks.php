<?php

// Run through portal_laravel_validation.php --review-tests, never PHPUnit.
// There is no PDO driver and the Laravel connection factory is blocked.
if (!isset($app, $kernel) || PDO::getAvailableDrivers() !== [] || extension_loaded('mysqli')) {
    throw new RuntimeException('Run only through the isolated validation harness.');
}

use App\Portal\ReadOnly\ProformaReadService;
use App\Portal\ReadOnly\SelectReader;
use Illuminate\Http\Request;

$passed = 0;
$check = static function (bool $result, string $name) use (&$passed): void {
    if (!$result) {
        throw new RuntimeException('FAILED: '.$name);
    }
    $passed++;
    echo "PASS: {$name}\n";
};
$destination = ['host'=>'168.232.32.74', 'port'=>'3306', 'database'=>'organizadorprueba', 'username'=>'RmSoft20X', 'password'=>'unused'];
foreach (['database'=>'organizador', 'host'=>'localhost', 'port'=>'3307', 'username'=>'another'] as $key => $value) {
    try {
        \App\Portal\ReadOnly\MySqlReviewReader::open(array_replace($destination, [$key=>$value]));
        $check(false, 'destination must be rejected');
    } catch (RuntimeException $e) {
        $check($e->getMessage() === 'Unexpected portal review destination.', 'wrong '.$key.' rejected before connection');
    }
}
$base = [
    'cliente' => [['idclientes_potenciales' => 10]],
    'proforma' => [['id' => 15, 'id_cobro' => 20, 'nro_prof' => 'PF-001', 'emp' => '<script>private</script>',
        'mes' => 10, 'anio' => 2026, 'vtotal' => '1234.56', 'estado' => 3]],
    'relacion' => [['id_cliente' => '10']],
    'detalle' => [['descripcion' => '<img src=x onerror=alert(1)>', 'cantidad' => '1.5', 'vr_unidad' => '823.04', 'vr_parcial' => '1234.56']],
];
$lookup = static function (array $rows, string $document = '900123456', string $number = 'PF-001'): array {
    $reader = new class($rows) implements SelectReader {
        public array $calls = [];
        public function __construct(private array $rows) {}
        public function select(string $sql, array $bindings): array
        {
            $key = array_search($sql, ProformaReadService::QUERIES, true);
            if ($key === false || !str_starts_with($sql, 'SELECT ') || str_contains($sql, ';')) {
                throw new RuntimeException('Unexpected SQL');
            }
            $this->calls[] = [$key, $bindings];
            return $this->rows[$key];
        }
    };
    return [(new ProformaReadService($reader))->find($document, $number), $reader->calls];
};
[$detail, $calls] = $lookup($base);
$check($detail !== null && $detail['total'] === '1234.56' && $detail['lineas'][0]['cantidad'] === '1.5', 'unique relation and decimal precision');
$check(array_keys($detail) === ['numero', 'empresa', 'periodo', 'estado', 'total', 'lineas'], 'public DTO whitelist');
$check($calls === [['cliente', ['900123456']], ['proforma', ['900123456', 'PF-001']], ['relacion', [20]], ['detalle', [15]]], 'fixed SELECT queries and parameter bindings');
foreach (['cliente', 'proforma', 'relacion', 'detalle'] as $key) {
    $rows = $base;
    $rows[$key] = [];
    [$result] = $lookup($rows);
    $check($result === null, 'zero '.$key.' is blocked');
}
foreach (['cliente', 'proforma', 'relacion'] as $key) {
    $rows = $base;
    $rows[$key][] = $rows[$key][0];
    [$result, $queries] = $lookup($rows);
    $check($result === null && !in_array('detalle', array_column($queries, 0), true), 'multiple '.$key.' blocked before detail');
}
foreach (['11', null, '', '10abc', '010', '10 '] as $id) {
    $rows = $base;
    $rows['relacion'][0]['id_cliente'] = $id;
    [$result] = $lookup($rows);
    $check($result === null, 'invalid or foreign client '.var_export($id, true));
}
foreach ([null, 0, -1, '20invalid'] as $cobro) {
    $rows = $base;
    $rows['proforma'][0]['id_cobro'] = $cobro;
    [$result, $queries] = $lookup($rows);
    $check($result === null && count($queries) === 2, 'no historical fallback '.var_export($cobro, true));
}
[$result, $queries] = $lookup($base, "900' OR 1=1");
$check($result === null && $queries === [], 'invalid input causes no query');
$check(!ProformaReadService::validInput([], 'PF-001'), 'array input rejected');
$rows = $base;
$rows['detalle'] = array_fill(0, 501, $base['detalle'][0]);
[$result] = $lookup($rows);
$check($result === null, 'oversized detail blocked');
$rows = $base;
$rows['proforma'][0]['estado'] = 99;
[$result] = $lookup($rows);
$check($result['estado'] === 'No disponible', 'unknown state is not represented as paid');

$app->instance('request', Request::create('http://127.0.0.1:8789/portal/revision'));
$html = view('portal.revision', ['detail' => $detail, 'submitted' => true])->render();
$check(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>private'), 'customer output escaped');
$check(str_contains($html, '&lt;img') && str_contains($html, '$ 1.234,56'), 'concept escaped and monetary precision preserved');
$check(!str_contains($html, '900123456') && !str_contains($html, 'Bold') && !str_contains($html, '/activacion'), 'detail has no document, payment or activation controls');

// Real Laravel route/middleware/controller, with all DB drivers absent.
$originalEnv = $app['env'];
$app['config']->set('portal_review.enabled', true);
$send = static function (string $environment, string $peer, string $method, ?string $origin, array $input = []) use ($app, $kernel) {
    $app->instance('env', $environment);
    $server = ['REMOTE_ADDR' => $peer];
    if ($origin !== null) $server['HTTP_ORIGIN'] = $origin;
    $request = Request::create('http://127.0.0.1:8789/portal/revision', $method, $input, [], [], $server);
    return $kernel->handle($request);
};
$check($send('production', '127.0.0.1', 'GET', null)->getStatusCode() === 404, 'production blocked');
$check($send('local', '192.168.1.5', 'GET', null)->getStatusCode() === 404, 'non-loopback blocked');
$check($send('production', '127.0.0.1', 'POST', 'http://127.0.0.1:8789', ['documento'=>'900123456','numero'=>'5521'])->getStatusCode() === 404, 'production POST blocked before lookup');
$check($send('local', '192.168.1.5', 'POST', 'http://127.0.0.1:8789', ['documento'=>'900123456','numero'=>'5521'])->getStatusCode() === 404, 'external peer POST blocked before lookup');
$app['config']->set('portal_review.enabled', false);
$check($send('local', '127.0.0.1', 'GET', null)->getStatusCode() === 404, 'disabled flag blocked');
$app['config']->set('portal_review.enabled', true);
$check($send('local', '127.0.0.1', 'POST', 'http://evil.test')->getStatusCode() === 403, 'cross-origin POST blocked');
$check($send('local', '127.0.0.1', 'POST', null)->getStatusCode() === 403, 'missing Origin blocked');
$hostRequest = Request::create('http://external.example/portal/revision', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
$check($kernel->handle($hostRequest)->getStatusCode() === 404, 'untrusted host blocked');
$proxyRequest = Request::create('http://127.0.0.1:8789/portal/revision', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.5', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1']);
$check($kernel->handle($proxyRequest)->getStatusCode() === 404, 'forwarded address cannot bypass peer restriction');
$response = $send('local', '127.0.0.1', 'GET', null);
$check($response->getStatusCode() === 200 && str_contains($response->getContent(), 'Solicitar código'), 'local OTP form rendered');
$check($response->headers->getCookies() === [] && str_contains($response->headers->get('Cache-Control'), 'no-store'), 'no session cookie and no-store');
$response = $send('local', '127.0.0.1', 'POST', 'http://127.0.0.1:8789', ['documento' => 'bad', 'numero' => 'PF-001']);
$check($response->getStatusCode() === 200 && str_contains($response->getContent(), \App\Portal\Otp\PortalOtpService::MESSAGE), 'invalid search generic response');
$response = $send('local', '127.0.0.1', 'POST', 'http://127.0.0.1:8789', ['documento' => '900123456', 'numero' => 'PF-001']);
$check($response->getStatusCode() === 200 && str_contains($response->getContent(), \App\Portal\Otp\PortalOtpService::MESSAGE) && !str_contains($response->getContent(), 'invoice-card'), 'revision cannot bypass OTP with document and number');
require __DIR__.'/portal_otp_checks.php';
$app->instance('env', $originalEnv);
echo "{$passed} checks passed. No database driver or real connection used.\n";
