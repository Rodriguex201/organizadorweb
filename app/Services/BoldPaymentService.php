<?php

namespace App\Services;

use App\Portal\Otp\PortalOtpService;
use App\Portal\ReadOnly\PortalReadGateway;
use Brick\Math\BigDecimal;
use RuntimeException;
use Throwable;
use App\Payments\Bold\{AttemptRepository, BoldHttpClient, CurlBoldHttpClient};

/** Local sandbox transport; all persistence goes through the guarded attempt repository. */
final class BoldPaymentService
{
    public const ENDPOINT = 'https://integrations.api.bold.co/online/link/v1';

    public function __construct(
        private PortalOtpService $otp,
        private PortalReadGateway $reader,
        private array $settings,
        private string $applicationEnvironment,
        private ?BoldHttpClient $http = null,
        private ?AttemptRepository $attemptRepository = null,
    ) {}

    public static function forPortal(PortalOtpService $otp): self
    {
        return new self($otp, new PortalReadGateway(), config('bold'), app()->environment());
    }

    /** Only an opaque portal session token is accepted. No amount or target parameters. */
    public function prepare(#[\SensitiveParameter] string $portalToken): array
    {
        if ($this->applicationEnvironment !== 'local'
            || ($this->settings['environment'] ?? null) !== 'sandbox') {
            throw new RuntimeException('Preparación Bold disponible únicamente en local y sandbox.');
        }
        try {
            $grant = $this->otp->grant($portalToken);
            if ($grant === null) throw new RuntimeException();
            $row = $this->reader->paymentSnapshot($grant);
            if ($row === null || $row['cliente_id'] !== $grant['cliente_id']
                || $row['proforma_id'] !== $grant['proforma_id']
                || !in_array((string) $row['estado'], ['2', '3'], true)
                || !is_string($row['vtotal'])
                || !preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $row['vtotal'])) {
                throw new RuntimeException();
            }
            $amount = BigDecimal::of($row['vtotal'])->toScale(2);
            if ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan('9999999999999999.99')) {
                throw new RuntimeException();
            }
        } catch (Throwable) {
            // Do not retain an exception chain containing credentials, SQL or tokens.
            throw new RuntimeException('No es posible preparar el pago de esta proforma.');
        }
        $reference = 'PF_'.bin2hex(random_bytes(16));
        // Decimal JSON number, without passing money through binary floating point.
        $json = '{"amount_type":"CLOSE","amount":{"currency":"COP","total_amount":'
            .$amount.',"tip_amount":0},"reference":"'.$reference.'"}';
        return [
            'reference' => $reference,
            'cliente_id' => $row['cliente_id'],
            'proforma_id' => $row['proforma_id'],
            'environment' => 'sandbox',
            'currency' => 'COP',
            'amount' => (string) $amount,
            'body' => $json,
            'status' => 'prepared_only',
        ];
    }

    public function createLink(#[\SensitiveParameter] string $portalToken): array
    {
        if (($this->settings['enabled'] ?? false) !== true) {
            throw new RuntimeException('Las llamadas a Bold están deshabilitadas.');
        }
        $key = $this->settings['api_key'] ?? null;
        if ($this->applicationEnvironment !== 'local' || ($this->settings['environment'] ?? null) !== 'sandbox'
            || !is_string($key) || trim($key) === '' || preg_match('/[\r\n]/', $key)) {
            throw new RuntimeException('Configuración de transporte Bold no válida.');
        }
        // Authorize before opening the writing connection, then revalidate when reserving/claiming.
        $this->prepare($portalToken);
        $repository = $this->attemptRepository ?? AttemptRepository::open(
            $this->settings['database'] ?? [], $this->applicationEnvironment, $this->settings['environment']);
        $flow = new BoldAttemptService($this, $repository);
        $http = $this->http ?? new CurlBoldHttpClient();
        $row = $flow->reserve($portalToken);
        if ($row['estado'] === 'RESERVADO') {
            $claimed = $flow->claim($portalToken);
            if ($claimed === null) return $flow->reserve($portalToken);
            // Re-read the source immediately before dispatch; retain the frozen amount/reference.
            $row = $flow->reserve($portalToken);
            if ($row['estado'] !== 'SOLICITANDO') return $row;
            $body = '{"amount_type":"CLOSE","amount":{"currency":"COP","total_amount":'
                .$claimed['importe'].',"tip_amount":0},"reference":'
                .json_encode($claimed['referencia'], JSON_THROW_ON_ERROR).'}';
            try { $response = $http->create($key, $body); }
            catch (Throwable) { $response = ['uncertain'=>true, 'status'=>null]; }
            $status = $response['status'] ?? null;
            $decoded = [];
            if (!($response['uncertain'] ?? true) && is_int($status)) {
                try {
                    $this->parseCreationBody($status, $response['body'], $response['content_type']);
                    $decoded = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
                } catch (Throwable) { /* Invalid response is persisted as REVISION, without its body. */ }
            }
            if (!$flow->recordCreation($claimed, ($response['uncertain'] ?? true) ? null : $status, $decoded)) {
                throw new RuntimeException('El intento requiere revisión; no se repetirá el envío.');
            }
            $row = $flow->reserve($portalToken);
        }
        // Other executors never POST for SOLICITANDO, uncertain, review or active attempts.
        if ($row['estado'] !== 'LINK_CREADO') return $row;
        try { $response = $http->lookup($key, $row['payment_link']); }
        catch (Throwable) { $response = ['uncertain'=>true, 'status'=>null]; }
        $status = $response['status'] ?? null;
        if (($response['uncertain'] ?? true) || $status === null || $status >= 500 || in_array($status, [408,429], true)) {
            $repository->transition((int)$row['id'], $row['referencia'], 'LINK_CREADO', 'INDETERMINADO', [
                'error_codigo'=>'VERIFY_TRANSPORT_UNCERTAIN', 'ultima_consulta_at'=>gmdate('Y-m-d H:i:s'),
            ]);
        } else {
            $remote = [];
            try {
                if ($status >= 200 && $status < 300 && strlen($response['body']) <= 65536
                    && strtolower(trim(explode(';', $response['content_type'] ?? '', 2)[0])) === 'application/json') {
                    $object = json_decode($response['body'], false, 32, JSON_THROW_ON_ERROR);
                    if ($object instanceof \stdClass) $remote = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
                }
            } catch (Throwable) { /* Fail closed without recording remote data. */ }
            // A source change during HTTP must not activate a stale snapshot.
            $row = $flow->reserve($portalToken);
            if ($row['estado'] === 'LINK_CREADO') $flow->recordVerification($row, $remote);
        }
        return $flow->reserve($portalToken);
    }

    /** Validate a simulated creation response; not proof of payment or sandbox. */
    public function parseCreationResponse(int $status, array $response): array
    {
        $payload = $response['payload'] ?? null;
        $id = is_array($payload) ? ($payload['payment_link'] ?? null) : null;
        $url = is_array($payload) ? ($payload['url'] ?? null) : null;
        if ($status < 200 || $status >= 300 || ($response['errors'] ?? null) !== []
            || !is_array($payload) || array_is_list($payload)
            || !is_string($id) || trim($id) === '' || strlen($id) > 128
            || !is_string($url) || !$this->isAllowedCheckoutUrl($url)) {
            throw new RuntimeException('Respuesta de creación Bold no válida.');
        }
        return ['payment_link' => $id, 'url' => $url];
    }

    private function isAllowedCheckoutUrl(string $url): bool
    {
        // Exact host allowlist, not a suffix match or an assumed link/path format.
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'checkout.bold.co'
            && !isset($parts['user']) && !isset($parts['pass'])
            && (!isset($parts['port']) || $parts['port'] === 443);
    }

    /**
     * Optional diagnostic entry point. The sink receives ONLY
     * a bounded, sanitized JSON document on failure. Never pass headers or request data.
     * No logging or filesystem writes occur unless the caller explicitly supplies a sink.
     */
    public function parseCreationBody(
        int $status,
        #[\SensitiveParameter] string $body,
        #[\SensitiveParameter] ?string $contentType,
        ?\Closure $diagnosticSink = null,
    ): array {
        $decoded = null;
        $jsonValid = false;
        $withinLimit = strlen($body) <= 65536;
        if ($withinLimit) {
            try {
                $decoded = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
                $jsonValid = true;
                if ($decoded instanceof \stdClass
                    && ($decoded->payload ?? null) instanceof \stdClass
                    && property_exists($decoded, 'errors') && $decoded->errors === []
                    && strtolower(trim(explode(';', $contentType ?? '', 2)[0])) === 'application/json') {
                    $array = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
                    return $this->parseCreationResponse($status, $array);
                }
            } catch (\JsonException|RuntimeException) {
                // Never propagate raw exception content, body or headers.
            }
        }
        if ($diagnosticSink !== null && $this->applicationEnvironment === 'local'
            && ($this->settings['environment'] ?? null) === 'sandbox') {
            $root = $decoded instanceof \stdClass ? get_object_vars($decoded) : [];
            $payloadPresent = array_key_exists('payload', $root);
            $payload = $payloadPresent && $root['payload'] instanceof \stdClass
                ? get_object_vars($root['payload']) : [];
            $budget = 120;
            $diagnostic = [
                'http_status' => $status >= 100 && $status <= 599 ? $status : null,
                'content_type' => $this->safeContentType($contentType),
                'body_bytes' => strlen($body),
                'body_within_limit' => $withinLimit,
                'json_valid' => $jsonValid,
                'root_type' => $jsonValid ? get_debug_type($decoded) : 'unavailable',
                'top_level_keys' => $this->diagnosticKeys($root),
                'payload_present' => $payloadPresent,
                'payload_type' => $payloadPresent ? get_debug_type($root['payload']) : 'missing',
                'payload_keys' => $this->diagnosticKeys($payload),
                'payment_link_type' => array_key_exists('payment_link', $payload) ? get_debug_type($payload['payment_link']) : 'missing',
                'url_type' => array_key_exists('url', $payload) ? get_debug_type($payload['url']) : 'missing',
                'errors_present' => array_key_exists('errors', $root),
                'sanitized_body' => $this->diagnosticShape($decoded, 0, $budget),
                'redaction' => 'All scalar values omitted; unknown keys masked; sensitive subtrees omitted.',
            ];
            $encoded = json_encode($diagnostic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (strlen($encoded) > 8192) {
                $diagnostic['sanitized_body'] = ['omitted' => 'output_size_limit'];
                $encoded = json_encode($diagnostic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }
            try { $diagnosticSink($encoded); } catch (Throwable) {
                // Diagnostics must never change failure handling or leak sink exceptions.
            }
        }
        throw new RuntimeException('Respuesta de creación Bold no válida.');
    }

    private function safeContentType(?string $value): array
    {
        if ($value === null) return ['present' => false];
        $parts = explode(';', strtolower(substr($value, 0, 256)));
        $media = trim($parts[0]);
        $known = ['application/json', 'application/problem+json', 'text/plain', 'text/html', 'application/octet-stream'];
        return [
            'present' => true,
            'media_type' => in_array($media, $known, true) ? $media : '[unrecognized]',
            'charset_utf8' => preg_match('/;\s*charset\s*=\s*"?utf-8"?\s*(?:;|$)/i', substr($value, 0, 256)) === 1,
            'other_parameters_omitted' => count($parts) > 1,
        ];
    }

    private function diagnosticKeys(array $values): array
    {
        $keys = [];
        foreach (array_slice(array_keys($values), 0, 20) as $i => $key) {
            $keys[] = $this->diagnosticKey((string) $key, $i);
        }
        return $keys;
    }

    private function diagnosticKey(string $key, int $index): string
    {
        // Even JSON property names can contain reflected credentials or payer data.
        return in_array($key, ['payload','errors','payment_link','url','code','message','detail',
            'type','status','data','error','error_code','description','id','reference','amount_type',
            'total','currency','is_sandbox','payer','customer','headers','request','token','api_key',
            'secret','authorization','email','phone','document'], true) ? $key : '[other_key_'.$index.']';
    }

    private function diagnosticShape(mixed $value, int $depth, int &$budget): array
    {
        if (--$budget < 0 || $depth >= 6) return ['omitted' => 'structure_limit'];
        $type = get_debug_type($value);
        if (!is_array($value) && !$value instanceof \stdClass) {
            return ['type' => $type, 'value' => '[redacted]'];
        }
        $values = (array) $value;
        $out = ['type' => $type, 'count' => count($values), 'entries' => []];
        foreach (array_slice($values, 0, 20, true) as $key => $child) {
            $name = is_array($value) ? (string) count($out['entries']) : $this->diagnosticKey((string) $key, count($out['entries']));
            $sensitive = in_array(strtolower((string) $key), ['payer','customer','headers','request','token','api_key','secret','authorization','email','phone','document'], true);
            $out['entries'][$name] = $sensitive
                ? ['type' => get_debug_type($child), 'omitted' => 'sensitive']
                : $this->diagnosticShape($child, $depth + 1, $budget);
        }
        return $out;
    }
}
