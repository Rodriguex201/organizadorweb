<?php

namespace App\Services\Cadena;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Diagnóstico de recepción, antes de lectores y consultas comerciales. */
class CadenaUploadService
{
    public function inspect(array $files, string $uploadLimit): array
    {
        $issues = [];
        foreach (['facturas', 'soporte', 'eventos'] as $category) {
            $uploads = $files[$category] ?? [];
            if (!is_array($uploads)) { continue; } // La validación existente informa estructura inválida.
            foreach ($uploads as $index => $upload) {
                if (!$upload instanceof UploadedFile || $upload->isValid()) { continue; }
                $code = $upload->getError();
                [$symbol, $reason] = match ($code) {
                    UPLOAD_ERR_INI_SIZE => ['UPLOAD_ERR_INI_SIZE', 'Supera el límite PHP por archivo (upload_max_filesize='.$uploadLimit.').'],
                    UPLOAD_ERR_FORM_SIZE => ['UPLOAD_ERR_FORM_SIZE', 'Supera el límite de subida indicado por el formulario.'],
                    UPLOAD_ERR_PARTIAL => ['UPLOAD_ERR_PARTIAL', 'La subida quedó incompleta. Vuelve a intentarlo. Puede deberse a una interrupción de conexión o al tiempo de recepción; este código no distingue la causa.'],
                    UPLOAD_ERR_NO_FILE => ['UPLOAD_ERR_NO_FILE', 'No se recibió el archivo. Selecciónalo de nuevo.'],
                    UPLOAD_ERR_NO_TMP_DIR => ['UPLOAD_ERR_NO_TMP_DIR', 'El servidor no dispone de un directorio temporal para recibir archivos. Contacta al administrador.'],
                    UPLOAD_ERR_CANT_WRITE => ['UPLOAD_ERR_CANT_WRITE', 'El servidor no pudo escribir el archivo temporal. El administrador debe revisar permisos, espacio y cuota de disco.'],
                    UPLOAD_ERR_EXTENSION => ['UPLOAD_ERR_EXTENSION', 'Una extensión de PHP interrumpió la subida. El administrador debe revisar las extensiones y los registros del servidor.'],
                    UPLOAD_ERR_OK => ['UPLOAD_ERR_OK', 'PHP no informó un error, pero el archivo temporal no se reconoce como una subida HTTP válida. Contacta al administrador.'],
                    default => ['UPLOAD_ERR_UNKNOWN', 'PHP informó un error de subida no reconocido. Contacta al administrador.'],
                };
                $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $upload->getClientOriginalName()) ?: 'archivo';
                $issues[$category.'.'.$index] = [
                    'code' => $code, 'symbol' => $symbol,
                    'message' => 'No se pudo subir "'.$name.'". '.$reason.' Código: '.$symbol.' ('.$code.').',
                ];
            }
        }
        return $issues;
    }
}
