<?php

namespace App\Payments\Bold;

use PDO;
use PDOException;
use RuntimeException;

/** All mutations are bounded INSERT/UPDATE on intentos_pago; no default Laravel DB. */
final class AttemptRepository
{
    private const TRANSITIONS = [
        'RESERVADO' => ['SOLICITANDO', 'REVISION', 'FALLIDO'],
        'SOLICITANDO' => ['LINK_CREADO', 'INDETERMINADO', 'REVISION'],
        'LINK_CREADO' => ['ACTIVO', 'REVISION', 'INDETERMINADO'],
        'ACTIVO' => ['REVISION', 'INDETERMINADO'],
        'INDETERMINADO' => ['REVISION'],
    ];
    private const MUTABLE = [
        'payment_link', 'checkout_url', 'http_status', 'error_codigo',
        'estado_remoto', 'ambiente_verificado', 'ultima_consulta_at',
        'solicitud_iniciada_at',
    ];

    private function __construct(private PDO $pdo, private bool $isolated = false) {}

    public static function open(array $config, string $environment, string $boldEnvironment): self
    {
        $host = $config['host'] ?? null;
        $port = (string) ($config['port'] ?? '');
        if ($environment !== 'local' || $boldEnvironment !== 'sandbox'
            || ($config['database'] ?? null) !== 'organizadorprueba'
            || !is_string($host) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/', $host)
            || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || !is_string($config['username'] ?? null) || trim($config['username']) === ''
            || !is_string($config['password'] ?? null) || $config['password'] === '') {
            throw new RuntimeException('Destino de intentos de pago no permitido.');
        }
        try {
            $pdo = new PDO(
                'mysql:host='.$host.';port='.$port.';dbname=organizadorprueba;charset=utf8mb4',
                $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                ],
            );
            $repository = new self($pdo);
            $repository->guard();
            return $repository;
        } catch (\Throwable) {
            throw new RuntimeException('No se pudo abrir la persistencia de intentos.');
        }
    }

    /** Tests must run without any MySQL driver and with a private memory database. */
    public static function isolated(PDO $pdo): self
    {
        if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')
            || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Solo SQLite en memoria.');
        }
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $db) {
            if ($db['file'] !== '') throw new RuntimeException('Base persistente no permitida.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return new self($pdo, true);
    }

    private function guard(): void
    {
        if (!$this->isolated && $this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'organizadorprueba') {
            throw new RuntimeException('Base activa no permitida.');
        }
    }

    private function table(): string
    {
        return $this->isolated ? '`intentos_pago`' : '`organizadorprueba`.`intentos_pago`';
    }

    /** Internal draft from BoldPaymentService, never request data. Unique key arbitrates races. */
    public function reserve(array $draft): array
    {
        if (($draft['environment'] ?? null) !== 'sandbox' || ($draft['currency'] ?? null) !== 'COP'
            || !preg_match('/\APF_[a-f0-9]{32}\z/', $draft['reference'] ?? '')
            || !preg_match('/\A[0-9]+\.[0-9]{2}\z/', $draft['amount'] ?? '')
            || !preg_match('/\A[1-9][0-9]*\z/', (string) ($draft['proforma_id'] ?? ''))
            || !preg_match('/\A[1-9][0-9]*\z/', (string) ($draft['cliente_id'] ?? ''))) {
            throw new RuntimeException('Borrador de intento inválido.');
        }
        $this->guard();
        try {
            $statement = $this->pdo->prepare('INSERT INTO '.$this->table()
                .' (proforma_id,cliente_id,referencia,pasarela,ambiente,importe,moneda,estado) VALUES (?,?,?,\'bold\',?,?,?,\'RESERVADO\')');
            $statement->execute([$draft['proforma_id'], $draft['cliente_id'], $draft['reference'],
                $draft['environment'], $draft['amount'], $draft['currency']]);
        } catch (PDOException $e) {
            // Only a unique-key collision is reusable; all other errors fail closed.
            $duplicate = $this->isolated
                ? (($e->errorInfo[1] ?? null) === 19 && str_contains($e->getMessage(), 'UNIQUE constraint failed'))
                : (($e->errorInfo[1] ?? null) === 1062);
            if (!$duplicate) throw new RuntimeException('No se pudo reservar el intento.');
        }
        $row = $this->blocking($draft['proforma_id'], $draft['environment']);
        if (!$row) throw new RuntimeException('No se pudo recuperar el intento bloqueante.');
        return $row;
    }

    public function blocking(string $proforma, string $environment): ?array
    {
        $this->guard();
        $statement = $this->pdo->prepare('SELECT * FROM '.$this->table()
            .' WHERE proforma_id=? AND pasarela=\'bold\' AND ambiente=? AND bloqueo_activo=1');
        $statement->execute([$proforma, $environment]);
        return $statement->fetch() ?: null;
    }

    /** Compare-and-swap: exactly one caller can win RESERVADO -> SOLICITANDO. */
    public function transition(int $id, string $reference, string $from, string $to, array $fields = []): bool
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)
            || array_diff(array_keys($fields), self::MUTABLE)) {
            throw new RuntimeException('Transición o campos no permitidos.');
        }
        $this->guard();
        $fields['estado'] = $to;
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        $set = implode(',', array_map(fn ($key) => '`'.$key.'`=?', array_keys($fields)));
        try {
            $statement = $this->pdo->prepare('UPDATE '.$this->table().' SET '.$set
                .' WHERE id=? AND referencia=? AND estado=? AND ambiente=\'sandbox\' AND pasarela=\'bold\'');
            $statement->execute([...array_values($fields), $id, $reference, $from]);
            return $statement->rowCount() === 1;
        } catch (PDOException) {
            throw new RuntimeException('No se pudo persistir el estado del intento.');
        }
    }
}
