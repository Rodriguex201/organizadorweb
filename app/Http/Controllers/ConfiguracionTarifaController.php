<?php

namespace App\Http\Controllers;

use App\Services\TarifaConfigService;
use App\Services\RetencionFuenteConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionTarifaController extends Controller
{
    public function __construct(private readonly TarifaConfigService $tarifaConfigService)
    {
    }

    public function index(RetencionFuenteConfigService $retenciones): View
    {
        return view('configuracion.tarifas', [
            'tarifas' => $this->tarifaConfigService->all(),
            'retenciones' => $retenciones->all(),
        ]);
    }

    public function storeRetencion(Request $request, RetencionFuenteConfigService $retenciones): RedirectResponse
    {
        abort_unless(puedeOperar(), 403);
        $retenciones->save($request->only(['porcentaje', 'descripcion', 'activo']));
        return redirect()->route('configuracion.tarifas.index')->with('status', 'Retención creada correctamente.');
    }

    public function updateRetencion(Request $request, int $retencion, RetencionFuenteConfigService $retenciones): RedirectResponse
    {
        abort_unless(puedeOperar(), 403);
        $retenciones->save($request->only(['porcentaje', 'descripcion', 'activo']), $retencion);
        return redirect()->route('configuracion.tarifas.index')->with('status', 'Retención actualizada correctamente.');
    }

    public function estadoRetencion(Request $request, int $retencion, RetencionFuenteConfigService $retenciones): RedirectResponse
    {
        abort_unless(puedeOperar(), 403);
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $retenciones->setActive($retencion, (bool) $data['activo']);
        return redirect()->route('configuracion.tarifas.index')->with('status', 'Estado de retención actualizado.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(
            puedeOperar(),
            403,
            'No tienes permisos para realizar esta operación.'
        );

        $validated = $request->validate([
            'tarifas' => ['required', 'array'],
            'tarifas.*.valor' => ['nullable', 'numeric', 'min:0'],
            'tarifas.*.activo' => ['nullable', 'boolean'],
        ]);

        $this->tarifaConfigService->updateMany($validated['tarifas'] ?? []);

        return redirect()
            ->route('configuracion.tarifas.index')
            ->with('status', 'Tarifas guardadas correctamente.')
            ->with('status_type', 'success');
    }
}
