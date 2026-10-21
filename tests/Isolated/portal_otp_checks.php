<?php
// Included only by the driver-free Laravel validation harness.
use App\Portal\Otp\{PrivateOtpStore, PortalOtpService, PortalOtpMailer};
use App\Portal\ReadOnly\ProformaReadService as OtpReadService;
use App\Portal\ReadOnly\SelectReader as OtpReader;
use Illuminate\Support\Facades\Http;
if (!isset($check,$app) || PDO::getAvailableDrivers() !== []) throw new RuntimeException('Isolated harness required');

$otpRoot = getenv('PORTAL_VALIDATION_DIR').'/otp-'.bin2hex(random_bytes(8));
$otpKey = base64_encode(random_bytes(32));
$now = 10000;
$sent = [];
$target = ['cliente_id'=>'10','proforma_id'=>'15','documento'=>'900123456','numero'=>'PF-001','email'=>'pilot@example.test'];
$makeOtp = function (?array $resolved, string $name) use (&$now,&$sent,$otpRoot,$otpKey): PortalOtpService {
    return new PortalOtpService(new PrivateOtpStore($otpRoot.'/'.$name,$otpKey),$otpKey,['pilot@example.test'],
        fn ($d,$n)=>$resolved, function ($email,$code) use (&$sent) { $sent[]=[$email,$code]; }, fn ()=>$GLOBALS['otp_test_clock']);
};
$GLOBALS['otp_test_clock']=$now;
$tick = function ($delta) { $GLOBALS['otp_test_clock'] += $delta; };
foreach ([''=>null, 'a@example.test;b@example.test'=>null, 'bad;pilot@example.test'=>null,
    'pilot@EXAMPLE.test; pilot@example.test'=>'pilot@example.test'] as $raw=>$expected) {
    $check(PortalOtpService::email($raw)===$expected,'strict single recipient normalization');
}
foreach (['missing'=>null,'empty'=>array_replace($target,['email'=>'']), 'multiple'=>array_replace($target,['email'=>'a@example.test;b@example.test']), 'unauthorized'=>array_replace($target,['email'=>'other@example.test'])] as $label=>$row) {
    $count=count($sent); $s=$makeOtp($row,$label); $token=$s->request('127.0.0.1','900123456','PF-001');
    $check(strlen($token)===64 && count($sent)===$count,'generic challenge without mail: '.$label);
    $check($s->verify('127.0.0.1',$token,'00000000')===null,'dummy challenge cannot authorize: '.$label);
}
$s=$makeOtp($target,'valid'); $token=$s->request('127.0.0.1','900123456','PF-001'); $code=end($sent)[1];
$check(preg_match('/\A[0-9]{8}\z/',$code)===1,'eight digit cryptographic code');
$wrong=$code==='00000000'?'11111111':'00000000';
$check($s->verify('127.0.0.1',$token,$wrong)===null,'incorrect OTP rejected');
$auth=$s->verify('127.0.0.1',$token,$code);
$check(is_string($auth) && $s->grant($auth)['proforma_id']==='15','correct OTP issues exact proforma grant');
$check($s->verify('127.0.0.1',$token,$code)===null,'used OTP rejected');
$check($s->grant('forged')===null,'forged session rejected');
$check(!str_contains(file_get_contents($otpRoot.'/valid/state.enc'),$code),'state contains no plaintext OTP');
$tick(900); $check($s->grant($auth)===null,'grant expires absolutely after 15 minutes');
$s=$makeOtp($target,'expired'); $token=$s->request('ip','d','n'); $code=end($sent)[1]; $tick(300);
$check($s->verify('ip',$token,$code)===null,'OTP expires exactly at five minutes');
$s=$makeOtp($target,'attempts'); $token=$s->request('ip','d','n'); $code=end($sent)[1]; $wrong=$code==='00000000'?'11111111':'00000000';
for($i=0;$i<5;$i++) $s->verify('ip',$token,$wrong);
$check($s->verify('ip',$token,$code)===null,'five failures exhaust challenge');
$s=$makeOtp($target,'resend'); $old=$s->request('ip','d','n'); $oldCode=end($sent)[1]; $count=count($sent);
$s->request('ip','d','n'); $check(count($sent)===$count,'resend blocked before 60 seconds');
$tick(60); $new=$s->request('ip','d','n'); $newCode=end($sent)[1];
$check(count($sent)===$count+1 && $s->verify('ip',$old,$oldCode)===null,'resend invalidates old challenge');
$check($s->verify('ip',$new,$newCode)!==null,'resent OTP works');
// Pair and destination limits independently, with IPs varied.
foreach (['pair','destination'] as $type) {
    $s=$makeOtp($target,'limit-'.$type); $count=count($sent);
    for($i=0;$i<4;$i++) { $s->request('ip'.$i,'d',$type==='pair'?'n':'n'.$i); $tick(60); }
    $check(count($sent)===$count+3,$type.' limited to three per 15 minutes');
    $tick(901);
    for($i=0;$i<4;$i++) { $s->request('ip'.($i+4),'d',$type==='pair'?'n':'later'.$i); $tick(60); }
    $check(count($sent)===$count+6,$type.' limited to six per hour');
}
// Distinct authorized destinations isolate IP-only limits.
$resolverCalls=0;
$s=new PortalOtpService(new PrivateOtpStore($otpRoot.'/ip',$otpKey),$otpKey,[],function()use(&$resolverCalls){$resolverCalls++;return null;},fn()=>throw new RuntimeException('No mail expected'),fn()=>$GLOBALS['otp_test_clock']);
for($i=0;$i<6;$i++) $s->request('ip','d','n'.$i);
$check($resolverCalls===5,'IP request limit before lookup: five/minute');
for($batch=0;$batch<6;$batch++) { $tick(61); for($i=0;$i<5;$i++) $s->request('ip','d','batch'.$batch.'-'.$i); }
$check($resolverCalls===30,'IP request limit thirty/hour');
$s=$makeOtp($target,'verify-ip'); $token=$s->request('ip','d','n'); $code=end($sent)[1];
for($i=0;$i<20;$i++) $s->verify('ip','nonexistent'.$i,'00000000');
$check($s->verify('ip',$token,$code)===null,'verification IP limit includes nonexistent challenges');
$check($s->verify('another-ip',$token,$code)!==null,'IP limit does not consume unrelated challenge');
$broken=$otpRoot.'/not-directory'; file_put_contents($broken,'blocked');
$s=new PortalOtpService(new PrivateOtpStore($broken,$otpKey),$otpKey,['pilot@example.test'],fn()=>$target,function()use(&$sent){$sent[]='unexpected';},fn()=>$GLOBALS['otp_test_clock']);
$count=count($sent);$token=$s->request('ip','d','n');
$check(count($sent)===$count && $s->verify('ip',$token,'00000000')===null,'storage failure fails closed');

