<?php

namespace App\Services\Cadena;

use Illuminate\Contracts\Encryption\Encrypter;
use RuntimeException;
use ZipArchive;

/** Packages existing download tables. No readers, queries or commercial operations. */
class CadenaPaqueteService
{
    public const FILES = [
        'ResumenCombinado.xlsx' => 'Facturas/notas',
        'ResumenDocumentoSoporte.xlsx' => 'Documento soporte',
        'ResumenEventos.xlsx' => 'Eventos',
    ];

    public function create(array $tokens, string $month, int $year, string $session, Encrypter $cipher, CadenaExportService $exporter): string
    {
        $paths = [];
        $zip = new ZipArchive();
        $opened = false;
        $result = null;
        $batch = null;
        $category = 'Paquete';
        try {
            foreach (self::FILES as $name => $category) {
                if (!isset($tokens[$name])) { throw new RuntimeException('Falta el resumen requerido.'); }
                $payload = (new CadenaDescargaService())->decode($tokens[$name], $name, $month, $year, $session, $cipher);
                if (empty($payload['lote_descarga']) || !array_key_exists('paquete_errores', $payload)) {
                    throw new RuntimeException('Prepara una nueva vista previa para descargar el paquete.');
                }
                if ($payload['paquete_errores'] !== []) { throw new RuntimeException(implode(' ', $payload['paquete_errores'])); }
                $batch ??= $payload['lote_descarga'];
                if ($batch !== $payload['lote_descarga']) { throw new RuntimeException('Los resúmenes pertenecen a distintas vistas previas.'); }
                $path = tempnam(sys_get_temp_dir(), 'cadena-part-');
                if ($path === false) { throw new RuntimeException('No hay espacio temporal.'); }
                $paths[$name] = $path;
                $exporter->write($payload['table'], ucfirst($month).' '.$year, $path);
                unset($payload);
            }
            $category = 'Paquete ZIP';
            $result = tempnam(sys_get_temp_dir(), 'cadena-package-');
            if ($result === false) { $result = null; throw new RuntimeException('No hay espacio temporal.'); }
            if ($zip->open($result, ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('No se pudo abrir el ZIP.'); }
            $opened = true;
            foreach ($paths as $name => $path) {
                if (!$zip->addFile($path, $name)) { throw new RuntimeException('No se pudo incluir '.$name.'.'); }
            }
            if (!$zip->close()) { throw new RuntimeException('No se pudo completar el ZIP.'); }
            $opened = false;
            if ($zip->open($result, ZipArchive::CHECKCONS) !== true) { throw new RuntimeException('El ZIP no superó la verificación.'); }
            $opened = true;
            if ($zip->numFiles !== count(self::FILES)) { throw new RuntimeException('El ZIP está incompleto.'); }
            $zip->close();
            $opened = false;
            return $result; // Caller removes this file after the response is sent.
        } catch (\Throwable $exception) {
            if ($opened) { $zip->close(); $opened = false; }
            if ($result !== null && is_file($result)) { unlink($result); }
            $reason = get_class($exception) === RuntimeException::class ? $exception->getMessage() : 'No fue posible generar el archivo. Prepara una nueva vista previa o utiliza su descarga individual.';
            throw new RuntimeException($category.': '.$reason, 0, $exception);
        } finally {
            foreach ($paths as $path) { if (is_file($path)) { unlink($path); } }
        }
    }
}
