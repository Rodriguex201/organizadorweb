<?php
declare(strict_types=1);
// Before autoload: no MySQL driver, Laravel bootstrap or real environment.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) throw new RuntimeException('Only SQLite memory');
define('BOLD_MEMORY_TEST', true);
require __DIR__.'/bold_payment.php';

use App\Payments\Bold\AttemptRepository;
use App\Services\BoldAttemptService;

$now=10000;
$key=base64_encode(random_bytes(32));$code=null;
$otp=new App\Portal\Otp\PortalOtpService(new App\Portal\Otp\PrivateOtpStore(sys_get_temp_dir().'/bold-reserve-'.bin2hex(random_bytes(8)),$key),$key,['test@example.test'],fn ()=>$target,
 function ($email,$value) use (&$code){$code=$value;},fn ()=>10000);
$challenge=$otp->request('ip','900123456','PF-1');$token=$otp->verify('ip',$challenge,$code);
$service=new App\Services\BoldPaymentService($otp,$gateway,['environment'=>'sandbox','enabled'=>false],'local');
$pdo=new PDO('sqlite::memory:');
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'
    || $pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file']!=='') throw new RuntimeException('Isolation failed');
// In-memory schema models the unique slot and monetary snapshot; never a migration.
$pdo->exec("CREATE TABLE intentos_pago (
 id INTEGER PRIMARY KEY AUTOINCREMENT, proforma_id INTEGER NOT NULL, cliente_id INTEGER NOT NULL,
 referencia TEXT NOT NULL UNIQUE, pasarela TEXT NOT NULL, ambiente TEXT NOT NULL,
 importe TEXT NOT NULL CHECK(CAST(importe AS REAL)>0), moneda TEXT NOT NULL, estado TEXT NOT NULL,
 payment_link TEXT, checkout_url TEXT, http_status INTEGER, error_codigo TEXT, estado_remoto TEXT,
 ambiente_verificado INTEGER NOT NULL DEFAULT 0, ultima_consulta_at TEXT, solicitud_iniciada_at TEXT,
 created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
 bloqueo_activo INTEGER GENERATED ALWAYS AS (CASE WHEN estado IN ('FALLIDO','EXPIRADO','CANCELADO') THEN NULL ELSE 1 END) STORED,
 UNIQUE(proforma_id,pasarela,ambiente,bloqueo_activo), UNIQUE(pasarela,ambiente,payment_link)
)");
$repo=AttemptRepository::isolated($pdo);
$attempts=new BoldAttemptService($service,$repo);
$row=$attempts->reserve($token);
$frozen=array_intersect_key($row,array_flip(['proforma_id','cliente_id','referencia','importe','moneda','ambiente']));
$check($row['estado']==='RESERVADO' && $row['importe']==='129119.65','first reservation from persisted net');
$again=$attempts->reserve($token);
$check($again['id']===$row['id'] && $again['referencia']===$row['referencia'],'repeat reuses blocking reference');
$check((int)$pdo->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn()===1,'double request creates one row');
$reject(fn ()=>$repo->transition((int)$row['id'],$row['referencia'],'RESERVADO','SOLICITANDO',['importe'=>'1.00']),'immutable fields cannot update');