// Real ownership resolver with a SELECT-only fake; email is fetched only after identity.
$fixture=$base; $fixture['email']=[['email'=>'pilot@example.test']];
$reader=new class($fixture) implements OtpReader {
    public array $calls=[];
    public function __construct(public array $rows) {}
    public function select(string $sql,array $bindings):array {
        $key=array_search($sql,OtpReadService::QUERIES,true);
        if($key===false) throw new RuntimeException('Unapproved SQL');
        $this->calls[]=[$key,$bindings]; return $this->rows[$key];
    }
    public function close():void {}
};
$resolver=new OtpReadService($reader); $resolved=$resolver->otpTarget('900123456','PF-001');
$check($resolved['cliente_id']==='10' && end($reader->calls)===['email',['10']],'email query uses exact resolved client ID');
$check(!in_array('detalle',array_column($reader->calls,0),true),'OTP resolution does not query concepts');
$check($resolver->authorizedDetail($resolved)!==null,'authorized identity can retrieve detail');
$reader->calls=[]; $foreign=array_replace($resolved,['proforma_id'=>'999']);
$check($resolver->authorizedDetail($foreign)===null && !in_array('detalle',array_column($reader->calls,0),true),'grant for another proforma blocked before detail');
$reader->rows['relacion'][]=['id_cliente'=>'99']; $reader->calls=[];
$check($resolver->otpTarget('900123456','PF-001')===null && !in_array('email',array_column($reader->calls,0),true),'ambiguous relationship blocks email query');

