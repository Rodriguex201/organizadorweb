<?php

namespace App\Portal\ReadOnly;

use PDO;
use RuntimeException;

/** Dedicated connection: no Eloquent, observers, events, jobs or default DB. */
final class MySqlReviewReader implements SelectReader
{
    private function __construct(private PDO $pdo) {}

    public static function open(array $config): self
    {
        // Fail before opening a socket if the configured destination changes.
        if (($config['database'] ?? null) !== 'organizadorprueba'
            || ($config['host'] ?? null) !== '168.232.32.74'
            || (string) ($config['port'] ?? '') !== '3306'
            || ($config['username'] ?? null) !== 'RmSoft20X') {
            throw new RuntimeException('Unexpected portal review destination.');
        }
        foreach (['database', 'username', 'password'] as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '') {
                throw new RuntimeException('Read-only connection is not configured.');
            }
        }
        if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/', $config['host'])
            || !preg_match('/\A[a-zA-Z0-9_]+\z/', $config['database'])
            || !ctype_digit((string) $config['port'])) {
            throw new RuntimeException('Invalid read-only connection configuration.');
        }
        $pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ],
        );
        // First statement on this exact connection, before any application data.
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'organizadorprueba') {
            throw new RuntimeException('Unexpected active database.');
        }
        // One consistent snapshot for ownership and detail. These commands do not
        // alter application data. Failure aborts; never fall back to read-write.
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        return new self($pdo);
    }

    public function select(string $sql, array $bindings): array
    {
        if (!in_array($sql, ProformaReadService::QUERIES, true)) {
            throw new RuntimeException('Query is not in the read-only allowlist.');
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll();
    }

    public function close(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
