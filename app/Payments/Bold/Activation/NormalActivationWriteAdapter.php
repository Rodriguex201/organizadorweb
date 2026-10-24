<?php
namespace App\Payments\Bold\Activation;

use App\Payments\Bold\ActivationRuleResolver;
use Closure;
use DateTimeImmutable;
use Throwable;

/** No planner/command invokes this adapter yet. It never computes a renewal. */
final class NormalActivationWriteAdapter
{
    public function __construct(private Closure $connections, private bool $enabled = false) {}

    public function execute(array $task): array
    {
        if (!$this->enabled) return $this->result('REVISION', 'ESCRITURA_REMOTA_DESHABILITADA');
        try { self::validateTask($task); }
        catch (Throwable) { return $this->result('REVISION', 'OBJETIVO_INVALIDO'); }
        $connection = null; $writing = false; $committing = false;
        try {
            $connection = ($this->connections)($task);
            if (!$connection instanceof NormalActivationWriteConnection) throw new \RuntimeException();
            $connection->begin();
            $actual = self::dates($connection->lockedSnapshot(), $task['empresa_codigo']);
            $objective = [$task['fecha_inicio_objetivo'], $task['fecha_fin_objetivo']];
            $previous = [$task['fecha_inicio_anterior'], $task['fecha_fin_anterior'],
                $task['fecha_inicio_global_anterior'], $task['fecha_fin_global_anterior']];
            if ($actual === [...$objective, ...$objective]) {
                $connection->rollback();
                return $this->result('YA_COMPLETADA');
            }
            if ($actual !== $previous) throw new ActivationReadException('REVISION', 'PRECONDICION_CAMBIADA');
            $writing = true;
            if ($connection->updateIndividual(array_slice($previous, 0, 2), $objective) !== 1
                || $connection->updateGlobal(array_slice($previous, 2, 2), $objective) !== 1) {
                throw new ActivationReadException('REVISION', 'FILAS_ACTUALIZADAS_NO_COINCIDEN');
            }
            $committing = true;
            $connection->commit();
            $connection = null;
            // A fresh connection, same frozen destination; never resolve another server.
            $verification = ($this->connections)($task);
            try {
                $verification->begin();
                $observed = self::dates($verification->lockedSnapshot(), $task['empresa_codigo']);
                $verification->rollback();
            } catch (Throwable $e) {
                try { $verification->rollback(); } catch (Throwable) {}
                throw $e;
            }
            return $observed === [...$objective, ...$objective]
                ? $this->result('COMPLETADA') : $this->result('REVISION', 'VERIFICACION_DIVERGENTE');
        } catch (Throwable $e) {
            $rolledBack = false;
            if ($connection !== null) {
                try { $connection->rollback(); $rolledBack = true; } catch (Throwable) {}
            }
            if ($committing || ($writing && !$rolledBack)) return $this->result('INDETERMINADA', 'CONFIRMACION_REMOTA_INCIERTA');
            if ($e instanceof ActivationReadException) return $this->result($e->state, $e->getMessage());
            return $this->result('REINTENTABLE', 'OPERACION_REMOTA_NO_DISPONIBLE');
        }
    }

    public static function validateTask(array $task): void
    {
        $code = $task['empresa_codigo'] ?? '';
        if (!is_string($code) || !preg_match('/\A[A-Za-z0-9]{1,64}\z/', $code)
            || in_array(strtolower($code), ['organizador','organizadorprueba','empresas','api','mysql','sys','information_schema','performance_schema'], true)
            || ($task['base_destino'] ?? null) !== strtolower($code)
            || !in_array($task['conexion_logica'] ?? null, ['mysql_213','mysql_167'], true)
            || ($task['regla_version'] ?? null) !== ActivationRuleResolver::VERSION
            || ($task['servicio'] ?? null) !== 'ACTIVACION_NORMAL'
            || !is_string($task['objetivo_sha256'] ?? null)
            || !hash_equals(ActivationRuleResolver::fingerprint($task), $task['objetivo_sha256'])) throw new \RuntimeException();
        foreach (['fecha_inicio_anterior','fecha_fin_anterior','fecha_inicio_global_anterior','fecha_fin_global_anterior','fecha_inicio_objetivo','fecha_fin_objetivo'] as $key) self::date($task[$key] ?? null);
        if ($task['fecha_inicio_anterior'] !== $task['fecha_inicio_global_anterior']
            || $task['fecha_fin_anterior'] !== $task['fecha_fin_global_anterior']
            || self::date($task['fecha_inicio_anterior'])->modify('+2 days')->format('Y-m-d') !== $task['fecha_fin_anterior']
            || self::date($task['fecha_inicio_objetivo'])->modify('+2 days')->format('Y-m-d') !== $task['fecha_fin_objetivo']
            || $task['fecha_fin_objetivo'] <= $task['fecha_fin_anterior']) throw new \RuntimeException();
    }

    private static function dates(array $snapshot, string $code): array
    {
        foreach (['individual','global'] as $key) {
            if (!is_array($snapshot[$key] ?? null) || count($snapshot[$key]) !== 1)
                throw new ActivationReadException('REVISION', 'REGISTRO_NO_UNICO');
        }
        if (($snapshot['global'][0]['emprobra'] ?? null) !== $code) throw new ActivationReadException('REVISION', 'CODIGO_NO_COINCIDE');
        $dates = [$snapshot['individual'][0]['seg_fecha'] ?? null, $snapshot['individual'][0]['seg_maxima'] ?? null,
            $snapshot['global'][0]['emprfpago'] ?? null, $snapshot['global'][0]['emprfinit'] ?? null];
        try { foreach ($dates as $date) self::date($date); }
        catch (Throwable) { throw new ActivationReadException('REVISION', 'FECHAS_INVALIDAS'); }
        return $dates;
    }

    private static function date(mixed $value): DateTimeImmutable
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new \RuntimeException();
        return $date;
    }

    private function result(string $state, ?string $error = null): array
    {
        return ['estado'=>$state, 'error_codigo'=>$error];
    }
}
