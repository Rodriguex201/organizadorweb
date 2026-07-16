<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    private const EMISORAS = [
        'SAS' => [
            'logo' => 'rmsoft.png',
            'razon_social' => 'RM SOFT Casa de Software SAS',
            'nit' => '900770401-8',
            'banco' => 'Bancolombia',
            'cuenta_tipo' => 'Cuenta Ahorros',
            'cuenta_numero' => '85131975584',
            'cartera_email' => 'cartera.rmsoft1@gmail.com',
        ],
        'PCS' => [
            'logo' => 'pcs.png',
            'razon_social' => 'Maria Edilma Carranza Leon',
            'nit' => '1004994836-0',
            'banco' => 'Bancolombia',
            'cuenta_tipo' => 'Cuenta Ahorros',
            'cuenta_numero' => '851-0000-4419',
            'cartera_email' => 'cartera.rmsoft1@gmail.com',
        ],
        'SMP' => [
            'logo' => 'rmsoft.png',
            'razon_social' => 'Raul Osvaldo Ramos M.',
            'nit' => '75036432-7',
            'banco' => 'Bancolombia',
            'cuenta_tipo' => 'Cuenta Ahorros',
            'cuenta_numero' => '851-0000-2888',
            'cartera_email' => 'cartera.rmsoft1@gmail.com',
        ],
    ];

    public function __construct(
        private readonly NumeroALetrasService $numeroALetrasService,
    ) {
    }

    public function generateForProformaId(int $proformaId, bool $regenerar = false): array
    {
        $startedAt = microtime(true);
        $cabecera = DB::table('sg_proform')->where('id', $proformaId)->first();

        if (!$cabecera) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        $rutaExistente = trim((string) ($cabecera->rpdf ?? ''));
        $nombreExistente = trim((string) ($cabecera->npdf ?? ''));
        $relativeAnterior = null;

        if ($rutaExistente !== '' && $nombreExistente !== '') {
            $relativeAnterior = $this->construirRutaRelativa($rutaExistente, $nombreExistente);
        }

        if (!$regenerar && $relativeAnterior !== null) {
            if (Storage::disk('local')->exists($relativeAnterior)) {
                $durationMs = (microtime(true) - $startedAt) * 1000;
                Log::info('Proforma PDF: reutilizado existente.', [
                    'proforma_id' => $proformaId,
                    'regenerar' => $regenerar,
                    'relative_path' => $relativeAnterior,
                    'duration_ms' => round($durationMs, 2),
                ]);

                return [
                    'relative_path' => $relativeAnterior,
                    'absolute_path' => Storage::disk('local')->path($relativeAnterior),
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
            'cliente_pdf' => $this->resolverDatosClientePdf($cabecera),
            'mes_nombre' => $this->resolverNombreMes((int) ($cabecera->mes ?? 0)),
            'fecha_emision' => now()->format('Y-m-d'),
            'logo_path' => $this->resolverLogoPath((string) ($cabecera->emisora ?? '')),
            'emisor_pdf' => $this->resolveIssuerData((string) ($cabecera->emisora ?? '')),
            'total_en_letras' => $this->numeroALetrasService->toColombianPesos((float) ($cabecera->vtotal ?? 0)),
        ];

        $renderStartedAt = microtime(true);
        $pdf = Pdf::loadView('proformas.pdf', $data)->setPaper('a4');
        $pdfBinario = $pdf->output();
        $renderDurationMs = (microtime(true) - $renderStartedAt) * 1000;

        $ruta = 'proformas/'.((string) ($cabecera->anio ?? date('Y')));
        $nombreArchivo = $this->construirNombreArchivo($cabecera, $proformaId);
        $relativePath = $this->construirRutaRelativa($ruta, $nombreArchivo);

        $storageStartedAt = microtime(true);
        if ($regenerar && $relativeAnterior !== null && Storage::disk('local')->exists($relativeAnterior)) {
            Storage::disk('local')->delete($relativeAnterior);
        }

        Storage::disk('local')->put($relativePath, $pdfBinario);
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (
            !$regenerar
            && $relativeAnterior !== null
            && $relativeAnterior !== $relativePath
            && Storage::disk('local')->exists($relativeAnterior)
        ) {
            Storage::disk('local')->delete($relativeAnterior);
        }
        $storageDurationMs = (microtime(true) - $storageStartedAt) * 1000;

        $hash = hash('sha256', $pdfBinario);

        $persistStartedAt = microtime(true);
        DB::table('sg_proform')
            ->where('id', $proformaId)
            ->update([
                'rpdf' => $ruta,
                'npdf' => $nombreArchivo,
                'hpdf' => $hash,
            ]);
        $persistDurationMs = (microtime(true) - $persistStartedAt) * 1000;
        $totalDurationMs = (microtime(true) - $startedAt) * 1000;

        Log::info('Proforma PDF: generacion finalizada.', [
            'proforma_id' => $proformaId,
            'regenerar' => $regenerar,
            'filename' => $nombreArchivo,
            'absolute_path' => $absolutePath,
            'file_hash_sha256' => is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null,
            'file_modified_at' => is_file($absolutePath) ? date('Y-m-d H:i:s', filemtime($absolutePath)) : null,
            'detail_count' => $detalle->count(),
            'render_ms' => round($renderDurationMs, 2),
            'storage_ms' => round($storageDurationMs, 2),
            'persist_ms' => round($persistDurationMs, 2),
            'total_ms' => round($totalDurationMs, 2),
        ]);

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'filename' => $nombreArchivo,
            'reused' => false,
        ];
    }

    public function buildBrowserFilename(int $proformaId): string
    {
        $cabecera = DB::table('sg_proform')
            ->select(['id', 'emp', 'nit', 'mes', 'anio'])
            ->where('id', $proformaId)
            ->first();

        if (!$cabecera) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        $clientePotencial = DB::table('clientes_potenciales')
            ->select(['nombre', 'empresa', 'codigo'])
            ->where('nit', trim((string) ($cabecera->nit ?? '')))
            ->first();

        $sgProformEmp = trim((string) ($cabecera->emp ?? ''));
        $clienteNombre = trim((string) ($clientePotencial->nombre ?? ''));
        $clienteEmpresa = trim((string) ($clientePotencial->empresa ?? ''));

        $empresaCandidates = [
            'clientes_potenciales.empresa' => $clienteEmpresa,
            'sg_proform.emp' => $sgProformEmp,
            'clientes_potenciales.nombre' => $clienteNombre,
        ];

        $empresa = '';

        foreach ($empresaCandidates as $value) {
            $empresa = $this->sanitizeBrowserFilenameSegment($value);

            if ($empresa !== '') {
                break;
            }
        }

        $mes = $this->sanitizeBrowserFilenameSegment($this->resolverNombreMes((int) ($cabecera->mes ?? 0)));
        $anio = $this->sanitizeBrowserFilenameSegment((string) ($cabecera->anio ?? ''));
        if ($empresa === '') {
            $empresa = 'SIN_EMPRESA';
        }

        if ($mes === '') {
            $mes = 'SIN_MES';
        }

        if ($anio === '') {
            $anio = 'SIN_ANIO';
        }

        $filenameBase = 'PROFORMA_'.$empresa.'_'.$mes.'_'.$anio;
        $filenameLimited = Str::limit($filenameBase, 146, '');
        $filename = rtrim($filenameLimited, '._ ');

        return $filename.'.pdf';
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

    public function resolveIssuerData(string $emisora): array
    {
        $emisoraNormalizada = strtoupper(trim($emisora));
        $config = self::EMISORAS[$emisoraNormalizada] ?? self::EMISORAS['SAS'];

        return [
            'codigo' => $emisoraNormalizada !== '' ? $emisoraNormalizada : 'SAS',
            'razon_social' => $config['razon_social'],
            'nit' => $config['nit'],
            'banco' => $config['banco'],
            'cuenta_tipo' => $config['cuenta_tipo'],
            'cuenta_numero' => $config['cuenta_numero'],
            'cartera_email' => $config['cartera_email'],
            'logo_path' => $this->resolverLogoPath($emisoraNormalizada),
        ];
    }

    private function resolverLogoPath(string $emisora): ?string
    {
        $em = strtoupper(trim($emisora));
        $logo = self::EMISORAS[$em]['logo'] ?? self::EMISORAS['SAS']['logo'];
        $path = public_path("images/logos/{$logo}");


        return file_exists($path) ? $path : null;
    }

    private function construirRutaRelativa(string $ruta, string $archivo): string
    {
        return trim($ruta, '/').'/'.ltrim($archivo, '/');
    }

    private function resolverDatosClientePdf(object $cabecera): array
    {
        $nit = trim((string) ($cabecera->nit ?? ''));

        $clientePotencial = null;
        if ($nit !== '') {
            $clientePotencial = DB::table('clientes_potenciales')
                ->select(['direccion', 'celular1', 'email'])
                ->where('nit', $nit)
                ->first();
        }

        return [
            'direccion' => $this->valorOPlaceholder($clientePotencial->direccion ?? null),
            'telefono' => $this->valorOPlaceholder($clientePotencial->celular1 ?? null),
            'correo' => $this->valorOPlaceholder($clientePotencial->email ?? null),
        ];
    }

    private function valorOPlaceholder(mixed $valor): string
    {
        $valorNormalizado = trim((string) ($valor ?? ''));

        return $valorNormalizado !== '' ? $valorNormalizado : 'N/D';
    }

    private function sanitizeBrowserFilenameSegment(string $value): string
    {
        $value = trim(Str::ascii($value));
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^A-Za-z0-9\s_-]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return trim(mb_strtoupper($value), '_');
    }
}