// Provider payload is intercepted. Real HTTP is never permitted.
$app->instance('env','local');
$app['config']->set('portal_otp.send_enabled',true);
$app['config']->set('services.resend',['key'=>'fake','from_address'=>'otp@example.test']);
Http::fake(['api.resend.com/*'=>Http::response(['id'=>'fake'],200)]);
(new PortalOtpMailer)->send('pilot@example.test','12345678');
Http::assertSent(fn($r)=>$r->url()==='https://api.resend.com/emails' && $r['to']===['pilot@example.test'] && !isset($r['attachments']) && !isset($r['cc']) && !isset($r['bcc']) && count($r->data())===4);
$check(true,'Resend mocked: one recipient and no attachments');
$app['config']->set('portal_otp.send_enabled',false);
try { (new PortalOtpMailer)->send('pilot@example.test','12345678'); $check(false,'mail switch'); }
catch(RuntimeException) { $check(true,'real mail disabled by default switch'); }

$app['config']->set('portal_otp.key',$otpKey);
$app['config']->set('portal_otp.directory',$otpRoot.'/http');
$bodies=[];
foreach ([['documento'=>'900123456','numero'=>'PF-001'],['documento'=>'bad','numero'=>'absent']] as $input) {
    $response=$send('local','127.0.0.1','POST','http://127.0.0.1:8789',$input);
    $bodies[]=$response->getContent();
    $check(str_contains($response->getContent(),PortalOtpService::MESSAGE) && !str_contains($response->getContent(),'invoice-card'),'HTTP request cannot reveal detail before OTP');
    foreach($response->headers->getCookies() as $c) $check($c->getPath()==='/portal' && $c->isHttpOnly() && $c->getSameSite()==='strict','portal-only cookie attributes');
}
$check($bodies[0]===$bodies[1],'valid/invalid request bodies indistinguishable');
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision?numero=5521','GET',[],['laravel_session'=>'admin','portal_otp_auth'=>'forged'],[],['REMOTE_ADDR'=>'127.0.0.1']);
$check(!str_contains($kernel->handle($request)->getContent(),'invoice-card'),'admin/forged cookies and target parameters cannot bypass revision');
// Multi-recipient selection: only opaque IDs and masked labels cross the boundary.
$multi=array_replace($target,['email'=>'pilot@example.test;second@example.test']);
$s=$makeOtp($multi,'multi'); $count=count($sent); $token=$s->request('ip','d','n');
$options=$s->choices($token);
$check(count($options)===2 && count($sent)===$count,'two emails require selection and never broadcast');
$check(!str_contains(json_encode($options),'pilot@') && !str_contains(json_encode($options),'second@'),'selection DTO does not expose full addresses');
$s->choose('ip',$token,'pilot@example.test');
$check(count($sent)===$count,'arbitrary email is not accepted as identifier');
$s->choose('ip',$token,bin2hex(random_bytes(24)));
$check(count($sent)===$count,'forged opaque identifier cannot send');
$s->choose('ip',$token,$options[0]['id']);
$check(count($sent)===$count+1 && end($sent)[0]==='pilot@example.test','valid selection sends only chosen authorized destination');
$s->choose('ip',$token,$options[0]['id']);
$check(count($sent)===$count+1,'selection identifier cannot be replayed');
$s=$makeOtp($multi,'multi-denied'); $token=$s->request('ip','d','n'); $options=$s->choices($token); $count=count($sent);
$s->choose('ip',$token,$options[1]['id']);
$check(count($sent)===$count,'selected email outside allowlist is blocked');
$current=$multi;
$s=new PortalOtpService(new PrivateOtpStore($otpRoot.'/multi-changed',$otpKey),$otpKey,['pilot@example.test'],function()use(&$current){return $current;},function($e,$c)use(&$sent){$sent[]=[$e,$c];},fn()=>$GLOBALS['otp_test_clock']);
$token=$s->request('ip','d','n'); $options=$s->choices($token); $current['email']='second@example.test'; $count=count($sent);
$s->choose('ip',$token,$options[0]['id']);
$check(count($sent)===$count,'removed email fails fresh server-side membership check');
$current=$multi;
$tick(60); $token=$s->request('ip','d','n'); $options=$s->choices($token); $current['cliente_id']='99';
$s->choose('ip',$token,$options[0]['id']);
$check(count($sent)===$count,'changed client identity fails fresh server-side ownership check');
$s=$makeOtp($multi,'multi-expired'); $token=$s->request('ip','d','n'); $options=$s->choices($token); $tick(300);
$s->choose('ip',$token,$options[0]['id']);
$check(count($sent)===$count && $s->choices($token)===[],'expired selection cannot send');
$maskedHtml=view('portal.revision',['detail'=>null,'submitted'=>false,'otpStage'=>true,'otpError'=>false,'emailChoices'=>$options])->render();
$check(str_contains($maskedHtml,'name="option"') && !str_contains($maskedHtml,'pilot@example.test') && !str_contains($maskedHtml,'second@example.test'),'selection HTML contains opaque IDs and masks only');

