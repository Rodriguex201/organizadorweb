<?php

namespace App\Http\Controllers;

use App\Services\TarifaConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionTarifaController extends Controller
{
    public function __construct(private readonly TarifaConfigService $tarifaConfigService)
    {
    }

    public function index(): View
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta seccion es solo para administradores.'
        );

        return view('configuracion.tarifas', [
            'tarifas' => $this->tarifaConfigService->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(
            strtolower(session('rol_nombre', '')) === 'admin',
            403,
            'Esta seccion es solo para administradores.'
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
