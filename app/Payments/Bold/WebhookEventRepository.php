<?php

namespace App\Payments\Bold;

use PDO;
use PDOException;
use RuntimeException;

final class WebhookEventRepository implements WebhookEventStore
{
    private function __construct(private PDO $pdo, private bool $isolated = false) {}

    public static function open(array $config, string $environment, string $boldEnvironment): self
    {
        $host = $config['host'] ?? null;
        $port = (string) ($config['port'] ?? '');
        if ($environment !== 'local' || $boldEnvironment !== 'sandbox'
            || ($config['database'] ?? null) !== 'organizadorprueba'
            || !is_string($host) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/', $host)
            || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || !is_string($config['username'] ?? null) || $config['username'] === ''
            || !is_string($config['password'] ?? null) || $config['password'] === '') {
            throw new RuntimeException('Configuración de eventos no permitida.');
        }
        try {
            $pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname=organizadorprueba;charset=utf8mb4',
                $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 1,
                    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                ]);
            $repo = new self($pdo);
            $repo->guard();
            // Bound contention without changing any global setting or table.
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
            return $repo;
        } catch (\Throwable) { throw new RuntimeException('Persistencia de eventos no disponible.'); }
    }

    public static function isolated(PDO $pdo): self
    {
        if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')
            || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') throw new RuntimeException('Solo SQLite en memoria.');
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $db) {
            if ($db['file'] !== '') throw new RuntimeException('Solo SQLite en memoria.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return new self($pdo, true);
    }

    private function guard(): void
    {
        if (!$this->isolated && $this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'organizadorprueba') {
            throw new RuntimeException('Base no permitida.');
        }
    }

    private function table(string $name): string
    {
        return ($this->isolated ? '' : '`organizadorprueba`.').'`'.$name.'`';
    }

    public function existingHash(string $eventId): ?string
    {
        $this->guard();
        $q = $this->pdo->prepare('SELECT raw_sha256 FROM '.$this->table('eventos_pago')
            ." WHERE pasarela = 'bold' AND ambiente = 'sandbox' AND evento_id = ? LIMIT 1");
        $q->execute([$eventId]);
        $hash = $q->fetchColumn();
        return $hash === false ? null : $hash;
    }

    public function attempt(string $link): ?array
    {
        $this->guard();
        $q = $this->pdo->prepare('SELECT id, importe, moneda, ambiente, ambiente_verificado, transaction_id FROM '
            .$this->table('intentos_pago')." WHERE pasarela = 'bold' AND ambiente = 'sandbox' AND payment_link = ? LIMIT 2");
        $q->execute([$link]);
        $rows = $q->fetchAll();
        return count($rows) === 1 ? $rows[0] : null;
    }

    public function save(array $event): int
    {
        $this->guard();
        $columns = ['evento_id', 'intento_pago_id', 'payment_link', 'payment_id', 'merchant_id', 'tipo',
            'importe', 'moneda', 'raw_sha256', 'estado_validacion', 'motivo'];
        $sql = 'INSERT INTO '.$this->table('eventos_pago').' ('.implode(',', $columns)
            .",pasarela,ambiente,validacion_firma,modo_validacion) VALUES ("
            .implode(',', array_fill(0, count($columns), '?')).",'bold','sandbox',1,'SANDBOX_EMPTY_HMAC')";
        try {
            $this->pdo->prepare($sql)->execute(array_map(static fn ($key) => $event[$key], $columns));
            return 200;
        } catch (PDOException $e) {
            $duplicate = $this->isolated
                ? (($e->errorInfo[1] ?? null) === 19 && str_contains($e->getMessage(), 'UNIQUE constraint failed'))
                : (($e->errorInfo[1] ?? null) === 1062);
            if (!$duplicate) throw new RuntimeException('No se pudo guardar el evento.');
            $hash = $this->existingHash($event['evento_id']);
            if ($hash === null) throw new RuntimeException('No se pudo recuperar el evento.');
            return hash_equals($hash, $event['raw_sha256']) ? 200 : 409;
        }
    }
}
