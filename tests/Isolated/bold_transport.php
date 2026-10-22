<?php
declare(strict_types=1);
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli') || extension_loaded('curl')) {
    throw new RuntimeException('Only SQLite memory; no real HTTP client');
}
require __DIR__.'/bold_attempts.php';

use App\Payments\Bold\BoldHttpClient;
use App\Services\BoldPaymentService;
use App\Portal\Otp\{PrivateOtpStore, PortalOtpService};

$scenario = function (string $mode, int $id) use ($reader, $gateway, $repo, $check): array {
    $reader->headers=[['id'=>(string)$id,'id_cobro'=>'20','estado'=>'2','vtotal'=>'129119.65']];
    $reader->relations=[['id_cliente'=>'10']];
    $target=['cliente_id'=>'10','proforma_id'=>(string)$id,'documento'=>'900123456','numero'=>'PF-1','email'=>'test@example.test'];
    $key=base64_encode(random_bytes(32));$code=null;
    $otp=new PortalOtpService(new PrivateOtpStore(sys_get_temp_dir().'/bold-http-'.bin2hex(random_bytes(8)),$key),$key,['test@example.test'],
        fn ()=>$target,function($email,$value)use(&$code){$code=$value;},fn()=>10000);
    $ch=$otp->request('ip','900123456','PF-1');$token=$otp->verify('ip',$ch,$code);
    $http=new class($mode,$repo,(string)$id,$reader,$check) implements BoldHttpClient {
        public int $posts=0;public int $gets=0;public string $reference='';public ?Closure $duringPost=null;
        public function __construct(private string $mode,private $repo,private string $id,private $reader,private Closure $check){}
        public function create(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $body):array {
            ++$this->posts;$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
            $row=$this->repo->blocking($this->id,'sandbox');$this->reference=$row['referencia'];
            ($this->check)($row['estado']==='SOLICITANDO'&&$data['reference']===$row['referencia']&&str_contains($body,'"total_amount":129119.65'),'dispatch uses claimed persisted amount/reference');
            if($this->duringPost)($this->duringPost)();
            if($this->mode==='post-throw')throw new RuntimeException('synthetic sensitive exception');
            if($this->mode==='post-timeout')return ['status'=>null,'uncertain'=>true,'body'=>'','content_type'=>null];
            $body=json_encode(['payload'=>['payment_link'=>'synthetic-'.$this->id,'url'=>'https://checkout.bold.co/pay/fixture-'.$this->id],'errors'=>[]]);
            if($this->mode==='post-invalid')$body='{';
            return ['status'=>$this->mode==='post-500'?500:200,'uncertain'=>false,'body'=>$body,'content_type'=>'application/json'];
        }
        public function lookup(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $id):array {
            ++$this->gets;$row=$this->repo->blocking($this->id,'sandbox');
            ($this->check)($row['estado']==='LINK_CREADO'&&(int)$row['ambiente_verificado']===0&&$id===$row['payment_link'],'GET only after persisted LINK_CREADO');
            if($this->mode==='get-timeout')return ['status'=>null,'uncertain'=>true,'body'=>'','content_type'=>null];
            $remote=['id'=>$id,'reference'=>$this->reference,'total'=>'129119.65','amount_type'=>'CLOSE','is_sandbox'=>true,'status'=>'ACTIVE'];
            foreach(['id','reference','total','amount_type','is_sandbox','status'] as $field) {
                if($this->mode==='mismatch-'.$field)$remote[$field]=$field==='is_sandbox'?false:($field==='total'?'1.00':'wrong');
            }
            if($this->mode==='source-changed')$this->reader->headers[0]['vtotal']='130000.00';
            return ['status'=>200,'uncertain'=>false,'body'=>$this->mode==='get-invalid'?'{':json_encode($remote),'content_type'=>'application/json'];
        }
    };
    $svc=new BoldPaymentService($otp,$gateway,['enabled'=>true,'environment'=>'sandbox','api_key'=>'synthetic-key'],'local',$http,$repo);
    return [$svc,$http,$token];
};
$id=300;
foreach(['success','post-timeout','post-throw','post-500','post-invalid','get-timeout','get-invalid',
    'mismatch-id','mismatch-reference','mismatch-total','mismatch-amount_type','mismatch-is_sandbox','mismatch-status'] as $mode) {
    [$svc,$http,$token]=$scenario($mode,++$id);
    $row=$svc->createLink($token);
    $expected=$mode==='success'?'ACTIVO':(in_array($mode,['post-timeout','post-throw','post-500','get-timeout'],true)?'INDETERMINADO':'REVISION');
    $check($row['estado']===$expected,'transport outcome '.$mode);
    $check($http->posts===1&&$http->gets===(str_starts_with($mode,'post-')?0:1),'one POST and conditional GET '.$mode);
    $again=$svc->createLink($token);
    $check($again['id']===$row['id']&&$http->posts===1&&$http->gets===(str_starts_with($mode,'post-')?0:1),'reuse without new HTTP '.$mode);
    $check(($expected==='ACTIVO')===isset($row['checkout_url']),'unverified checkout withheld '.$mode);
}
[$svc,$http,$token]=$scenario('success',++$id);
$http->duringPost=function()use($svc,$token,$check){$r=$svc->createLink($token);$check($r['estado']==='SOLICITANDO','concurrent call loses claim without sending');};
$svc->createLink($token);$check($http->posts===1&&$http->gets===1,'concurrent orchestration one POST');
[$svc,$http,$token]=$scenario('source-changed',++$id);
$reject(fn()=>$svc->createLink($token),'source change during GET blocks activation');
$row=$repo->blocking((string)$id,'sandbox');
$check($row['estado']==='REVISION'&&$row['importe']==='129119.65','changed source preserves frozen amount');
[$svc,$http,$token]=$scenario('success',++$id);
$reject(fn()=>$svc->createLink('forged'),'invalid authorization before HTTP');
$check($http->posts===0&&$http->gets===0,'no HTTP for invalid grant');
echo "OK: $count checks including transport; SQLite memory, fake HTTP only.\n";
