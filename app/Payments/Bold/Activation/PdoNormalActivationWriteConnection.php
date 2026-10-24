<?php
namespace App\Payments\Bold\Activation;

use PDO;
use Throwable;

/** Explicit, frozen destination only. No Laravel default connection or fallback. */
final class PdoNormalActivationWriteConnection implements NormalActivationWriteConnection
{
    private PDO $pdo;
    private string $base;
    private string $code;

    public static function adapter(#[\SensitiveParameter] array $namedConnections, array $policy): NormalActivationWriteAdapter
    {
        $enabled = ($policy['remote_enabled'] ?? false) === true;
        return new NormalActivationWriteAdapter(static function (array $task) use ($namedConnections, $enabled) {
            $config = $namedConnections[$task['conexion_logica']] ?? [];
            return new self($config, $task, $enabled);
        }, $enabled);
    }

    public function __construct(#[\SensitiveParameter] array $config, array $task, bool $enabled = false)
    {
        if (!$enabled) throw new ActivationReadException('REVISION', 'ESCRITURA_REMOTA_DESHABILITADA');
        NormalActivationWriteAdapter::validateTask($task);
        $this->base = $task['base_destino']; $this->code = $task['empresa_codigo'];
        if (!is_string($config['host'] ?? null) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/', $config['host'])
            || !ctype_digit((string)($config['port'] ?? '')) || (int)$config['port'] < 1 || (int)$config['port'] > 65535
            || !is_string($config['username'] ?? null) || trim($config['username']) === ''
            || !is_string($config['password'] ?? null) || $config['password'] === '')
            throw new ActivationReadException('REVISION', 'CONEXION_EXPLICITA_INCOMPLETA');
        try {
            $this->pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$this->base.';charset=utf8mb4',
                $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false,
                    PDO::ATTR_TIMEOUT=>5, PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
            if ($this->pdo->query('SELECT DATABASE()')->fetchColumn() !== $this->base) throw new \RuntimeException();
            $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 5');
        } catch (Throwable) { throw new ActivationReadException('REINTENTABLE', 'CONEXION_REMOTA_NO_DISPONIBLE'); }
    }

    public function begin(): void
    {
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $this->pdo->beginTransaction();
    }

    public function lockedSnapshot(): array
    {
        // Consistent lock order for all executors. Full matching sets, never LIMIT 1.
        $global = $this->query('SELECT emprobra, emprfpago, emprfinit FROM `empresas`.`empresas` WHERE emprobra = ? FOR UPDATE', [$this->code])->fetchAll();
        $individual = $this->query('SELECT seg_fecha, seg_maxima FROM `'.$this->base.'`.`xxxxsegx` FOR UPDATE')->fetchAll();
        // After acquiring metadata locks: refuse engines incapable of atomic rollback.
        $engines = $this->query('SELECT TABLE_SCHEMA, TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE (TABLE_SCHEMA = ? AND TABLE_NAME = ?) OR (TABLE_SCHEMA = ? AND TABLE_NAME = ?)',
            [$this->base,'xxxxsegx','empresas','empresas'])->fetchAll();
        if (count($engines) !== 2) throw new ActivationReadException('REVISION', 'TABLAS_TRANSACCIONALES_NO_VERIFICADAS');
        foreach ($engines as $table) if (strtoupper((string)$table['ENGINE']) !== 'INNODB')
            throw new ActivationReadException('REVISION', 'MOTOR_NO_TRANSACCIONAL');
        return ['individual'=>$individual, 'global'=>$global];
    }

    public function updateIndividual(array $previous, array $objective): int
    {
        return $this->query('UPDATE `'.$this->base.'`.`xxxxsegx` SET seg_fecha = ?, seg_maxima = ? WHERE seg_fecha = ? AND seg_maxima = ?', [...$objective,...$previous])->rowCount();
    }

    public function updateGlobal(array $previous, array $objective): int
    {
        return $this->query('UPDATE `empresas`.`empresas` SET emprfpago = ?, emprfinit = ? WHERE emprobra = ? AND BINARY emprobra = BINARY ? AND emprfpago = ? AND emprfinit = ?',
            [...$objective,$this->code,$this->code,...$previous])->rowCount();
    }

    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    private function query(string $sql, array $values = []): \PDOStatement
    {
        $query = $this->pdo->prepare($sql); $query->execute($values); return $query;
    }
}