// Two independent PHP processes contend for the same file lock and OTP.
$s=$makeOtp($target,'concurrent'); $token=$s->request('ip','d','n'); $code=end($sent)[1];
$workerInput=json_encode(['directory'=>$otpRoot.'/concurrent','key'=>$otpKey,'now'=>$GLOBALS['otp_test_clock'],'token'=>$token,'code'=>$code]);
$workers=[];
for($i=0;$i<2;$i++) {
    $process=proc_open([PHP_BINARY,'-n','-d','extension_dir='.ini_get('extension_dir'),'-d','extension=openssl',__DIR__.'/portal_otp_worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process)) throw new RuntimeException('Cannot run concurrency test');
    fwrite($pipes[0],$workerInput); fclose($pipes[0]); $workers[]=[$process,$pipes];
}
$wins=0;
foreach($workers as [$process,$pipes]) {
    $result=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
    $check(proc_close($process)===0 && $error==='' && in_array($result,['0','1'],true),'concurrent worker completed safely');
    $wins+=(int)$result;
}
$check($wins===1,'exactly one concurrent OTP consumption succeeds');

// End-to-end real controller, cookies, mailer and store; only SQL/provider are faked.
$gateway=new class($target,$detail) extends \App\Portal\ReadOnly\PortalReadGateway {
    public int $detailCalls=0;
    public function __construct(private array $target,private array $dto){}
    public function target(string $document,string $number):?array { return $document===$this->target['documento'] && $number===$this->target['numero'] ? $this->target : null; }
    public function detail(array $grant):?array { $this->detailCalls++; return $grant==array_diff_key($this->target,['email'=>true]) ? $this->dto : null; }
};
$app->instance(\App\Portal\ReadOnly\PortalReadGateway::class,$gateway);
$app['config']->set('portal_otp.send_enabled',true);
$app['config']->set('portal_otp.allowlist','pilot@example.test');
$app['config']->set('portal_otp.directory',$otpRoot.'/http-success');
$deliveredCode=null;
Http::fake(['api.resend.com/*'=>function($r)use(&$deliveredCode){ preg_match('/[0-9]{8}/',$r['text'],$m); $deliveredCode=$m[0]; return Http::response(['id'=>'fake'],200); }]);
$response=$send('local','127.0.0.1','POST','http://127.0.0.1:8789',['documento'=>$target['documento'],'numero'=>$target['numero']]);
$challengeCookie=null;
foreach($response->headers->getCookies() as $c) if($c->getName()==='portal_otp_challenge') $challengeCookie=$c->getValue();
$check($deliveredCode!==null && $gateway->detailCalls===0 && !str_contains($response->getContent(),'invoice-card'),'HTTP valid request sends mocked OTP without revealing detail');
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision','POST',['action'=>'verify','code'=>$deliveredCode],['portal_otp_challenge'=>$challengeCookie],[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_ORIGIN'=>'http://127.0.0.1:8789']);
$response=$kernel->handle($request); $authCookie=null;
foreach($response->headers->getCookies() as $c) if($c->getName()==='portal_otp_auth') $authCookie=$c->getValue();
$check($authCookie!==null && str_contains($response->getContent(),'invoice-card') && $gateway->detailCalls===1,'HTTP OTP verification renders authorized detail');
$check(!str_contains($kernel->handle($request)->getContent(),'invoice-card'),'HTTP replay does not render detail');
foreach(['','?numero=another'] as $query) {
    $request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision'.$query,'GET',[],['portal_otp_auth'=>$authCookie],[],['REMOTE_ADDR'=>'127.0.0.1']);
    $response=$kernel->handle($request);
    $check(str_contains($response->getContent(),'invoice-card')===($query===''),'HTTP session restricted to original proforma');
}
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision','POST',['action'=>'restart'],['portal_otp_auth'=>$authCookie],[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_ORIGIN'=>'http://127.0.0.1:8789']);
$check(str_contains($kernel->handle($request)->getContent(),'Solicitar código'),'new consultation returns to identification');
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision','GET',[],['portal_otp_auth'=>$authCookie],[],['REMOTE_ADDR'=>'127.0.0.1']);
$check(!str_contains($kernel->handle($request)->getContent(),'invoice-card'),'new consultation revokes previous server grant');
$app['config']->set('portal_otp.send_enabled',false);
unset($GLOBALS['otp_test_clock']);

