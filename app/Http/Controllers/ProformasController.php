<?php

namespace App\Http\Controllers;

use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class ProformasController extends Controller
{
    public function __construct(
        private readonly ProformasService $proformasService,
        private readonly ProformaPdfService $proformaPdfService,
        private readonly ProformaEmailService $proformaEmailService,
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'nro_prof' => ['nullable', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:60'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'emisora' => ['nullable', 'string', 'max:20'],
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'estado' => ['nullable', 'integer', 'min:0'],
        ]);

        $filters = [
            'nro_prof' => $validated['nro_prof'] ?? null,
            'nit' => $validated['nit'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'emisora' => $validated['emisora'] ?? null,
            'mes' => $validated['mes'] ?? null,
            'anio' => $validated['anio'] ?? null,
            'estado' => $validated['estado'] ?? null,
        ];

        return view('proformas.index', [
            'proformas' => $this->proformasService->paginateProformas($filters),
            'filters' => $filters,
            'estados' => ProformasService::ESTADOS,
            'meses' => ProformasService::MESES,
            'proformasService' => $this->proformasService,
        ]);
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
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        return view('proformas.show', [
            'proforma' => $proforma,
            'proformasService' => $this->proformasService,
        ]);
    }

    public function enviarCorreo(int $id): RedirectResponse
    {
        $proforma = $this->proformasService->findProformaById($id);

        if (!$proforma) {
            throw new NotFoundHttpException('Proforma no encontrada.');
        }

        if (!$this->proformasService->canSendProforma($proforma)) {
            return redirect()->back()->with('status', 'Primero debe generar la proforma antes de enviarla')->with('status_type', 'error');
        }

        try {
            $this->proformaEmailService->sendProforma($proforma);
            $this->proformasService->registrarEnvioExitoso($id);

            return redirect()->back()->with('status', 'Proforma enviada por correo correctamente.')->with('status_type', 'success');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->back()->with('status', 'No se pudo enviar el correo: '.$exception->getMessage())->with('status_type', 'error');
        }
    }

    public function updateEstado(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'integer'],
            'redirect_to' => ['nullable', 'string', 'in:index,show'],
        ]);

        $resultado = $this->proformasService->updateEstado($id, (int) $validated['estado']);

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
