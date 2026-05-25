<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use App\Services\ImportacionesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportacionesController extends Controller
{
    private const SESSION_BATCH_KEY = 'importaciones.batch';

    public function __construct(
        private readonly ImportacionesService $importacionesService,
    ) {
    }

    public function index(Request $request): View
    {
        $batch = $request->session()->get(self::SESSION_BATCH_KEY);
        $preview = is_array($batch)
            ? $this->importacionesService->buildPreview($batch)
            : null;

        return view('importaciones.index', [
            'meses' => CobrosService::MESES,
            'selectedMes' => $preview['periodo']['mes'] ?? (CobrosService::MESES[(int) now()->format('n')] ?? 'enero'),
            'selectedAnio' => $preview['periodo']['anio'] ?? (int) now()->format('Y'),
            'preview' => $preview,
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mes' => ['required', 'string', 'in:' . implode(',', CobrosService::MESES)],
            'anio' => ['required', 'integer', 'min:2000', 'max:9999'],
            'facturas_file' => ['nullable', 'file', 'mimes:csv,xlsx,xls'],
            'soporte_file' => ['nullable', 'file', 'mimes:csv,xlsx,xls'],
            'recepcion_file' => ['nullable', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        if (
            !$request->hasFile('facturas_file')
            && !$request->hasFile('soporte_file')
            && !$request->hasFile('recepcion_file')
        ) {
            return back()
                ->withErrors(['archivos' => 'Debes cargar al menos un archivo de facturas, soporte o recepcion.'])
                ->withInput();
        }

        $batch = $this->importacionesService->buildBatchFromUploads(
            [
                'facturas_file' => $request->file('facturas_file'),
                'soporte_file' => $request->file('soporte_file'),
                'recepcion_file' => $request->file('recepcion_file'),
            ],
            (string) $validated['mes'],
            (int) $validated['anio'],
        );

        $request->session()->put(self::SESSION_BATCH_KEY, $batch);

        return redirect()
            ->route('configuracion.importaciones.index')
            ->with('status', 'Vista previa generada correctamente. Revisa los datos antes de extraer.')
            ->with('status_type', 'success');
    }

    public function extract(Request $request): RedirectResponse
    {
        $batch = $request->session()->get(self::SESSION_BATCH_KEY);

        if (!is_array($batch)) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', 'No hay una vista previa temporal para procesar.')
                ->with('status_type', 'warning');
        }

        $preview = $this->importacionesService->buildPreview($batch);
        $result = $this->importacionesService->processBatch(
            $batch,
            $preview,
            (string) session('usuario', 'usuario'),
            session()->has('idusuario') ? (int) session('idusuario') : null,
        );

        $request->session()->forget(self::SESSION_BATCH_KEY);

        $message = sprintf(
            'Extraccion finalizada. Registros procesados: %d. Actualizados: %d. Errores: %d. Log #%d.',
            $result['processed'],
            $result['updated'],
            count($result['errors']),
            $result['log_id'],
        );

        if (($result['errors'] ?? []) !== []) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', $message)
                ->with('status_type', 'warning')
                ->with('importacion_errores_finales', $result['errors']);
        }

        return redirect()
            ->route('configuracion.importaciones.index')
            ->with('status', $message)
            ->with('status_type', 'success');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_BATCH_KEY);

        return redirect()
            ->route('configuracion.importaciones.index')
            ->with('status', 'Vista previa temporal eliminada.')
            ->with('status_type', 'success');
    }
}
