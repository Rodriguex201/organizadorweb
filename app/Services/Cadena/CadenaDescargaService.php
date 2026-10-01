<?php

namespace App\Services\Cadena;

use Illuminate\Contracts\Encryption\Encrypter;
use RuntimeException;

/** One authenticated payload per downloadable file. No database or workbook generation. */
class CadenaDescargaService
{
    public function prepare(array $exports, string $month, int $year, string $session, Encrypter $cipher): array
    {
        $tokens = [];
        $errors = $exports['errores'];
        $batch = bin2hex(random_bytes(16));
        $packageErrors = $exports['paquete_errores'] ?? [];
        foreach (CadenaPaqueteService::FILES as $filename => $category) {
            if (!isset($exports['tables'][$filename])) {
                $packageErrors[] = $category.': '.($errors[$filename] ?? 'No se preparó '.$filename.'.');
            }
        }
        foreach ($exports['tables'] as $filename => $table) {
            try {
                $payload = json_encode(['archivo' => $filename, 'table' => $table, 'mes' => $month,
                    'anio' => $year, 'expires' => time() + 1800, 'session' => hash('sha256', $session),
                    'lote_descarga' => $batch, 'paquete_errores' => $packageErrors], JSON_THROW_ON_ERROR);
                if (strlen($payload) > 32 * 1024 * 1024) {
                    throw new RuntimeException('El contenido JSON excede 32 MiB.');
                }
                $token = $cipher->encryptString(base64_encode(gzencode($payload)));
                if (strlen($token) > 4 * 1024 * 1024) {
                    throw new RuntimeException('El token cifrado excede 4 MiB.');
                }
                $tokens[$filename] = $token;
            } catch (\Throwable $exception) {
                $reason = $exception instanceof RuntimeException && get_class($exception) === RuntimeException::class
                    ? $exception->getMessage() : 'No fue posible codificar esta descarga.';
                $errors[$filename] = (new CadenaExportService())->errorMessage($filename, count($table['rows']), $reason);
            }
            unset($payload, $token);
        }
        foreach (CadenaPaqueteService::FILES as $filename => $category) {
            if (!isset($tokens[$filename]) && isset($exports['tables'][$filename])) {
                $packageErrors[] = $category.': no se pudo preparar su token.';
            }
        }
        return ['tokens' => $tokens, 'archivos' => array_keys($tokens), 'errores' => $errors, 'omitidos' => $exports['omitidos'], 'paquete_errores' => $packageErrors];
    }

    public function decode(string $token, string $filename, string $month, int $year, string $session, Encrypter $cipher): array
    {
        if (strlen($token) > 4 * 1024 * 1024) { throw new RuntimeException('Token demasiado grande.'); }
        $encoded = base64_decode($token, true);
        if ($encoded === false || base64_encode($encoded) !== $token) {
            throw new RuntimeException('Token de descarga inválido.');
        }
        $compressed = base64_decode($cipher->decryptString($token), true);
        $json = $compressed === false ? false : gzdecode($compressed, 32 * 1024 * 1024);
        $payload = json_decode($json === false ? '' : $json, true, 512, JSON_THROW_ON_ERROR);
        if (($payload['expires'] ?? 0) < time()
            || !hash_equals($payload['session'] ?? '', hash('sha256', $session))
            || ($payload['mes'] ?? null) !== $month || ($payload['anio'] ?? null) !== $year
            || ($payload['archivo'] ?? null) !== $filename || !is_array($payload['table'] ?? null)) {
            throw new RuntimeException('La descarga no corresponde a la sesión, archivo o período, o expiró.');
        }
        return $payload;
    }
}
