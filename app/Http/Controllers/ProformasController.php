<?php

namespace App\Http\Controllers;

use App\Services\EmpresaActivacionService;
use App\Services\ProformaEmailService;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProformasController extends Controller
{
    private const FILTER_KEYS = ['nro_prof', 'nit', 'empresa', 'emisora', 'mes', 'anio', 'estado', 'envio', 'filtro_nota'];

    public function __construct(
        private readonly ProformasService $proformasService,
        private readonly ProformaPdfService $proformaPdfService,
        private readonly ProformaEmailService $proformaEmailService,
        private readonly ProformaDashboardExportService $proformaDashboardExportService,
        private readonly EmpresaActivacionService $empresaActivacionService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $hasFilterQuery = collect(self::FILTER_KEYS)->contains(
            fn (string $key) => $request->query->has($key)
        );

        $defaultFilters = $this->defaultFilters();

        if (!$hasFilterQuery) {
            $this->storeFilterSession($defaultFilters);

            return view('proformas.index', [
                'proformas' => $this->emptyProformasPaginator($request),
                'filters' => $defaultFilters,
                'estados' => ProformasService::ESTADOS,
                'meses' => ProformasService::MESES,
                'proformasService' => $this->proformasService,
                'hasSearched' => false,
            ]);
        }

        $rawFilters = $hasFilterQuery
            ? $request->only(self::FILTER_KEYS)
            : [];

        $validated = Validator::make($rawFilters, [
            'nro_prof' => ['nullable', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:60'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'emisora' => ['nullable', 'string', 'max:20'],
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'estado' => ['nullable', 'integer', 'min:0'],
            'envio' => ['nullable', 'in:0,1'],
            'filtro_nota' => ['nullable', 'in:con,sin'],
        ])->validate();

        $periodo = $this->proformasService->normalizePeriodoFilters(
            $validated['mes'] ?? null,
            $validated['anio'] ?? null,
        );

        $filters = [
            'nro_prof' => $validated['nro_prof'] ?? null,
            'nit' => $validated['nit'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'emisora' => $validated['emisora'] ?? null,
            'mes' => $periodo['mes'],
            'anio' => $periodo['anio'],
            'estado' => $validated['estado'] ?? null,
            'envio' => isset($validated['envio']) ? (string) $validated['envio'] : null,
            'filtro_nota' => $validated['filtro_nota'] ?? null,
        ];

        $this->storeFilterSession($filters);

        return view('proformas.index', [
            'proformas' => $this->proformasService->paginateProformas($filters),
            'filters' => $filters,
            'estados' => ProformasService::ESTADOS,
            'meses' => ProformasService::MESES,
            'proformasService' => $this->proformasService,
            'hasSearched' => true,
        ]);
    }

    public function clearFilters(): RedirectResponse
    {
        session()->forget(['proformas', 'proformas.filtros_originales']);
        session()->forget('proformas.codigo');

        return redirect()->route('proformas.index');
    }

    public function confirmarEnvioMasivo(Request $request, int $grupo): View|JsonResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        ]);

        $periodo = $this->proformasService->normalizePeriodoFilters(
            $validated['mes'] ?? null,
            $validated['anio'] ?? null,
        );

        $resumen = $this->proformasService->buildBatchEnvioResumen($grupo, $periodo);

        if ($request->expectsJson()) {
            return response()->json([
                'grupo' => $grupo,
                'periodo' => $periodo,
                'resumen' => [
                    'total_encontradas' => $resumen['total_encontradas'],
                    'validas_count' => $resumen['validas_count'],
                    'omitidas_count' => $resumen['omitidas_count'],
                    'omitidas_por_motivo' => $resumen['omitidas_por_motivo'],
                    'validas' => $resumen['validas']->map(fn (object $proforma) => [
                        'id' => (int) $proforma->id,
                        'nro_prof' => (string) ($proforma->nro_prof ?? ''),
                        'empresa' => (string) ($proforma->emp ?? ''),
                        'nit' => (string) ($proforma->nit ?? ''),
                        'email' => (string) ($proforma->cliente_email ?? ''),
                        'fecha_arriendo' => \Illuminate\Support\Carbon::make($proforma->cliente_fecha_arriendo)?->format('d/m/Y') ?: 'N/D',
                    ])->values(),
                ],
            ]);
        }

        return view('proformas.confirmar-envio-masivo', [
            'grupo' => $grupo,
            'resumen' => $resumen,
            'filtrosPeriodo' => [
                'mes' => $periodo['mes'],
                'anio' => $periodo['anio'],
            ],
        ]);
    }

    public function enviarMasivo(Request $request, int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'proformas' => ['required', 'array', 'min:1'],
            'proformas.*' => ['integer'],
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        ]);

        $periodo = $this->proformasService->normalizePeriodoFilters(
            $validated['mes'] ?? null,
            $validated['anio'] ?? null,
        );

        $ids = array_values(array_unique(array_map('intval', $validated['proformas'] ?? [])));
        $candidatas = $this->proformasService->findBatchCandidatesByIdsForPeriodo($grupo, $ids, $periodo);
        $delaySeconds = max(0, (int) config('services.proforma_bulk_send_delay_seconds', 2));
        $totalCandidatas = $candidatas->count();

        $enviadas = 0;
        $fallidas = [];
        $omitidas = 0;

        foreach ($candidatas->values() as $index => $proforma) {
            if ($this->proformasService->invalidReasonForBatch($proforma) !== null) {
                $omitidas++;
                continue;
            }

            try {
                $this->proformaEmailService->sendProforma($proforma);
                $this->proformasService->registrarEnvioExitoso((int) $proforma->id);
                $enviadas++;
            } catch (\Throwable $exception) {
                $this->proformasService->registrarIntentoFallido((int) $proforma->id);
                $fallidas[] = [
                    'id' => $proforma->id,
                    'nro_prof' => $proforma->nro_prof,
                    'error' => $exception->getMessage(),
                ];
                report($exception);
            }

            if ($delaySeconds > 0 && $index < ($totalCandidatas - 1)) {
                Log::info(sprintf('Esperando %d segundos antes del siguiente envío', $delaySeconds));
                sleep($delaySeconds);
            }
        }

        $omitidas += max(0, count($ids) - $candidatas->count());

        $statusType = count($fallidas) > 0 ? 'error' : 'success';
        $message = "Envio masivo grupo {$grupo} finalizado. Enviadas: {$enviadas}. Omitidas: {$omitidas}. Fallidas: ".count($fallidas).'.';

        if ($fallidas !== []) {
            $message .= ' Fallas: '.collect($fallidas)
                ->take(3)
                ->map(fn (array $fallida) => sprintf('#%s (%s)', $fallida['nro_prof'] ?: $fallida['id'], $fallida['error']))
                ->implode(' | ');
        }

        return redirect()
            ->route('proformas.index', [
                'mes' => $periodo['mes'],
                'anio' => $periodo['anio'],
            ])
            ->with('status', $message)
            ->with('status_type', $statusType);
    }

    public function dashboard(Request $request): View
    {
        $validated = $request->validate([
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'estado' => ['nullable', 'integer'],
        ]);

        $periodo = $this->proformasService->normalizePeriodoFilters(
            $validated['mes'] ?? null,
            $validated['anio'] ?? null,
        );
        $estado = array_key_exists('estado', $validated)
            ? $this->normalizarEntero($validated['estado'])
            : null;

        $dashboard = $this->proformasService->getDashboardData(
            $periodo['mes'],
            $periodo['anio'],
            $estado,
        );

        return view('proformas.dashboard', [
            'dashboard' => $dashboard,
            'filters' => array_merge($periodo, ['estado' => $estado]),
            'meses' => ProformasService::MESES,
            'estados' => ProformasService::ESTADOS,
            'exportOptions' => $this->proformaDashboardExportService->getModalOptions(array_merge($periodo, ['estado' => $estado])),
            'proformasService' => $this->proformasService,
        ]);
    }

    public function exportDashboard(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'dashboard_mes' => ['nullable', 'string', 'max:20'],
            'dashboard_anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'dashboard_estado' => ['nullable', 'integer'],
            'scope' => ['required', 'in:current_filters,current_month,full_year,monthly_range'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'mes_desde' => ['nullable', 'integer', 'min:1', 'max:12'],
            'mes_hasta' => ['nullable', 'integer', 'min:1', 'max:12'],
            'estado' => ['nullable', 'integer'],
            'mode' => ['required', 'in:summary,detailed'],
            'format' => ['required', 'in:xlsx'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
        ]);

        $dashboardFilters = [
            'mes' => $validated['dashboard_mes'] ?? null,
            'anio' => $validated['dashboard_anio'] ?? null,
            'estado' => $validated['dashboard_estado'] ?? null,
        ];

        try {
            $filters = $this->proformaDashboardExportService->resolveFilters($validated, $dashboardFilters);

            if ($request->expectsJson()) {
                $prepared = $this->proformaDashboardExportService->prepareTemporaryDownload(
                    $filters,
                    $validated['columns'] ?? [],
                    $validated['mode'] ?? ProformaDashboardExportService::EXPORT_MODE_DETAILED,
                    $validated['format'] ?? ProformaDashboardExportService::FORMAT_XLSX,
                );

                return response()->json([
                    'ok' => true,
                    'message' => 'Excel generado correctamente.',
                    'download_url' => route('proformas.dashboard.export.download', ['token' => $prepared['token']]),
                    'filename' => $prepared['filename'],
                    'record_count' => $prepared['record_count'],
                    'duration_ms' => $prepared['duration_ms'],
                ]);
            }

            return $this->proformaDashboardExportService->download(
                $filters,
                $validated['columns'] ?? [],
                $validated['mode'] ?? ProformaDashboardExportService::EXPORT_MODE_DETAILED,
                $validated['format'] ?? ProformaDashboardExportService::FORMAT_XLSX,
            );
        } catch (\Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se pudo generar el archivo Excel. Verifica los filtros e inténtalo nuevamente.',
                ], 422);
            }

            throw $exception;
        }
    }

    public function downloadDashboardExport(string $token): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->proformaDashboardExportService->downloadTemporaryFile($token);
        } catch (\Throwable $exception) {
            report($exception);

            if (request()->expectsJson()) {
                return response()->json([
                    'message' => 'No se encontró el archivo exportado o ya expiró.',
                ], 404);
            }

            return redirect()
                ->route('proformas.dashboard')
                ->with('status', 'No se encontró el archivo exportado o ya expiró.')
                ->with('status_type', 'error');
        }
    }

    public function showPdf(Request $request, int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId(
            $id,
            $request->boolean('regenerar'),
        );
        $browserFilename = $this->proformaPdfService->buildBrowserFilename($id);

        return response()->file($resultado['absolute_path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$browserFilename.'"; filename*=UTF-8\'\''.rawurlencode($browserFilename),
        ]);
    }

    public function downloadPdf(int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId($id);
        $browserFilename = $this->proformaPdfService->buildBrowserFilename($id);

        return response()->download($resultado['absolute_path'], $browserFilename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$browserFilename.'"; filename*=UTF-8\'\''.rawurlencode($browserFilename),
        ]);
    }

    public function show(int $id): View
    {
        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            abort(404, 'Proforma no encontrada');
        }

        session(['proformas.filtros_originales' => $this->sanitizeFilterArray(request()->query())]);

        return view('proformas.show', [
            'proforma' => $proforma,
            'proformasService' => $this->proformasService,
        ]);
    }

    public function backToIndex(int $id): RedirectResponse
    {
        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        $redirectFilters = $this->getStoredReturnFilters();
        $estadoFiltrado = session('proformas.estado');
        $estadoActual = (int) ($proforma->estado ?? 0);
        $debeLimpiarFiltroEstado = $estadoFiltrado !== null
            && (string) $estadoFiltrado !== ''
            && (int) $estadoFiltrado !== $estadoActual;

        if ($debeLimpiarFiltroEstado) {
            session()->forget('proformas.estado');
            unset($redirectFilters['estado']);

            return redirect()
                ->route('proformas.index', $redirectFilters)
                ->with('warning', 'La proforma cambio de estado y ya no coincide con el filtro actual.');
        }

        return redirect()->route('proformas.index', $redirectFilters);
    }

    public function enviarCorreo(int $id): RedirectResponse|JsonResponse
    {
        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        if (!$this->proformasService->canSendProforma($proforma)) {
            return redirect()->back()
                ->with('status', 'Primero debe generar la proforma antes de enviarla')
                ->with('status_type', 'error');
        }

        $isReenvio = (int) ($proforma->enviado ?? 0) === 1;
        $logPrefix = $isReenvio ? '[REENVIO PROFORMA]' : '[ENVIO MANUAL PROFORMA]';
        $destinatarios = null;

        Log::info($logPrefix.' ANTES DE OBTENER PROFORMA', [
            'proforma_id' => $id,
        ]);

        try {
            $destinatarios = $this->proformaEmailService->resolveDestinatarios($proforma, $logPrefix);

            Log::info($logPrefix.' DATOS PREVIOS', [
                'proforma_id' => $id,
                'email_original_cliente' => $destinatarios['original'],
                'correo_final_a_enviar' => $destinatarios['emails'],
                'cantidad_correos' => $destinatarios['count'],
            ]);

            $this->proformaEmailService->sendProforma($proforma, [
                'destinatarios' => $destinatarios,
                'log_prefix' => $logPrefix,
            ]);
            $this->proformasService->registrarEnvioExitoso($id);
            Log::info($logPrefix.' REENVIO EXITOSO', [
                'proforma_id' => $id,
            ]);

            $proformaActualizada = $this->proformasService->findProformaById($id);

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Proforma enviada por correo correctamente.',
                    'proforma' => [
                        'id' => $id,
                        'enviado' => (int) ($proformaActualizada->enviado ?? 1),
                        'fecha_envio' => $proformaActualizada->fecha_envio ?? null,
                        'intentos_envio' => (int) ($proformaActualizada->intentos_envio ?? 0),
                        'estado' => (int) ($proformaActualizada->estado ?? 0),
                    ],
                ]);
            }

            return redirect()->back()->with('status', 'Proforma enviada por correo correctamente.')->with('status_type', 'success');
        } catch (\Throwable $exception) {
            $this->proformasService->registrarIntentoFallido($id);

            Log::error($logPrefix.' CATCH', [
                'proforma_id' => $id,
                'mensaje_error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'payload' => [
                    'email_original_cliente' => $destinatarios['original'] ?? null,
                    'correo_final_a_enviar' => $destinatarios['emails'] ?? [],
                    'cantidad_correos' => $destinatarios['count'] ?? 0,
                ],
            ]);

            report($exception);

            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo enviar el correo: '.$exception->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('status', 'No se pudo enviar el correo: '.$exception->getMessage())->with('status_type', 'error');
        }
    }

    public function updateEstado(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'integer'],
            'redirect_to' => ['nullable', 'string', 'in:index,show'],
        ]);

        $resultado = $this->proformasService->updateEstado($id, (int) $validated['estado']);

        if ($request->expectsJson()) {
            return response()->json($resultado, $resultado['ok'] ? 200 : 422);
        }

        $routeName = ($validated['redirect_to'] ?? 'index') === 'show' ? 'proformas.show' : 'proformas.index';
        $routeParams = $routeName === 'proformas.show' ? ['id' => $id] : [];

        $redirect = redirect()->route($routeName, $routeParams);

        if ($routeName === 'proformas.index') {
            $redirect->withInput();
        }

        return $resultado['ok']
            ? $redirect->with('status', $resultado['message'])->with('status_type', 'success')
            : $redirect->with('status', $resultado['message'])->with('status_type', 'error');
    }

    public function marcarEnviada(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $resultado = $this->proformasService->marcarEnvioManual($id);

        if ($resultado['ok']) {
            $this->registrarLogEnvioManual($id, 'marcar_enviada_manual');
        }

        if ($request->expectsJson()) {
            return response()->json($resultado, $resultado['ok'] ? 200 : 422);
        }

        return $resultado['ok']
            ? redirect()->back()->with('status', $resultado['message'])->with('status_type', 'success')
            : redirect()->back()->with('status', $resultado['message'])->with('status_type', 'error');
    }

    public function marcarNoEnviada(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $resultado = $this->proformasService->marcarNoEnviada($id);

        if ($resultado['ok']) {
            $this->registrarLogEnvioManual($id, 'marcar_no_enviada_manual');
        }

        if ($request->expectsJson()) {
            return response()->json($resultado, $resultado['ok'] ? 200 : 422);
        }

        return $resultado['ok']
            ? redirect()->back()->with('status', $resultado['message'])->with('status_type', 'success')
            : redirect()->back()->with('status', $resultado['message'])->with('status_type', 'error');
    }

    public function obtenerActivacion(Request $request, int $id): JsonResponse
    {
        if ($response = $this->denyIfNotActivationAdmin()) {
            return $response;
        }

        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            return response()->json([
                'ok' => false,
                'message' => 'La proforma seleccionada no existe.',
            ], 404);
        }

        Log::info('[ACTIVACION REQUEST]', $request->all());

        try {
            $codigoEmpresa = $this->resolverCodigoEmpresaActivacion($request, $id, $proforma);
            $detalle = $this->empresaActivacionService->obtenerDetalle($codigoEmpresa);

            return response()->json([
                'ok' => true,
                'message' => 'Datos de activación cargados correctamente.',
                'data' => $detalle,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage() ?: 'No fue posible consultar la activación de la empresa.',
            ], 422);
        }
    }

    public function guardarActivacion(Request $request, int $id): JsonResponse
    {
        if ($response = $this->denyIfNotActivationAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d', 'before_or_equal:fecha_fin'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ]);

        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            return response()->json([
                'ok' => false,
                'message' => 'La proforma seleccionada no existe.',
            ], 404);
        }

        Log::info('[ACTIVACION REQUEST]', $request->all());

        try {
            $codigoEmpresa = $this->resolverCodigoEmpresaActivacion($request, $id, $proforma);
            $detalle = $this->empresaActivacionService->guardarActivacion(
                $codigoEmpresa,
                $validated['fecha_inicio'],
                $validated['fecha_fin'],
                $this->resolverUsuarioLog(),
            );

            return response()->json([
                'ok' => true,
                'message' => 'La activación de la empresa se actualizó correctamente.',
                'data' => $detalle,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage() ?: 'No fue posible guardar la activación de la empresa.',
            ], 422);
        }
    }

    private function storeFilterSession(array $filters): void
    {
        session()->forget('proformas.codigo');

        session([
            'proformas.numero' => $filters['nro_prof'],
            'proformas.nit' => $filters['nit'],
            'proformas.empresa' => $filters['empresa'],
            'proformas.emisora' => $filters['emisora'],
            'proformas.mes' => $filters['mes'],
            'proformas.anio' => $filters['anio'],
            'proformas.estado' => $filters['estado'],
            'proformas.envio' => $filters['envio'],
            'proformas.filtro_nota' => $filters['filtro_nota'],
        ]);
    }

    private function defaultFilters(): array
    {
        return [
            'nro_prof' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => (int) now()->format('n'),
            'anio' => (int) now()->format('Y'),
            'estado' => null,
            'envio' => null,
            'filtro_nota' => null,
        ];
    }

    private function emptyProformasPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: 15,
            currentPage: 1,
            options: [
                'path' => $request->url(),
                'pageName' => 'page',
            ],
        );
    }

    private function getStoredReturnFilters(): array
    {
        $storedFilters = session('proformas.filtros_originales');

        if (is_array($storedFilters) && $storedFilters !== []) {
            return $this->sanitizeFilterArray($storedFilters);
        }

        return $this->sanitizeFilterArray([
            'nro_prof' => session('proformas.numero'),
            'nit' => session('proformas.nit'),
            'empresa' => session('proformas.empresa'),
            'emisora' => session('proformas.emisora'),
            'mes' => session('proformas.mes'),
            'anio' => session('proformas.anio'),
            'estado' => session('proformas.estado'),
            'envio' => session('proformas.envio'),
            'filtro_nota' => session('proformas.filtro_nota'),
        ]);
    }

    private function sanitizeFilterArray(array $filters): array
    {
        $sanitized = [];

        foreach (self::FILTER_KEYS as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];

            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function registrarLogEnvioManual(int $proformaId, string $accion): void
    {
        Log::info('Proforma envio manual actualizado.', [
            'accion' => $accion,
            'proforma_id' => $proformaId,
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
        ]);
    }

    private function resolverUsuarioLog(): string
    {
        $usuario = trim((string) session('usuario', 'usuario'));
        $idUsuario = session()->has('idusuario') ? (string) session('idusuario') : null;

        return $idUsuario ? "{$usuario} ({$idUsuario})" : $usuario;
    }

    private function resolverCodigoEmpresaActivacion(Request $request, int $id, ?object $proforma): string
    {
        $codigoRequest = trim((string) $request->input('codigo', ''));
        if ($codigoRequest !== '') {
            return $codigoRequest;
        }

        $codigoProforma = trim((string) ($proforma->codigo ?? ''));
        if ($codigoProforma !== '') {
            return $codigoProforma;
        }

        $idProforma = (int) ($request->input('id_proforma', $id) ?: $id);
        $codigoDirecto = trim((string) (DB::table('sg_proform')
            ->where('id', $idProforma)
            ->value('codigo') ?? ''));

        if ($codigoDirecto !== '') {
            return $codigoDirecto;
        }

        throw new \RuntimeException('No fue posible determinar el código de la empresa desde la proforma seleccionada.');
    }

    private function denyIfNotActivationAdmin(): ?JsonResponse
    {
        $roleId = session('rol_id', session('roles_idroles'));

        if ((int) $roleId === 1) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'No autorizado para gestionar activaciones.',
        ], 403);
    }

    private function normalizarEntero(null|string|int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' || !ctype_digit($string)) {
            return null;
        }

        return (int) $string;
    }
}
