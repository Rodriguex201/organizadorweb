<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
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
        'buscar' => ['nullable', 'string', 'max:100'],
        'orden_fecha' => ['nullable', 'in:asc,desc'],
        'grupo_fecha' => ['nullable', 'in:7,27'],
        'filtro_nota' => ['nullable', 'in:con,sin'],
        'filtro_envio' => ['nullable', 'in:enviadas,no_enviadas'],
        'debug' => ['nullable'],
    ]);

    // 🔥 SOLO ESTE FILTER
    $isFirstCleanVisit = $request->query() === [];
    $now = Carbon::now();

$filters = [
    'mes' => isset($validated['mes'])
        ? strtolower(trim($validated['mes']))
        : ($isFirstCleanVisit ? (CobrosService::MESES[(int) $now->month] ?? null) : null),
    'anio' => isset($validated['anio'])
        ? (int) $validated['anio']
        : ($isFirstCleanVisit ? (int) $now->year : null),
    'proforma' => $request->filled('proforma') ? $validated['proforma'] : null,
    'buscar' => $validated['buscar'] ?? null,
    'orden_fecha' => $validated['orden_fecha'] ?? null,
    'grupo_fecha' => $validated['grupo_fecha'] ?? null,
    'filtro_nota' => $validated['filtro_nota'] ?? null,
    'filtro_envio' => $validated['filtro_envio'] ?? null,
];



    $cobros = $this->cobrosService->paginateCobros($filters);

    return view('cobros.index', [
        'cobros' => $cobros,
        'filters' => $filters,
        'meses' => $this->cobrosService::MESES,
    ]);
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
            'buscar' => $validated['buscar'] ?? null,
            'orden_fecha' => $validated['orden_fecha'] ?? null,
            'grupo_fecha' => $validated['grupo_fecha'] ?? null,
            'filtro_nota' => $validated['filtro_nota'] ?? null,
            'filtro_envio' => $validated['filtro_envio'] ?? null,
        ];

        $idsCobro = $this->cobrosService->findCobrosForMassGeneration($filters, $grupo);

        $creadas = 0;
        $actualizadas = 0;
        $fallidas = [];
        $omitidas = 0;
        $proformasListas = [];

        foreach ($idsCobro as $idCobro) {
            $cobro = $this->cobrosService->findCobroById((int) $idCobro);

            if (!$cobro) {
                $omitidas++;
                continue;
            }

            try {
                $resultado = $this->proformaStoreService->storeFromCobro($cobro);
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

        $statusType = count($fallidas) > 0 ? 'warning' : 'success';
        $message = "Generacion masiva grupo {$grupo} finalizada. Creadas: {$creadas}. Actualizadas: {$actualizadas}. Omitidas: {$omitidas}. Fallidas: ".count($fallidas).'.';

        if ($fallidas !== []) {
            $message .= ' Errores: '.collect($fallidas)
                ->take(3)
                ->map(fn (array $fallida) => sprintf('cobro #%s (%s)', $fallida['id_cobro'], $fallida['error']))
                ->implode(' | ');
        }

        if ($proformasListas !== []) {
            session()->put('cobros.proformas_listas_para_envio', [
                'grupo' => $grupo,
                'filters' => $filters,
                'proformas' => array_values($proformasListas),
            ]);
        } else {
            session()->forget('cobros.proformas_listas_para_envio');
        }

        return redirect()
            ->route('cobros.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->with('status', $message)
            ->with('status_type', $statusType);
    }

    public function enviarProformasMasivo(Request $request, int $grupo): RedirectResponse
    {
        if (!in_array($grupo, [7, 27], true)) {
            abort(404);
        }

        $payload = session('cobros.proformas_listas_para_envio');

        if (!is_array($payload) || (int) ($payload['grupo'] ?? 0) !== $grupo) {
            return redirect()
                ->route('cobros.index')
                ->with('status', 'No hay un lote de proformas listo para enviar.')
                ->with('status_type', 'warning');
        }

        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $proformas = is_array($payload['proformas'] ?? null) ? $payload['proformas'] : [];

        $enviadas = [];
        $omitidas = [];
        $fallidas = [];

        foreach ($proformas as $item) {
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

    public function show(int $id): View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        $proformaPersistidaId = $this->proformaStoreService->findExistingProformaIdFromCobro($cobro);

        return view('cobros.show', [
            'cobro' => $cobro,
            'proformaPersistidaId' => $proformaPersistidaId,
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
    'valor_terminal_recepcion' => ['nullable', 'numeric', 'min:0'],
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
                'valor_terminal_recepcion' => 'valor_extra2',
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
                'valor_terminal_recepcion' => 'vlrextra2',
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

        $cobroActualizado = $this->cobrosService->findCobroById($id) ?: $cobro;
        $resultado = $this->proformaStoreService->regenerateFromCobro($cobroActualizado);
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
            $cobro->valor_terminal_recepcion ?? null,
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
        ]);
    }

    public function storeProforma(int $id): RedirectResponse
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        $resultado = $this->proformaStoreService->storeFromCobro($cobro);

        $flashType = $resultado['duplicated'] ? 'warning' : 'success';

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

        return response()->file($resultado['absolute_path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }

    private function asegurarPdfDeProforma(int $proformaId): array
    {
        return $this->proformaPdfService->generateForProformaId($proformaId);
    }
}
