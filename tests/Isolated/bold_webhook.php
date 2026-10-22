<?php
declare(strict_types=1);

// No Laravel bootstrap, .env, network or MySQL driver.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) throw new RuntimeException('Only SQLite memory');
require __DIR__.'/../../app/Payments/Bold/WebhookEventStore.php';
require __DIR__.'/../../app/Payments/Bold/WebhookEventRepository.php';
require __DIR__.'/../../app/Services/BoldWebhookService.php';

use App\Payments\Bold\WebhookEventRepository;
use App\Services\BoldWebhookService;

$count = 0;
$check = function (bool $ok, string $name) use (&$count): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$name);
    ++$count; echo 'PASS: '.$name.PHP_EOL;
};
$pdo = new PDO('sqlite::memory:');
$repo = WebhookEventRepository::isolated($pdo); // Guard before any schema statement.
$pdo->exec("CREATE TABLE intentos_pago (id INTEGER PRIMARY KEY, pasarela TEXT, ambiente TEXT, payment_link TEXT,
 importe TEXT, moneda TEXT, ambiente_verificado INTEGER, transaction_id TEXT)");
$pdo->exec("INSERT INTO intentos_pago VALUES (1,'bold','sandbox','fixture-link','179253.00','COP',1,NULL)");
$pdo->exec("CREATE TABLE eventos_pago (id INTEGER PRIMARY KEY, evento_id TEXT, intento_pago_id INTEGER,
 payment_link TEXT, payment_id TEXT, merchant_id TEXT, tipo TEXT, importe TEXT, moneda TEXT, raw_sha256 TEXT,
 estado_validacion TEXT, motivo TEXT, pasarela TEXT, ambiente TEXT, validacion_firma INTEGER, modo_validacion TEXT,
 UNIQUE(pasarela,ambiente,evento_id))");
$before = $pdo->query('SELECT * FROM intentos_pago')->fetchAll();
$opens = 0;
$service = new BoldWebhookService(function () use ($repo, &$opens) { ++$opens; return $repo; }, 'fixture-merchant');
$fixture = [
    'id'=>'fixture-event', 'type'=>'SALE_APPROVED', 'subject'=>'fixture-payment', 'source'=>'/payments/links',
    'data'=>['payment_id'=>'fixture-payment','merchant_id'=>'fixture-merchant', 'integration'=>'LINK',
        'metadata'=>['reference'=>'fixture-link'], 'amount'=>['total'=>179253,'currency'=>'COP'],
        'payer_email'=>'not-stored@example.test','card'=>['cardholder_name'=>'Not stored']],
];
$sign = fn ($raw) => hash_hmac('sha256', base64_encode($raw), '');
$send = fn ($f) => $service->receive($r=json_encode($f, JSON_THROW_ON_ERROR), $sign($r), 'application/json');
$raw = json_encode($fixture);
$check($service->receive($raw, '', 'application/json') === 400 && $opens === 0, 'missing signature: no DB');
$check($service->receive($raw, str_repeat('0',64), 'application/json') === 400 && $opens === 0, 'wrong signature: no DB');
$check($service->receive($raw.' ', $sign($raw), 'application/json') === 400 && $opens === 0, 'raw whitespace tampering: no DB');
$check($service->receive('{', $sign('{'), 'application/json') === 400 && $opens === 0, 'signed invalid JSON: no DB');
$check($service->receive($raw, $sign($raw), 'text/plain') === 415 && $opens === 0, 'content type: no DB');
$check($service->receive(str_repeat('x',65537), '', 'application/json') === 413 && $opens === 0, 'bounded raw body');
$check($send($fixture) === 200, 'valid sandbox event');
$row = $pdo->query('SELECT * FROM eventos_pago')->fetch();
$check($row['estado_validacion'] === 'VALIDADO' && $row['importe'] === '179253.00' && $row['modo_validacion'] === 'SANDBOX_EMPTY_HMAC', 'validated decimal evidence');
$check(!str_contains(json_encode($row), 'not-stored') && !str_contains(json_encode($row), 'Not stored'), 'payer excluded');
$check($send($fixture) === 200 && (int)$pdo->query('SELECT COUNT(*) FROM eventos_pago')->fetchColumn() === 1, 'identical replay');
$different = $fixture; $different['data']['amount']['total'] = 1;
$check($send($different) === 409, 'same ID different raw hash');
$persisted = array_intersect_key($row, array_flip(['evento_id','intento_pago_id','payment_link','payment_id','merchant_id','tipo','importe','moneda','raw_sha256','estado_validacion','motivo']));
$check($repo->save($persisted) === 200, 'unique constraint handles race with identical event');
$persisted['raw_sha256'] = str_repeat('a',64);
$check($repo->save($persisted) === 409, 'unique constraint handles race with conflicting event');

