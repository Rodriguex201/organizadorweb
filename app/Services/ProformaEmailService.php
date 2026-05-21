<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProformaEmailService
{
    public function sendProforma(object $proforma, array $options = []): void
    {
        $logPrefix = $this->normalizeLogPrefix($options['log_prefix'] ?? null);
        $destinatariosData = $options['destinatarios'] ?? $this->resolveDestinatarios($proforma, $logPrefix);
        $destinatarios = $destinatariosData['emails'];

        $pdf = $this->resolvePdfPath($proforma);
        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('services.resend.from_address'));
        $fromName = trim((string) config('services.resend.from_name'));
        $replyTo = trim((string) config('services.resend.reply_to'));
        $missingConfig = $this->resolveMissingConfig($apiKey, $fromAddress, $replyTo);

        if ($logPrefix !== null) {
            Log::info($logPrefix.' REENVIO INICIADO', [
                'proforma_id' => $proforma->id ?? null,
                'proforma_numero' => $proforma->nro_prof ?? null,
                'destinatarios' => $destinatarios,
                'payload_resend' => $this->buildResendPayload($proforma, $destinatarios, $pdf, $fromAddress, $fromName, $replyTo),
            ]);
        }

        if ($missingConfig !== []) {
            throw new RuntimeException(
                'Falta configurar '.implode(', ', $missingConfig).'. '.
                'Este envio usa RESEND_* y no las variables MAIL_*.'
            );
        }

        if ($this->isGmailAddress($fromAddress)) {
            throw new RuntimeException('RESEND_FROM_ADDRESS no puede ser gmail.com. Use un dominio remitente valido.');
        }

        $payload = $this->buildResendPayload($proforma, $destinatarios, $pdf, $fromAddress, $fromName, $replyTo);
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'message', $response->body());

            if ($logPrefix !== null) {
                Log::error($logPrefix.' ERROR RESPUESTA RESEND', [
                    'proforma_id' => $proforma->id ?? null,
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                ]);
            }

            throw new RuntimeException('Resend no pudo enviar el correo: '.$message);
        }

        if ($logPrefix !== null) {
            Log::info($logPrefix.' REENVIO OK', [
                'proforma_id' => $proforma->id ?? null,
                'response_resend' => $response->json(),
                'message_id' => data_get($response->json(), 'id'),
            ]);
        }
    }

    /**
     * @return array{original:string,emails:array<int,string>,count:int,invalidos:array<int,string>}
     */
    public function resolveDestinatarios(object $proforma, ?string $logPrefix = null): array
    {
        $original = $this->resolveClienteEmailRaw($proforma);
        $emails = [];
        $invalidos = [];
        $logPrefix = $this->normalizeLogPrefix($logPrefix);

        foreach (explode(',', $original) as $email) {
            $email = trim($email);

            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidos[] = $email;
                continue;
            }

            $emails[] = $email;
        }

        $emails = array_values(array_unique($emails));

        if ($logPrefix !== null) {
            foreach ($invalidos as $emailInvalido) {
                Log::warning($logPrefix.' EMAIL INVALIDO', [
                    'proforma_id' => $proforma->id ?? null,
                    'email' => $emailInvalido,
                ]);
            }
        }

        if ($emails === []) {
            throw new RuntimeException('El cliente no tiene correos validos registrados en clientes_potenciales.email.');
        }

        return [
            'original' => $original,
            'emails' => $emails,
            'count' => count($emails),
            'invalidos' => $invalidos,
        ];
    }

    private function resolveClienteEmailRaw(object $proforma): string
    {
        if (!empty($proforma->id_cliente)) {
            $email = DB::table('clientes_potenciales')
                ->where('idclientes_potenciales', $proforma->id_cliente)
                ->value('email');

            $email = trim((string) $email);

            if ($email !== '') {
                return $email;
            }
        }

        $nit = trim((string) ($proforma->nit ?? ''));

        if ($nit !== '') {
            $email = DB::table('clientes_potenciales')
                ->where('nit', $nit)
                ->value('email');

            $email = trim((string) $email);

            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    /**
     * @return array{filename:string,contents:string}
     */
    private function resolvePdfPath(object $proforma): array
    {
        $ruta = trim((string) ($proforma->rpdf ?? ''));
        $archivo = trim((string) ($proforma->npdf ?? ''));

        if ($ruta === '' || $archivo === '') {
            throw new RuntimeException('La proforma no tiene PDF generado para adjuntar.');
        }

        $relativePath = trim($ruta, '/').'/'.ltrim($archivo, '/');

        if (!Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('No se encontro el archivo PDF en almacenamiento local.');
        }

        return [
            'filename' => $archivo,
            'contents' => Storage::disk('local')->get($relativePath),
        ];
    }

    private function isGmailAddress(string $email): bool
    {
        $normalized = mb_strtolower(trim($email));

        return str_ends_with($normalized, '@gmail.com') || str_ends_with($normalized, '@googlemail.com');
    }

    /**
     * @return list<string>
     */
    private function resolveMissingConfig(string $apiKey, string $fromAddress, string $replyTo): array
    {
        $missing = [];

        if ($apiKey === '') {
            $missing[] = 'RESEND_API_KEY';
        }

        if ($fromAddress === '') {
            $missing[] = 'RESEND_FROM_ADDRESS';
        }

        if ($replyTo === '') {
            $missing[] = 'RESEND_REPLY_TO';
        }

        return $missing;
    }

    /**
     * @param  array<int,string>  $destinatarios
     * @param  array{filename:string,contents:string}  $pdf
     * @return array<string,mixed>
     */
    private function buildResendPayload(
        object $proforma,
        array $destinatarios,
        array $pdf,
        string $fromAddress,
        string $fromName,
        string $replyTo
    ): array {
        return [
            'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddress) : $fromAddress,
            'reply_to' => [$replyTo],
            'to' => $destinatarios,
            'subject' => sprintf('Proforma #%s', (string) ($proforma->nro_prof ?: $proforma->id)),
            'text' => "Cordial saludo,\n\nBuen dia,\n\nNos permitimos adjuntar la proforma correspondiente a los servicios contratados.\n\n*** RECUERDE HACER EL PAGO DE LA PROFORMA EN SU TOTALIDAD, NO PARCIALMENTE ***\n\nEnviar soporte de pago al correo cartera.rmsoft1@gmail.com o la linea telefonica de cartera por WhatsApp 3128133868, con sus datos y factura que se abona.\n\nCordialmente,\nRM Soft",
            'attachments' => [
                [
                    'filename' => $pdf['filename'],
                    'content' => base64_encode($pdf['contents']),
                ],
            ],
        ];
    }

    private function normalizeLogPrefix(mixed $logPrefix): ?string
    {
        $logPrefix = trim((string) $logPrefix);

        return $logPrefix !== '' ? $logPrefix : null;
    }
}
