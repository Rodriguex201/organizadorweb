<?php

namespace App\Http\Controllers;

use App\Models\ClientePotencial;
use App\Services\CobrosService;
use App\Services\CobroExtraordinarioService;
use App\Services\ClienteRetiradoService;
use App\Services\ProformaEmailService;
use App\Services\RevisarProformaCalculator;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformasService;
use App\Services\ProformaStoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CobrosController extends Controller
{
    public function __construct(
        private readonly CobrosService $cobrosService,
        private readonly CobroExtraordinarioService $cobroExtraordinarioService,
        private readonly ClienteRetiradoService $clienteRetiradoService,
        private readonly ProformaPreviewService $proformaPreviewService,
        private readonly ProformaStoreService $proformaStoreService,
        private readonly ProformaPdfService $proformaPdfService,
        private readonly ProformasService $proformasService,
        private readonly ProformaEmailService $proformaEmailService,
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
    ) {
    }

public function index(Request $request): View
{
    $validated = $request->validate([
        'mes' => ['nullable', 'string', 'max:20'],
        'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        'ano' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        'proforma' => ['nullable', 'string', 'max:100'],
        'codigo' => ['nullable', 'string', 'max:100'],
        'buscar' => ['nullable', 'string', 'max:100'],
        'orden_fecha' => ['nullable', 'in:asc,desc'],
        'grupo_fecha' => ['nullable', 'in:7,27'],
        'filtro_nota' => ['nullable', 'in:con,sin'],
        'filtro_envio' => ['nullable', 'in:enviadas,no_enviadas'],
        'debug' => ['nullable'],
    ]);

    // 🔥 SOLO ESTE FILTER
    $hasFilterQuery = collect([
        'mes',
        'anio',
        'ano',
        'proforma',
        'codigo',
        'buscar',
        'grupo_fecha',
        'filtro_nota',
        'filtro_envio',
    ])->contains(fn (string $key) => $request->query->has($key));
    $now = Carbon::now();

$filters = [
    'mes' => isset($validated['mes'])
        ? strtolower(trim($validated['mes']))
        : (CobrosService::MESES[(int) $now->month] ?? null),
    'anio' => isset($validated['anio'])
        ? (int) $validated['anio']
        : (int) $now->year,
    'proforma' => $request->filled('proforma') ? $validated['proforma'] : null,
    'codigo' => $validated['codigo'] ?? null,
    'buscar' => $validated['buscar'] ?? null,
    'orden_fecha' => $validated['orden_fecha'] ?? null,
    'grupo_fecha' => $validated['grupo_fecha'] ?? null,
    'filtro_nota' => $validated['filtro_nota'] ?? null,
    'filtro_envio' => $validated['filtro_envio'] ?? null,
];

    $this->sanitizePendingProformasForEnvioSession();

    if (!$hasFilterQuery) {
        return view('cobros.index', [
            'cobros' => $this->emptyCobrosPaginator($request),
            'filters' => $filters,
            'meses' => $this->cobrosService::MESES,
            'periodSummary' => null,
            'canClearPendingBatch' => $this->canManagePendingBatchCleanup(),
            'hasSearched' => false,
        ]);
    }

    $cobros = $this->cobrosService->paginateCobros($filters);

    return view('cobros.index', [
        'cobros' => $cobros,
        'filters' => $filters,
        'meses' => $this->cobrosService::MESES,
        'periodSummary' => $this->cobrosService->getPeriodSummary($filters),
        'canClearPendingBatch' => $this->canManagePendingBatchCleanup(),
        'hasSearched' => true,
    ]);
}

    private function buildPeriodSummary(array $filters): ?object
    {
        $mes = trim((string) ($filters['mes'] ?? ''));
        $anio = $filters['anio'] ?? null;

        if ($mes === '' || $anio === null) {
            return null;
        }

        $yearColumn = $this->resolveValoresExternosYearColumn();

        return DB::table('valores_externos')
            ->selectRaw('
                COALESCE(SUM(numero_facturas), 0) as total_facturas,
                COALESCE(SUM(numero_nota_debito), 0) as total_notas_debito,
                COALESCE(SUM(numero_nota_credito), 0) as total_notas_credito,
                COALESCE(SUM(numero_documento_soporte), 0) as total_documentos_soporte,
                COALESCE(SUM(numero_nota_ajuste), 0) as total_notas_ajuste,
                COALESCE(SUM(numero_acuse), 0) as total_acuses,
                COALESCE(SUM(valor_facturas), 0) as valor_facturas,
                COALESCE(SUM(valor_documentos), 0) as valor_documentos,
                COALESCE(SUM(valor_acuse), 0) as valor_acuse,
                COALESCE(SUM(valor_mensualidad), 0) as valor_mensualidad,
                COALESCE(SUM(valor_total), 0) as valor_total
            ')
            ->whereRaw('LOWER(TRIM(mes)) = ?', [mb_strtolower($mes)])
            ->where($yearColumn, (int) $anio)
            ->first();
    }

    private function resolveValoresExternosYearColumn(): string
    {
        foreach (['año', 'aÃ±o', 'aÃƒÂ±o', 'aÃƒÆ’Ã‚Â±o'] as $candidate) {
            if (Schema::hasColumn('valores_externos', $candidate)) {
                return $candidate;
            }
        }

        return 'año';
    }

    public function createExtraordinary(Request $request): View
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
        ]);

        $clientes = $this->cobroExtraordinarioService->getClientes();
        $clientesRetirados = $this->cobroExtraordinarioService->getRetiradosSearchCandidates();
        $clientesRetiradosSearch = $clientesRetirados->map(function (object $cliente): array {
            $label = trim((string) ($cliente->codigo ?? '')).' - '.trim((string) ($cliente->empresa ?: $cliente->nombre ?: 'Sin nombre'));

            return [
                'id' => (string) ($cliente->id ?? ''),
                'label' => $label !== ' - ' ? $label : 'Cliente retirado',
                'search' => mb_strtolower($label),
            ];
        })->values()->all();
        $selectedClienteId = (int) old('cliente_id', $validated['cliente_id'] ?? 0);
        $selectedCliente = $selectedClienteId > 0
            ? $this->cobroExtraordinarioService->findCliente($selectedClienteId)
            : null;

        $mes = old('mes', isset($validated['mes']) ? mb_strtolower(trim((string) $validated['mes'])) : CobrosService::MESES[(int) now()->month]);
        $anio = (int) old('anio', $validated['anio'] ?? (int) now()->year);

        $input = [
            'numero_facturas' => (float) old('numero_facturas', 0),
            'numero_documento_soporte' => (float) old('numero_documento_soporte', 0),
            'numero_acuse' => (float) old('numero_acuse', 0),
            'valor_extra' => (float) old('valor_extra', $selectedCliente->vlrextra ?? 0),
            'valor_extra2' => (float) old('valor_extra2', $selectedCliente->vlrextra2 ?? 0),
        ];

        $preview = $selectedCliente
            ? $this->cobroExtraordinarioService->buildPreview($selectedCliente, $input)
            : null;

        return view('cobros.create-extraordinary', [
            'clientes' => $clientes,
            'selectedCliente' => $selectedCliente,
            'selectedClienteId' => $selectedClienteId > 0 ? $selectedClienteId : null,
            'meses' => CobrosService::MESES,
            'selectedMes' => $mes,
            'selectedAnio' => $anio,
            'preview' => $preview,
            'clientesRetirados' => $clientesRetirados,
            'clientesRetiradosSearch' => $clientesRetiradosSearch,
        ]);
    }

    public function storeExtraordinary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'mes' => ['required', 'string', 'in:'.implode(',', CobrosService::MESES)],
            'anio' => ['required', 'integer', 'min:1900', 'max:9999'],
            'numero_facturas' => ['nullable', 'numeric', 'min:0'],
            'numero_documento_soporte' => ['nullable', 'numeric', 'min:0'],
            'numero_acuse' => ['nullable', 'numeric', 'min:0'],
            'valor_extra' => ['nullable', 'numeric', 'min:0'],
            'valor_extra2' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cliente = $this->cobroExtraordinarioService->findCliente((int) $validated['cliente_id']);

        if (!$cliente) {
            return back()
                ->withInput()
                ->with('status', 'El cliente seleccionado no existe.')
                ->with('status_type', 'warning');
        }

        $resultado = $this->cobroExtraordinarioService->createCobro($cliente, $validated);

        if (($resultado['blocked'] ?? false) === true) {
            return back()
                ->withInput()
                ->with('status', $resultado['message'])
                ->with('status_type', 'warning');
        }

        if (($resultado['duplicated'] ?? false) === true) {
            return back()
                ->withInput()
                ->with('status', $resultado['message'].' Cobro existente #'.($resultado['id_cobro'] ?? 'N/D').'.')
                ->with('status_type', 'warning');
        }

        return redirect()
            ->route('cobros.show', ['id' => $resultado['id_cobro']])
            ->with('status', $resultado['message'])
            ->with('status_type', 'success');
    }

    public function generarProformasMasivo(Request $request, int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'proforma' => ['nullable', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:100'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'orden_fecha' => ['nullable', 'in:asc,desc'],
            'grupo_fecha' => ['nullable', 'in:7,27'],
            'filtro_nota' => ['nullable', 'in:con,sin'],
            'filtro_envio' => ['nullable', 'in:enviadas,no_enviadas'],
        ]);

        $filters = [
            'mes' => isset($validated['mes']) ? strtolower(trim($validated['mes'])) : null,
            'anio' => isset($validated['anio']) ? (int) $validated['anio'] : null,
            'proforma' => $request->filled('proforma') ? $validated['proforma'] : null,
            'codigo' => $validated['codigo'] ?? null,
            'buscar' => $validated['buscar'] ?? null,
            'orden_fecha' => $validated['orden_fecha'] ?? null,
            'grupo_fecha' => $validated['grupo_fecha'] ?? null,
            'filtro_nota' => $validated['filtro_nota'] ?? null,
            'filtro_envio' => $validated['filtro_envio'] ?? null,
        ];

        Log::info('Generacion masiva grupo: inicio.', [
            'grupo' => $grupo,
            'filters' => $filters,
            'database' => DB::connection()->getDatabaseName(),
            'mass_generation_snapshot' => $this->cobrosService->buildMassGenerationDebugSnapshot($filters, $grupo),
        ]);

        $candidatos = $this->cobrosService->findCobroCandidatesForMassGeneration($filters, $grupo);
        $resultado = $this->procesarGeneracionMasiva($grupo, $filters, $candidatos);

        return $this->buildMassGenerationRedirect($grupo, $filters, $resultado);
    }

    public function activarPendientesFacturacionMasivo(Request $request, int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'clientes' => ['required', 'array', 'min:1'],
            'clientes.*' => ['integer', 'min:1'],
        ]);

        $payload = session('cobros.proformas_masivo_pendientes_facturacion');

        if (!is_array($payload) || (int) ($payload['grupo'] ?? 0) !== $grupo) {
            return redirect()
                ->route('cobros.index')
                ->with('status', 'No hay clientes pendientes de facturacion disponibles para activar.')
                ->with('status_type', 'warning');
        }

        $items = collect($payload['items'] ?? []);
        $seleccionados = array_values(array_unique(array_map('intval', $validated['clientes'] ?? [])));

        $aActivar = $items
            ->filter(fn (array $item) => in_array((int) ($item['cliente_id'] ?? 0), $seleccionados, true))
            ->values();

        if ($aActivar->isEmpty()) {
            return redirect()
                ->route('cobros.index', array_filter($payload['filters'] ?? [], fn ($value) => $value !== null && $value !== ''))
                ->with('status', 'No se seleccionaron clientes pendientes validos.')
                ->with('status_type', 'warning');
        }

        $clienteIds = $aActivar->pluck('cliente_id')->map(fn ($id) => (int) $id)->all();
        $clientes = DB::table('clientes_potenciales')
            ->whereIn('idclientes_potenciales', $clienteIds)
            ->get()
            ->keyBy('idclientes_potenciales');

        $fechaHoy = Carbon::now()->toDateString();
        $activados = [];

        DB::transaction(function () use ($aActivar, $clientes, $fechaHoy, &$activados): void {
            foreach ($aActivar as $item) {
                $clienteId = (int) ($item['cliente_id'] ?? 0);
                $clienteActual = $clientes->get($clienteId);

                if (!$clienteActual) {
                    continue;
                }

                $update = [];

                if (Schema::hasColumn('clientes_potenciales', 'estado_facturacion')) {
                    $update['estado_facturacion'] = ClientePotencial::ESTADO_FACTURACION_ACTIVO;
                }

                if (
                    Schema::hasColumn('clientes_potenciales', 'fecha_inicio_facturacion')
                    && empty($clienteActual->fecha_inicio_facturacion ?? null)
                ) {
                    $update['fecha_inicio_facturacion'] = $fechaHoy;
                }

                if ($update !== []) {
                    DB::table('clientes_potenciales')
                        ->where('idclientes_potenciales', $clienteId)
                        ->update($update);
                }

                $activados[] = array_merge($item, [
                    'estado_facturacion' => ClientePotencial::ESTADO_FACTURACION_ACTIVO,
                    'fecha_inicio_facturacion' => $clienteActual->fecha_inicio_facturacion ?? ($update['fecha_inicio_facturacion'] ?? null),
                ]);

                Log::info('Cobros masivo pendiente activado.', [
                    'accion' => 'activar_facturacion_masiva',
                    'fecha' => now()->toDateTimeString(),
                    'usuario' => $this->resolveMassActionUser(),
                    'cliente_id' => $clienteId,
                    'cliente_codigo' => $item['codigo'] ?? null,
                    'cliente_empresa' => $item['empresa'] ?? null,
                    'grupo' => $item['grupo'] ?? null,
                ]);
            }
        });

        $restantes = $items
            ->reject(fn (array $item) => in_array((int) ($item['cliente_id'] ?? 0), $seleccionados, true))
            ->values()
            ->all();

        if ($restantes !== []) {
            session()->put('cobros.proformas_masivo_pendientes_facturacion', [
                'grupo' => $grupo,
                'filters' => $payload['filters'] ?? [],
                'items' => $restantes,
            ]);
        } else {
            session()->forget('cobros.proformas_masivo_pendientes_facturacion');
        }

        session()->put('cobros.proformas_masivo_regenerar_pendientes', [
            'grupo' => $grupo,
            'filters' => $payload['filters'] ?? [],
            'items' => array_values($activados),
        ]);

        Log::info('Cobros masivo pendientes activados: resumen.', [
            'accion' => 'activar_facturacion_masiva_resumen',
            'fecha' => now()->toDateTimeString(),
            'usuario' => $this->resolveMassActionUser(),
            'grupo' => $grupo,
            'clientes_activados' => count($activados),
        ]);

        return redirect()
            ->route('cobros.index', array_filter($payload['filters'] ?? [], fn ($value) => $value !== null && $value !== ''))
            ->with('status', 'Se activaron '.count($activados).' clientes.')
            ->with('status_type', 'success')
            ->with('cobros_proformas_masivo_activados', [
                'grupo' => $grupo,
                'count' => count($activados),
            ]);
    }

    public function regenerarPendientesFacturacionMasivo(int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $payload = session('cobros.proformas_masivo_regenerar_pendientes');

        if (!is_array($payload) || (int) ($payload['grupo'] ?? 0) !== $grupo) {
            return redirect()
                ->route('cobros.index')
                ->with('status', 'No hay clientes activados pendientes de regeneracion.')
                ->with('status_type', 'warning');
        }

        $candidatos = collect($payload['items'] ?? [])->map(function (array $item): object {
            return (object) $item;
        });

        $resultado = $this->procesarGeneracionMasiva($grupo, $payload['filters'] ?? [], $candidatos);

        session()->forget('cobros.proformas_masivo_regenerar_pendientes');

        return $this->buildMassGenerationRedirect($grupo, $payload['filters'] ?? [], $resultado)
            ->with('status', 'Se activaron '.count($payload['items'] ?? []).' clientes. '.$resultado['message'])
            ->with('status_type', $resultado['status_type']);
    }

    public function descartarRegeneracionPendientesFacturacionMasivo(int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $payload = session('cobros.proformas_masivo_regenerar_pendientes');
        session()->forget('cobros.proformas_masivo_regenerar_pendientes');

        return redirect()
            ->route('cobros.index', array_filter(($payload['filters'] ?? []), fn ($value) => $value !== null && $value !== ''))
            ->with('status', 'No se regeneraron las proformas omitidas.')
            ->with('status_type', 'warning');
    }

    public function enviarProformasMasivo(Request $request, int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $this->sanitizePendingProformasForEnvioSession();
        $payload = session('cobros.proformas_listas_para_envio');

        if (!is_array($payload) || (int) ($payload['grupo'] ?? 0) !== $grupo) {
            return redirect()
                ->route('cobros.index')
                ->with('status', 'No hay un lote de proformas listo para enviar.')
                ->with('status_type', 'warning');
        }

        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $proformas = is_array($payload['proformas'] ?? null) ? $payload['proformas'] : [];
        $delaySeconds = max(0, (int) config('services.proforma_bulk_send_delay_seconds', 2));
        $totalProformas = count($proformas);

        $enviadas = [];
        $omitidas = [];
        $fallidas = [];

        foreach (array_values($proformas) as $index => $item) {
            $proformaId = (int) ($item['id'] ?? 0);
            $empresa = trim((string) ($item['empresa'] ?? 'Sin nombre'));

            if ($proformaId <= 0) {
                $omitidas[] = ['empresa' => $empresa, 'motivo' => 'ID de proforma invalido.'];
                continue;
            }

            $proforma = $this->proformasService->findProformaById($proformaId);

            if (!$proforma) {
                $omitidas[] = ['empresa' => $empresa, 'motivo' => 'Proforma no encontrada.'];
                continue;
            }

            if ((int) ($proforma->enviado ?? 0) === 1) {
                $omitidas[] = ['empresa' => $empresa, 'motivo' => 'La proforma ya estaba enviada.'];
                continue;
            }

            try {
                $this->asegurarPdfDeProforma($proformaId);
                $proformaActualizada = $this->proformasService->findProformaById($proformaId);

                if (!$this->proformasService->canSendProforma($proformaActualizada)) {
                    $omitidas[] = ['empresa' => $empresa, 'motivo' => 'La proforma no quedo lista para envio.'];
                    continue;
                }

                $this->proformaEmailService->sendProforma($proformaActualizada);
                $this->proformasService->registrarEnvioExitoso($proformaId);

                $enviadas[] = $empresa;
            } catch (\Throwable $exception) {
                $this->proformasService->registrarIntentoFallido($proformaId);

                Log::error('Error en envio masivo de proformas desde cobros.', [
                    'grupo' => $grupo,
                    'proforma_id' => $proformaId,
                    'empresa' => $empresa,
                    'message' => $exception->getMessage(),
                ]);

                report($exception);

                $fallidas[] = [
                    'empresa' => $empresa,
                    'error' => $exception->getMessage(),
                ];
            }

            if ($delaySeconds > 0 && $index < ($totalProformas - 1)) {
                Log::info(sprintf('Esperando %d segundos antes del siguiente envío', $delaySeconds));
                sleep($delaySeconds);
            }
        }

        session()->forget('cobros.proformas_listas_para_envio');

        $message = "Envio masivo grupo {$grupo} finalizado. Enviadas: ".count($enviadas).'. Omitidas: '.count($omitidas).'. Fallidas: '.count($fallidas).'.';

        if ($enviadas !== []) {
            $message .= ' Enviadas: '.collect($enviadas)->take(5)->implode(', ').'.';
        }

        if ($omitidas !== []) {
            $message .= ' Omitidas: '.collect($omitidas)
                ->take(5)
                ->map(fn (array $omitida) => $omitida['empresa'].' ('.$omitida['motivo'].')')
                ->implode(' | ').'.';
        }

        if ($fallidas !== []) {
            $message .= ' Fallidas: '.collect($fallidas)
                ->take(5)
                ->map(fn (array $fallida) => $fallida['empresa'].' ('.$fallida['error'].')')
                ->implode(' | ').'.';
        }

        return redirect()
            ->route('cobros.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->with('status', $message)
            ->with('status_type', count($fallidas) > 0 ? 'warning' : 'success');
    }

    public function limpiarLotePendienteEnvio(): RedirectResponse
    {
        abort_unless(
            $this->canManagePendingBatchCleanup(),
            403,
            'No tienes permisos para usar esta herramienta.'
        );

        $this->clearCobrosPendingBatchSession();

        return redirect()
            ->route('cobros.index')
            ->with('status', 'Lote pendiente de envio limpiado correctamente.')
            ->with('status_type', 'success');
    }

    public function show(int $id): View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        $proformaPersistidaId = $this->proformaStoreService->findExistingProformaIdFromCobro($cobro);
        $proformaPersistida = $proformaPersistidaId !== null
            ? $this->proformasService->findProformaById($proformaPersistidaId)
            : null;

        return view('cobros.show', [
            'cobro' => $cobro,
            'proformaPersistidaId' => $proformaPersistidaId,
            'proformaPersistida' => $proformaPersistida,
            'canSendPersistedProforma' => $this->proformasService->canSendProforma($proformaPersistida),
            'facturacionCliente' => $this->buildFacturacionClienteData($cobro),
        ]);
    }



    public function revisar(int $id): View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        $reviewValues = $this->cobrosService->mapCobroToRevisionValues($cobro);
        $formData = $this->revisarProformaCalculator->calculate($reviewValues);
        $proformaPersistidaId = $this->proformaStoreService->findExistingProformaIdFromCobro($cobro);

        return view('cobros.revisar', [
            'cobro' => $cobro,
            'reviewValues' => $reviewValues,
            'formData' => $formData,
            'proformaPersistidaId' => $proformaPersistidaId,
            'facturacionCliente' => $this->buildFacturacionClienteData($cobro),
        ]);
    }

    public function guardarRevision(Request $request, int $id): RedirectResponse|View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

