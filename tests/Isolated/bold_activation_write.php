<?php
// Run with php -n. No framework, credentials, database drivers or network.
if (class_exists(PDO::class) && PDO::getAvailableDrivers() !== []) throw new RuntimeException('Run php -n without database drivers');
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__, 2).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
use App\Payments\Bold\Activation\NormalActivationWriteAdapter;
use App\Payments\Bold\Activation\NormalActivationWriteConnection;
use App\Payments\Bold\Activation\PdoNormalActivationWriteConnection;
use App\Payments\Bold\ActivationRuleResolver;

final class MemoryRemote
{
    public array $dates = ['2026-01-29','2026-01-31','2026-01-29','2026-01-31'];
    public bool $locked = false;
    public int $writes = 0;
    public int $opens = 0;
    public string $fault = '';
    public ?Closure $duringWrite = null;
    public function open(array $task): NormalActivationWriteConnection
    {
        if ($task['conexion_logica'] !== 'mysql_213' || $task['base_destino'] !== 'test1') throw new RuntimeException();
        $this->opens++;
        if ($this->fault === 'offline' || ($this->fault === 'verify_offline' && $this->opens === 2)) throw new RuntimeException('private transport error');
        return new class($this) implements NormalActivationWriteConnection {
            private ?array $pending = null;
            public function __construct(private MemoryRemote $db) {}
            public function begin(): void {
                if ($this->db->locked) throw new RuntimeException('lock timeout');
                $this->db->locked = true; $this->pending = $this->db->dates;
            }
            public function lockedSnapshot(): array {
                $d = $this->pending;
                $individual = [['seg_fecha'=>$d[0],'seg_maxima'=>$d[1]]];
                if ($this->db->fault === 'duplicate') $individual[] = $individual[0];
                return ['individual'=>$individual, 'global'=>[['emprobra'=>'TEST1','emprfpago'=>$d[2],'emprfinit'=>$d[3]]]];
            }
            public function updateIndividual(array $previous, array $objective): int {
                $this->db->writes++;
                if ($this->db->duringWrite) { $f = $this->db->duringWrite; $this->db->duringWrite = null; $f(); }
                if (array_slice($this->pending,0,2) !== $previous) return 0;
                $this->pending = [...$objective,...array_slice($this->pending,2,2)]; return 1;
            }
            public function updateGlobal(array $previous, array $objective): int {
                $this->db->writes++;
                if ($this->db->fault === 'partial') return 0;
                if (in_array($this->db->fault, ['technical','rollback_lost'], true)) throw new RuntimeException('private transport error');
                if (array_slice($this->pending,2,2) !== $previous) return 0;
                $this->pending = [...array_slice($this->pending,0,2),...$objective]; return 1;
            }
            public function commit(): void {
                $this->db->dates = $this->pending; $this->pending = null; $this->db->locked = false;
                if ($this->db->fault === 'verify_changed') $this->db->dates[3] = '2026-03-01';
                if ($this->db->fault === 'lost_commit') throw new RuntimeException('private lost response');
            }
            public function rollback(): void {
                if ($this->db->fault === 'rollback_lost') throw new RuntimeException('rollback unconfirmed');
                if ($this->pending !== null) { $this->pending = null; $this->db->locked = false; }
            }
        };
    }
}
$task = ['servicio'=>'ACTIVACION_NORMAL','empresa_codigo'=>'TEST1','conexion_logica'=>'mysql_213','base_destino'=>'test1',
    'regla_version'=>ActivationRuleResolver::VERSION,'fecha_inicio_anterior'=>'2026-01-29','fecha_fin_anterior'=>'2026-01-31',
    'fecha_inicio_global_anterior'=>'2026-01-29','fecha_fin_global_anterior'=>'2026-01-31',
    'fecha_inicio_objetivo'=>'2026-02-26','fecha_fin_objetivo'=>'2026-02-28'];
