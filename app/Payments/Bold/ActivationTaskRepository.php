<?php
namespace App\Payments\Bold;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use App\Payments\Bold\Activation\{RemoteActivationAdapter,DisabledNormalActivationAdapter};

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
            $engines = $pdo->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='organizadorprueba' AND TABLE_NAME IN ('activaciones_pago','intentos_pago','sg_proform','sg_proford','clientes_potenciales','valores_externos')")->fetchAll(PDO::FETCH_COLUMN);
            if (count($engines)!==6 || array_diff($engines,['InnoDB'])) throw new RuntimeException();
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

    public function plan(int $id, ActivationRuleResolver $resolver, RemoteActivationAdapter $adapter = new DisabledNormalActivationAdapter()): array
    {
        if($id<1)throw new RuntimeException('Intento inválido.');$this->guard();
        if($this->pdo->inTransaction())throw new RuntimeException('Transacción dedicada requerida.');
        $a=$this->table('intentos_pago');$t=$this->table('activaciones_pago');$p=$this->table('sg_proform');
        $d=$this->table('sg_proford');$c=$this->table('clientes_potenciales');$v=$this->table('valores_externos');
        $lock=$this->isolated?'':' FOR UPDATE';
        $companyLock=null;
        try {
            $this->pdo->beginTransaction();
            $attempt=$this->rows("SELECT * FROM $a WHERE id=? AND pasarela='bold' AND ambiente='sandbox'".$lock,[$id])[0]??null;
            if(!$attempt || $attempt['estado_aplicacion']!=='APLICADO'){$this->rollback();return ['id'=>$id,'result'=>'NO_APLICADO'];}
            $existing=$this->rows("SELECT id,servicio FROM $t WHERE intento_pago_id=? ORDER BY id".$lock,[$id]);
            if($existing!==[]){$this->rollback();return ['id'=>$id,'result'=>count($existing)===1&&$existing[0]['servicio']==='ACTIVACION_NORMAL'?'YA_PLANIFICADO':'REVISION_PLAN_LEGADO','tareas'=>array_column($existing,'id')];}
            $proforma=$this->rows("SELECT id,id_cobro,nit,estado,vtotal,fpag,fpago FROM $p WHERE id=?".$lock,[$attempt['proforma_id']])[0]??null;
            $client=$this->rows("SELECT idclientes_potenciales,codigo,nit FROM $c WHERE idclientes_potenciales=?".$lock,[$attempt['cliente_id']])[0]??null;
            if(!$proforma || !$client)throw new RuntimeException('Relación inexistente.');
            $lines=$this->rows("SELECT id,ref_codigo FROM $d WHERE proforma_id=? ORDER BY orden,id".$lock,[$proforma['id']]);
            $error=null;
            if($attempt['estado']!=='PAGADO' || $attempt['estado_remoto']!=='PAID' || empty($attempt['aplicado_at'])
                || (int)$attempt['proforma_aplicada_id']!==(int)$proforma['id'] || (int)$attempt['ambiente_verificado']!==1) $error='PAGO_APLICADO_INCONSISTENTE';
            elseif(!in_array((int)$proforma['estado'],[4,6],true))$error='PROFORMA_ESTADO_INCONSISTENTE';
            elseif($proforma['fpago']!=='BOLD' || (string)$proforma['vtotal']!==(string)$attempt['importe'] || $attempt['moneda']!=='COP')$error='PAGO_NETO_NO_COINCIDE';
            elseif(trim((string)$client['nit'])==='' || trim((string)$client['nit'])!==trim((string)$proforma['nit']))$error='CLIENTE_NO_COINCIDE';
            $cobros=$this->rows("SELECT id_cliente,Proforma FROM $v WHERE id_cobro=?".$lock,[$proforma['id_cobro']]);
            if($error===null && (count($cobros)!==1 || (string)$cobros[0]['id_cliente']!==(string)$client['idclientes_potenciales'] || !in_array((int)$cobros[0]['Proforma'],[4,6],true)))$error='COBRO_NO_COINCIDE';
            $company=$client['codigo'];$snapshot=null;
            if($error===null && is_string($company) && preg_match('/\A[A-Za-z0-9]{1,100}\z/',$company)){
                // Serialize before reading the simulated remote precondition, not just before writing.
                if(!$this->isolated){
                    $name=hash('sha256','organizadorprueba:activation:'.strtolower($company));
                    $acquired=$this->rows('SELECT GET_LOCK(?,0) AS acquired',[$name])[0]['acquired'];
                    if((int)$acquired!==1){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR','motivo'=>'EMPRESA_OCUPADA'];}
                    $companyLock=$name;
                }
                $blocking=$this->rows("SELECT id FROM $t WHERE LOWER(empresa_codigo)=? AND servicio='ACTIVACION_NORMAL' AND estado NOT IN ('COMPLETADA','NO_REQUIERE') ORDER BY id".$lock,[strtolower($company)]);
                if($blocking!==[]){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR','motivo'=>'EMPRESA_OCUPADA'];}
                try{$snapshot=$adapter->consult(['empresa_codigo'=>$company]);}
                catch(Throwable){$error='LECTURA_REMOTA_NO_DISPONIBLE';}
            }
            $task=$resolver->resolve($lines,$company,$proforma['fpag'],$snapshot,$error);
            $key=hash('sha256',json_encode(['bold','sandbox',(string)$id,$task['servicio']],JSON_THROW_ON_ERROR));
            $task=['intento_pago_id'=>$id,'proforma_id'=>$proforma['id'],'cliente_id'=>$client['idclientes_potenciales'],'clave_idempotencia'=>$key]+$task;
            $task['conceptos_origen']=json_encode($task['conceptos_origen'],JSON_THROW_ON_ERROR);
            $columns=implode(',',array_map(fn($column)=>'`'.$column.'`',array_keys($task)));
            $q=$this->pdo->prepare("INSERT INTO $t ($columns) VALUES (".implode(',',array_fill(0,count($task),'?')).')');
            $q->execute(array_values($task));
            $taskId=(int)$this->pdo->lastInsertId();$this->pdo->commit();
            return ['id'=>$id,'result'=>'PLANIFICADO','tareas'=>[$taskId],'estado'=>$task['estado'],'motivo'=>$task['error_codigo']];
        }catch(Throwable){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR','motivo'=>'PLANIFICACION_NO_CONFIRMADA'];}
        finally {if($companyLock!==null)try{$this->rows('SELECT RELEASE_LOCK(?)',[$companyLock]);}catch(Throwable){}}
    }

    /** Local reservation primitive only. No executor or remote call is provided. */
    public function reserve(int $id, string $now, string $until, string $token): array
    {
        if($id<1 || !preg_match('/\A[0-9a-f]{64}\z/',$token) || !self::timestamp($now) || !self::timestamp($until) || $until<=$now) throw new RuntimeException('Reserva inválida.');
        $this->guard();if($this->pdo->inTransaction())throw new RuntimeException('Transacción dedicada requerida.');
        $t=$this->table('activaciones_pago');$lock=$this->isolated?'':' FOR UPDATE';
        try{
            $this->pdo->beginTransaction();$row=$this->rows("SELECT * FROM $t WHERE id=?".$lock,[$id])[0]??null;
            $recover=$row && in_array($row['estado'],['EJECUTANDO','VERIFICANDO','INDETERMINADA'],true) && $row['reserva_hasta']!==null && $row['reserva_hasta']<=$now;
            if(!$row || (!in_array($row['estado'],['PENDIENTE','REINTENTABLE'],true) && !$recover) || $row['servicio']!=='ACTIVACION_NORMAL'
                || empty($row['empresa_codigo']) || empty($row['objetivo_sha256']) || ($row['proximo_intento_at']!==null && $row['proximo_intento_at']>$now)){
                $this->rollback();return ['id'=>$id,'result'=>'NO_RESERVABLE'];
            }
            $state=$recover?'INDETERMINADA':'EJECUTANDO';
            $q=$this->pdo->prepare("UPDATE $t SET estado=?,intentos=intentos+1,ultimo_intento_at=?,token_ejecucion=?,reserva_hasta=?,proximo_intento_at=NULL WHERE id=? AND estado=?");
            $q->execute([$state,$now,$token,$until,$id,$row['estado']]);if($q->rowCount()!==1)throw new RuntimeException();
            $this->pdo->commit();return ['id'=>$id,'result'=>'RESERVADA'];
        }catch(PDOException $e){$this->rollback();$duplicate=$this->isolated ? str_contains($e->getMessage(),'UNIQUE constraint failed') : ($e->errorInfo[1]??0)===1062;return ['id'=>$id,'result'=>$duplicate?'OCUPADA':'REINTENTAR'];}
        catch(Throwable){$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR'];}
    }

    /** Persist only a validated observation supplied by the adapter. Never writes remotely. */
    public function observe(int $id, string $token, string $now, ?array $snapshot, bool $writeMayHaveOccurred = false): array
    {
        $this->guard();
        if (!self::timestamp($now) || !preg_match('/\A[0-9a-f]{64}\z/',$token) || $this->pdo->inTransaction()) throw new RuntimeException('Observación inválida.');
        $t=$this->table('activaciones_pago');$lock=$this->isolated?'':' FOR UPDATE';
        try {
            $this->pdo->beginTransaction();
            $task=$this->rows("SELECT * FROM $t WHERE id=?".$lock,[$id])[0]??null;
            if ($task && $task['estado']==='COMPLETADA') {$this->rollback();return ['id'=>$id,'result'=>'YA_COMPLETADA'];}
            if (!$task || !in_array($task['estado'],['EJECUTANDO','VERIFICANDO','INDETERMINADA'],true)
                || !hash_equals((string)$task['token_ejecucion'],$token) || $task['reserva_hasta']<=$now) {
                $this->rollback();return ['id'=>$id,'result'=>'RESERVA_PERDIDA'];
            }
            $comparison=$snapshot===null?'CONSULTA_NO_DISPONIBLE':ActivationRuleResolver::compare($task,$snapshot);
            if ($comparison==='PRECONDICION') {$this->rollback();return ['id'=>$id,'result'=>'PRECONDICION','puede_escribir'=>true];}
            $uncertain=$writeMayHaveOccurred || $task['estado']==='INDETERMINADA';
            if (in_array($comparison,['CONSULTA_NO_DISPONIBLE','RESOLUCION_INCOMPLETA'],true)) {
                $state=$uncertain?'INDETERMINADA':'REINTENTABLE';$error=$uncertain?'RESULTADO_REMOTO_INCIERTO':'CONSULTA_NO_DISPONIBLE';
            } else {$state=$comparison==='OBJETIVO'?'COMPLETADA':'REVISION';$error=$state==='COMPLETADA'?null:$comparison;}
            $retain=$state==='INDETERMINADA';
            $next=$state==='REINTENTABLE'?(new \DateTimeImmutable($now,new \DateTimeZone('UTC')))->modify('+5 minutes')->format('Y-m-d H:i:s.u'):null;
            $q=$this->pdo->prepare("UPDATE $t SET estado=?,error_codigo=?,token_ejecucion=?,reserva_hasta=?,proximo_intento_at=?,verificado_at=? WHERE id=? AND token_ejecucion=?");
            $q->execute([$state,$error,$retain?$token:null,$retain?$task['reserva_hasta']:null,$next,$state==='COMPLETADA'?$now:null,$id,$token]);
            if ($q->rowCount()!==1) throw new RuntimeException();
            $this->pdo->commit();return ['id'=>$id,'result'=>$state,'motivo'=>$error];
        } catch(Throwable) {$this->rollback();return ['id'=>$id,'result'=>'REINTENTAR'];}
    }
    private static function timestamp(string $value):bool{
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u',$value,new \DateTimeZone('UTC'));
        return $date!==false && $date->format('Y-m-d H:i:s.u')===$value;
    }
}
