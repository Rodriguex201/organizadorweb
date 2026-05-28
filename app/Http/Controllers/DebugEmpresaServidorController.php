<?php

namespace App\Http\Controllers;

use App\Services\EmpresaServidorService;
use Illuminate\Http\JsonResponse;

class DebugEmpresaServidorController extends Controller
{
    public function show(string $codigo): JsonResponse
    {
        return response()->json(EmpresaServidorService::debugConexionPorCodigo($codigo));
    }
}
