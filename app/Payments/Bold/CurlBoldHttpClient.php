<?php

namespace App\Payments\Bold;

use App\Services\BoldPaymentService;

/** Fixed destinations, one dispatch per call, no retries, redirects or logging. */
final class CurlBoldHttpClient implements BoldHttpClient
{
    public function create(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $body): array
    {
        return $this->send('POST', BoldPaymentService::ENDPOINT, $key, $body);
    }

    public function lookup(#[\SensitiveParameter] string $key, #[\SensitiveParameter] string $id): array
    {
        // Encode as one path segment; never follow the checkout URL or assume an ID prefix.
        $segment = str_replace('.', '%2E', rawurlencode($id));
        return $this->send('GET', BoldPaymentService::ENDPOINT.'/'.$segment, $key);
    }

    private function send(string $method, #[\SensitiveParameter] string $url,
        #[\SensitiveParameter] string $key, #[\SensitiveParameter] ?string $body = null): array
    {
        $failure = ['status'=>null, 'body'=>'', 'content_type'=>null, 'uncertain'=>true];
        $handle = null;
        try {
            if ($key === '' || preg_match('/[\r\n]/', $key)) return $failure;
            $handle = curl_init($url);
            $captured = '';
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_HTTPHEADER=>['Authorization: x-api-key '.$key, 'Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>25,
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_VERBOSE=>false,
                CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$captured): int {
                    if (strlen($captured) + strlen($chunk) > 65536) return 0;
                    $captured .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
            $uncertain = $ok === false || curl_errno($handle) !== 0;
            return ['status'=>$status ?: null, 'body'=>$uncertain ? '' : $captured,
                'content_type'=>is_string($contentType) ? $contentType : null, 'uncertain'=>$uncertain];
        } catch (\Throwable) {
            return $failure; // Never propagate cURL exceptions or request context.
        } finally {
            if ($handle instanceof \CurlHandle) curl_close($handle);
        }
    }
}