$validated = $request->validate([
    'numero_equipos' => ['nullable', 'numeric', 'min:0'],
    'valor_principal' => ['nullable', 'numeric', 'min:0'],
    'valor_terminal' => ['nullable', 'numeric', 'min:0'],
    'numero_equipos_extra' => ['nullable', 'numeric', 'min:0'],
    'valor_equipo_extra' => ['nullable', 'numeric', 'min:0'],
    'empleados' => ['nullable', 'numeric', 'min:0'],
    'valor_nomina' => ['nullable', 'numeric', 'min:0'],
    'numero_moviles' => ['nullable', 'numeric', 'min:0'],
    'valor_movil' => ['nullable', 'numeric', 'min:0'],
    'facturas' => ['nullable', 'numeric', 'min:0'],
    'nota_debito' => ['nullable', 'numeric', 'min:0'],
    'nota_credito' => ['nullable', 'numeric', 'min:0'],
    'soporte' => ['nullable', 'numeric', 'min:0'],
    'nota_ajuste' => ['nullable', 'numeric', 'min:0'],
    'acuse' => ['nullable', 'numeric', 'min:0'],
    'otro_valor_extra' => ['nullable', 'numeric', 'min:0'],
    'otro_valor_extra_2' => ['nullable', 'numeric', 'min:0'],
    'precio_factura' => ['nullable', 'numeric', 'min:0'],
    'precio_soporte' => ['nullable', 'numeric', 'min:0'],
    'precio_acuse' => ['nullable', 'numeric', 'min:0'],
    'accion' => ['nullable', 'in:recalcular,guardar,generar'],
    'codigo_concepto_extra' => ['nullable', 'string', 'max:100'],
    'descripcion_concepto_extra' => ['nullable', 'string', 'max:500'],
]);

