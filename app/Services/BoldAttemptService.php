<?php

namespace App\Services;

use App\Payments\Bold\AttemptRepository;
use Brick\Math\BigDecimal;
use RuntimeException;

/** Server-side orchestration. No HTTP transport, routes, mail or proforma updates. */
final class BoldAttemptService
{
    public function __construct(private BoldPaymentService $payments, private AttemptRepository $attempts) {}

    /** Explicit construction only; no connection is opened by registering application routes. */
    public static function forPortal(\App\Portal\Otp\PortalOtpService $otp): self
    {
        $environment = app()->environment();
        if ($environment !== 'local' || config('bold.environment') !== 'sandbox') {
            throw new RuntimeException('Intentos disponibles únicamente en local y sandbox.');
        }
        return new self(
            new BoldPaymentService($otp, new \App\Portal\ReadOnly\PortalReadGateway(), config('bold'), $environment),
            AttemptRepository::open(config('bold.database'), $environment, config('bold.environment')),
        );
    }

    public function reserve(#[\SensitiveParameter] string $token): array
    {
        // Re-resolves ownership, payable status and persisted net through the READ ONLY gateway.
        $draft = $this->payments->prepare($token);
        $row = $this->attempts->reserve($draft);
        if ((string) $row['cliente_id'] !== $draft['cliente_id']
            || (string) $row['proforma_id'] !== $draft['proforma_id']
            || $row['moneda'] !== $draft['currency'] || $row['ambiente'] !== $draft['environment']
            || !BigDecimal::of((string) $row['importe'])->isEqualTo($draft['amount'])) {
            $this->review($row);
            throw new RuntimeException('El intento requiere revisión; sus importes no se modificaron.');
        }
        // Do not expose a link while remote state is unknown or under review.
        if (!in_array($row['estado'], ['RESERVADO', 'ACTIVO'], true)) {
            unset($row['checkout_url']);
        }
        return $row;
    }

    public function claim(#[\SensitiveParameter] string $token): ?array
    {
        $row = $this->reserve($token);
        if (!$this->attempts->transition((int) $row['id'], $row['referencia'], 'RESERVADO', 'SOLICITANDO', [
            'solicitud_iniciada_at' => gmdate('Y-m-d H:i:s'),
        ])) return null;
        $row['estado'] = 'SOLICITANDO';
        return $row;
    }

    /** Trusted executor only, not an HTTP controller. Null status represents a timeout. */
    public function recordCreation(array $claimed, ?int $status, array $response = []): bool
    {
        if ($status === null || $status >= 500 || $status === 408 || $status === 429) {
            return $this->attempts->transition((int) $claimed['id'], $claimed['referencia'], 'SOLICITANDO', 'INDETERMINADO', [
                'http_status' => $status, 'error_codigo' => 'CREATION_UNCERTAIN',
            ]);
        }
        try {
            $link = $this->payments->parseCreationResponse($status, $response);
        } catch (RuntimeException) {
            // A response error alone is not proof that no external link exists.
            return $this->attempts->transition((int) $claimed['id'], $claimed['referencia'], 'SOLICITANDO', 'REVISION', [
                'http_status' => $status >= 100 && $status <= 599 ? $status : null,
                'error_codigo' => 'INVALID_CREATION_RESPONSE',
            ]);
        }
        return $this->attempts->transition((int) $claimed['id'], $claimed['referencia'], 'SOLICITANDO', 'LINK_CREADO', [
            'payment_link' => $link['payment_link'], 'checkout_url' => $link['url'], 'http_status' => $status,
        ]);
    }

    /** Future authenticated GET result; never browser callback data or proof of payment. */
    public function recordVerification(array $row, array $remote): bool
    {
        $valid = ($remote['is_sandbox'] ?? null) === true
            && ($remote['id'] ?? null) === ($row['payment_link'] ?? null)
            && ($remote['reference'] ?? null) === $row['referencia']
            && ($remote['amount_type'] ?? null) === 'CLOSE'
            && ($remote['status'] ?? null) === 'ACTIVE';
        try {
            $valid = $valid && isset($remote['total'])
                && BigDecimal::of((string) $remote['total'])->isEqualTo((string) $row['importe']);
        } catch (\Throwable) { $valid = false; }
        return $this->attempts->transition((int) $row['id'], $row['referencia'], 'LINK_CREADO', $valid ? 'ACTIVO' : 'REVISION', [
            'ambiente_verificado' => $valid ? 1 : 0,
            'estado_remoto' => $valid ? 'ACTIVE' : null,
            'ultima_consulta_at' => gmdate('Y-m-d H:i:s'),
            'error_codigo' => $valid ? null : 'VERIFICATION_MISMATCH',
        ]);
    }

    /** Safe failure only before dispatch; an uncertain POST must never release the slot. */
    public function failBeforeDispatch(array $row): bool
    {
        return $this->attempts->transition((int) $row['id'], $row['referencia'], 'RESERVADO', 'FALLIDO', [
            'error_codigo' => 'LOCAL_PRE_DISPATCH_FAILURE',
        ]);
    }

    private function review(array $row): void
    {
        if (in_array($row['estado'], ['RESERVADO', 'SOLICITANDO', 'LINK_CREADO', 'ACTIVO', 'INDETERMINADO'], true)) {
            $this->attempts->transition((int) $row['id'], $row['referencia'], $row['estado'], 'REVISION', [
                'error_codigo' => 'SNAPSHOT_MISMATCH',
            ]);
        }
    }
}
