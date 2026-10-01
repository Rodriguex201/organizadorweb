<?php

namespace App\Services\Cadena;

/** Local OS lock; independent of Laravel's cache/database configuration. */
class CadenaProcesoLock
{
    private $handle = null;

    public function acquire(string $directory, string $session): bool
    {
        if ($this->handle !== null) { throw new \LogicException('Lock already acquired.'); }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el bloqueo temporal.');
        }
        $handle = @fopen($directory.'/'.hash('sha256', $session).'.lock', 'c');
        if ($handle === false) { throw new \RuntimeException('No se pudo abrir el bloqueo temporal.'); }
        if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return false; }
        $this->handle = $handle;
        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
        // Do not unlink: another process may already hold/open the same lock file.
    }

    public function __destruct() { $this->release(); }
}