$idCliente = $cobro->id_cliente ?? null;

if ($request->filled('precio_factura') && $idCliente) {
    DB::table('clientes_potenciales')
        ->where('idclientes_potenciales', $idCliente)
        ->update([
            'vlrfactura' => (float) $request->input('precio_factura')
        ]);
}

$clienteSelect = ['vlrfactura', 'vlrsoporte', 'vlrecepcion'];
if (Schema::hasColumn('clientes_potenciales', 'numextra')) {
    $clienteSelect[] = 'numextra';
}
if (Schema::hasColumn('clientes_potenciales', 'vlrextrae')) {
    $clienteSelect[] = 'vlrextrae';
}

$preciosCliente = DB::table('clientes_potenciales')
    ->where('idclientes_potenciales', $idCliente ?? 0)
    ->select($clienteSelect)
    ->first();

$validated['precio_factura'] = $request->filled('precio_factura')
    ? (float) $request->input('precio_factura')
    : (float) ($preciosCliente->vlrfactura ?? 0);

$validated['precio_soporte'] = $request->filled('precio_soporte')
    ? (float) $request->input('precio_soporte')
    : (float) ($preciosCliente->vlrsoporte ?? 0);
$validated['precio_acuse'] = $request->filled('precio_acuse')
    ? (float) $request->input('precio_acuse')
    : (float) ($preciosCliente->vlrecepcion ?? 0);
        $formData = $this->revisarProformaCalculator->calculate($validated);
        $accion = $validated['accion'] ?? 'guardar';
        $valorExtra = (float) ($formData['otro_valor_extra'] ?? 0);

        Log::info('Cobros revisar request recibido.', [
            'id_cobro' => $id,
            'accion' => $accion,
            'validated' => $validated,
            'form_data' => $formData,
        ]);

        if ($accion === 'recalcular') {
            return view('cobros.revisar', [
                'cobro' => $cobro,
                'reviewValues' => $formData,
                'formData' => $formData,
                'proformaPersistidaId' => $this->proformaStoreService->findExistingProformaIdFromCobro($cobro),
            ])->with('status', 'Valores recalculados en pantalla. Aún no se guardan.')->with('status_type', 'warning');
        }

        $payloadValoresExternos = $this->extractPersistedPayload(
            'valores_externos',
            [
                'facturas' => 'numero_facturas',
                'nota_debito' => 'numero_nota_debito',
                'nota_credito' => 'numero_nota_credito',
                'soporte' => 'numero_documento_soporte',
                'nota_ajuste' => 'numero_nota_ajuste',
                'acuse' => 'numero_acuse',
                'otro_valor_extra' => 'valor_extra',
                'otro_valor_extra_2' => 'valor_extra2',
                'valor_facturas' => 'valor_facturas',
                'valor_documentos' => 'valor_documentos',
                'valor_acuse' => 'valor_acuse',
                'total_mensualidad' => 'valor_mensualidad',
                'valor_total_proforma' => 'valor_total',
            ],
            $formData,
        );

        $payloadClientes = $this->extractPersistedPayload(
            'clientes_potenciales',
            [
                'numero_equipos' => 'numequipos',
                'valor_principal' => 'vlrprincipal',
                'valor_terminal' => 'vlrterminal',
                'numero_equipos_extra' => 'numextra',
                'valor_equipo_extra' => 'vlrextrae',
                'empleados' => 'numero_empleados',
                'valor_nomina' => 'vlrnomina',
                'numero_moviles' => 'numeromoviles',
                'valor_movil' => 'vlrmovil',
                'otro_valor_extra' => 'vlrextra',
                'otro_valor_extra_2' => 'vlrextra2',
                'precio_factura' => 'vlrfactura',
                'precio_soporte' => 'vlrsoporte',
                'precio_acuse' => 'vlrecepcion',
            ],
            $formData,
        );

        $actualizoValoresExternos = $this->cobrosService->updateCobroRevision($id, $formData);
        $actualizoCliente = $idCliente
            ? $this->cobrosService->updateClienteRevision((int) $idCliente, $formData)
            : false;

        $cobroRefrescado = $this->cobrosService->findCobroById($id) ?: $cobro;
        $reviewValuesPersistidos = $this->cobrosService->mapCobroToRevisionValues($cobroRefrescado);

        Log::info('Cobros revisar payload persistido.', [
            'id_cobro' => $id,
            'id_cliente' => $idCliente,
            'payload_valores_externos' => $payloadValoresExternos,
            'payload_clientes_potenciales' => $payloadClientes,
            'actualizo_valores_externos' => $actualizoValoresExternos,
            'actualizo_clientes_potenciales' => $actualizoCliente,
            'review_values_persistidos' => $reviewValuesPersistidos,
            'snapshot_valores_externos' => DB::table('valores_externos')->where('id_cobro', $id)->first(),
            'snapshot_cliente' => $idCliente
                ? DB::table('clientes_potenciales')->where('idclientes_potenciales', $idCliente)->first()
                : null,
        ]);

        if ($accion === 'generar') {
            if ($this->clienteTieneFacturacionPendiente($cobro)) {
                return redirect()
                    ->route('cobros.revisar', $id)
                    ->with('status', 'Este cliente aun no ha iniciado facturacion.')
                    ->with('status_type', 'warning');
            }

            if ($valorExtra > 0) {
                $request->validate([
                    'codigo_concepto_extra' => ['required', 'string', 'max:100'],
                    'descripcion_concepto_extra' => ['required', 'string', 'max:500'],
                ], [
                    'codigo_concepto_extra.required' => 'El código del concepto extra es obligatorio cuando existe un cargo extra.',
                    'descripcion_concepto_extra.required' => 'La descripción del concepto extra es obligatoria cuando existe un cargo extra.',
                ]);
            }

            $cobroActualizado = $this->cobrosService->findCobroById($id);
            $resultado = $this->proformaStoreService->storeFromCobro(
                $cobroActualizado ?: $cobro,
                [
                    'codigo_concepto_extra' => trim((string) $request->input('codigo_concepto_extra', '')),
                    'descripcion_concepto_extra' => trim((string) $request->input('descripcion_concepto_extra', '')),
                ],
            );

            if (($resultado['blocked'] ?? false) === true) {
                return redirect()
                    ->route('cobros.proforma.preview', $id)
                    ->with('status', $resultado['message'])
                    ->with('status_type', 'warning');
            }

            return redirect()
                ->route('cobros.proforma.preview', $id)
                ->with('status', $resultado['message'].' Revisión guardada. Flujo de envío por correo pendiente para fase siguiente.')
                ->with('status_type', $resultado['duplicated'] ? 'warning' : 'success');
        }

        return redirect()
            ->route('cobros.revisar', $id)
            ->with('status', 'Revisión guardada correctamente. Ya puede regenerar la proforma actual si lo necesita.')
            ->with('status_type', 'success');
    }

    public function regenerateProforma(Request $request, int $id): RedirectResponse
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        if ($this->clienteRetiradoService->estaRetirado($cobro)) {
            return redirect()
                ->route('cobros.show', $id)
                ->with('status', 'No es posible regenerar proformas para clientes retirados.')
                ->with('status_type', 'warning');
        }

        if ($this->clienteTieneFacturacionPendiente($cobro)) {
            return redirect()
                ->route('cobros.show', $id)
                ->with('status', 'Este cliente aun no ha iniciado facturacion.')
                ->with('status_type', 'warning');
        }

        $cobroActualizado = $this->cobrosService->findCobroById($id) ?: $cobro;
        $resultado = $this->proformaStoreService->regenerateFromCobro($cobroActualizado);

        if (($resultado['blocked'] ?? false) === true) {
            $redirectRoute = $request->input('redirect_to') === 'revisar' ? 'cobros.revisar' : 'cobros.show';

            return redirect()
                ->route($redirectRoute, $id)
                ->with('status', $resultado['message'])
                ->with('status_type', 'warning');
        }

        $proformaId = (int) ($resultado['proforma_id'] ?? 0);

        if ($proformaId <= 0) {
            throw new NotFoundHttpException('No se pudo resolver la proforma a regenerar.');
        }

        $this->proformaPdfService->generateForProformaId($proformaId, true);

        $redirectRoute = $request->input('redirect_to') === 'revisar' ? 'cobros.revisar' : 'cobros.show';

        return redirect()
            ->route($redirectRoute, $id)
            ->with('status', 'Proforma regenerada correctamente. Se reemplazaron cabecera, detalle y PDF con los valores actuales.')
            ->with('status_type', 'success');
    }

    public function updateNotaCobro(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'nota_cobro' => ['nullable', 'string', 'max:2000'],
        ]);

        $clienteActualizado = DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', $id)
            ->update([
                'nota_cobro' => $validated['nota_cobro'] ?? null,
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $clienteActualizado > 0,
                'nota_cobro' => $validated['nota_cobro'] ?? null,
                'message' => 'Nota de cobro guardada correctamente.',
            ]);
        }

        return back()->with('status', 'Nota de cobro guardada correctamente.');
    }

    public function clearNotaCobro(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $clienteActualizado = DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', $id)
            ->update([
                'nota_cobro' => null,
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $clienteActualizado > 0,
                'nota_cobro' => null,
                'message' => 'Nota de cobro eliminada correctamente.',
            ]);
        }

        return back()->with('status', 'Nota de cobro eliminada correctamente.');
    }

    private function mapCobroToRevisionData(object $cobro): array
    {
        return $this->cobrosService->mapCobroToRevisionValues($cobro);
    }

    private function extractPersistedPayload(string $table, array $map, array $data): array
    {
        $payload = [];

        foreach ($map as $inputKey => $column) {
            if (!array_key_exists($inputKey, $data) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $payload[$column] = (float) $data[$inputKey];
        }

        return $payload;
    }

    private function existeRevisionGuardada(object $cobro): bool
    {
        $indicadores = [
            $cobro->precio_soporte ?? null,
            $cobro->precio_acuse ?? null,
            $cobro->total_facturas ?? null,
            $cobro->total_documentos ?? null,
            $cobro->otro_valor_extra_2 ?? $cobro->valor_terminal_recepcion ?? null,
            $cobro->otro_valor_extra ?? null,
            $cobro->numextra ?? null,
            $cobro->vlrextrae ?? null,
        ];

        foreach ($indicadores as $valor) {
            if ($valor !== null) {
                return true;
            }
        }

        return false;
    }

    private function valorRevisionOBase(bool $existeRevisionGuardada, mixed $valorRevision, mixed $valorBase): float
    {
        if ($existeRevisionGuardada && $valorRevision !== null) {
            return (float) $valorRevision;
        }

        if (!$existeRevisionGuardada && $valorBase !== null) {
            return (float) $valorBase;
        }

        if ($valorRevision !== null) {
            return (float) $valorRevision;
        }

        if ($valorBase !== null) {
            return (float) $valorBase;
        }

        return 0.0;

    }

    public function previewProforma(int $id): View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        $proforma = $this->proformaPreviewService->buildFromCobro($cobro);
        $proformaPersistidaId = $this->proformaStoreService->findExistingProformaIdFromCobro($cobro);

        return view('cobros.proforma-preview', [
            'cobro' => $cobro,
            'proforma' => $proforma,
            'proformaPersistidaId' => $proformaPersistidaId,
            'facturacionCliente' => $this->buildFacturacionClienteData($cobro),
        ]);
    }

    public function storeProforma(int $id): RedirectResponse
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        if ($this->clienteRetiradoService->estaRetirado($cobro)) {
            return redirect()
                ->route('cobros.proforma.preview', $id)
                ->with('status', 'No es posible generar proformas para clientes retirados.')
                ->with('status_type', 'warning');
        }

        if ($this->clienteTieneFacturacionPendiente($cobro)) {
            return redirect()
                ->route('cobros.proforma.preview', $id)
                ->with('status', 'Este cliente aun no ha iniciado facturacion.')
                ->with('status_type', 'warning');
        }

        $resultado = $this->proformaStoreService->storeFromCobro($cobro);

        $flashType = ($resultado['blocked'] ?? false) === true
            ? 'warning'
            : (($resultado['duplicated'] ?? false) ? 'warning' : 'success');

        return redirect()
            ->route('cobros.proforma.preview', $id)
            ->with('status', $resultado['message'])
            ->with('status_type', $flashType)
            ->with('proforma_id', $resultado['proforma_id'] ?? null);
    }

    public function showProformaPdf(Request $request, int $id): BinaryFileResponse
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

    private function asegurarPdfDeProforma(int $proformaId): array
    {
        return $this->proformaPdfService->generateForProformaId($proformaId);
    }

    private function buildFacturacionClienteData(object $cobro): array
    {
        $estado = ClientePotencial::normalizeEstadoFacturacion(
            $cobro->cliente_estado_facturacion ?? $cobro->estado_facturacion ?? null
        );

        return [
            'estado' => $estado,
            'es_pendiente' => $estado === ClientePotencial::ESTADO_FACTURACION_PENDIENTE,
            'fecha_inicio' => $cobro->cliente_fecha_inicio_facturacion ?? $cobro->fecha_inicio_facturacion ?? null,
            'cliente_id' => (int) ($cobro->cliente_id ?? $cobro->id_cliente ?? 0),
        ];
    }

    private function clienteTieneFacturacionPendiente(object $cobro): bool
    {
        return (bool) ($this->buildFacturacionClienteData($cobro)['es_pendiente'] ?? false);
    }

    private function buildOmitidaDetalleItem(string $codigo, string $empresa, string $motivo, array $extra = []): array
    {
        return array_merge([
            'codigo' => $codigo !== '' ? $codigo : 'Sin codigo',
            'empresa' => $empresa !== '' ? $empresa : 'Sin nombre',
            'motivo' => $motivo !== '' ? $motivo : 'Motivo no especificado',
        ], $extra);
    }

    private function procesarGeneracionMasiva(int $grupo, array $filters, \Illuminate\Support\Collection $candidatos): array
    {
        $idsCobro = $candidatos->pluck('id_cobro')
            ->map(fn ($idCobro) => (int) $idCobro)
            ->filter(fn (int $idCobro) => $idCobro > 0)
            ->values();

        Log::info('Generacion masiva grupo: ids resueltos.', [
            'grupo' => $grupo,
            'total_candidatos' => $candidatos->count(),
            'total_ids_cobro' => $idsCobro->count(),
            'ids_cobro_muestra' => $idsCobro->take(15)->values()->all(),
        ]);

        $creadas = 0;
        $actualizadas = 0;
        $fallidas = [];
        $omitidas = 0;
        $omitidasProtegidas = 0;
        $omitidasDetalle = [];
        $proformasListas = [];
        $cobrosEncontradosEnLoop = 0;
        $storeInvocations = 0;
        $pendientesFacturacion = [];

        foreach ($candidatos as $candidato) {
            $idCobro = (int) ($candidato->id_cobro ?? 0);
            $codigoCliente = trim((string) ($candidato->codigo ?? 'Sin codigo'));
            $empresaCliente = trim((string) ($candidato->empresa ?? $candidato->nombre ?? 'Sin nombre'));

            if ($idCobro <= 0) {
                $omitidas++;
                $omitidasDetalle[] = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, 'Datos incompletos');
                continue;
            }

            if ($this->clienteTieneFacturacionPendiente($candidato)) {
                $omitidas++;
                $detalle = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, 'Estado Facturacion = PENDIENTE', [
                    'cliente_id' => (int) ($candidato->cliente_id ?? $candidato->id_cliente ?? 0),
                    'id_cobro' => $idCobro,
                    'fecha_arriendo' => $candidato->fecha_arriendo ?? null,
                    'fecha_creacion_cliente' => $candidato->cliente_fecha_creacion ?? null,
                    'valor_total_actual' => (float) ($candidato->valor_total ?? 0),
                    'estado_facturacion' => ClientePotencial::normalizeEstadoFacturacion($candidato->estado_facturacion ?? null),
                    'grupo' => $grupo,
                ]);
                $omitidasDetalle[] = $detalle;
                $pendientesFacturacion[] = $detalle;
                continue;
            }

            if ($this->clienteRetiradoService->estaRetirado($candidato)) {
                $omitidas++;
                $omitidasDetalle[] = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, 'Cliente retirado');
                continue;
            }

            $cobro = $this->cobrosService->findCobroById((int) $idCobro);

            if (!$cobro) {
                Log::warning('Generacion masiva grupo: cobro no encontrado en loop.', [
                    'grupo' => $grupo,
                    'id_cobro' => $idCobro,
                ]);
                $omitidas++;
                $omitidasDetalle[] = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, 'Datos incompletos');
                continue;
            }

            $cobrosEncontradosEnLoop++;

            try {
                $storeInvocations++;
                $resultado = $this->proformaStoreService->storeFromCobro($cobro, [], false, true);

                Log::info('Generacion masiva grupo: resultado storeFromCobro.', [
                    'grupo' => $grupo,
                    'id_cobro' => $idCobro,
                    'resultado' => $resultado,
                ]);

                if (($resultado['blocked'] ?? false) === true) {
                    $motivoOmitida = (string) ($resultado['message'] ?? 'No se pudo generar la proforma.');
                    $omitidas++;
                    $omitidasDetalle[] = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, $motivoOmitida);
                    continue;
                }

                if (($resultado['protected'] ?? false) === true || ($resultado['omitted'] ?? false) === true) {
                    $omitidas++;
                    $omitidasProtegidas++;
                    $motivoOmitida = (string) ($resultado['message'] ?? 'Proforma protegida omitida en la generación masiva.');
                    $omitidasDetalle[] = $this->buildOmitidaDetalleItem($codigoCliente, $empresaCliente, $motivoOmitida, [
                        'id_cobro' => $idCobro,
                        'proforma_id' => (int) ($resultado['proforma_id'] ?? 0),
                        'protegida' => true,
                    ]);
                    continue;
                }

                $proformaId = (int) ($resultado['proforma_id'] ?? 0);

                if (($resultado['duplicated'] ?? false) === true) {
                    $actualizadas++;
                } else {
                    $creadas++;
                }

                if ($proformaId > 0) {
                    $this->asegurarPdfDeProforma($proformaId);

                    $proformasListas[] = [
                        'id' => $proformaId,
                        'empresa' => trim((string) ($cobro->cliente_empresa ?? $cobro->cliente_nombre ?? 'Sin nombre')),
                    ];
                }
            } catch (\Throwable $exception) {
                Log::error('Error en generacion masiva de proformas desde cobros.', [
                    'grupo' => $grupo,
                    'id_cobro' => $idCobro,
                    'message' => $exception->getMessage(),
                ]);

                report($exception);

                $fallidas[] = [
                    'id_cobro' => $idCobro,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        Log::info('Generacion masiva grupo: resumen final.', [
            'grupo' => $grupo,
            'total_candidatos' => $candidatos->count(),
            'total_ids_cobro' => $idsCobro->count(),
            'cobros_encontrados_en_loop' => $cobrosEncontradosEnLoop,
            'store_invocations' => $storeInvocations,
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
            'omitidas_protegidas' => $omitidasProtegidas,
            'omitidas' => $omitidas,
            'omitidas_detalle' => $omitidasDetalle,
            'fallidas' => count($fallidas),
            'fallidas_detalle' => array_slice($fallidas, 0, 10),
        ]);

        $statusType = count($fallidas) > 0 ? 'warning' : 'success';
        $message = "Generacion masiva grupo {$grupo} finalizada. Generadas: {$creadas}. Actualizadas: {$actualizadas}. Omitidas protegidas: {$omitidasProtegidas}. Omitidas: {$omitidas}. Fallidas: ".count($fallidas).'.';

        if ($fallidas !== []) {
            $message .= ' Errores: '.collect($fallidas)
                ->take(3)
                ->map(fn (array $fallida) => sprintf('cobro #%s (%s)', $fallida['id_cobro'], $fallida['error']))
                ->implode(' | ');
        }

        return [
            'status_type' => $statusType,
            'message' => $message,
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
            'omitidas_protegidas' => $omitidasProtegidas,
            'omitidas' => $omitidas,
            'fallidas' => count($fallidas),
            'fallidas_detalle' => $fallidas,
            'omitidas_detalle' => $omitidasDetalle,
            'pendientes_facturacion' => $pendientesFacturacion,
            'proformas_listas' => array_values($proformasListas),
        ];
    }

    private function buildMassGenerationRedirect(int $grupo, array $filters, array $resultado): RedirectResponse
    {
        $proformasListas = array_values($resultado['proformas_listas'] ?? []);
        $existingLote = session('cobros.proformas_listas_para_envio');
        $cantidadDescartadaLoteAnterior = 0;

        if ($proformasListas !== []) {
            if (is_array($existingLote) && (int) ($existingLote['grupo'] ?? 0) === $grupo) {
                $cantidadDescartadaLoteAnterior = count(is_array($existingLote['proformas'] ?? null) ? $existingLote['proformas'] : []);
            }

            $proformasListas = $this->mergePendingProformasForEnvio($grupo, $proformasListas);

            session()->put('cobros.proformas_listas_para_envio', [
                'grupo' => $grupo,
                'filters' => $filters,
                'proformas' => $proformasListas,
            ]);

            Log::info('Cobros lote temporal de envio reiniciado para nueva generacion masiva.', [
                'grupo' => $grupo,
                'cantidad_descartada_lote_anterior' => $cantidadDescartadaLoteAnterior,
                'cantidad_nuevo_lote' => count($proformasListas),
                'usuario' => $this->resolveMassActionUser(),
                'fecha_hora' => now()->toDateTimeString(),
            ]);
        } elseif (!is_array($existingLote) || (int) ($existingLote['grupo'] ?? 0) !== $grupo) {
            session()->forget('cobros.proformas_listas_para_envio');
        }

        if (($resultado['pendientes_facturacion'] ?? []) !== []) {
            session()->put('cobros.proformas_masivo_pendientes_facturacion', [
                'grupo' => $grupo,
                'filters' => $filters,
                'items' => array_values($resultado['pendientes_facturacion'] ?? []),
            ]);
        } else {
            session()->forget('cobros.proformas_masivo_pendientes_facturacion');
        }

        $redirect = redirect()
            ->route('cobros.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->with('status', $resultado['message'] ?? 'Proceso finalizado.')
            ->with('status_type', $resultado['status_type'] ?? 'success');

        if (($resultado['omitidas_detalle'] ?? []) !== []) {
            $redirect->with('cobros_proformas_masivo_omitidas', $resultado['omitidas_detalle']);
        }

        return $redirect;
    }

    private function resolveMassActionUser(): string
    {
        $usuario = trim((string) session('usuario', 'usuario'));
        $idUsuario = session()->has('idusuario') ? (string) session('idusuario') : null;

        return $idUsuario ? "{$usuario} ({$idUsuario})" : $usuario;
    }

    private function mergePendingProformasForEnvio(int $grupo, array $nuevasProformas): array
    {
        $resultado = $this->sanitizeProformasForEnvioLote(
            $grupo,
            collect($nuevasProformas)
            ->filter(fn ($item) => is_array($item))
            ->keyBy(fn (array $item) => (int) $item['id'])
            ->values()
            ->all(),
            'nueva_generacion'
        );

        return $resultado['proformas'];
    }

    private function clearCobrosPendingBatchSession(): void
    {
        session()->forget([
            'cobros.proformas_listas_para_envio',
            'cobros.proformas_masivo_pendientes_facturacion',
            'cobros.proformas_masivo_regenerar_pendientes',
            'cobros_proformas_masivo_omitidas',
            'cobros_proformas_masivo_activados',
        ]);
    }

    private function canManagePendingBatchCleanup(): bool
    {
        return app()->environment(['local', 'testing']) || esAdmin();
    }

    private function emptyCobrosPaginator(Request $request): LengthAwarePaginator
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

    private function sanitizePendingProformasForEnvioSession(): void
    {
        $payload = session('cobros.proformas_listas_para_envio');

        if (!is_array($payload)) {
            return;
        }

        $proformas = is_array($payload['proformas'] ?? null) ? $payload['proformas'] : [];

        if ($proformas === []) {
            session()->forget('cobros.proformas_listas_para_envio');

            return;
        }

        $resultado = $this->sanitizeProformasForEnvioLote(
            (int) ($payload['grupo'] ?? 0),
            $proformas,
            'sesion_pendiente'
        );
        $proformasSanitizadas = $resultado['proformas'];

        if ($proformasSanitizadas === []) {
            session()->forget('cobros.proformas_listas_para_envio');

            return;
        }

        $payload['proformas'] = array_values($proformasSanitizadas);
        session()->put('cobros.proformas_listas_para_envio', $payload);
    }

    /**
     * @param array<int, mixed> $proformas
     * @return array{proformas: array<int, array<string, mixed>>, discarded: int}
     */
    private function sanitizeProformasForEnvioLote(int $grupo, array $proformas, string $context): array
    {
        $sanitizadas = [];
        $discarded = 0;

        foreach ($proformas as $item) {
            if (!is_array($item)) {
                $discarded++;
                continue;
            }

            $proformaId = (int) ($item['id'] ?? 0);
            $empresa = trim((string) ($item['empresa'] ?? ''));

            if ($proformaId <= 0) {
                $discarded++;
                $this->logEnvioBatchDiscard($grupo, $context, $proformaId, null, null, null, 'ID de proforma invalido.', $empresa);
                continue;
            }

            $proforma = $this->proformasService->findProformaById($proformaId);

            if (!$proforma) {
                $discarded++;
                $this->logEnvioBatchDiscard($grupo, $context, $proformaId, null, null, null, 'Proforma inexistente o eliminada.', $empresa);
                continue;
            }

            $estado = (int) ($proforma->estado ?? 0);
            $enviado = (int) ($proforma->enviado ?? 0);
            $nroProf = $proforma->nro_prof ?? null;

            if ($enviado === 1) {
                $discarded++;
                $this->logEnvioBatchDiscard($grupo, $context, $proformaId, $nroProf, $estado, $enviado, 'Proforma ya enviada.', $empresa);
                continue;
            }

            if ($estado === ProformasService::ESTADO_PAGADA) {
                $discarded++;
                $this->logEnvioBatchDiscard($grupo, $context, $proformaId, $nroProf, $estado, $enviado, 'Proforma pagada.', $empresa);
                continue;
            }

            if ($estado === ProformasService::ESTADO_FACTURADA) {
                $discarded++;
                $this->logEnvioBatchDiscard($grupo, $context, $proformaId, $nroProf, $estado, $enviado, 'Proforma facturada.', $empresa);
                continue;
            }

            $sanitizadas[] = [
                'id' => $proformaId,
                'empresa' => $empresa !== '' ? $empresa : trim((string) ($proforma->emp ?? 'Sin nombre')),
            ];
        }

        return [
            'proformas' => array_values($sanitizadas),
            'discarded' => $discarded,
        ];
    }

    private function logEnvioBatchDiscard(
        int $grupo,
        string $context,
        int $proformaId,
        null|string|int $nroProf,
        ?int $estado,
        ?int $enviado,
        string $motivo,
        string $empresa = ''
    ): void {
        Log::info('Cobros lote temporal de envio: proforma descartada.', [
            'grupo' => $grupo,
            'contexto' => $context,
            'proforma_id' => $proformaId > 0 ? $proformaId : null,
            'nro_prof' => $nroProf,
            'estado' => $estado,
            'enviado' => $enviado,
            'motivo' => $motivo,
            'empresa' => $empresa !== '' ? $empresa : null,
        ]);
    }
}
