<?php
declare(strict_types=1);
if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')) throw new RuntimeException('Only SQLite memory');
require __DIR__.'/../../app/Services/BoldPaymentApplicationService.php';
use App\Services\BoldPaymentApplicationService;

function fixture(): array {
    $p = new PDO('sqlite::memory:');
    $s = BoldPaymentApplicationService::isolated($p, fn () => new DateTimeImmutable('2026-10-22 02:00:00.123456+00:00'));
    $p->exec("CREATE TABLE intentos_pago (id INTEGER PRIMARY KEY,proforma_id INT,cliente_id INT,referencia TEXT,pasarela TEXT,ambiente TEXT,importe TEXT,moneda TEXT,estado TEXT,estado_remoto TEXT,ambiente_verificado INT,payment_link TEXT,transaction_id TEXT,estado_aplicacion TEXT DEFAULT 'PENDIENTE',aplicado_at TEXT,aplicado_por TEXT,aplicacion_ultimo_intento_at TEXT,aplicacion_error_codigo TEXT,estado_proforma_anterior INT,proforma_aplicada_id INT GENERATED ALWAYS AS (CASE WHEN estado_aplicacion='APLICADO' THEN proforma_id ELSE NULL END) STORED,UNIQUE(proforma_aplicada_id),UNIQUE(pasarela,ambiente,transaction_id),CHECK(estado_aplicacion<>'APLICADO' OR (aplicado_at IS NOT NULL AND estado='PAGADO' AND estado_remoto='PAID')))");
    $p->exec("CREATE TABLE sg_proform (id INT PRIMARY KEY,id_cobro INT,nit TEXT,estado INT,vtotal TEXT,fpago TEXT,fpag TEXT,comprobante_pago TEXT,rpdf TEXT,retencion_porcentaje TEXT,retencion_valor TEXT,valor_original TEXT)");
    // Deliberately no PK here: duplicate relationship must still fail closed.
    $p->exec('CREATE TABLE valores_externos (id_cobro INT,id_cliente TEXT,Proforma INT,concepto TEXT)');
    $p->exec('CREATE TABLE clientes_potenciales (idclientes_potenciales INT PRIMARY KEY,nit TEXT)');
    $p->exec("INSERT INTO clientes_potenciales VALUES(7,'900000001')");
    $p->exec("INSERT INTO valores_externos VALUES(9,'7',2,'fixture')");
    $p->exec("INSERT INTO sg_proform VALUES(5,9,'900000001',2,'96.75',NULL,NULL,'keep-proof','keep-pdf','3.250','3.25','100.00')");
    $p->exec("INSERT INTO intentos_pago(id,proforma_id,cliente_id,referencia,pasarela,ambiente,importe,moneda,estado,estado_remoto,ambiente_verificado,payment_link,transaction_id) VALUES(1,5,7,'fixture-ref','bold','sandbox','96.75','COP','PAGADO','PAID',1,'fixture-link','fixture-tx')");
    return [$p,$s];
}
function allRows(PDO $p,string $table):array{return $p->query('SELECT * FROM '.$table)->fetchAll();}
$n=0;$check=function(bool $ok,string $label)use(&$n){if(!$ok)throw new RuntimeException($label);++$n;echo "PASS $label\n";};
[$p,$s]=fixture();$before=allRows($p,'sg_proform')[0];
$check($s->apply(1,'cli:test')['result']==='APLICADO','confirmed payment applied');
$after=allRows($p,'sg_proform')[0];$a=allRows($p,'intentos_pago')[0];
$check($after['estado']===4&&$after['fpago']==='BOLD'&&$after['fpag']==='2026-10-21'&&allRows($p,'valores_externos')[0]['Proforma']===4,'both states and Bogota date');
foreach(['comprobante_pago','rpdf','vtotal','retencion_porcentaje','retencion_valor','valor_original']as$key)$check($after[$key]===$before[$key],'preserved '.$key);
$check($a['estado']==='PAGADO'&&$a['aplicado_por']==='cli:test'&&$a['aplicado_at']==='2026-10-22 02:00:00.123456'&&$a['estado_proforma_anterior']===2,'durable application evidence');
$snapshot=[allRows($p,'intentos_pago'),allRows($p,'sg_proform'),allRows($p,'valores_externos')];
$check($s->apply(1,'cli:repeat')['result']==='YA_APLICADO'&&$snapshot===[allRows($p,'intentos_pago'),allRows($p,'sg_proform'),allRows($p,'valores_externos')],'repeat no writes');
$p->exec('UPDATE sg_proform SET estado=6');$check($s->apply(1,'cli:test')['result']==='YA_APLICADO'&&allRows($p,'sg_proform')[0]['estado']===6,'later invoicing never degraded');

