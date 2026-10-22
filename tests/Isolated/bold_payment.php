<?php
declare(strict_types=1);
// Run php -n: no Laravel bootstrap, .env, PDO drivers or external transport.
if (PDO::getAvailableDrivers() !== (defined('BOLD_MEMORY_TEST') ? ['sqlite'] : []) || extension_loaded('mysqli')) throw new RuntimeException('Database drivers forbidden');
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Portal\Otp\{PrivateOtpStore, PortalOtpService};
use App\Portal\ReadOnly\{PortalReadGateway, ProformaReadService, SelectReader};
use App\Services\BoldPaymentService;

$count = 0;
$check = function (bool $ok, string $name) use (&$count) { if (!$ok) throw new RuntimeException($name); ++$count; echo "PASS: $name\n"; };
$reject = function (Closure $action, string $name) use ($check) {
    try { $action(); } catch (RuntimeException) { $check(true, $name); return; }
    $check(false, $name);
};
$now = 10000; $code = null;
$target = ['cliente_id'=>'10','proforma_id'=>'15','documento'=>'900123456','numero'=>'PF-1','email'=>'test@example.test'];
$key = base64_encode(random_bytes(32));
$store = new PrivateOtpStore(sys_get_temp_dir().'/bold-isolated-'.bin2hex(random_bytes(8)), $key);
$otp = new PortalOtpService($store, $key, ['test@example.test'], fn ()=>$target,
    function ($email, $value) use (&$code) { $code=$value; }, function () use (&$now) { return $now; });
