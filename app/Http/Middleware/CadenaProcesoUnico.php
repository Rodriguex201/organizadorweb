<?php

namespace App\Http\Middleware;

use App\Services\Cadena\CadenaProcesoLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CadenaProcesoUnico
{
    public function handle(Request $request, Closure $next): Response
    {
        $lock = new CadenaProcesoLock();
        try {
            $acquired = $lock->acquire(storage_path('framework/cadena-locks'), $request->session()->getId());
        } catch (\RuntimeException) {
            return response()->json(['message' => 'No se pudo proteger la generación. Vuelve a intentar.'], 503);
        }
        if (!$acquired) {
            return response()->json(['message' => 'Ya hay una generación en curso en esta sesión. Espera a que termine antes de volver a intentar.'], 409);
        }
        try {
            return $next($request);
        } finally {
            $lock->release();
        }
    }
}
