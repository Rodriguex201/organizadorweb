<?php

namespace App\Portal\Otp;

use Closure;
use Throwable;

final class PortalOtpService
{
    public const MESSAGE = 'Si los datos corresponden a una proforma habilitada, recibirás un código en el correo autorizado.';
    public function __construct(
        private PrivateOtpStore $store,
        private string $key,
        private array $allowlist,
        private Closure $resolve,
        private Closure $send,
        private Closure $clock,
        private bool $deliveryEnabled = true,
    ) {}

    public static function email(string $raw): ?string
    {
        $addresses = self::emails($raw);
        return count($addresses) === 1 ? $addresses[0] : null;
    }

    public static function emails(string $raw): array
    {
        $addresses = [];
        foreach (preg_split('/[;,]/', $raw) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (!filter_var($part, FILTER_VALIDATE_EMAIL)) return [];
            // Domain case is insignificant; do not merge potentially distinct local parts.
            [$local, $domain] = explode('@', $part, 2);
            $addresses[] = $local.'@'.strtolower($domain);
        }
        $addresses = array_values(array_unique($addresses));
        return $addresses;
    }

    private function hash(string $value): string { return hash_hmac('sha256', $value, $this->key); }
    private function clean(array &$s, int $now): void
    {
        foreach (['challenges','grants'] as $group) {
            foreach ($s[$group] as $key=>$row) if ($row['expires'] <= $now) unset($s[$group][$key]);
        }
        foreach ($s['rates'] as $key=>$times) {
            $s['rates'][$key] = array_values(array_filter($times, fn ($t) => $t > $now-3600));
            if ($s['rates'][$key] === []) unset($s['rates'][$key]);
        }
    }
    private function reserve(array &$s, array $rules, int $now): bool
    {
        foreach ($rules as [$key,$seconds,$max]) {
            if (count(array_filter($s['rates'][$key] ?? [], fn ($t) => $t > $now-$seconds)) >= $max) return false;
        }
        foreach (array_unique(array_column($rules, 0)) as $key) $s['rates'][$key][] = $now;
        return true;
    }

    public function request(string $ip, string $document, string $number): string
    {
        // Always return an indistinguishable opaque challenge, even when blocked.
        $token = bin2hex(random_bytes(32));
        $id = $this->hash('challenge:'.$token);
        $now = ($this->clock)();
        $pair = $this->hash('pair:'.$document.'|'.$number);
        $ipKey = $this->hash('send-ip:'.$ip);
        try {
            $allowed = $this->store->transaction(function (&$s) use ($now,$pair,$ipKey) {
                $this->clean($s,$now);
                return $this->reserve($s,[[$ipKey,60,5],[$ipKey,3600,30],[$pair,60,1],[$pair,900,3],[$pair,3600,6]],$now);
            });
            if (!$allowed) return $token;
            $target = ($this->resolve)($document,$number);
            if ($target === null) return $token;
            $emails = self::emails($target['email']);
            if ($emails === []) return $token;
            unset($target['email']);
            $options = [];
            foreach ($emails as $email) $options[bin2hex(random_bytes(24))] = $email;
            $this->store->transaction(function (&$s) use ($id,$pair,$target,$options,$now) {
                foreach ($s['challenges'] as $oldId=>$row) if ($row['pair'] === $pair) unset($s['challenges'][$oldId]);
                $s['challenges'][$id] = ['pair'=>$pair,'target'=>$target,'options'=>$options,'expires'=>$now+300];
            });
            if (count($options) === 1) $this->choose($ip,$token,array_key_first($options));
        } catch (Throwable) { /* Fail closed without sensitive exception logging. */ }
        return $token;
    }

    public function choices(string $token): array
    {
        try {
            return $this->store->transaction(function (&$s) use ($token) {
                $this->clean($s,($this->clock)());
                $options = $s['challenges'][$this->hash('challenge:'.$token)]['options'] ?? [];
                $result=[];
                foreach ($options as $id=>$email) {
                    [$local,$domain]=explode('@',$email,2);
                    $result[]=['id'=>$id,'label'=>substr($local,0,1).'***@'.$domain];
                }
                return $result;
            });
        } catch (Throwable) { return []; }
    }

