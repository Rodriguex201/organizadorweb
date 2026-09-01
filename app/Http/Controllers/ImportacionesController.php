<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use App\Services\CobrosBasePeriodoService;
use App\Services\ImportacionesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportacionesController extends Controller
{
    private const SESSION_BATCH_KEY = 'importaciones.batch';

    public function __construct(
        private readonly ImportacionesService $importacionesService,
        private readonly CobrosBasePeriodoService $cobrosBasePeriodoService,
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

        $pendingAssignments = collect($preview['rows'] ?? [])
            ->filter(fn (array $row) => ($row['status'] ?? null) === 'pending_assignment')
            ->pluck('nit')
            ->unique()
            ->count();

        if ($pendingAssignments > 0) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', sprintf(
                    'Hay %d NIT pendiente(s) de asignacion. Resuelvelos antes de extraer los datos.',
                    $pendingAssignments
                ))
                ->with('status_type', 'warning');
        }

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

    public function generateBase(Request $request): RedirectResponse
    {
        $batch = $request->session()->get(self::SESSION_BATCH_KEY);

        if (!is_array($batch)) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', 'No hay una carga temporal activa para determinar el periodo.')
                ->with('status_type', 'warning');
        }

        $periodo = is_array($batch['periodo'] ?? null) ? $batch['periodo'] : [];
        $mes = (string) ($periodo['mes'] ?? '');
        $anio = (int) ($periodo['anio'] ?? 0);

        if ($mes === '' || $anio <= 0) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', 'No fue posible resolver el periodo de la carga temporal.')
                ->with('status_type', 'warning');
        }

        $result = $this->cobrosBasePeriodoService->generate($mes, $anio);
        $message = sprintf(
            'Registros base generados para %s %d. Nuevos: %d. Ya existentes: %d. Clientes activos evaluados: %d.',
            ucfirst((string) $result['periodo']['mes']),
            (int) $result['periodo']['anio'],
            (int) $result['created'],
            (int) $result['skipped_existing'],
            (int) $result['total_active_clients'],
        );

        return redirect()
            ->route('configuracion.importaciones.index')
            ->with('status', $message)
            ->with('status_type', 'success');
    }

    public function assignAmbiguous(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_id' => ['required', 'string'],
            'id_cobro' => ['required', 'integer', 'min:1'],
        ]);

        $batch = $request->session()->get(self::SESSION_BATCH_KEY);

        if (!is_array($batch)) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', 'No hay una carga temporal activa para resolver coincidencias ambiguas.')
                ->with('status_type', 'warning');
        }

        try {
            $assignmentResult = $this->importacionesService->assignManualMatch(
                $batch,
                (string) $validated['entry_id'],
                (int) $validated['id_cobro'],
            );
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('configuracion.importaciones.index')
                ->with('status', $exception->getMessage())
                ->with('status_type', 'warning');
        }

        $request->session()->put(self::SESSION_BATCH_KEY, $assignmentResult['batch']);

        $resolvedEntries = (int) ($assignmentResult['resolved_entries'] ?? 1);
        $nit = (string) ($assignmentResult['nit'] ?? '');
        $message = $resolvedEntries > 1
            ? sprintf(
                'Se resolvieron automaticamente %d entradas para el NIT %s.',
                $resolvedEntries,
                $nit
            )
            : 'Coincidencia ambigua resuelta manualmente. Ya puedes continuar con la extraccion.';

        return redirect()
            ->route('configuracion.importaciones.index')
            ->with('status', $message)
            ->with('status_type', 'success');
    }
}
