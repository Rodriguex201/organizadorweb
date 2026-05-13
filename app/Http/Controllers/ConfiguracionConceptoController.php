<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConceptoRequest;
use App\Http\Requests\UpdateConceptoRequest;
use App\Models\Concepto;
use App\Services\ConceptosConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfiguracionConceptoController extends Controller
{
    public function __construct(private readonly ConceptosConfigService $conceptosConfigService)
    {
    }

    public function index(): View
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta sección es solo para administradores.'
        );

        return view('configuracion.conceptos', [
            'conceptos' => $this->conceptosConfigService->allWithUsage(),
        ]);
    }

    public function store(StoreConceptoRequest $request): RedirectResponse
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta sección es solo para administradores.'
        );

        $this->conceptosConfigService->create($request->validated());

        return redirect()
            ->route('configuracion.conceptos.index')
            ->with('status', 'Concepto creado correctamente.')
            ->with('status_type', 'success');
    }

    public function update(UpdateConceptoRequest $request, Concepto $concepto): RedirectResponse
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta sección es solo para administradores.'
        );

        $this->conceptosConfigService->update($concepto, $request->validated());

        return redirect()
            ->route('configuracion.conceptos.index')
            ->with('status', 'Concepto actualizado correctamente.')
            ->with('status_type', 'success');
    }

    public function destroy(Concepto $concepto): RedirectResponse
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta sección es solo para administradores.'
        );

        $result = $this->conceptosConfigService->delete($concepto);

        return redirect()
            ->route('configuracion.conceptos.index')
            ->with('status', $result['message'])
            ->with('status_type', $result['status_type']);
    }

    public function toggle(Concepto $concepto): RedirectResponse
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta sección es solo para administradores.'
        );

        $activo = $this->conceptosConfigService->toggleActive($concepto);

        return redirect()
            ->route('configuracion.conceptos.index')
            ->with('status', $activo ? 'Concepto activado correctamente.' : 'Concepto desactivado correctamente.')
            ->with('status_type', 'success');
    }
}
