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
        $payload = $this->buildProformaPayload($proforma, $destinatarios, $pdf);

        $this->sendPayload(
            $payload,
            $destinatarios,
            $logPrefix,
            [
                'proforma_id' => $proforma->id ?? null,
                'proforma_numero' => $proforma->nro_prof ?? null,
            ],
        );
    }

    public function sendDocument(array $documento, array $options = []): void
    {
        $logPrefix = $this->normalizeLogPrefix($options['log_prefix'] ?? null);
        $destinatariosData = $options['destinatarios'] ?? $this->resolveDestinatariosFromRaw(
            (string) ($options['destinatarios_raw'] ?? ''),
            $logPrefix,
        );
        $destinatarios = $destinatariosData['emails'];

        $payload = $this->buildDocumentPayload(
            $destinatarios,
            $documento,
            [
                'subject' => (string) ($options['subject'] ?? 'Documento adjunto'),
                'text' => (string) ($options['text'] ?? 'Cordial saludo.'),
            ],
        );

        $this->sendPayload(
            $payload,
            $destinatarios,
            $logPrefix,
            [
                'documento' => [
                    'filename' => $documento['filename'] ?? null,
                    'contexto' => $documento['contexto'] ?? null,
                ],
            ],
        );
    }

    /**
     * @return array{original:string,emails:array<int,string>,count:int,invalidos:array<int,string>}
     */
    public function resolveDestinatariosFromRaw(string $destinatariosRaw, ?string $logPrefix = null): array
    {
        $original = trim($destinatariosRaw);
        $apiKey = trim((string) config('services.resend.key'));
        $logPrefix = $this->normalizeLogPrefix($logPrefix);
        ['emails' => $emails, 'invalidos' => $invalidos] = $this->parseDestinatarios($original);

        if ($logPrefix !== null) {
            Log::info($logPrefix.' DESTINATARIOS PROCESADOS', [
                'destinatarios_originales' => $original,
                'destinatarios_procesados' => $emails,
                'correos_invalidos_descartados' => $invalidos,
                'cantidad_validos' => count($emails),
            ]);

            foreach ($invalidos as $emailInvalido) {
                Log::warning($logPrefix.' EMAIL INVALIDO', [
                    'email' => $emailInvalido,
                ]);
            }
        }

        if ($emails === []) {
            throw new RuntimeException('No se encontraron correos validos para el envio.');
        }

        return [
            'original' => $original,
            'emails' => $emails,
            'count' => count($emails),
            'invalidos' => $invalidos,
        ];
    }

    /**
     * @return array{original:string,emails:array<int,string>,count:int,invalidos:array<int,string>}
     */
    public function resolveDestinatarios(object $proforma, ?string $logPrefix = null): array
    {
        $original = $this->resolveClienteEmailRaw($proforma);
        $logPrefix = $this->normalizeLogPrefix($logPrefix);
        ['emails' => $emails, 'invalidos' => $invalidos] = $this->parseDestinatarios($original);

        if ($logPrefix !== null) {
            Log::info($logPrefix.' DESTINATARIOS PROCESADOS', [
                'proforma_id' => $proforma->id ?? null,
                'destinatarios_originales' => $original,
                'destinatarios_procesados' => $emails,
                'correos_invalidos_descartados' => $invalidos,
                'cantidad_validos' => count($emails),
            ]);

            foreach ($invalidos as $emailInvalido) {
                Log::warning($logPrefix.' EMAIL INVALIDO', [
                    'proforma_id' => $proforma->id ?? null,
                    'email' => $emailInvalido,
                ]);
            }
        }

        if ($emails === []) {
            throw new RuntimeException('El cliente no tiene correos validos registrados en clientes_potenciales.email. Motivo: todos los destinatarios fueron descartados tras la validacion.');
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
     * @return array{emails:array<int,string>,invalidos:array<int,string>}
     */
    private function parseDestinatarios(string $destinatariosRaw): array
    {
        $segmentos = preg_split('/[;,]/', $destinatariosRaw) ?: [];
        $emails = [];
        $invalidos = [];

        foreach ($segmentos as $email) {
            $email = trim((string) $email);

            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidos[] = $email;

                continue;
            }

            $emails[] = $email;
        }

        return [
            'emails' => array_values(array_unique($emails)),
            'invalidos' => array_values(array_unique($invalidos)),
        ];
    }

    /**
     * @param  array<int,string>  $destinatarios
     * @param  array{filename:string,contents:string}  $pdf
     * @return array<string,mixed>
     */
    private function buildProformaPayload(object $proforma, array $destinatarios, array $pdf): array
    {
        return $this->buildDocumentPayload($destinatarios, $pdf, [
            'subject' => sprintf('Proforma #%s', (string) ($proforma->nro_prof ?: $proforma->id)),
            'text' => "Cordial saludo,\n\nBuen dia,\n\nNos permitimos adjuntar la proforma correspondiente a los servicios contratados.\n\n*** RECUERDE HACER EL PAGO DE LA PROFORMA EN SU TOTALIDAD, NO PARCIALMENTE ***\n\nEnviar soporte de pago al correo cartera.rmsoft1@gmail.com o la linea telefonica de cartera por WhatsApp 3128133868, con sus datos y factura que se abona.\n\nCordialmente,\nRM Soft",
        ]);
    }

    /**
     * @param  array<int,string>  $destinatarios
     * @param  array{filename:string,contents:string}  $documento
     * @param  array{subject:string,text:string}  $meta
     * @return array<string,mixed>
     */
    private function buildDocumentPayload(array $destinatarios, array $documento, array $meta): array
    {
        $fromAddress = trim((string) config('services.resend.from_address'));
        $fromName = trim((string) config('services.resend.from_name'));
        $replyTo = trim((string) config('services.resend.reply_to'));

        return [
            'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddress) : $fromAddress,
            'reply_to' => [$replyTo],
            'to' => $destinatarios,
            'subject' => $meta['subject'],
            'text' => $meta['text'],
            'attachments' => [
                [
                    'filename' => $documento['filename'],
                    'content' => base64_encode($documento['contents']),
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<int,string>  $destinatarios
     * @param  array<string,mixed>  $contexto
     */
    private function sendPayload(array $payload, array $destinatarios, ?string $logPrefix = null, array $contexto = []): void
    {
        $apiKey = trim((string) config('services.resend.key'));
        $fromAddress = trim((string) config('services.resend.from_address'));
        $replyTo = trim((string) config('services.resend.reply_to'));
        $missingConfig = $this->resolveMissingConfig($apiKey, $fromAddress, $replyTo);

        if ($logPrefix !== null) {
            Log::info($logPrefix.' REENVIO INICIADO', $contexto + [
                'destinatarios' => $destinatarios,
                'payload_resend' => $payload,
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

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'message', $response->body());

            if ($logPrefix !== null) {
                Log::error($logPrefix.' ERROR RESPUESTA RESEND', $contexto + [
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                ]);
            }

            throw new RuntimeException('Resend no pudo enviar el correo: '.$message);
        }

        if ($logPrefix !== null) {
            Log::info($logPrefix.' REENVIO OK', $contexto + [
                'response_resend' => $response->json(),
                'message_id' => data_get($response->json(), 'id'),
            ]);
        }
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

    private function normalizeLogPrefix(mixed $logPrefix): ?string
    {
        $logPrefix = trim((string) $logPrefix);

        return $logPrefix !== '' ? $logPrefix : null;
    }
}
