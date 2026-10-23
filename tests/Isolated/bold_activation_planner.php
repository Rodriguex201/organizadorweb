<?php
// Run with php -n -d extension=pdo_sqlite; never bootstrap Laravel.
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) {
    throw new RuntimeException('Only SQLite :memory: is permitted.');
}
$root=dirname(__DIR__,2);
spl_autoload_register(function($class)use($root){if(str_starts_with($class,'App\\'))require $root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Payments\Bold\{ActivationRuleResolver,ActivationTaskRepository};
use App\Services\BoldActivationPlanner;
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
class TestPDO extends PDO {public bool $fail=false; public function commit():bool{if($this->fail){$this->fail=false;throw new RuntimeException('simulated crash');}return parent::commit();}}
$p=new TestPDO('sqlite::memory:');
$repo=ActivationTaskRepository::isolated($p);
$p->exec("CREATE TABLE intentos_pago(id INTEGER PRIMARY KEY,proforma_id INT,cliente_id INT,pasarela TEXT DEFAULT 'bold',ambiente TEXT DEFAULT 'sandbox',estado_aplicacion TEXT DEFAULT 'APLICADO',estado TEXT DEFAULT 'PAGADO',estado_remoto TEXT DEFAULT 'PAID',aplicado_at TEXT DEFAULT '2026-01-01',proforma_aplicada_id INT,ambiente_verificado INT DEFAULT 1);
CREATE TABLE sg_proform(id INTEGER PRIMARY KEY,id_cobro INT,nit TEXT,estado INT DEFAULT 4,vtotal TEXT DEFAULT '100.00');
CREATE TABLE clientes_potenciales(idclientes_potenciales INTEGER PRIMARY KEY,codigo TEXT,nit TEXT);
CREATE TABLE valores_externos(id_cobro INTEGER PRIMARY KEY,id_cliente TEXT,Proforma INT DEFAULT 4);
CREATE TABLE sg_proford(id INTEGER PRIMARY KEY,proforma_id INT,ref_codigo TEXT,orden INT DEFAULT 1);
CREATE TABLE conceptos(codigo TEXT PRIMARY KEY);
INSERT INTO conceptos VALUES ('0010');
CREATE TABLE activaciones_pago(id INTEGER PRIMARY KEY,intento_pago_id INT,proforma_id INT,cliente_id INT,servicio TEXT,clave_idempotencia TEXT UNIQUE,empresa_codigo TEXT,regla_version TEXT,conceptos_origen TEXT,estado TEXT,error_codigo TEXT,objetivo_sha256 TEXT,proximo_intento_at TEXT,ultimo_intento_at TEXT,token_ejecucion TEXT,reserva_hasta TEXT,intentos INT DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(intento_pago_id,servicio));
CREATE UNIQUE INDEX serial_company ON activaciones_pago(empresa_codigo,servicio) WHERE estado IN ('EJECUTANDO','VERIFICANDO','INDETERMINADA');");
function seed(TestPDO $p,int $id,?string $company='TEST'):void{
    $p->exec("INSERT INTO intentos_pago(id,proforma_id,cliente_id,proforma_aplicada_id) VALUES ($id,$id,$id,$id);INSERT INTO sg_proform(id,id_cobro,nit) VALUES ($id,$id,'N$id');INSERT INTO valores_externos(id_cobro,id_cliente) VALUES ($id,'$id');INSERT INTO sg_proford(proforma_id,ref_codigo) VALUES ($id,'0010')");
    $p->prepare('INSERT INTO clientes_potenciales VALUES (?,?,?)')->execute([$id,$company,'N'.$id]);
}
$config=require $root.'/config/bold_activations.php';$resolver=new ActivationRuleResolver($config);$planner=new BoldActivationPlanner($repo,$resolver);
seed($p,1);$r=$planner->one(1);
check($r['result']==='PLANIFICADO'&&$r['motivo']==='REGLA_NO_APROBADA','applied known concept creates review');
$before=$p->query('SELECT * FROM activaciones_pago')->fetchAll();
check($planner->one(1)['result']==='YA_PLANIFICADO'&&$before===$p->query('SELECT * FROM activaciones_pago')->fetchAll(),'repeat makes no mutation');
seed($p,2);$p->exec("INSERT INTO sg_proford(proforma_id,ref_codigo) VALUES (2,'UNKNOWN'),(2,'0010')");$r=$planner->one(2);
$origins=json_decode($p->query('SELECT conceptos_origen FROM activaciones_pago WHERE intento_pago_id=2')->fetchColumn(),true);
check($r['motivo']==='CONCEPTO_DESCONOCIDO'&&array_column($origins,'ref_codigo')===['0010','UNKNOWN','0010'],'multiple concepts preserved including duplicates and unknown');
seed($p,3,null);check($planner->one(3)['motivo']==='CLIENTE_SIN_CODIGO','missing company stays review');
seed($p,4);$p->fail=true;check($planner->one(4)['result']==='REINTENTAR'&&(int)$p->query('SELECT COUNT(*) FROM activaciones_pago WHERE intento_pago_id=4')->fetchColumn()===0,'crash before commit rolls back');
check(count($planner->batch())===1&&$planner->one(4)['result']==='YA_PLANIFICADO','next batch recovers missing planning');
seed($p,5);$p->exec("UPDATE intentos_pago SET estado_aplicacion='PENDIENTE' WHERE id=5");check($planner->one(5)['result']==='NO_APLICADO','unapplied payment excluded');
check($planner->batch()===[],'batch does not replan');
foreach([6,7]as$id){seed($p,$id);$p->prepare("INSERT INTO activaciones_pago(intento_pago_id,servicio,empresa_codigo,estado,objetivo_sha256) VALUES (?,'ACTIVACION_NORMAL','SAMECOMPANY','PENDIENTE',?)")->execute([$id,str_repeat('a',64)]);$ids[]=(int)$p->lastInsertId();}
$now='2026-10-23 12:00:00.000000';$until='2026-10-23 12:05:00.000000';
check($repo->reserve($ids[0],$now,$until,str_repeat('b',64))['result']==='RESERVADA','first company reservation');
check($repo->reserve($ids[1],$now,$until,str_repeat('c',64))['result']==='OCUPADA','other payment same company service blocked');
$p->exec("UPDATE activaciones_pago SET estado='INDETERMINADA' WHERE id=".$ids[0]);
check($repo->reserve($ids[1],$now,$until,str_repeat('d',64))['result']==='OCUPADA','uncertain result retains company lock');
check($repo->reserve(1,$now,$until,str_repeat('a',64))['result']==='NO_RESERVABLE','review planning cannot execute');
foreach(['DisabledNormalActivationAdapter','DisabledEventsActivationAdapter']as$name){$class='App\\Payments\\Bold\\Activation\\'.$name;$adapter=new $class;foreach(['consult'=>[[]],'comparePrecondition'=>[[],[]],'writeAbsoluteObjective'=>[[],[],[]],'rereadAndVerify'=>[[],[]]]as$method=>$args){try{$adapter->$method(...$args);throw new LogicException('allowed');}catch(RuntimeException $e){check($e->getMessage()==='ACTIVACION_REMOTA_DESHABILITADA',"$name $method disabled");}}}
foreach([array_replace($config,['remote_enabled'=>true]),array_replace($config,['rules'=>['0010'=>[]]])]as$bad){try{new ActivationRuleResolver($bad);throw new LogicException('allowed');}catch(RuntimeException){check(true,'unsafe configuration rejected');}}
try{ActivationTaskRepository::open(['database'=>'organizador'],'local','sandbox');throw new LogicException('allowed');}catch(RuntimeException){check(true,'wrong database rejected before connection');}
echo "All isolated planner tests passed. No network or MySQL drivers.\n";
