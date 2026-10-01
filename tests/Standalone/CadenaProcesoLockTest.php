<?php
// No Laravel bootstrap, .env, PDO or database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use App\Services\Cadena\CadenaProcesoLock;
$dir = sys_get_temp_dir().'/cadena-lock-test-'.bin2hex(random_bytes(6));
$a = new CadenaProcesoLock(); $b = new CadenaProcesoLock();
$checks = 0;
$check = function ($ok) use (&$checks) { if (!$ok) throw new RuntimeException('Lock assertion failed'); $checks++; };
try {
    $check($a->acquire($dir, 'same-session'));
    $check(!$b->acquire($dir, 'same-session'));
    $check($b->acquire($dir, 'other-session')); $b->release();
    $a->release(); $check($b->acquire($dir, 'same-session')); $b->release();
    try {
        $check($a->acquire($dir, 'same-session'));
        try { throw new RuntimeException('Simulated validation/generation failure'); }
        finally { $a->release(); }
    } catch (RuntimeException) {}
    $check($b->acquire($dir, 'same-session')); $b->release();
    $check($a->acquire($dir, 'same-session')); unset($a);
    $check($b->acquire($dir, 'same-session')); $b->release();
    echo "OK: $checks lock assertions, no DB.\n";
} finally {
    if (isset($a)) $a->release(); $b->release();
    foreach (glob($dir.'/*.lock') ?: [] as $file) unlink($file);
    if (is_dir($dir)) rmdir($dir);
}
