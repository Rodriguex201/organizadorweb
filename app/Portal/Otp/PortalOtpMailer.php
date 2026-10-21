<?php

namespace App\Portal\Otp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PortalOtpMailer
{
    public function send(string $email, string $code): void
    {
        if (config('portal_otp.send_enabled') !== true || !app()->environment('local')) {
            throw new RuntimeException('OTP delivery unavailable.');
        }
        $key = (string) config('services.resend.key');
        $from = (string) config('services.resend.from_address');
        if ($key === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('OTP delivery unavailable.');
        // No attachments, recipient logs, payload logs, automatic retries or administrative calls.
        $response = Http::withToken($key)->acceptJson()->connectTimeout(5)->timeout(15)
            ->post('https://api.resend.com/emails', [
                'from' => $from,
                'to' => [$email],
                'subject' => 'Código de verificación',
                'text' => "Código de verificación: {$code}\nExpira en 5 minutos desde su solicitud.\nIgnora este correo si no lo solicitaste.",
            ]);
        if (!$response->successful()) throw new RuntimeException('OTP delivery unavailable.');
    }
}
