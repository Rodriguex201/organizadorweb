<?php

namespace App\Services;

use App\Payments\Bold\WebhookEventStore;
use Closure;

final class BoldWebhookService
{
    public const MAX_BYTES = 65536;

    /** The lazy store factory must never run before signature/structure validation. */
    public function __construct(private Closure $storeFactory, private string $merchant) {}

    public static function allowed(string $environment, array $config, string $remote, string $host): bool
    {
        return $environment === 'local'
            && ($config['environment'] ?? null) === 'sandbox'
            && ($config['database']['database'] ?? null) === 'organizadorprueba'
            && ($config['webhook']['enabled'] ?? false) === true
            && self::identifier($config['webhook']['merchant_id'] ?? null)
            && in_array($remote, ['127.0.0.1', '::1'], true)
            && in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }

    public function receive(string $raw, string $signature, string $contentType): int
    {
        if (strlen($raw) > self::MAX_BYTES) return 413;
        if (!preg_match('/\A[a-f0-9]{64}\z/', $signature)
            || !hash_equals(hash_hmac('sha256', base64_encode($raw), ''), $signature)) return 400;
        if (strtolower(trim(explode(';', $contentType)[0])) !== 'application/json') return 415;
        if (!self::identifier($this->merchant)) return 503;
        try {
            // Validate JSON, then preserve every numeric lexeme as text. No float-to-money conversion.
            json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            $lossless = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/s',
                static fn (array $m): string => $m[0][0] === '"' ? $m[0] : '"'.$m[0].'"', $raw);
            $body = json_decode($lossless, false, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) { return 400; }
        if (!is_object($body) || !self::identifier($body->id ?? null)
            || !self::identifier($body->type ?? null, 64) || !is_object($body->data ?? null)) return 400;

        $data = $body->data;
        $link = is_object($data->metadata ?? null) ? ($data->metadata->reference ?? null) : null;
        $payment = $data->payment_id ?? null;
        $merchant = $data->merchant_id ?? null;
        $amount = is_object($data->amount ?? null) ? self::money($data->amount->total ?? null) : null;
        $currency = is_object($data->amount ?? null) ? ($data->amount->currency ?? null) : null;
        $hash = hash('sha256', $raw);

        try {
            /** @var WebhookEventStore $store */
            $store = ($this->storeFactory)();
            $existing = $store->existingHash($body->id);
            if ($existing !== null) return hash_equals($existing, $hash) ? 200 : 409;
            $attempt = self::identifier($link) ? $store->attempt($link) : null;
            $reason = match (true) {
                !in_array($body->type, ['SALE_APPROVED', 'SALE_REJECTED', 'VOID_APPROVED', 'VOID_REJECTED'], true) => 'EVENTO_NO_SOPORTADO',
                ($data->integration ?? null) !== 'LINK' || ($body->source ?? null) !== '/payments/links' => 'INTEGRACION_NO_COINCIDE',
                !self::identifier($payment) || ($body->subject ?? null) !== $payment => 'TRANSACCION_INVALIDA',
                !self::identifier($merchant) || !hash_equals($this->merchant, $merchant) => 'COMERCIO_NO_COINCIDE',
                $attempt === null => 'INTENTO_NO_ENCONTRADO',
                ($attempt['ambiente'] ?? null) !== 'sandbox' || (int) ($attempt['ambiente_verificado'] ?? 0) !== 1 => 'AMBIENTE_NO_VERIFICADO',
                $currency !== 'COP' || $currency !== ($attempt['moneda'] ?? null) => 'MONEDA_NO_COINCIDE',
                $amount === null || $amount === '0.00' || $amount !== self::money($attempt['importe'] ?? null) => 'IMPORTE_NO_COINCIDE',
                !empty($attempt['transaction_id']) && $attempt['transaction_id'] !== $payment => 'TRANSACCION_NO_COINCIDE',
                default => null,
            };
            return $store->save([
                'evento_id' => $body->id, 'intento_pago_id' => $attempt['id'] ?? null,
                'payment_link' => self::identifier($link) ? $link : null,
                'payment_id' => self::identifier($payment) ? $payment : null,
                'merchant_id' => self::identifier($merchant) ? $merchant : null,
                'tipo' => $body->type, 'importe' => $amount,
                'moneda' => is_string($currency) && preg_match('/\A[A-Z]{3}\z/', $currency) ? $currency : null,
                'raw_sha256' => $hash,
                'estado_validacion' => $reason === null ? 'VALIDADO' : ($reason === 'EVENTO_NO_SOPORTADO' ? 'IGNORADO' : 'REVISION'),
                'motivo' => $reason,
            ]);
        } catch (\Throwable) {
            // Never log raw body, exception, headers, identifiers or payer data.
            return 503;
        }
    }

    private static function identifier(mixed $value, int $limit = 128): bool
    {
        return is_string($value) && strlen($value) <= $limit && preg_match('/\A[\x21-\x7E]+\z/', $value) === 1;
    }

    private static function money(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/\A(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?\z/', $value, $m)) return null;
        return $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
    }
}
