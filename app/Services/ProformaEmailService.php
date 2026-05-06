<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProformaEmailService
{
    public function sendProforma(object $proforma): void
    {
        $clienteEmail = $this->resolveClienteEmail($proforma);

        if ($clienteEmail === null) {
            throw new RuntimeException('El cliente no tiene un correo registrado en clientes_potenciales.email.');
        }

        $pdf = $this->resolvePdfPath($proforma);
        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('services.resend.from_address'));
        $fromName = trim((string) config('services.resend.from_name'));
        $replyTo = trim((string) config('services.resend.reply_to'));
        $missingConfig = $this->resolveMissingConfig($apiKey, $fromAddress, $replyTo);

        Log::info('Proforma email debug: preparando envio', [
            'proforma_id' => $proforma->id ?? null,
            'proforma_numero' => $proforma->nro_prof ?? null,
            'cliente_email' => $clienteEmail,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
            'resend_api_key_masked' => $this->maskSecret($apiKey),
            'pdf_filename' => $pdf['filename'],
            'pdf_bytes' => strlen($pdf['contents']),
            'missing_config' => $missingConfig,
            'mail_mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_username' => (string) config('mail.mailers.smtp.username'),
            'mail_password_masked' => $this->maskSecret((string) config('mail.mailers.smtp.password')),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
            'notice' => 'El envio de proformas usa services.resend.*; MAIL_* solo se registra como referencia.',
        ]);

        if ($missingConfig !== []) {
            throw new RuntimeException(
                'Falta configurar '.implode(', ', $missingConfig).'. '.
                'Este envio usa RESEND_* y no las variables MAIL_*.'
            );
        }

        if ($this->isGmailAddress($fromAddress)) {
            throw new RuntimeException('RESEND_FROM_ADDRESS no puede ser gmail.com. Use un dominio remitente valido.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddress) : $fromAddress,
                'reply_to' => [$replyTo],
                'to' => [$clienteEmail],
                'subject' => sprintf('Proforma #%s', (string) ($proforma->nro_prof ?: $proforma->id)),
                'text' => "Cordial saludo,\n\nBuen dia,\n\nNos permitimos adjuntar la proforma correspondiente a los servicios contratados.\n\n*** RECUERDE HACER EL PAGO DE LA PROFORMA EN SU TOTALIDAD, NO PARCIALMENTE ***\n\nEnviar soporte de pago al correo cartera.rmsoft1@gmail.com o la linea telefonica de cartera por WhatsApp 3128133868, con sus datos y factura que se abona.\n\nCordialmente,\nRM Soft",
                'attachments' => [
                    [
                        'filename' => $pdf['filename'],
                        'content' => base64_encode($pdf['contents']),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'message', $response->body());

            Log::error('Proforma email debug: Resend respondio con error', [
                'proforma_id' => $proforma->id ?? null,
                'status' => $response->status(),
                'response_body' => $response->body(),
                'response_json' => $response->json(),
            ]);

            throw new RuntimeException('Resend no pudo enviar el correo: '.$message);
        }

        Log::info('Proforma email debug: envio exitoso', [
            'proforma_id' => $proforma->id ?? null,
            'status' => $response->status(),
            'response_json' => $response->json(),
        ]);
    }

    private function resolveClienteEmail(object $proforma): ?string
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

        return null;
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

    private function maskSecret(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '(empty)';
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }
}
