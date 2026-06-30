<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectorioApiService
{
    public static function shouldUseExternalApi(): bool
    {
        $forceApi = filter_var(config('services.directorio_api.force_api', false), FILTER_VALIDATE_BOOL);
        if ($forceApi) {
            return true;
        }

        return app()->environment('production') && PHP_OS_FAMILY === 'Linux';
    }

    /**
     * @param array{clienteId?:mixed,cliente_id?:mixed,codigo?:string,empresa?:string} $payload
     */
    public function notificarClienteCreado(array $payload): void
    {
        $apiPayload = $this->buildApiPayload($payload);
        $url = trim((string) config('services.directorio_api.url', ''));
        $token = trim((string) config('services.directorio_api.token', ''));
        $timeout = max(1, (int) config('services.directorio_api.timeout', 10));
        $verifySsl = filter_var(config('services.directorio_api.verify_ssl', true), FILTER_VALIDATE_BOOL);

        $requestContext = [
            'cliente_id' => $apiPayload['clienteId'],
            'codigo' => $apiPayload['codigo'],
        ];

        if ($url === '') {
            Log::error('Directorio API: configuracion faltante para URL.', [
                ...$requestContext,
                'status' => 'config_error',
                'error' => 'DIRECTORIO_API_URL no esta configurado.',
            ]);
            return;
        }

        if (app()->environment('production') && $token === '') {
            Log::error('Directorio API: configuracion faltante para token.', [
                ...$requestContext,
                'status' => 'config_error',
                'error' => 'DIRECTORIO_API_TOKEN no esta configurado.',
            ]);
            return;
        }

        try {
            Log::info('Directorio API: request', [
                ...$requestContext,
                'status' => 'request',
            ]);

            $request = Http::acceptJson()
                ->connectTimeout(5)
                ->retry(2, 300)
                ->timeout($timeout)
                ->withOptions([
                    'verify' => $verifySsl,
                ]);

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, $apiPayload);

            $this->logResponse($response, $requestContext);
        } catch (\Throwable $exception) {
            Log::error('Directorio API: exception', [
                ...$requestContext,
                'status' => 'exception',
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
                'stacktrace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function logResponse(Response $response, array $requestContext): void
    {
        $responseBody = $response->json();
        $ok = $response->successful() && (($responseBody['ok'] ?? true) === true);

        $context = [
            ...$requestContext,
            'http_status' => $response->status(),
            'status' => $ok ? 'success' : 'error',
        ];

        if ($ok) {
            Log::info('Directorio API: response', $context);
            return;
        }

        Log::error('Directorio API: response', [
            ...$context,
            'error' => is_array($responseBody)
                ? ($responseBody['error'] ?? $responseBody['message'] ?? 'Respuesta no exitosa de la API.')
                : $response->body(),
        ]);
    }

    /**
     * @param array{clienteId?:mixed,cliente_id?:mixed,codigo?:string,empresa?:string} $payload
     * @return array{clienteId:mixed,codigo:string,empresa:string}
     */
    private function buildApiPayload(array $payload): array
    {
        return [
            'clienteId' => $payload['clienteId'] ?? $payload['cliente_id'] ?? null,
            'codigo' => trim((string) ($payload['codigo'] ?? '')),
            'empresa' => trim((string) ($payload['empresa'] ?? '')),
        ];
    }
}