foreach (['amount','currency','merchant','integration','link','transaction','environment','unknown'] as $case) {
    $f=$fixture; $f['id']='fixture-'.$case;
    switch ($case) {
        case 'amount': $f['data']['amount']['total']='179253.001'; break;
        case 'currency': $f['data']['amount']['currency']='USD'; break;
        case 'merchant': $f['data']['merchant_id']='different'; break;
        case 'integration': $f['data']['integration']='BUTTON'; break;
        case 'link': $f['data']['metadata']['reference']='absent'; break;
        case 'transaction': $f['subject']='different'; break;
        case 'environment': $pdo->exec('UPDATE intentos_pago SET ambiente_verificado=0'); break;
        case 'unknown': $f['type']='UNKNOWN_EVENT'; break;
    }
    $check($send($f) === 200, 'durable mismatch: '.$case);
    $r=$pdo->query('SELECT * FROM eventos_pago ORDER BY id DESC LIMIT 1')->fetch();
    $check($r['estado_validacion'] === ($case === 'unknown' ? 'IGNORADO' : 'REVISION'), 'quarantine: '.$case);
    if ($case === 'environment') $pdo->exec('UPDATE intentos_pago SET ambiente_verificado=1');
}
$decimal = str_replace('179253', '179253.00', $raw);
$decimal = str_replace('fixture-event', 'fixture-decimal', $decimal);
$check($service->receive($decimal,$sign($decimal),'application/json; charset=utf-8')===200, 'JSON numeric decimal lexeme');
$check($pdo->query('SELECT importe FROM eventos_pago ORDER BY id DESC LIMIT 1')->fetchColumn()==='179253.00', 'exact decimal retained');
$f=$fixture; $f['id']='fixture-exponent'; $r=str_replace('179253', '1.79253e5', json_encode($f));
$check($service->receive($r,$sign($r),'application/json')===200 && $pdo->query('SELECT estado_validacion FROM eventos_pago ORDER BY id DESC LIMIT 1')->fetchColumn()==='REVISION', 'exponent fails closed');
$f=$fixture; $f['id']='fixture-outage';
$outage = new BoldWebhookService(fn () => throw new RuntimeException('private connection details'), 'fixture-merchant');
$r=json_encode($f);
$check($outage->receive($r,$sign($r),'application/json')===503, 'connection failure sanitized');
$check($pdo->query('SELECT * FROM intentos_pago')->fetchAll()===$before, 'attempt unchanged by receiver');
$pdo->exec('DROP TABLE eventos_pago');
$check($send($f)===503, 'persistence failure');

$cfg=['environment'=>'sandbox','database'=>['database'=>'organizadorprueba'],'webhook'=>['enabled'=>true,'merchant_id'=>'fixture-merchant']];
$check(BoldWebhookService::allowed('local',$cfg,'127.0.0.1','localhost'), 'local gate');
$check(!BoldWebhookService::allowed('production',$cfg,'127.0.0.1','localhost'), 'production denied');
$check(!BoldWebhookService::allowed('local',$cfg,'203.0.113.1','localhost'), 'external peer denied');
$check(!BoldWebhookService::allowed('local',$cfg,'127.0.0.1','public.example.test'), 'public host denied');
$cfg['database']['database']='forbidden';
$check(!BoldWebhookService::allowed('local',$cfg,'127.0.0.1','localhost'), 'wrong database denied');
echo "OK: {$count} checks; SQLite memory only.\n";
