<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use App\Services\RevisarProformaCalculator;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'debug' => ['nullable'],
        ]);


        $filters = [
            'mes' => $validated['mes'] ?? null,
            'anio' => $validated['anio'] ?? $validated['ano'] ?? null,
            'proforma' => $validated['proforma'] ?? null,
        ];


        if ($request->boolean('debug')) {
            dd($this->cobrosService->debugSnapshot($filters));
        }


        $cobros = $this->cobrosService->paginateCobros($filters);

        return view('cobros.index', [
            'cobros' => $cobros,
            'filters' => $filters,

            'meses' => CobrosService::MESES,

        ]);
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

        $formData = $this->revisarProformaCalculator->calculate($this->mapCobroToRevisionData($cobro));

        return view('cobros.revisar', [
            'cobro' => $cobro,
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
        ]);

        $formData = $this->revisarProformaCalculator->calculate($validated);
        $accion = $validated['accion'] ?? 'guardar';

        if ($accion === 'recalcular') {
            return view('cobros.revisar', [
                'cobro' => $cobro,
                'formData' => $formData,
            ])->with('status', 'Valores recalculados en pantalla. Aún no se guardan.')->with('status_type', 'warning');
        }

        $this->cobrosService->updateCobroRevision($id, $formData);

        if ($accion === 'generar') {
            $cobroActualizado = $this->cobrosService->findCobroById($id);
            $resultado = $this->proformaStoreService->storeFromCobro($cobroActualizado ?: $cobro);

            return redirect()
                ->route('cobros.proforma.preview', $id)
                ->with('status', $resultado['message'].' Revisión guardada. Flujo de envío por correo pendiente para fase siguiente.')
                ->with('status_type', $resultado['duplicated'] ? 'warning' : 'success');
        }

        return redirect()
            ->route('cobros.revisar', $id)
            ->with('status', 'Revisión guardada correctamente.');
    }

    private function mapCobroToRevisionData(object $cobro): array
    {
        return [
            'numero_equipos' => $cobro->numero_equipos ?? 0,
            'valor_principal' => $cobro->valor_principal ?? 0,
            'valor_terminal' => $cobro->valor_terminal ?? 0,
            'empleados' => $cobro->empleados ?? 0,
            'valor_nomina' => $cobro->vlrnomina ?? ($cobro->valor_nomina ?? 0),
            'numero_moviles' => $cobro->numero_moviles ?? 0,
            'valor_movil' => $cobro->valor_movil ?? 0,
            'facturas' => $cobro->numero_facturas ?? 0,
            'nota_debito' => $cobro->numero_nota_debito ?? 0,
            'nota_credito' => $cobro->numero_nota_credito ?? 0,
            'soporte' => $cobro->numero_documento_soporte ?? 0,
            'nota_ajuste' => $cobro->numero_nota_ajuste ?? 0,
            'acuse' => $cobro->numero_acuse ?? 0,
            'otro_valor_extra' => $cobro->otro_valor_extra ?? ($cobro->cliente_vlrextra ?? 0),
            'valor_terminal_recepcion' => $cobro->valor_terminal_recepcion ?? ($cobro->cliente_vlrextra2 ?? 0),
            'precio_factura' => $cobro->precio_factura ?? ($cobro->cliente_vlrfactura ?? 0),
            'precio_soporte' => $cobro->precio_soporte ?? ($cobro->cliente_vlrsoporte ?? 0),
            'precio_acuse' => $cobro->precio_acuse ?? ($cobro->cliente_vlrecepcion ?? 0),
        ];
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
}
