<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProformaPdfService
{
    private const MESES_ES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    public function generateForProformaId(int $proformaId, bool $regenerar = false): array
    {
        $cabecera = DB::table('sg_proform')->where('id', $proformaId)->first();

        if (!$cabecera) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        $rutaExistente = trim((string) ($cabecera->rpdf ?? ''));
        $nombreExistente = trim((string) ($cabecera->npdf ?? ''));
        if (!$regenerar && $rutaExistente !== '' && $nombreExistente !== '') {
            $relative = $this->construirRutaRelativa($rutaExistente, $nombreExistente);
            if (Storage::disk('local')->exists($relative)) {
                return [
                    'relative_path' => $relative,
                    'absolute_path' => Storage::disk('local')->path($relative),
                    'filename' => $nombreExistente,
                    'reused' => true,
                ];
            }
        }

        $detalle = DB::table('sg_proford')
            ->where('proforma_id', $proformaId)
            ->orderBy('orden')
            ->get();

        $data = [
            'cabecera' => $cabecera,
            'detalle' => $detalle,
            'mes_nombre' => $this->resolverNombreMes((int) ($cabecera->mes ?? 0)),
            'fecha_emision' => now()->format('Y-m-d'),
            'logo_path' => $this->resolverLogoPath((string) ($cabecera->emisora ?? '')),
        ];

        $pdf = Pdf::loadView('proformas.pdf', $data)->setPaper('a4');
        $pdfBinario = $pdf->output();

        $ruta = 'proformas/'.((string) ($cabecera->anio ?? date('Y')));
        $nombreArchivo = $this->construirNombreArchivo($cabecera, $proformaId);
        $relativePath = $this->construirRutaRelativa($ruta, $nombreArchivo);

        Storage::disk('local')->put($relativePath, $pdfBinario);

        $hash = hash('sha256', $pdfBinario);

        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update([
                'rpdf' => $ruta,
                'npdf' => $nombreArchivo,
                'hpdf' => $hash,
            ]);

        return [
            'relative_path' => $relativePath,
            'absolute_path' => Storage::disk('local')->path($relativePath),
            'filename' => $nombreArchivo,
            'reused' => false,
        ];
    }

    private function construirNombreArchivo(object $cabecera, int $proformaId): string
    {
        $nroProforma = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($cabecera->nro_prof ?? $proformaId));
        $nit = preg_replace('/\D+/', '', (string) ($cabecera->nit ?? ''));
        $nit = $nit !== '' ? $nit : 'sin-nit';

        return sprintf('proforma-%s-%s-%d.pdf', $nroProforma, $nit, $proformaId);
    }

    private function resolverNombreMes(int $mes): string
    {
        return self::MESES_ES[$mes] ?? (string) $mes;
    }

    private function resolverLogoPath(string $emisora): ?string
    {
        $em = strtoupper(trim($emisora));
        if (!in_array($em, ['SAS', 'PCS', 'SMP'], true)) {
            return null;
        }

        $path = public_path("images/logos/{$em}.png");

        return file_exists($path) ? $path : null;
    }

    private function construirRutaRelativa(string $ruta, string $archivo): string
    {
        return trim($ruta, '/').'/'.ltrim($archivo, '/');
    }
}