// Two requests in flight both reach their fresh source read before either can claim.
$gateway->beforeRead=fn ()=>Fiber::suspend();
$a=new Fiber(fn ()=>$attempts->claim($token)); $b=new Fiber(fn ()=>$attempts->claim($token));
$a->start(); $b->start(); $a->resume(); $b->resume(); $gateway->beforeRead=null;
$claimed=$a->getReturn();
$check($claimed!==null && $b->getReturn()===null,'interleaved requests: exactly one executor');
$check($attempts->recordCreation($claimed,null),'timeout persisted');
$unknown=$attempts->reserve($token);
$check($unknown['estado']==='INDETERMINADO' && $unknown['id']===$row['id'],'indeterminate reused without new link');
$check($attempts->claim($token)===null,'uncertain attempt cannot redispatch');
$check(!$attempts->failBeforeDispatch($unknown),'uncertain attempt cannot release slot');
$reader->headers[0]['vtotal']='130000.00';
$reject(fn ()=>$attempts->reserve($token),'changed vtotal blocks');
$changed=$repo->blocking('15','sandbox');
$check($changed['estado']==='REVISION' && array_intersect_key($changed,$frozen)===$frozen,'review preserves all frozen fields');
$reader->headers[0]['vtotal']='129119.65';
foreach (['4','6'] as $state) {
 $reader->headers[0]['estado']=$state;
 $reject(fn ()=>$attempts->reserve($token),'paid/factured cannot reserve');
}
$reader->headers[0]['estado']='2';
$check((int)$pdo->query('SELECT COUNT(*) FROM intentos_pago')->fetchColumn()===1,'paid/factured create nothing');

// Unique constraint semantics for every approved state, including terminal fixtures.
foreach (['RESERVADO','SOLICITANDO','INDETERMINADO','LINK_CREADO','ACTIVO','PROCESANDO','PAGADO','REVISION','FALLIDO','EXPIRADO','CANCELADO'] as $index=>$state) {
 $draft=$service->prepare($token); $draft['proforma_id']=(string)(100+$index);
 $first=$repo->reserve($draft);
 $q=$pdo->prepare('UPDATE intentos_pago SET estado=? WHERE id=?');$q->execute([$state,$first['id']]);
 $draft['reference']='PF_'.bin2hex(random_bytes(16)); $next=$repo->reserve($draft);
 $closed=in_array($state,['FALLIDO','EXPIRADO','CANCELADO'],true);
 $check(($next['id']!==$first['id'])===$closed,'unique slot state '.$state);
}

// Start a different authorized fixture, then simulate documented HTTP responses.
$target['proforma_id']='16';
// Existing OTP is limited to 15: use a fresh OTP service for the new internal fixture.
$key2=base64_encode(random_bytes(32));$code2=null;
$otp2=new App\Portal\Otp\PortalOtpService(new App\Portal\Otp\PrivateOtpStore(sys_get_temp_dir().'/bold-attempt-'.bin2hex(random_bytes(8)),$key2),$key2,['test@example.test'],fn ()=>$target,
 function ($email,$value) use (&$code2){$code2=$value;},fn ()=>10000);
$ch=$otp2->request('ip','900123456','PF-1');$token2=$otp2->verify('ip',$ch,$code2);
$reader->headers[0]['id']='16';
$svc2=new App\Services\BoldPaymentService($otp2,$gateway,['environment'=>'sandbox','enabled'=>false],'local');
$flow2=new BoldAttemptService($svc2,$repo);
// Both requests pause before insert, testing the unique-key collision recovery path.
$gateway->beforeRead=fn ()=>Fiber::suspend();
$a=new Fiber(fn ()=>$flow2->reserve($token2));$b=new Fiber(fn ()=>$flow2->reserve($token2));
$a->start();$b->start();$a->resume();$b->resume();$gateway->beforeRead=null;
$check($a->getReturn()['id']===$b->getReturn()['id'],'overlapping reservations reuse unique row');
$claimed=$flow2->claim($token2);
$response=$alternative;
$check($flow2->recordCreation($claimed,200,$response),'simulated HTTP 200 persists link');
$check(!$flow2->recordCreation($claimed,200,$response),'late response cannot overwrite state');
$linked=$repo->blocking('16','sandbox');
$check($linked['estado']==='LINK_CREADO' && $linked['payment_link']==='TEST-opaque-123'
    && (int)$linked['ambiente_verificado']===0,'opaque ID and independent URL persist only as LINK_CREADO');
