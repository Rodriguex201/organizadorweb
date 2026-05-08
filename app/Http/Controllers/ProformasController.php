<?php

namespace App\Http\Controllers;

use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\DB;


class ProformasController extends Controller
{
    public function __construct(
        private readonly ProformasService $proformasService,
        private readonly ProformaPdfService $proformaPdfService,
        private readonly ProformaEmailService $proformaEmailService,
    ) {
    }

public function index(Request $request): View|RedirectResponse
{
    // 🔥 SOLO limpiar, NO redirigir
if ($request->get('from') === 'detalle') {

    $filtros = session('proformas.filtros_originales', []);

    // 🔥 limpiar solo estado
    unset($filtros['estado']);

    // 🔥 redirigir con filtros originales
    return redirect()->route('proformas.index', $filtros);
}

    $filterKeys = ['nro_prof', 'codigo', 'nit', 'empresa', 'emisora', 'mes', 'anio', 'estado', 'envio'];
    $hasRequestFilters = collect($filterKeys)->contains(fn (string $key) => $request->filled($key));

    $rawFilters = [
        'nro_prof' => $request->input('nro_prof', session('proformas.numero')),
        'codigo' => $request->input('codigo', session('proformas.codigo')),
        'nit' => $request->input('nit', session('proformas.nit')),
        'empresa' => $request->input('empresa', session('proformas.empresa')),
        'emisora' => $request->input('emisora', session('proformas.emisora')),
        'mes' => $request->input('mes', session('proformas.mes')),
        'anio' => $request->input('anio', session('proformas.anio')),
        'estado' => $request->input('estado', session('proformas.estado')),
        'envio' => $request->input('envio', session('proformas.envio')),
    ];

    $validated = Validator::make($rawFilters, [
        'nro_prof' => ['nullable', 'string', 'max:100'],
        'codigo' => ['nullable', 'string', 'max:50'],
        'nit' => ['nullable', 'string', 'max:60'],
        'empresa' => ['nullable', 'string', 'max:200'],
        'emisora' => ['nullable', 'string', 'max:20'],
        'mes' => ['nullable', 'string', 'max:20'],
        'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        'estado' => ['nullable', 'integer', 'min:0'],
        'envio' => ['nullable', 'in:0,1'],
    ])->validate();

    $periodo = $this->proformasService->normalizePeriodoFilters(
        $validated['mes'] ?? null,
        $validated['anio'] ?? null,
    );

    // 🔥 AQUÍ VA
    if (!$request->filled('mes')) {
        $request->merge(['mes' => $periodo['mes']]);
    }

    if (!$request->filled('anio')) {
        $request->merge(['anio' => $periodo['anio']]);
    }

    $filters = [
        'nro_prof' => $validated['nro_prof'] ?? null,
        'codigo' => $validated['codigo'] ?? null,
        'nit' => $validated['nit'] ?? null,
        'empresa' => $validated['empresa'] ?? null,
        'emisora' => $validated['emisora'] ?? null,
        'mes' => $periodo['mes'],
        'anio' => $periodo['anio'],
        'estado' => $validated['estado'] ?? null,
        'envio' => isset($validated['envio']) ? (string) $validated['envio'] : null,
    ];

    if ($hasRequestFilters) {
        session([
            'proformas.numero' => $filters['nro_prof'],
            'proformas.codigo' => $filters['codigo'],
            'proformas.nit' => $filters['nit'],
            'proformas.empresa' => $filters['empresa'],
            'proformas.emisora' => $filters['emisora'],
            'proformas.mes' => $filters['mes'],
            'proformas.anio' => $filters['anio'],
            'proformas.estado' => $filters['estado'],
            'proformas.envio' => $filters['envio'],
        ]);
    }

    return view('proformas.index', [
        'proformas' => $this->proformasService->paginateProformas($filters),
        'filters' => $filters,
        'estados' => ProformasService::ESTADOS,
        'meses' => ProformasService::MESES,
        'proformasService' => $this->proformasService,
    ]);
}

    public function clearFilters(): RedirectResponse
    {
        session()->forget('proformas');

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
                        'fecha_arriendo' => (string) ($proforma->cliente_fecha_arriendo ?? ''),
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

        $enviadas = 0;
        $fallidas = [];
        $omitidas = 0;

        foreach ($candidatas as $proforma) {
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
        }

        $omitidas += max(0, count($ids) - $candidatas->count());

        $statusType = count($fallidas) > 0 ? 'error' : 'success';
        $message = "Envío masivo grupo {$grupo} finalizado. Enviadas: {$enviadas}. Omitidas: {$omitidas}. Fallidas: ".count($fallidas).'.';

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
        ]);

        $periodo = $this->proformasService->normalizePeriodoFilters(
            $validated['mes'] ?? null,
            $validated['anio'] ?? null,
        );

        $dashboard = $this->proformasService->getDashboardData(
            $periodo['mes'],
            $periodo['anio'],
        );

        return view('proformas.dashboard', [
            'dashboard' => $dashboard,
            'filters' => $periodo,
            'meses' => ProformasService::MESES,
            'proformasService' => $this->proformasService,
        ]);
    }


    public function showPdf(Request $request, int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId(
            $id,
            $request->boolean('regenerar'),
        );

        return response()->file($resultado['absolute_path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }

    public function downloadPdf(int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId($id);

        return response()->download($resultado['absolute_path'], $resultado['filename'], [
            'Content-Type' => 'application/pdf',
        ]);
    }

public function show(int $id): View
{
    $proforma = $this->proformasService->findProformaById($id);

    if (!$proforma) {
        abort(404, 'Proforma no encontrada');
    }

    // 🔥 AQUÍ VA
    session(['proformas.filtros_originales' => request()->query()]);

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

        $estadoFiltrado = session('proformas.estado');
        $estadoActual = (int) ($proforma->estado ?? 0);
        $debeLimpiarFiltroEstado = $estadoFiltrado !== null
            && (string) $estadoFiltrado !== ''
            && (int) $estadoFiltrado !== $estadoActual;

        if ($debeLimpiarFiltroEstado) {
            session()->forget('proformas.estado');

            return redirect()
                ->route('proformas.index')
                ->with('warning', 'La proforma cambió de estado y ya no coincide con el filtro actual.');
        }

        return redirect()->route('proformas.index');
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

        try {
            $this->proformaEmailService->sendProforma($proforma);
            $this->proformasService->registrarEnvioExitoso($id);
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
                    ],
                ]);
            }

            return redirect()->back()->with('status', 'Proforma enviada por correo correctamente.')->with('status_type', 'success');
        } catch (\Throwable $exception) {
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
}
