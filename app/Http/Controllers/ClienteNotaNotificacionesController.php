<?php

namespace App\Http\Controllers;

use App\Services\ClienteNotaNotificacionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteNotaNotificacionesController extends Controller
{
    public function __construct(private readonly ClienteNotaNotificacionesService $notificaciones) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['pagina' => ['nullable', 'integer', 'min:1']]);

        return response()->json($this->notificaciones->pendientes(
            (int) $request->session()->get('idusuario'), (int) ($data['pagina'] ?? 1),
        ))->header('Cache-Control', 'no-store');
    }

    public function leida(Request $request, string $notaId): JsonResponse
    {
        $this->notificaciones->marcarLeida((int) $request->session()->get('idusuario'), $notaId);

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }
}