$check(!isset($flow2->reserve($token2)['checkout_url']),'unverified link not exposed');
$remote=['is_sandbox'=>true,'id'=>'TEST-opaque-123','reference'=>$linked['referencia'],'amount_type'=>'CLOSE','status'=>'ACTIVE','total'=>'129119.65'];
$check($flow2->recordVerification($linked,$remote),'verified simulated response activates attempt');
$active=$flow2->reserve($token2);
$check($active['estado']==='ACTIVO' && $active['checkout_url']==='https://checkout.bold.co/pay/synthetic-link','active link reused');
$check($flow2->claim($token2)===null,'active link never sent twice');
$reject(fn ()=>$repo->transition((int)$active['id'],$active['referencia'],'ACTIVO','FALLIDO'),'active cannot release by local failure');
$check(!str_contains(json_encode($active),'must-not-propagate'),'raw provider fields not persisted');
$reject(fn ()=>AttemptRepository::open(['database'=>'organizador'],'local','sandbox'),'wrong database blocked before socket');
$reject(fn ()=>AttemptRepository::open([],'production','sandbox'),'production blocked before socket');
$dbFixture=['database'=>'organizadorprueba','host'=>'mysql.example.invalid','port'=>'3306','username'=>'fixture','password'=>'synthetic-only'];
$invalidConfigs=[array_replace($dbFixture,['database'=>'organizador']),array_replace($dbFixture,['host'=>'host;dbname=organizador']),array_replace($dbFixture,['port'=>'0']),array_replace($dbFixture,['port'=>'65536'])];
foreach (['host','port','username','password','database'] as $field) $invalidConfigs[]=array_replace($dbFixture,[$field=>'']);
foreach ($invalidConfigs as $config) {
    try { AttemptRepository::open($config,'local','sandbox'); $check(false,'invalid config accepted'); }
    catch (RuntimeException $e) { $check($e->getMessage()==='Destino de intentos de pago no permitido.','invalid dedicated config blocked before PDO'); }
}
try { AttemptRepository::open($dbFixture,'local','production'); $check(false,'production accepted'); }
catch (RuntimeException $e) { $check($e->getMessage()==='Destino de intentos de pago no permitido.','non sandbox blocked before PDO'); }

$reject(fn ()=>$service->prepare($token2),'token from another OTP store rejected');
$draft=$svc2->prepare($token2);$draft['proforma_id']='200';
$fresh=$repo->reserve($draft);
$check($flow2->failBeforeDispatch($fresh),'local failure before sending releases reservation');
$draft['reference']='PF_'.bin2hex(random_bytes(16));
$check($repo->reserve($draft)['id']!==$fresh['id'],'new attempt after proven local failure');
foreach ([['status'=>503,'expected'=>'INDETERMINADO'],['status'=>400,'expected'=>'REVISION']] as $i=>$case) {
 $draft['proforma_id']=(string)(201+$i);$draft['reference']='PF_'.bin2hex(random_bytes(16));
 $fresh=$repo->reserve($draft);$repo->transition((int)$fresh['id'],$fresh['referencia'],'RESERVADO','SOLICITANDO');
 $flow2->recordCreation($fresh,$case['status'],['errors'=>['sensitive provider text']]);
 $saved=$repo->blocking($draft['proforma_id'],'sandbox');
 $check($saved['estado']===$case['expected'] && !str_contains(json_encode($saved),'sensitive provider text'),'HTTP failure conservatively persists safe metadata');
}
$draft['proforma_id']='203';$draft['reference']='PF_'.bin2hex(random_bytes(16));
$fresh=$repo->reserve($draft);$repo->transition((int)$fresh['id'],$fresh['referencia'],'RESERVADO','SOLICITANDO');
$r=['payload'=>['payment_link'=>'LNK_OTHER','url'=>'https://checkout.bold.co/LNK_OTHER'],'errors'=>[]];
$flow2->recordCreation($fresh,200,$r);$linked=$repo->blocking('203','sandbox');
$remote['is_sandbox']=false;$flow2->recordVerification($linked,$remote);
$check($repo->blocking('203','sandbox')['estado']==='REVISION','wrong environment never activates link');
echo "OK: $count checks total; SQLite :memory: only, no real HTTP or MySQL.\n";