$task['objetivo_sha256'] = ActivationRuleResolver::fingerprint($task);
$checks = 0;
function check(bool $condition, string $name): void { global $checks; if (!$condition) throw new RuntimeException($name); $checks++; }
function adapter(MemoryRemote $db): NormalActivationWriteAdapter { return new NormalActivationWriteAdapter($db->open(...), true); }
$db = new MemoryRemote; $initial = $db->dates;
check((new NormalActivationWriteAdapter($db->open(...)))->execute($task)['error_codigo'] === 'ESCRITURA_REMOTA_DESHABILITADA' && $db->opens === 0,'default disabled');
$policy = require dirname(__DIR__,2).'/config/bold_activations.php';
check($policy['remote_enabled'] === false,'configuration unchanged');
check(PdoNormalActivationWriteConnection::adapter([], $policy)->execute($task)['error_codigo'] === 'ESCRITURA_REMOTA_DESHABILITADA','PDO factory disabled before connection');
check(adapter($db)->execute($task)['estado'] === 'COMPLETADA','successful write');
check($db->dates === ['2026-02-26','2026-02-28','2026-02-26','2026-02-28'] && $db->opens === 2,'absolute dates and fresh verification');
check(adapter($db)->execute($task)['estado'] === 'YA_COMPLETADA' && $db->writes === 2,'idempotency no extra write');
$db = new MemoryRemote; $db->dates[0] = '2026-01-28';
check(adapter($db)->execute($task)['estado'] === 'REVISION' && $db->writes === 0,'changed precondition');
foreach (['partial'=>'REVISION','technical'=>'REINTENTABLE'] as $fault=>$expected) {
    $db = new MemoryRemote; $db->fault = $fault;
    check(adapter($db)->execute($task)['estado'] === $expected && $db->dates === $initial && !$db->locked,'rollback '.$fault);
    $db->fault = ''; check(adapter($db)->execute($task)['estado'] === 'COMPLETADA','retry '.$fault);
}
foreach (['lost_commit','verify_offline'] as $fault) {
    $db = new MemoryRemote; $db->fault = $fault;
    check(adapter($db)->execute($task)['estado'] === 'INDETERMINADA','uncertain '.$fault);
    $writes = $db->writes; $db->fault = '';
    check(adapter($db)->execute($task)['estado'] === 'YA_COMPLETADA' && $db->writes === $writes,'recover without rewrite '.$fault);
}
$db = new MemoryRemote; $db->fault = 'offline';
check(adapter($db)->execute($task)['estado'] === 'REINTENTABLE' && $db->writes === 0,'offline before writing');
$db = new MemoryRemote; $db->fault = 'duplicate';
check(adapter($db)->execute($task)['estado'] === 'REVISION' && $db->writes === 0,'ambiguous row set');
$db = new MemoryRemote; $db->fault = 'rollback_lost';
check(adapter($db)->execute($task)['estado'] === 'INDETERMINADA','unconfirmed rollback');
$db = new MemoryRemote; $db->fault = 'verify_changed';
check(adapter($db)->execute($task)['estado'] === 'REVISION','post commit different state');
$db = new MemoryRemote; $second = null;
$db->duringWrite = function () use ($db,$task,&$second) { $second = adapter($db)->execute($task); };
check(adapter($db)->execute($task)['estado'] === 'COMPLETADA','first concurrent executor');
check($second['estado'] === 'REINTENTABLE' && $db->writes === 2,'second executor cannot write while locked');
check(adapter($db)->execute($task)['estado'] === 'YA_COMPLETADA' && $db->writes === 2,'second executor retry no extension');
foreach (['base_destino'=>'organizador','conexion_logica'=>'default','fecha_fin_objetivo'=>'2026-03-01'] as $key=>$value) {
    $db = new MemoryRemote; $bad = $task; $bad[$key] = $value;
    check(adapter($db)->execute($bad)['estado'] === 'REVISION' && $db->opens === 0,'reject modified frozen field '.$key);
}
echo "OK: $checks simulated checks; zero database/network connections.\n";
