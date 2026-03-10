<?php

namespace App\Http\Controllers;

use App\Services\EstadoProformaConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionEstadoProformaController extends Controller
{
    public function __construct(private readonly EstadoProformaConfigService $configService)
    {
    }

    public function index(): View
    {
        return view('configuracion.estados-proforma', [
            'estadosConfig' => $this->configService->all(),
        ]);
    }

    public function update(Request $request, int $estadoCodigo): RedirectResponse
    {
        $validated = $request->validate([
            'color_fondo' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_texto' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->configService->updateColors(
            $estadoCodigo,
            $validated['color_fondo'],
            $validated['color_texto'],
        );

        return redirect()
            ->route('configuracion.estados-proforma.index')
            ->with('status', 'Configuración actualizada correctamente.')
            ->with('status_type', 'success');
    }
}
