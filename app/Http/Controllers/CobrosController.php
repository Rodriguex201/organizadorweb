<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
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