$challenge = $otp->request('127.0.0.1','900123456','PF-1');
$token = $otp->verify('127.0.0.1',$challenge,$code);
$reader = new class implements SelectReader {
    public array $clients = [['idclientes_potenciales'=>'10']];
    public array $relations = [['id_cliente'=>'10']];
    public array $headers = [['id'=>'15','id_cobro'=>'20','estado'=>'2','vtotal'=>'129119.65']];
    public function select(string $sql, array $bindings): array {
        return match ($sql) {
            ProformaReadService::QUERIES['cliente'] => $this->clients,
            ProformaReadService::QUERIES['proforma'] => $this->headers,
            ProformaReadService::QUERIES['relacion'] => $this->relations,
            default => throw new RuntimeException('Unexpected query'),
        };
    }
};
$gateway = new class($reader) extends PortalReadGateway {
    public int $reads = 0;
    public ?Closure $beforeRead = null;
    public function __construct(private SelectReader $reader) {}
    public function paymentSnapshot(array $grant): ?array { ++$this->reads; if ($this->beforeRead) ($this->beforeRead)(); return (new ProformaReadService($this->reader))->paymentSnapshot($grant); }
};
$make = fn ($settings=[], $env='local') => new BoldPaymentService($otp,$gateway,$settings+['enabled'=>false,'environment'=>'sandbox'],$env);
$service=$make(); $prepared=$service->prepare($token);
$check($prepared['amount']==='129119.65' && str_contains($prepared['body'],'"total_amount":129119.65'), 'persisted net amount exact JSON');
$body=json_decode($prepared['body'],true,512,JSON_THROW_ON_ERROR);
$check($body['amount_type']==='CLOSE' && $body['amount']['currency']==='COP' && $body['amount']['tip_amount']===0, 'closed amount COP');
$check(array_keys($body)===['amount_type','amount','reference'], 'no payer email or sensitive fields');
$check($prepared['cliente_id']==='10' && $prepared['proforma_id']==='15', 'attempt linked internally to OTP target');
$check(preg_match('/\APF_[a-f0-9]{32}\z/',$prepared['reference'])===1 && $service->prepare($token)['reference']!==$prepared['reference'], 'unique opaque reference');
$check(count((new ReflectionMethod($service,'prepare'))->getParameters())===1, 'no frontend amount or proforma parameter');
$reject(fn ()=>$service->prepare('forged'), 'invalid OTP authorization');
foreach (['0.00','-1.00','1.001','NaN','1e3','',null,100.5] as $bad) {
    $reader->headers[0]['vtotal']=$bad; $reject(fn ()=>$service->prepare($token),'invalid total rejected');
}
$reader->headers[0]['vtotal']='129119.65';
foreach (['4','6','0','99'] as $state) { $reader->headers[0]['estado']=$state; $reject(fn ()=>$service->prepare($token),'paid or nonpayable state blocked'); }
$reader->headers[0]['estado']='3'; $check($service->prepare($token)['amount']==='129119.65','sent proforma allowed');
$header=$reader->headers;
$reader->headers=[]; $reject(fn ()=>$service->prepare($token),'missing proforma');
$reader->headers=[$header[0],$header[0]]; $reject(fn ()=>$service->prepare($token),'ambiguous proforma');
$reader->headers=$header; $reader->headers[0]['id']='16'; $reject(fn ()=>$service->prepare($token),'another proforma blocked');
$reader->headers=$header; $reader->relations=[['id_cliente'=>'11']]; $reject(fn ()=>$service->prepare($token),'foreign customer blocked');
$reader->relations=[['id_cliente'=>'10']];
$reject(fn ()=>$make(['environment'=>'production'])->prepare($token),'production Bold blocked');
$reject(fn ()=>$make([], 'production')->prepare($token),'nonlocal app blocked');
$reads=$gateway->reads;
$reject(fn ()=>$service->createLink($token),'disabled blocks before reads and HTTP');
$reject(fn ()=>$make(['enabled'=>true])->createLink($token),'missing key blocks transport');
$check($gateway->reads===$reads,'disabled or missing key blocks before source read');
$response=['payload'=>['payment_link'=>'LNK_TEST','url'=>'https://checkout.bold.co/LNK_TEST','secret'=>'must-not-propagate'],'errors'=>[]];
$check($service->parseCreationResponse(200,$response)===['payment_link'=>'LNK_TEST','url'=>'https://checkout.bold.co/LNK_TEST'],'response whitelist');
foreach ([401,429,500] as $status) $reject(fn ()=>$service->parseCreationResponse($status,$response),'HTTP failure sanitized');
$bad=$response; $bad['payload']['url']='https://evil.example/LNK_TEST'; $reject(fn ()=>$service->parseCreationResponse(200,$bad),'foreign URL blocked');
$bad=$response; $bad['errors']=['provider detail']; $reject(fn ()=>$service->parseCreationResponse(200,$bad),'provider error blocked');
$reject(fn ()=>$service->parseCreationResponse(200,[]),'malformed response blocked');
$alternative=['payload'=>['payment_link'=>'TEST-opaque-123','url'=>'https://checkout.bold.co/pay/synthetic-link'],'errors'=>[]];
foreach ([200,201,202] as $status) {
    $check($service->parseCreationResponse($status,$alternative)===$alternative['payload'],'2xx accepts opaque ID and independent allowed URL');
    $check($service->parseCreationBody($status,json_encode($alternative),'application/json; charset=utf-8')===$alternative['payload'],'JSON structural acceptance');
}
foreach (['', '   ', null, 123] as $id) {
    $bad=$alternative; $bad['payload']['payment_link']=$id;
    $reject(fn ()=>$service->parseCreationResponse(200,$bad),'empty or non string identifier rejected');
}
foreach (['http://checkout.bold.co/test','https://evil.example/test',
    'https://checkout.bold.co.evil.example/test','https://evilcheckout.bold.co/test',
    'https://user:pass@checkout.bold.co/test','https://user@checkout.bold.co/test',
    'https://checkout.bold.co:444/test','https://checkout.bold.co\\@evil.example/test',
    "https://checkout.bold.co/test\n",'not a URL',''] as $url) {
    $bad=$alternative; $bad['payload']['url']=$url;
    $reject(fn ()=>$service->parseCreationResponse(200,$bad),'unsafe checkout URL rejected');
}
$reject(fn ()=>$service->parseCreationBody(200,'{','application/json'),'invalid JSON rejected');
$reject(fn ()=>$service->parseCreationBody(200,json_encode($alternative),'text/html'),'non JSON content type rejected');
foreach (['{}','null','["synthetic error"]'] as $errors) {
    $json='{"payload":{"payment_link":"TEST","url":"https://checkout.bold.co/test"},"errors":'.$errors.'}';
    $reject(fn ()=>$service->parseCreationBody(200,$json,'application/json'),'errors must be an empty JSON array');
}
$reject(fn ()=>$service->parseCreationBody(200,'{"payload":[],"errors":[]}','application/json'),'payload must be a JSON object');
$now+=900; $reject(fn ()=>$service->prepare($token),'expired OTP session blocked');
echo "OK: $count adapter checks; no MySQL, no HTTP, no real mail.\n";