    public function choose(string $ip, string $token, string $option): void
    {
        $id=$this->hash('challenge:'.$token);
        $now=($this->clock)();
        try {
            // Selection is single-use and tied to the HttpOnly challenge cookie.
            $selection=$this->store->transaction(function (&$s) use ($id,$option,$ip,$now) {
                $this->clean($s,$now);
                if (!$this->reserve($s,[[$this->hash('select-ip:'.$ip),900,20]],$now)) return null;
                $row=$s['challenges'][$id] ?? null;
                if (!isset($row['options'][$option])) return null;
                unset($s['challenges'][$id]);
                return ['email'=>$row['options'][$option],'target'=>$row['target'],'pair'=>$row['pair']];
            });
            if ($selection===null) return;
            $email=$selection['email'];
            $target=($this->resolve)($selection['target']['documento'],$selection['target']['numero']);
            if ($target===null || !in_array($email,self::emails($target['email']),true)) return;
            unset($target['email']);
            if ($target!==$selection['target'] || !in_array($email,$this->allowlist,true) || !$this->deliveryEnabled) return;
            $pair=$selection['pair'];
            $destination = $this->hash('destination:'.$email);
            $code = str_pad((string) random_int(0,99999999),8,'0',STR_PAD_LEFT);
            $saved = $this->store->transaction(function (&$s) use ($id,$pair,$destination,$target,$code,$now,$ip) {
                $deliveryIp=$this->hash('delivery-ip:'.$ip);
                $deliveryPair=$this->hash('delivery-pair:'.$pair);
                if (!$this->reserve($s,[[$destination,900,3],[$destination,3600,6],[$deliveryIp,60,5],[$deliveryIp,3600,30],[$deliveryPair,60,1],[$deliveryPair,900,3],[$deliveryPair,3600,6]],$now)) return false;
                foreach ($s['challenges'] as $oldId=>$row) if ($row['pair'] === $pair) unset($s['challenges'][$oldId]);
                $s['challenges'][$id] = ['pair'=>$pair,'target'=>$target,'digest'=>$this->hash($id.':'.$code),'expires'=>$now+300,'fails'=>0];
                return true;
            });
            if ($saved) {
                try { ($this->send)($email,$code); }
                catch (Throwable) { $this->store->transaction(function (&$s) use ($id) { unset($s['challenges'][$id]); }); }
            }
        } catch (Throwable) { /* Fail closed without sensitive exception logging. */ }
    }

    public function verify(string $ip, string $token, string $code): ?string
    {
        $id = $this->hash('challenge:'.$token);
        $ipKey = $this->hash('verify-ip:'.$ip);
        $now = ($this->clock)();
        try {
            return $this->store->transaction(function (&$s) use ($id,$ipKey,$now,$code) {
                $this->clean($s,$now);
                if (!$this->reserve($s,[[$ipKey,900,20]],$now)) return null;
                if (!isset($s['challenges'][$id]['digest'])) return null;
                $row = &$s['challenges'][$id];
                if ($row['fails'] >= 5) return null;
                if (!preg_match('/\A[0-9]{8}\z/',$code) || !hash_equals($row['digest'],$this->hash($id.':'.$code))) {
                    $row['fails']++;
                    return null;
                }
                $auth = bin2hex(random_bytes(32));
                $s['grants'][$this->hash('grant:'.$auth)] = ['target'=>$row['target'],'expires'=>$now+900];
                unset($s['challenges'][$id]);
                return $auth;
            });
        } catch (Throwable) { return null; }
    }

    public function grant(string $token): ?array
    {
        try {
            return $this->store->transaction(function (&$s) use ($token) {
                $this->clean($s,($this->clock)());
                return $s['grants'][$this->hash('grant:'.$token)]['target'] ?? null;
            });
        } catch (Throwable) { return null; }
    }

    public function revoke(string $token): void
    {
        $this->store->transaction(function (&$s) use ($token) { unset($s['grants'][$this->hash('grant:'.$token)]); });
    }
}
