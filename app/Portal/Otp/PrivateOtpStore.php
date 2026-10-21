<?php

namespace App\Portal\Otp;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/** One stable lock protects counters, challenges and grants across PHP processes. */
final class PrivateOtpStore
{
    private Encrypter $cipher;
    public function __construct(private string $directory, string $key)
    {
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== 32) throw new RuntimeException('OTP unavailable.');
        $this->cipher = new Encrypter($decoded, 'AES-256-CBC');
    }

    public function transaction(callable $callback): mixed
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('OTP unavailable.');
        }
        $lock = @fopen($this->directory.'/state.lock', 'c');
        if (!$lock) throw new RuntimeException('OTP unavailable.');
        $temp = null;
        try {
            $deadline = microtime(true) + 5;
            while (!flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) throw new RuntimeException('OTP unavailable.');
                usleep(10000);
            }
            $path = $this->directory.'/state.enc';
            $state = ['rates'=>[], 'challenges'=>[], 'grants'=>[]];
            if (is_file($path)) {
                $bytes = file_get_contents($path);
                if ($bytes === false) throw new RuntimeException('OTP unavailable.');
                $state = json_decode($this->cipher->decryptString($bytes), true, 512, JSON_THROW_ON_ERROR);
                foreach (['rates','challenges','grants'] as $key) {
                    if (!isset($state[$key]) || !is_array($state[$key])) throw new RuntimeException('OTP unavailable.');
                }
            }
            $result = $callback($state);
            $bytes = $this->cipher->encryptString(json_encode($state, JSON_THROW_ON_ERROR));
            $temp = $this->directory.'/'.bin2hex(random_bytes(16)).'.tmp';
            if (file_put_contents($temp, $bytes) !== strlen($bytes)) throw new RuntimeException('OTP unavailable.');
            @chmod($temp, 0600);
            if (!rename($temp, $path)) throw new RuntimeException('OTP unavailable.');
            $temp = null;
            return $result; // Never return an authorization before persistence succeeds.
        } finally {
            if ($temp !== null && is_file($temp)) @unlink($temp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
