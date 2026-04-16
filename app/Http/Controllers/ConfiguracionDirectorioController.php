<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionDirectorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionDirectorioController extends Controller
{
    public function index(): View
    {
        abort_unless(esAdmin(), 403, 'Esta sección es solo para administradores.');

        return view('configuracion.directorio', [
            'configuracion' => ConfiguracionDirectorio::query()->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(esAdmin(), 403, 'Esta sección es solo para administradores.');

        $validated = $request->validate([
            'ruta_clientes' => ['required', 'string', 'max:500'],
        ], [
            'ruta_clientes.required' => 'La ruta base es obligatoria.',
        ]);

        $ruta = trim($validated['ruta_clientes']);

        if (preg_match('/^[A-Za-z]:\\\\/', $ruta) === 1) {
            return back()->withInput()->withErrors([
                'ruta_clientes' => 'No se permite usar unidades locales como Z:\\. Debe ingresar una ruta UNC.',
            ]);
        }

        if (!str_starts_with($ruta, '\\\\')) {
            return back()->withInput()->withErrors([
                'ruta_clientes' => 'La ruta debe iniciar con \\\\ (UNC), por ejemplo: \\\\192.168.1.150\\00_Organizador_Empresas_Rm.',
            ]);
        }

        $configuracion = ConfiguracionDirectorio::query()->first();

        if ($configuracion) {
            $configuracion->update([
                'ruta_clientes' => $ruta,
            ]);
        } else {
            ConfiguracionDirectorio::query()->create([
                'ruta_clientes' => $ruta,
            ]);
        }

        return redirect()
            ->route('configuracion.directorio.index')
            ->with('status', 'Directorio base guardado correctamente.');
    }
}
