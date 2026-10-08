<?php

namespace App\Http\Controllers;

use App\Services\ComprobantesZipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComprobantesZipController extends Controller
{
    public function descargar(Request $request, ComprobantesZipService $service)
    {
        $datos = $request->validate(['mes' => ['required', 'integer', 'between:1,12'], 'anio' => ['required', 'integer', 'between:1900,9999']]);
        $archivo = null;
        try {
            $resultado = $service->generar($service->registros((int) $datos['mes'], (int) $datos['anio']),
                Storage::disk('local')->path(''), storage_path('app/private/comprobantes-zip-temporales'));
            $archivo = $resultado['path'];
            return response()->download($archivo, sprintf('comprobantes_%02d_%04d.zip', $datos['mes'], $datos['anio']), [
                'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store',
                'X-Comprobantes-Incluidos' => (string) $resultado['incluidos'],
                'X-Comprobantes-Faltantes' => (string) $resultado['faltantes'],
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $error) {
            if ($archivo && is_file($archivo)) unlink($archivo);
            report($error);
            return response()->json(['message' => $error instanceof \RuntimeException
                ? $error->getMessage() : 'No se pudo generar el ZIP de comprobantes.'], 422);
        }
    }
}