// HTTP selection binds the opaque option to the challenge cookie.
$app->instance(\App\Portal\ReadOnly\PortalReadGateway::class,new class($multi) extends \App\Portal\ReadOnly\PortalReadGateway {
    public function __construct(private array $target){}
    public function target(string $d,string $n):?array {return $this->target;}
    public function detail(array $grant):?array {throw new RuntimeException('No detail before OTP');}
});
$app['config']->set('portal_otp.directory',$otpRoot.'/http-multi');
$app['config']->set('portal_otp.send_enabled',true);
$multiSent=0;
Http::fake(['api.resend.com/*'=>function($r)use(&$multiSent){$multiSent++;return Http::response(['id'=>'fake'],200);}]);
$response=$send('local','127.0.0.1','POST','http://127.0.0.1:8789',['documento'=>'900123456','numero'=>'PF-001']);
$html=$response->getContent();$cookie=null;
foreach($response->headers->getCookies() as $c) if($c->getName()==='portal_otp_challenge') $cookie=$c->getValue();
preg_match('/<option value="([a-f0-9]+)">p\*\*\*@example.test<\/option>/',$html,$match);
$check(isset($match[1]) && $multiSent===0 && !str_contains($html,'pilot@example.test'),'HTTP multiple addresses masked with no automatic mail');
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision','POST',['action'=>'choose','option'=>$match[1]],['portal_otp_challenge'=>'different-browser'],[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_ORIGIN'=>'http://127.0.0.1:8789']);
$kernel->handle($request);
$check($multiSent===0,'HTTP option from a different challenge cannot send');
$request=\Illuminate\Http\Request::create('http://127.0.0.1:8789/portal/revision','POST',['action'=>'choose','option'=>$match[1],'email'=>'attacker@example.test'],['portal_otp_challenge'=>$cookie],[],['REMOTE_ADDR'=>'127.0.0.1','HTTP_ORIGIN'=>'http://127.0.0.1:8789']);
$response=$kernel->handle($request);
$check($multiSent===1 && str_contains($response->getContent(),'Ingresa tu código') && !str_contains($response->getContent(),'invoice-card'),'HTTP selection sends exactly one OTP without detail');
Http::assertSent(fn($r)=>$r['to']===['pilot@example.test']);
$check(true,'posted arbitrary email ignored in HTTP selection');
$app['config']->set('portal_otp.send_enabled',false);
