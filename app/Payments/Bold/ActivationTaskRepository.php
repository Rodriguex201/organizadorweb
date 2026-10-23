<?php
namespace App\Payments\Bold;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Dedicated test database; mutations restricted to activaciones_pago. */
final class ActivationTaskRepository
{
    private function __construct(private PDO $pdo, private bool $isolated) {}

    public static function open(array $db, string $environment, string $boldEnvironment): self
    {
        if ($environment !== 'local' || $boldEnvironment !== 'sandbox' || ($db['database'] ?? '') !== 'organizadorprueba'
            || !is_string($db['host'] ?? null) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/', $db['host'])
            || !ctype_digit((string) ($db['port'] ?? '')) || (int) $db['port'] < 1 || (int) $db['port'] > 65535
            || !is_string($db['username'] ?? null) || trim($db['username']) === ''
            || !is_string($db['password'] ?? null) || $db['password'] === '') throw new RuntimeException('Destino de planificación no permitido.');
        try {
            $pdo = new PDO('mysql:host='.$db['host'].';port='.$db['port'].';dbname=organizadorprueba;charset=utf8mb4', $db['username'], $db['password'],
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5, PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
            $repo = new self($pdo, false); $repo->guard();
            $engines = $pdo->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='organizadorprueba' AND TABLE_NAME IN ('activaciones_pago','intentos_pago','sg_proform','sg_proford','clientes_potenciales','valores_externos','conceptos')")->fetchAll(PDO::FETCH_COLUMN);
            if (count($engines)!==7 || array_diff($engines,['InnoDB'])) throw new RuntimeException();
            return $repo;
        } catch (Throwable) { throw new RuntimeException('No se pudo abrir la persistencia de planificación.'); }
    }

    public static function isolated(PDO $pdo): self
    {
        if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli') || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite') throw new RuntimeException('Solo SQLite en memoria.');
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $db) if ($db['file'] !== '') throw new RuntimeException('Base persistente prohibida.');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        return new self($pdo, true);
    }

    private function guard(): void
    {
        if (!$this->isolated && $this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'organizadorprueba') throw new RuntimeException('Base activa no permitida.');
    }
    private function table(string $table): string
    {
        if (!in_array($table,['activaciones_pago','intentos_pago','sg_proform','sg_proford','clientes_potenciales','valores_externos','conceptos'],true)) throw new RuntimeException();
        return ($this->isolated?'':'`organizadorprueba`.').'`'.$table.'`';
    }
    private function rows(string $sql,array $args=[]): array { $q=$this->pdo->prepare($sql);$q->execute($args);return $q->fetchAll(); }
    private function rollback(): void { try { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); } catch (Throwable) {} }

    public function candidates(int $limit): array
    {
        $this->guard();$a=$this->table('intentos_pago');$t=$this->table('activaciones_pago');$limit=max(1,min(50,$limit));
        return array_column($this->rows("SELECT a.id FROM $a a WHERE a.pasarela='bold' AND a.ambiente='sandbox' AND a.estado_aplicacion='APLICADO' AND NOT EXISTS (SELECT 1 FROM $t t WHERE t.intento_pago_id=a.id) ORDER BY a.id LIMIT $limit"),'id');
    }

    public function plan(int $id, ActivationRuleResolver $resolver): array
    {
        if($id<1)throw new RuntimeException('Intento inválido.');$this->guard();
        if($this->pdo->inTransaction())throw new RuntimeException('Transacción dedicada requerida.');
        $a=$this->table('intentos_pago');$t=$this->table('activaciones_pago');$p=$this->table('sg_proform');
        $d=$this->table('sg_proford');$c=$this->table('clientes_potenciales');$v=$this->table('valores_externos');$catalog=$this->table('conceptos');
        $lock=$this->isolated?'':' FOR UPDATE';
        try {
            $this->pdo->beginTransaction();
            $attempt=$this->rows("SELECT * FROM $a WHERE id=? AND pasarela='bold' AND ambiente='sandbox'".$lock,[$id])[0]??null;
            if(!$attempt || $attempt['estado_aplicacion']!=='APLICADO'){$this->rollback();return ['id'=>$id,'result'=>'NO_APLICADO'];}
            $existing=$this->rows("SELECT id FROM $t WHERE intento_pago_id=? ORDER BY id".$lock,[$id]);
            if($existing!==[]){$this->rollback();return ['id'=>$id,'result'=>'YA_PLANIFICADO','tareas'=>array_column($existing,'id')];}
            $proforma=$this->rows("SELECT id,id_cobro,nit,estado,vtotal FROM $p WHERE id=?".$lock,[$attempt['proforma_id']])[0]??null;
            $client=$this->rows("SELECT idclientes_potenciales,codigo,nit FROM $c WHERE idclientes_potenciales=?".$lock,[$attempt['cliente_id']])[0]??null;
            if(!$proforma || !$client)throw new RuntimeException('Relación inexistente.');
            $lines=$this->rows("SELECT id,ref_codigo FROM $d WHERE proforma_id=? ORDER BY orden,id".$lock,[$proforma['id']]);
            $codes=array_values(array_unique(array_map(fn($line)=>(string)($line['ref_codigo']??''),$lines)));
            $known=[];
            foreach(array_chunk($codes,100)as$chunk){$marks=implode(',',array_fill(0,count($chunk),'?'));$known=array_merge($known,array_column($this->rows("SELECT codigo FROM $catalog WHERE codigo IN ($marks)",$chunk),'codigo'));}
            $error=null;
            if($attempt['estado']!=='PAGADO' || $attempt['estado_remoto']!=='PAID' || empty($attempt['aplicado_at'])
                || (int)$attempt['proforma_aplicada_id']!==(int)$proforma['id'] || (int)$attempt['ambiente_verificado']!==1) $error='PAGO_APLICADO_INCONSISTENTE';
            elseif(!in_array((int)$proforma['estado'],[4,6],true))$error='PROFORMA_ESTADO_INCONSISTENTE';
            elseif(trim((string)$client['nit'])==='' || trim((string)$client['nit'])!==trim((string)$proforma['nit']))$error='CLIENTE_NO_COINCIDE';
            $cobros=$this->rows("SELECT id_cliente,Proforma FROM $v WHERE id_cobro=?".$lock,[$proforma['id_cobro']]);
            if($error===null && (count($cobros)!==1 || (string)$cobros[0]['id_cliente']!==(string)$client['idclientes_potenciales'] || !in_array((int)$cobros[0]['Proforma'],[4,6],true)))$error='COBRO_NO_COINCIDE';
            $task=$resolver->resolve($lines,$known,$client['codigo'],$error);
            $key=hash('sha256',json_encode(['bold','sandbox',(string)$id,$task['servicio']],JSON_THROW_ON_ERROR));
            $q=$this->pdo->prepare("INSERT INTO $t (intento_pago_id,proforma_id,cliente_id,servicio,clave_idempotencia,empresa_codigo,regla_version,conceptos_origen,estado,error_codigo) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$id,$proforma['id'],$client['idclientes_potenciales'],$task['servicio'],$key,$task['empresa_codigo'],$task['regla_version'],json_encode($task['conceptos_origen'],JSON_THROW_ON_ERROR),$task['estado'],$task['error_codigo']]);
            $taskId=(int)$this->pdo->lastInsertId();$this->pdo->commit();
            return ['id'=>$id,'result'=>'PLANIFICADO','tareas'=>[$taskId],'estado'=>$task['estado'],'motivo'=>$task['error_codigo']];
        }catch(Throwable){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR','motivo'=>'PLANIFICACION_NO_CONFIRMADA'];}
    }

    /** Local reservation primitive only. No executor or remote call is provided. */
    public function reserve(int $id, string $now, string $until, string $token): array
    {
        if($id<1 || !preg_match('/\A[0-9a-f]{64}\z/',$token) || !self::timestamp($now) || !self::timestamp($until) || $until<=$now) throw new RuntimeException('Reserva inválida.');
        $this->guard();if($this->pdo->inTransaction())throw new RuntimeException('Transacción dedicada requerida.');
        $t=$this->table('activaciones_pago');$lock=$this->isolated?'':' FOR UPDATE';
        try{
            $this->pdo->beginTransaction();$row=$this->rows("SELECT * FROM $t WHERE id=?".$lock,[$id])[0]??null;
            if(!$row || !in_array($row['estado'],['PENDIENTE','REINTENTABLE'],true) || !in_array($row['servicio'],['ACTIVACION_NORMAL','EVENTOS'],true)
                || empty($row['empresa_codigo']) || empty($row['objetivo_sha256']) || ($row['proximo_intento_at']!==null && $row['proximo_intento_at']>$now)){
                $this->rollback();return ['id'=>$id,'result'=>'NO_RESERVABLE'];
            }
            $q=$this->pdo->prepare("UPDATE $t SET estado='EJECUTANDO',intentos=intentos+1,ultimo_intento_at=?,token_ejecucion=?,reserva_hasta=?,proximo_intento_at=NULL WHERE id=? AND estado=?");
            $q->execute([$now,$token,$until,$id,$row['estado']]);if($q->rowCount()!==1)throw new RuntimeException();
            $this->pdo->commit();return ['id'=>$id,'result'=>'RESERVADA'];
        }catch(PDOException $e){$this->rollback();$duplicate=$this->isolated ? str_contains($e->getMessage(),'UNIQUE constraint failed') : ($e->errorInfo[1]??0)===1062;return ['id'=>$id,'result'=>$duplicate?'OCUPADA':'REINTENTAR'];}
        catch(Throwable){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR'];}
    }
    private static function timestamp(string $value):bool{
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u',$value,new \DateTimeZone('UTC'));
        return $date!==false && $date->format('Y-m-d H:i:s.u')===$value;
    }
}
