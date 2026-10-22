<?php
if(PDO::getAvailableDrivers()!==['sqlite']||extension_loaded('mysqli'))throw new RuntimeException('SQLite only');
require __DIR__.'/../../app/Payments/Bold/AttemptRepository.php';
require __DIR__.'/../../app/Payments/Bold/BoldHttpClient.php';
require __DIR__.'/../../app/Services/BoldReconciliationService.php';
use App\Payments\Bold\{AttemptRepository,BoldHttpClient};
use App\Services\BoldReconciliationService;
$p=new PDO('sqlite::memory:');$repo=AttemptRepository::isolated($p);
$p->exec('CREATE TABLE intentos_pago(id INTEGER PRIMARY KEY,proforma_id INTEGER,cliente_id INTEGER,referencia TEXT,pasarela TEXT,ambiente TEXT,importe TEXT,moneda TEXT,estado TEXT,payment_link TEXT,estado_remoto TEXT,transaction_id TEXT,ambiente_verificado INTEGER,http_status INTEGER,error_codigo TEXT,ultima_consulta_at TEXT,created_at TEXT,updated_at TEXT)');
$p->exec("INSERT INTO intentos_pago VALUES(1,12,34,'fixture-ref','bold','sandbox','123.45','COP','ACTIVO','fixture-link','ACTIVE',NULL,1,NULL,NULL,NULL,'2020-01-01 00:00:00','2020-01-01 00:00:00')");
$http=new class implements BoldHttpClient {
 public int $calls=0;public array $remote=['id'=>'fixture-link','reference'=>'fixture-ref','total'=>'123.45','amount_type'=>'CLOSE','is_sandbox'=>true,'status'=>'PROCESSING'];public ?Closure $during=null;public bool $timeout=false;
 public function create(string $key,string $body):array{throw new RuntimeException('POST prohibited');}
 public int $status=200;
 public function lookup(string $key,string $id):array{++$this->calls;if($id!=='fixture-link')throw new RuntimeException('unknown link');if($this->during)($this->during)();return ['status'=>$this->timeout?null:$this->status,'body'=>json_encode($this->remote),'content_type'=>'application/json','uncertain'=>$this->timeout];}
};
$now=1800000000;$svc=new BoldReconciliationService($repo,$http,'fixture-key',function()use(&$now){return $now;});
$n=0;$check=function($ok,$name)use(&$n){if(!$ok)throw new RuntimeException($name);$n++;echo "PASS $name\n";};
$read=fn()=>$p->query('SELECT * FROM intentos_pago WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$frozen=array_intersect_key($read(),array_flip(['proforma_id','cliente_id','referencia','importe','moneda','payment_link','ambiente']));
$check($svc->one(1)['estado']==='PROCESANDO','processing pending');
$check($svc->one(1)['result']==='skipped'&&$http->calls===1,'backoff skips HTTP');
$now+=3601;$http->remote['status']='PAID';$http->remote['transaction_id']='fixture-transaction';
$check($svc->one(1)['estado']==='PAGADO','processing to paid');
$check($read()['transaction_id']==='fixture-transaction','transaction retained');
$http->remote['status']='ACTIVE';$now+=3601;
$check($svc->one(1)['result']==='skipped'&&$http->calls===2&&$read()['estado']==='PAGADO','paid never degraded nor fetched again');
$check(array_intersect_key($read(),$frozen)===$frozen,'snapshot unchanged');
foreach(['id','reference','total','amount_type','is_sandbox','status']as$field){
 $p->exec("UPDATE intentos_pago SET estado='ACTIVO',ultima_consulta_at=NULL");$saved=$http->remote[$field];$http->remote[$field]=$field==='is_sandbox'?false:'invalid';
 $check($svc->one(1)['estado']==='REVISION','mismatch '.$field);$http->remote[$field]=$saved;
}
foreach(['ACTIVE'=>'ACTIVO','REJECTED'=>'PROCESANDO','CANCELLED'=>'CANCELADO','EXPIRED'=>'EXPIRADO']as$remote=>$local){
 $p->exec("UPDATE intentos_pago SET estado='INDETERMINADO',ultima_consulta_at=NULL");$http->remote['status']=$remote;
 $check($svc->one(1)['estado']===$local,'remote '.$remote);
}
$p->exec("UPDATE intentos_pago SET estado='LINK_CREADO',ultima_consulta_at=NULL");$http->timeout=true;
$check($svc->one(1)['estado']==='LINK_CREADO'&&$read()['error_codigo']==='GET_UNAVAILABLE','timeout preserves block');$http->timeout=false;
$p->exec("UPDATE intentos_pago SET estado='ACTIVO',ultima_consulta_at=NULL");$http->remote['status']='ACTIVE';
$http->during=function()use($p){$p->exec("UPDATE intentos_pago SET estado='PAGADO'");};
$check($svc->one(1)['result']==='stale'&&$read()['estado']==='PAGADO','concurrent paid wins over delayed response');$http->during=null;
foreach([null,'different-transaction']as$tx){
 $p->exec("UPDATE intentos_pago SET estado='PROCESANDO',ultima_consulta_at=NULL");
 $http->remote['status']='PAID';$http->remote['transaction_id']=$tx;
 $check($svc->one(1)['estado']==='REVISION'&&$read()['transaction_id']==='fixture-transaction','missing or contradictory transaction blocked');
}
$p->exec("UPDATE intentos_pago SET estado='ACTIVO',ultima_consulta_at=NULL");$http->remote['status']='ACTIVE';$http->remote['currency']='USD';
$check($svc->one(1)['estado']==='REVISION'&&$read()['error_codigo']==='CURRENCY_MISMATCH','explicit contradictory currency blocked');unset($http->remote['currency']);
$p->exec("UPDATE intentos_pago SET estado='ACTIVO',ultima_consulta_at=NULL");$http->remote['total']=123.45;
$check($svc->one(1)['estado']==='ACTIVO','JSON numeric total preserved as decimal lexeme');$http->remote['total']='123.45';
$p->exec("UPDATE intentos_pago SET estado='PROCESANDO',estado_remoto='PROCESSING',ultima_consulta_at=NULL");
$http->status=404;$http->remote['status']='EXPIRED';$before=$read();
$check($svc->one(1)['estado']==='REVISION'&&$read()['error_codigo']==='GET_HTTP_404'&&(int)$read()['http_status']===404,'404 blocks in review with explicit reason');
$check($read()['estado_remoto']==='PROCESSING'&&$read()['transaction_id']===$before['transaction_id'],'404 body does not establish remote expiry or transaction');
$check(array_intersect_key($read(),$frozen)===$frozen&&$read()['ultima_consulta_at']!==null,'404 retains snapshot and records query time');
$calls=$http->calls;$check($svc->one(1)['result']==='skipped'&&$http->calls===$calls,'404 review is not polled again');
$p->exec("UPDATE intentos_pago SET estado='PAGADO',ultima_consulta_at=NULL");
$check($svc->one(1)['result']==='skipped'&&$http->calls===$calls&&$read()['estado']==='PAGADO','paid is not queried or degraded by 404');
$http->status=200;$http->remote['status']='ACTIVE';
$p->exec("UPDATE intentos_pago SET estado='ACTIVO',ultima_consulta_at=NULL,payment_link=NULL");$calls=$http->calls;
$check($svc->one(1)['result']==='skipped'&&$http->calls===$calls,'no link no GET');
$p->exec("UPDATE intentos_pago SET payment_link='fixture-link'");
for($i=2;$i<=25;$i++)$p->exec("INSERT INTO intentos_pago SELECT $i,proforma_id,cliente_id,referencia,pasarela,ambiente,importe,moneda,estado,payment_link,estado_remoto,transaction_id,ambiente_verificado,http_status,error_codigo,NULL,created_at,updated_at FROM intentos_pago WHERE id=1");
$calls=$http->calls;$check(count($svc->batch(100))===20&&$http->calls-$calls===20,'batch hard limit 20');
$check(!array_key_exists('moneda_verificada_get',$svc->one(999)),'missing attempt no effects');
echo "OK $n checks, SQLite memory only\n";