$cases=[
 'paid manually'=>"UPDATE sg_proform SET estado=4",
 'invoiced'=>"UPDATE sg_proform SET estado=6",
 'changed total'=>"UPDATE sg_proform SET vtotal='97.00'",
 'missing exact cobro'=>"UPDATE sg_proform SET id_cobro=NULL",
 'missing cobro row'=>'DELETE FROM valores_externos',
 'duplicate cobro'=>"INSERT INTO valores_externos SELECT * FROM valores_externos",
 'foreign client'=>"UPDATE valores_externos SET id_cliente='8'",
 'missing client'=>'DELETE FROM clientes_potenciales',
 'wrong identity'=>"UPDATE clientes_potenciales SET nit='900000002'",
 'paid cobro'=>'UPDATE valores_externos SET Proforma=4',
 'invoiced cobro'=>'UPDATE valores_externos SET Proforma=6',
 'unsynchronized cobro'=>'UPDATE valores_externos SET Proforma=3',
 'currency mismatch'=>"UPDATE intentos_pago SET moneda='USD'",
 'unverified environment'=>'UPDATE intentos_pago SET ambiente_verificado=0',
 'missing transaction'=>'UPDATE intentos_pago SET transaction_id=NULL',
 'missing link'=>'UPDATE intentos_pago SET payment_link=NULL',
];
foreach($cases as$name=>$sql){
 [$p,$s]=fixture();$p->exec($sql);$snap=[allRows($p,'sg_proform'),allRows($p,'valores_externos')];
 $r=$s->apply(1,'cli:test');$a=allRows($p,'intentos_pago')[0];
 $check($r['result']==='REVISION'&&$a['estado']==='PAGADO'&&$a['aplicado_at']===null&&$snap===[allRows($p,'sg_proform'),allRows($p,'valores_externos')],$name.' blocks without business writes');
}
foreach(["estado='REVISION'","estado='PROCESANDO',estado_remoto='PROCESSING'","estado='PAGADO',estado_remoto='ACTIVE'"]as$set){
 [$p,$s]=fixture();$p->exec('UPDATE intentos_pago SET '.$set);$snap=allRows($p,'intentos_pago');
 $check($s->apply(1,'cli:test')['result']==='NO_CONFIRMADO'&&$snap===allRows($p,'intentos_pago'),'unconfirmed untouched');
}
foreach(['valores_externos','intentos_pago']as$table){
 [$p,$s]=fixture();$snap=[allRows($p,'intentos_pago'),allRows($p,'sg_proform'),allRows($p,'valores_externos')];
 $p->exec("CREATE TRIGGER fail_write BEFORE UPDATE ON $table BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
 $check($s->apply(1,'cli:test')['result']==='REINTENTAR'&&$snap===[allRows($p,'intentos_pago'),allRows($p,'sg_proform'),allRows($p,'valores_externos')],'atomic rollback at '.$table);
 $p->exec('DROP TRIGGER fail_write');$check($s->apply(1,'cli:test')['result']==='APLICADO','retry after failure '.$table);
}
[$p,$s]=fixture();$p->exec("UPDATE intentos_pago SET estado_aplicacion='REVISION'");$snap=allRows($p,'intentos_pago');
$check($s->apply(1,'cli:test')['result']==='REVISION'&&$snap===allRows($p,'intentos_pago'),'review never automatically retried');
$check($s->apply(999,'cli:test')['result']==='NO_ENCONTRADO','unknown attempt');
[$p,$s]=fixture();$p->exec('UPDATE sg_proform SET estado=3');$p->exec('UPDATE valores_externos SET Proforma=3');
$check($s->apply(1,'cli:test')['result']==='APLICADO','sent proforma can be applied');
[$p,$s]=fixture();
$p->exec("INSERT INTO intentos_pago(id,proforma_id,cliente_id,referencia,pasarela,ambiente,importe,moneda,estado,estado_remoto,ambiente_verificado,payment_link,transaction_id,estado_aplicacion,aplicado_at) SELECT 2,proforma_id,cliente_id,'other-ref',pasarela,ambiente,importe,moneda,estado,estado_remoto,ambiente_verificado,'other-link','other-tx','APLICADO','2026-10-20' FROM intentos_pago WHERE id=1");
$check($s->apply(1,'cli:test')['motivo']==='PROFORMA_YA_APLICADA_OTRO_INTENTO'&&allRows($p,'sg_proform')[0]['estado']===2,'other durable application blocks');
foreach(['organizador','']as$database){
 try { BoldPaymentApplicationService::configured(['environment'=>'sandbox','database'=>['database'=>$database]],'local');$ok=false; }catch(RuntimeException){$ok=true;}
 $check($ok,'wrong database refused before connection');
}
echo "OK $n checks; SQLite memory only; no HTTP or Laravel bootstrap.\n";
