<?php

namespace App\Services;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/** Local sandbox only. No HTTP, Laravel default DB, uploads or commercial side effects. */
final class BoldPaymentApplicationService
{
    private function __construct(private PDO $pdo, private bool $isolated, private Closure $clock) {}

    public static function configured(array $config, string $environment): self
    {
        $db = $config['database'] ?? [];
        if ($environment !== 'local' || ($config['environment'] ?? '') !== 'sandbox'
            || ($db['database'] ?? '') !== 'organizadorprueba'
            || !is_string($db['host'] ?? null) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/', $db['host'])
            || !ctype_digit((string) ($db['port'] ?? '')) || (int) $db['port'] < 1 || (int) $db['port'] > 65535
            || !is_string($db['username'] ?? null) || trim($db['username']) === ''
            || !is_string($db['password'] ?? null) || $db['password'] === '') {
            throw new RuntimeException('Aplicación permitida únicamente en local/sandbox y organizadorprueba.');
        }
        try {
            $pdo = new PDO('mysql:host='.$db['host'].';port='.$db['port'].';dbname=organizadorprueba;charset=utf8mb4',
                $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
            $service = new self($pdo, false, fn () => new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $service->guard();
            $engines = $pdo->query("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='organizadorprueba' AND TABLE_NAME IN ('intentos_pago','sg_proform','valores_externos','clientes_potenciales')")->fetchAll(PDO::FETCH_COLUMN);
            if (count($engines) !== 4 || array_diff($engines, ['InnoDB'])) throw new RuntimeException();
            return $service;
        } catch (Throwable) { throw new RuntimeException('No se pudo preparar la aplicación local del pago.'); }
    }

    public static function isolated(PDO $pdo, Closure $clock): self
    {
        if (PDO::getAvailableDrivers() !== ['sqlite'] || extension_loaded('mysqli')
            || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') throw new RuntimeException('Solo SQLite en memoria.');
        foreach ($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $db) {
            if ($db['file'] !== '') throw new RuntimeException('Base persistente no permitida.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return new self($pdo, true, $clock);
    }

    private function guard(): void
    {
        if (!$this->isolated && $this->pdo->query('SELECT DATABASE()')->fetchColumn() !== 'organizadorprueba') {
            throw new RuntimeException('Base activa no permitida.');
        }
    }

    private function table(string $name): string
    {
        if (!in_array($name, ['intentos_pago','sg_proform','valores_externos','clientes_potenciales'], true)) throw new RuntimeException();
        return ($this->isolated ? '' : '`organizadorprueba`.').'`'.$name.'`';
    }

    private function rows(string $sql, array $values): array
    {
        $q = $this->pdo->prepare($sql); $q->execute($values); return $q->fetchAll();
    }

    private function update(string $sql, array $values): void
    {
        $q = $this->pdo->prepare($sql); $q->execute($values);
        if ($q->rowCount() !== 1) throw new RuntimeException('Conflicto de aplicación.');
    }

    /** Manual invocation only; the actor must come from trusted server code. */
    public function apply(int $id, string $actor): array
    {
        if ($id < 1 || !preg_match('/\A[a-zA-Z0-9:_-]{1,128}\z/', $actor)) throw new RuntimeException('Solicitud inválida.');
        $this->guard();
        if ($this->pdo->inTransaction()) throw new RuntimeException('Se requiere una transacción dedicada.');
        $a = $this->table('intentos_pago'); $p = $this->table('sg_proform');
        $v = $this->table('valores_externos'); $c = $this->table('clientes_potenciales');
        $lock = $this->isolated ? '' : ' FOR UPDATE';
        try {
            $this->pdo->beginTransaction();
            $rows = $this->rows("SELECT * FROM $a WHERE id=? AND pasarela='bold' AND ambiente='sandbox'".$lock, [$id]);
            if (count($rows) !== 1) { $this->pdo->rollBack(); return ['result'=>'NO_ENCONTRADO']; }
            $attempt = $rows[0];
            if ($attempt['estado_aplicacion'] === 'APLICADO') {
                $this->pdo->rollBack(); return ['result'=>'YA_APLICADO','id'=>$id];
            }
            if ($attempt['estado_aplicacion'] === 'REVISION') {
                $this->pdo->rollBack(); return ['result'=>'REVISION','id'=>$id];
            }
            // Unconfirmed attempts are never mutated or promoted to paid by this service.
            if ($attempt['estado'] !== 'PAGADO' || $attempt['estado_remoto'] !== 'PAID') {
                $this->pdo->rollBack(); return ['result'=>'NO_CONFIRMADO','id'=>$id];
            }
            $now = ($this->clock)();
            $stamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $date = $now->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
            $reason = null;
            if ($attempt['estado_aplicacion'] !== 'PENDIENTE' || (int) $attempt['ambiente_verificado'] !== 1
                || trim((string) $attempt['transaction_id']) === '' || trim((string) $attempt['payment_link']) === ''
                || trim((string) $attempt['referencia']) === '' || $attempt['moneda'] !== 'COP') $reason = 'CONFIRMACION_INCONSISTENTE';
            $proformas = $this->rows("SELECT id,id_cobro,nit,estado,vtotal FROM $p WHERE id=?".$lock, [$attempt['proforma_id']]);
            $proforma = $proformas[0] ?? null;
            if (!$reason && count($proformas) !== 1) $reason = 'PROFORMA_NO_EXISTE';
            if (!$reason && $this->rows("SELECT id FROM $a WHERE proforma_aplicada_id=? AND id<>?".$lock, [$attempt['proforma_id'],$id]) !== []) {
                $reason = 'PROFORMA_YA_APLICADA_OTRO_INTENTO';
            }
            if (!$reason && !in_array((int) $proforma['estado'], [2,3], true)) $reason = 'PROFORMA_ESTADO_NO_APLICABLE';
            if (!$reason && (self::money($attempt['importe']) === null || self::money($attempt['importe']) !== self::money($proforma['vtotal']))) $reason = 'IMPORTE_NO_COINCIDE';
            if (!$reason && !preg_match('/\A[1-9][0-9]*\z/', (string) $proforma['id_cobro'])) $reason = 'COBRO_SIN_REFERENCIA_EXACTA';
            if (!$reason) {
                $cobros = $this->rows("SELECT id_cobro,id_cliente,Proforma FROM $v WHERE id_cobro=?".$lock, [$proforma['id_cobro']]);
                if (count($cobros) !== 1) $reason = 'COBRO_NO_UNICO';
                elseif ((string) $cobros[0]['id_cliente'] !== (string) $attempt['cliente_id']) $reason = 'CLIENTE_NO_COINCIDE';
                elseif (!in_array((string) $cobros[0]['Proforma'], ['2','3'], true)
                    || (string) $cobros[0]['Proforma'] !== (string) $proforma['estado']) $reason = 'COBRO_ESTADO_NO_COINCIDE';
            }
            if (!$reason) {
                $clients = $this->rows("SELECT idclientes_potenciales,nit FROM $c WHERE idclientes_potenciales=?".$lock, [$attempt['cliente_id']]);
                if (count($clients) !== 1 || trim((string) $clients[0]['nit']) === ''
                    || trim((string) $clients[0]['nit']) !== trim((string) $proforma['nit'])) $reason = 'CLIENTE_IDENTIDAD_NO_COINCIDE';
            }
            if ($reason) {
                $this->update("UPDATE $a SET estado_aplicacion='REVISION',aplicacion_ultimo_intento_at=?,aplicacion_error_codigo=? WHERE id=? AND estado_aplicacion='PENDIENTE'", [$stamp,$reason,$id]);
                $this->pdo->commit(); return ['result'=>'REVISION','id'=>$id,'motivo'=>$reason];
            }
            $this->update("UPDATE $p SET estado=4,fpago='BOLD',fpag=? WHERE id=? AND estado=? AND id_cobro=? AND vtotal=?",
                [$date,$attempt['proforma_id'],$proforma['estado'],$proforma['id_cobro'],$proforma['vtotal']]);
            $this->update("UPDATE $v SET Proforma=4 WHERE id_cobro=? AND Proforma=?", [$proforma['id_cobro'],$proforma['estado']]);
            $this->update("UPDATE $a SET estado_aplicacion='APLICADO',aplicado_at=?,aplicado_por=?,aplicacion_ultimo_intento_at=?,aplicacion_error_codigo=NULL,estado_proforma_anterior=? WHERE id=? AND estado_aplicacion='PENDIENTE' AND estado='PAGADO' AND estado_remoto='PAID'",
                [$stamp,$actor,$stamp,$proforma['estado'],$id]);
            $this->pdo->commit(); return ['result'=>'APLICADO','id'=>$id];
        } catch (Throwable) {
            try { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); } catch (Throwable) { /* Connection loss: no sensitive exception escapes. */ }
            // A lost COMMIT response can be uncertain: retry observes the durable application marker.
            return ['result'=>'REINTENTAR','id'=>$id,'motivo'=>'APLICACION_NO_CONFIRMADA'];
        }
    }

    private static function money(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/\A(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?\z/', $value, $m)) return null;
        $decimal = $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
        return $decimal === '0.00' ? null : $decimal;
    }
}
