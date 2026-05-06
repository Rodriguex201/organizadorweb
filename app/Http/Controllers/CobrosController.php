<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\RevisarProformaCalculator;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformasService;
use App\Services\ProformaStoreService;
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
$filters = [
    'mes' => isset($validated['mes']) ? strtolower(trim($validated['mes'])) : null,
    'anio' => isset($validated['anio']) ? (int)$validated['anio'] : null,
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

        $valores = DB::table('valores_externos')
            ->where('id_cobro', $id)
            ->first();

        $formData = $this->revisarProformaCalculator->calculate($this->mapCobroToRevisionData($cobro));

        return view('cobros.revisar', [
            'cobro' => $cobro,
            'valores' => $valores,
            'formData' => $formData,
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

$preciosCliente = DB::table('clientes_potenciales')
    ->where('idclientes_potenciales', $idCliente ?? 0)
    ->select(['vlrfactura', 'vlrsoporte', 'vlrecepcion'])
    ->first();

$validated['precio_factura'] = $request->filled('precio_factura')
    ? (float) $request->input('precio_factura')
    : (float) ($preciosCliente->vlrfactura ?? 0);

$validated['precio_soporte'] = (float) ($preciosCliente->vlrsoporte ?? 0);
$validated['precio_acuse'] = (float) ($preciosCliente->vlrecepcion ?? 0);
        $formData = $this->revisarProformaCalculator->calculate($validated);
        $accion = $validated['accion'] ?? 'guardar';
        $valorExtra = (float) ($formData['otro_valor_extra'] ?? 0);

        if ($accion === 'recalcular') {
            $valores = DB::table('valores_externos')
                ->where('id_cobro', $id)
                ->first();

            return view('cobros.revisar', [
                'cobro' => $cobro,
                'valores' => $valores,
                'formData' => $formData,
            ])->with('status', 'Valores recalculados en pantalla. Aún no se guardan.')->with('status_type', 'warning');
        }

        $columnMap = [
            'numero_equipos' => 'numero_equipos',
            'valor_principal' => 'valor_principal',
            'valor_terminal' => 'valor_terminal',
            'empleados' => 'empleados',
            'valor_nomina' => 'vlrnomina',
            'numero_moviles' => 'numero_moviles',
            'valor_movil' => 'valor_movil',
            'facturas' => 'numero_facturas',
            'nota_debito' => 'numero_nota_debito',
            'nota_credito' => 'numero_nota_credito',
            'soporte' => 'numero_documento_soporte',
            'nota_ajuste' => 'numero_nota_ajuste',
            'acuse' => 'numero_acuse',
            'otro_valor_extra' => 'valor_extra',
            'valor_terminal_recepcion' => 'valor_extra2',
            'precio_soporte' => 'precio_soporte',
            'precio_acuse' => 'precio_acuse',
            'total_facturas' => 'total_facturas',
            'valor_facturas' => 'valor_facturas',
            'total_documentos' => 'total_documentos',
            'valor_documentos' => 'valor_documentos',
            'valor_acuse' => 'valor_acuse',
            'total_mensualidad' => 'valor_mensualidad',
            'valor_total_proforma' => 'valor_total',
        ];

        $payload = [];
        foreach ($columnMap as $key => $column) {
            if (!array_key_exists($key, $formData) || !Schema::hasColumn('valores_externos', $column)) {
                continue;
            }

            $payload[$column] = (float) $formData[$key];
        }

        if ($payload !== []) {
            DB::table('valores_externos')
                ->where('id_cobro', $id)
                ->update($payload);
        }

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
            ->route('cobros.show', $id)
            ->with('status', 'Revisión guardada correctamente.')
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

        $existeRevisionGuardada = $this->existeRevisionGuardada($cobro);

        return [
            'numero_equipos' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->numero_equipos ?? null, $cobro->cliente_numequipos ?? null),
            'valor_principal' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_principal ?? null, $cobro->cliente_vlrprincipal ?? null),
            'valor_terminal' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_terminal ?? null, $cobro->cliente_vlrterminal ?? null),
            'empleados' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->empleados ?? null, $cobro->cliente_numero_empleados ?? null),
            'valor_nomina' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->vlrnomina ?? null, $cobro->cliente_vlrnomina ?? null),
            'numero_moviles' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->numero_moviles ?? null, $cobro->cliente_numeromoviles ?? null),
            'valor_movil' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_movil ?? null, $cobro->cliente_vlrmovil ?? null),
            'facturas' => (float) ($cobro->numero_facturas ?? 0),
            'nota_debito' => (float) ($cobro->numero_nota_debito ?? 0),
            'nota_credito' => (float) ($cobro->numero_nota_credito ?? 0),
            'soporte' => (float) ($cobro->numero_documento_soporte ?? 0),
            'nota_ajuste' => (float) ($cobro->numero_nota_ajuste ?? 0),
            'acuse' => (float) ($cobro->numero_acuse ?? 0),
            'otro_valor_extra' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->otro_valor_extra ?? null, $cobro->cliente_vlrextra ?? null),
            'valor_terminal_recepcion' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->valor_terminal_recepcion ?? null, $cobro->cliente_vlrextra2 ?? null),
            'precio_factura' => (float) ($cobro->cliente_vlrfactura ?? 0),
            'precio_soporte' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->precio_soporte ?? null, $cobro->cliente_vlrsoporte ?? null),
            'precio_acuse' => $this->valorRevisionOBase($existeRevisionGuardada, $cobro->precio_acuse ?? null, $cobro->cliente_vlrecepcion ?? null),
        ];
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
