<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        if ($apiKey === '' || $fromAddress === '') {
            throw new RuntimeException('Falta configurar RESEND_API_KEY o RESEND_FROM_ADDRESS.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from' => $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddress) : $fromAddress,
                'to' => [$clienteEmail],
                'subject' => sprintf('Proforma #%s', (string) ($proforma->nro_prof ?: $proforma->id)),
                'text' => "Estimado cliente,\n\nAdjunto encontrará su proforma correspondiente al servicio contratado.\n\nPor favor revisar el documento adjunto.\n\nCordialmente,\nRM Soft",
                'attachments' => [
                    [
                        'filename' => $pdf['filename'],
                        'content' => base64_encode($pdf['contents']),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $message = (string) data_get($response->json(), 'message', $response->body());
            throw new RuntimeException('Resend no pudo enviar el correo: '.$message);
        }
    }

    private function resolveClienteEmail(object $proforma): ?string
    {
        $nit = trim((string) ($proforma->nit ?? ''));
        if ($nit === '') {
            return null;
        }

        $cliente = DB::table('clientes_potenciales')
            ->select(['email'])
            ->where('nit', $nit)
            ->first();

        $email = trim((string) ($cliente->email ?? ''));

        return $email !== '' ? $email : null;
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
            throw new RuntimeException('No se encontró el archivo PDF en almacenamiento local.');
        }

        return [
            'filename' => $archivo,
            'contents' => Storage::disk('local')->get($relativePath),
        ];
    }
}
