<?php

namespace App\Http\Controllers;

use App\Services\EmpresaActivacionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivacionEventosController extends Controller
{
    public function buscar(Request $request, EmpresaActivacionService $service): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        return $this->responder(fn () => $service->buscarEventos($data['q']));
    }

    public function mostrar(Request $request, EmpresaActivacionService $service): JsonResponse
    {
        $data = $request->validate(['empresa' => ['required', 'string', 'max:100']]);
        return $this->responder(fn () => $service->detalleEventos($data['empresa']));
    }

    public function guardar(Request $request, EmpresaActivacionService $service): JsonResponse
    {
        $data = $request->validate(['empresa' => ['required', 'string', 'max:100'], 'fecha_fin' => ['required', 'date_format:Y-m-d']]);
        $usuario = trim((string) $request->session()->get('usuario', 'usuario')).' ('.$request->session()->get('idusuario').')';
        return $this->responder(fn () => $service->actualizarLicenciaEventos($data['empresa'], $data['fecha_fin'], $usuario));
    }

    private function responder(callable $operacion): JsonResponse
    {
        try {
            $data = $operacion();
            if ($data === null) return response()->json(['ok' => false, 'message' => 'Empresa no encontrada en Eventos.'], 404);
            return response()->json(['ok' => true, 'data' => $data, 'message' => !empty($data['sin_cambios'])
                ? 'La fecha de vencimiento ya es '.$data['fecha_vencimiento_nueva'].'. No fue necesario realizar cambios.'
                : 'Operación completada correctamente.']);
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['ok' => false, 'message' => $error instanceof \RuntimeException && !$error instanceof \Illuminate\Database\QueryException
                ? $error->getMessage() : 'No fue posible consultar o actualizar Eventos.'], 422);
        }
    }
}
