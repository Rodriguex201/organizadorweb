<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ComprobantesZipService
{
    public function registros(int $mes, int $anio): iterable
    {
        return DB::table('sg_proform')->select(['id', 'nro_prof', 'comprobante_pago'])
            ->where('mes', $mes)->where('anio', $anio)
            ->whereNotNull('comprobante_pago')->whereRaw("TRIM(comprobante_pago) <> ''")
            ->orderBy('id')->cursor();
    }

    public function generar(iterable $registros, string $raizPrivada, string $temporales): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZIP no está disponible en este servidor.');
        $raiz = realpath($raizPrivada);
        if ($raiz === false) throw new RuntimeException('No se encuentra el almacenamiento privado.');
        $archivos = []; $incidencias = []; $usados = ['incidencias.txt' => true]; $total = 0;
        foreach ($registros as $registro) {
            $total++;
            $ruta = trim((string) $registro->comprobante_pago);
            $absoluta = realpath($raiz.DIRECTORY_SEPARATOR.$ruta);
            $prefijo = $raiz.DIRECTORY_SEPARATOR;
            $dentro = $absoluta !== false && (PHP_OS_FAMILY === 'Windows'
                ? strncasecmp($absoluta, $prefijo, strlen($prefijo)) === 0
                : str_starts_with($absoluta, $prefijo));
            if (!$dentro || !is_file($absoluta) || !is_readable($absoluta)) {
                $incidencias[] = 'Proforma ID '.$registro->id.' / número '.$registro->nro_prof.': '.str_replace(["\r", "\n"], ' ', $ruta);
                continue;
            }
            $nombre = basename(str_replace('\\', '/', $ruta));
            $nombre = preg_replace('/[\x00-\x1F\x7F]/', '_', $nombre);
            $extension = pathinfo($nombre, PATHINFO_EXTENSION);
            $base = pathinfo($nombre, PATHINFO_FILENAME);
            $sufijo = $extension !== '' ? '.'.$extension : '';
            $intento = 0; $entrada = $nombre;
            while (isset($usados[strtolower($entrada)])) {
                $entrada = $base.'_PROFORMA_'.$registro->id.($intento ? '_'.$intento : '').$sufijo;
                $intento++;
            }
            $usados[strtolower($entrada)] = true;
            $archivos[] = [$absoluta, $entrada];
        }
        if (!$total) throw new RuntimeException('No hay comprobantes para el período seleccionado.');
        if (!$archivos) throw new RuntimeException('No se pudo acceder a ninguno de los '.$total.' comprobantes del período. No se generó ZIP.');
        if (!is_dir($temporales) && !mkdir($temporales, 0700, true) && !is_dir($temporales)) {
            throw new RuntimeException('No se pudo crear el directorio temporal privado.');
        }
        $rutaZip = tempnam($temporales, 'comprobantes-');
        if ($rutaZip === false) throw new RuntimeException('No se pudo crear el ZIP temporal.');
        if (realpath(dirname($rutaZip)) !== realpath($temporales)) {
            unlink($rutaZip);
            throw new RuntimeException('No se pudo crear el ZIP dentro del almacenamiento privado.');
        }
        $zip = new ZipArchive(); $abierto = false;
        try {
            if ($zip->open($rutaZip, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo abrir el ZIP.');
            $abierto = true;
            foreach ($archivos as [$archivo, $entrada]) {
                if (!$zip->addFile($archivo, $entrada)) throw new RuntimeException('No se pudo incluir un comprobante.');
            }
            if ($incidencias && !$zip->addFromString('incidencias.txt', "Comprobantes faltantes o inaccesibles: ".count($incidencias)."\n\n".implode("\n", $incidencias)."\n")) {
                throw new RuntimeException('No se pudo incluir el informe de incidencias.');
            }
            $cerrado = $zip->close(); $abierto = false;
            if (!$cerrado) throw new RuntimeException('No se pudo completar el ZIP.');
            if ($zip->open($rutaZip, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('El ZIP no superó la verificación.');
            $abierto = true;
            if ($zip->numFiles !== count($archivos) + ($incidencias ? 1 : 0)) throw new RuntimeException('El ZIP está incompleto.');
            $zip->close(); $abierto = false;
            return ['path' => $rutaZip, 'incluidos' => count($archivos), 'faltantes' => count($incidencias)];
        } catch (\Throwable $error) {
            if ($abierto) $zip->close();
            if (is_file($rutaZip)) unlink($rutaZip);
            throw $error;
        }
    }
}
