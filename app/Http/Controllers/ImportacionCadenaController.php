<?php

namespace App\Http\Controllers;

use App\Services\Cadena\CadenaConsultaService;
use App\Services\Cadena\CadenaExportService;
use App\Services\Cadena\CadenaDescargaService;
use App\Services\Cadena\CadenaResumenService;
use App\Services\Cadena\CadenaSpreadsheetReader;
use App\Services\CobrosService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportacionCadenaController extends Controller
{
    public function index(): Response
    {
        return $this->page();
    }

    public function preview(Request $request, CadenaSpreadsheetReader $reader, CadenaResumenService $summary, CadenaConsultaService $query, CadenaExportService $exporter): Response
    {
        [$data, $preview] = $this->prepare($request, $reader, $summary, $query, $exporter);
        return $this->page(['preview' => $preview, 'selectedMes' => $data['mes'], 'selectedAnio' => (int) $data['anio']]);
    }

    private function prepare(Request $request, CadenaSpreadsheetReader $reader, CadenaResumenService $summary, CadenaConsultaService $query, CadenaExportService $exporter): array
    {
        $uploadIssues = (new \App\Services\Cadena\CadenaUploadService())->inspect(
            $request->files->all(), (string) ini_get('upload_max_filesize')
        );
        if ($uploadIssues !== []) {
            $configuredTmp = trim((string) ini_get('upload_tmp_dir'));
            $tmpDirectory = $configuredTmp !== '' ? $configuredTmp : sys_get_temp_dir();
            foreach ($uploadIssues as $field => $issue) {
                // No registrar contenido, nombres de clientes ni rutas temporales.
                \Illuminate\Support\Facades\Log::warning('Cadena: fallo de recepción de archivo.', [
                    'field' => $field, 'upload_error' => $issue['code'], 'upload_error_name' => $issue['symbol'],
                    'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'),
                    'max_file_uploads' => ini_get('max_file_uploads'), 'max_input_time' => ini_get('max_input_time'),
                    'max_execution_time' => ini_get('max_execution_time'), 'memory_limit' => ini_get('memory_limit'),
                    'upload_tmp_dir_configured' => $configuredTmp !== '',
                    'upload_tmp_dir_exists' => is_dir($tmpDirectory),
                    'upload_tmp_dir_writable' => is_writable($tmpDirectory),
                ]);
            }
            throw \Illuminate\Validation\ValidationException::withMessages(
                array_map(fn (array $issue) => $issue['message'], $uploadIssues)
            );
        }
        $rules = ['mes' => ['required', 'string', 'in:'.implode(',', CobrosService::MESES)], 'anio' => ['required', 'integer', 'min:2000', 'max:9999']];
        foreach (['facturas', 'soporte', 'eventos'] as $category) {
            $rules[$category] = ['nullable', 'array', 'max:5'];
            $rules[$category.'.*'] = ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:10240'];
        }
        $data = $request->validate($rules);
        $files = [];
        foreach (['facturas', 'soporte', 'eventos'] as $category) {
            foreach ($request->file($category, []) as $upload) {
                $file = ['categoria' => $category, 'archivo' => $upload->getClientOriginalName(), 'hash' => hash_file('sha256', $upload->getRealPath())];
                try {
                    $file += $reader->read($upload->getRealPath(), strtolower($upload->getClientOriginalExtension()), $category);
                } catch (\Throwable $exception) {
                    // Do not expose server paths or library internals in the browser.
                    $file['error'] = $exception instanceof \RuntimeException && get_class($exception) === \RuntimeException::class
                        ? $exception->getMessage() : 'No fue posible leer este archivo. Verifica su formato y contenido.';
                }
                $files[] = $file;
            }
        }
        if ($files === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['archivos' => 'Selecciona al menos un archivo original.']);
        }
        $warnings = [];
        $snapshotAvailable = true;
        try {
            $snapshot = $query->snapshot($data['mes'], (int) $data['anio'], (int) array_search($data['mes'], CobrosService::MESES, true));
            $warnings = $snapshot['advertencias'];
        } catch (\Throwable) {
            $snapshotAvailable = false;
            $snapshot = ['clientes' => [], 'destinos' => []];
            $warnings[] = 'No fue posible consultar clientes y proformas. Todos los NIT quedan pendientes; la protección del destino NO está verificada.';
        }
        $preview = $summary->build($files, $snapshot['clientes'], $snapshot['destinos']);
        $preview['advertencias'] = $warnings;
        $preview['consulta_disponible'] = $snapshotAvailable;

        try {
            $exports = $exporter->prepare($preview, $files);
            $exports['paquete_errores'] = $snapshotAvailable ? [] : ['Clientes y destinos: no fue posible completar la consulta de la vista previa.'];
            foreach ($files as $file) {
                if (isset($file['error'])) {
                    $exports['paquete_errores'][] = $file['categoria'].': '.$file['archivo'].' — '.$file['error'];
                }
            }
            $preview['descargas'] = (new CadenaDescargaService())->prepare($exports, $data['mes'], (int) $data['anio'],
                $request->session()->getId(), \Illuminate\Support\Facades\Crypt::getFacadeRoot());
        } catch (\Throwable) {
            $preview['advertencias'][] = 'No fue posible preparar las descargas. Divide la carga en archivos más pequeños y vuelve a preparar la vista previa.';
        }

        return [$data, $preview, $exports ?? null];
    }

    public function generate(Request $request, CadenaSpreadsheetReader $reader, CadenaResumenService $summary, CadenaConsultaService $query, CadenaExportService $exporter): Response
    {
        $action = $request->validate(['accion' => ['required', 'in:facturas,soporte,eventos,paquete']])['accion'];
        $categories = $action === 'paquete' ? ['facturas', 'soporte', 'eventos'] : [$action];
        $required = [];
        foreach (['facturas', 'soporte', 'eventos'] as $category) {
            if (in_array($category, $categories, true)) {
                $required[$category] = ['required', 'array', 'min:1', 'max:5'];
            } else {
                $request->files->remove($category);
                $request->request->remove($category);
            }
        }
        $request->validate($required);
        [$data, $preview, $exports] = $this->prepare($request, $reader, $summary, $query, $exporter);
        $issues = (new \App\Services\Cadena\CadenaGeneracionService())->issues($preview, $exports, $action);
        if ($issues !== []) {
            return response()->json(['message' => 'No se generó ningún archivo. Corrige los errores o pendientes y vuelve a intentar.', 'issues' => $issues], 422);
        }
        $path = null;
        try {
            if ($action === 'paquete') {
                $path = (new \App\Services\Cadena\CadenaPaqueteService())->create($preview['descargas']['tokens'], $data['mes'], (int) $data['anio'],
                    $request->session()->getId(), \Illuminate\Support\Facades\Crypt::getFacadeRoot(), $exporter);
                $filename = 'Cadena_'.ucfirst($data['mes']).'_'.$data['anio'].'.zip';
                $mime = 'application/zip';
            } else {
                $filename = \App\Services\Cadena\CadenaGeneracionService::FILES[$action];
                $path = tempnam(sys_get_temp_dir(), 'cadena-direct-');
                if ($path === false) { $path = null; throw new \RuntimeException('No hay espacio temporal para generar el archivo.'); }
                $exporter->write($exports['tables'][$filename], ucfirst($data['mes']).' '.$data['anio'], $path);
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            return response()->download($path, $filename, ['Content-Type' => $mime, 'Cache-Control' => 'no-store, private',
                'X-Cadena-Filename' => $filename])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if ($path !== null && is_file($path)) { unlink($path); }
            $reason = get_class($exception) === \RuntimeException::class ? $exception->getMessage() : 'No fue posible terminar el archivo. Revisa la selección y vuelve a intentar.';
            return response()->json(['message' => 'No se entregó un archivo parcial.', 'issues' => [$action.': '.$reason]], 422);
        }
    }

    public function download(Request $request, CadenaExportService $exporter): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4194304'],
            'archivo' => ['required', 'string', 'max:80'], 'mes' => ['required', 'string'], 'anio' => ['required', 'integer']]);
        try {
            $payload = (new CadenaDescargaService())->decode($data['token'], $data['archivo'], $data['mes'], (int) $data['anio'],
                $request->session()->getId(), \Illuminate\Support\Facades\Crypt::getFacadeRoot());
            $table = $payload['table'];
        } catch (\Throwable) {
            abort(422, 'La descarga expiró o no corresponde a esta sesión/período. Prepara una nueva vista previa.');
        }
        // Only the authenticated, encrypted preview is used; no queries, saved batch or legacy import.
        return response()->streamDownload(function () use ($exporter, $table, $payload) {
            $exporter->write($table, ucfirst($payload['mes']).' '.$payload['anio'], 'php://output');
        }, $data['archivo'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private']);
    }

    private function page(array $data = []): Response
    {
        // No saved batch: a new period/upload always produces a new, independent preview.
        return response()->view('importaciones.cadena.index', $data + [
            'meses' => CobrosService::MESES, 'selectedMes' => '', 'selectedAnio' => '', 'preview' => null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function downloadPackage(Request $request, CadenaExportService $exporter): Response
    {
        $data = $request->validate([
            'tokens' => ['required', 'array', 'size:3'], 'tokens.*' => ['required', 'string', 'max:4194304'],
            'mes' => ['required', 'string', 'in:'.implode(',', CobrosService::MESES)],
            'anio' => ['required', 'integer', 'min:2000', 'max:9999'],
        ]);
        try {
            $path = (new \App\Services\Cadena\CadenaPaqueteService())->create($data['tokens'], $data['mes'], (int) $data['anio'],
                $request->session()->getId(), \Illuminate\Support\Facades\Crypt::getFacadeRoot(), $exporter);
        } catch (\RuntimeException $exception) {
            return response()->view('importaciones.cadena.paquete-error', [
                'message' => $exception->getMessage(), 'tokens' => array_intersect_key($data['tokens'], \App\Services\Cadena\CadenaPaqueteService::FILES),
                'mes' => $data['mes'], 'anio' => $data['anio'],
            ], 422)->header('Cache-Control', 'no-store, private');
        }
        return response()->download($path, 'Cadena_'.ucfirst($data['mes']).'_'.$data['anio'].'.zip',
            ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend(true);
    }
}
