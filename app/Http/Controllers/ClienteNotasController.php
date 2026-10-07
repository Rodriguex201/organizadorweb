<?php

namespace App\Http\Controllers;

use App\Services\ClienteNotasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteNotasController extends Controller
{
    public function __construct(private readonly ClienteNotasService $notas) {}

    public function index(Request $request, int $clienteId): JsonResponse
    {
        $this->verificar($request, $clienteId);
        return response()->json($this->notas->listado($clienteId));
    }

    public function store(Request $request, int $clienteId): JsonResponse
    {
        $this->verificar($request, $clienteId);
        $this->notas->crear($clienteId, $this->datos($request));
        return response()->json($this->notas->listado($clienteId), 201);
    }

    public function update(Request $request, int $clienteId, string $notaId): JsonResponse
    {
        $this->verificar($request, $clienteId);
        $this->notas->modificar($clienteId, $notaId, $this->datos($request));
        return response()->json($this->notas->listado($clienteId));
    }

    public function completada(Request $request, int $clienteId, string $notaId): JsonResponse
    {
        $this->verificar($request, $clienteId);
        $data = $request->validate(['completada' => ['required', 'boolean']]);
        $this->notas->completar($clienteId, $notaId, (bool) $data['completada']);
        return response()->json($this->notas->listado($clienteId));
    }

    public function destroy(Request $request, int $clienteId, string $notaId): JsonResponse
    {
        $this->verificar($request, $clienteId);
        $this->notas->eliminar($clienteId, $notaId);
        return response()->json($this->notas->listado($clienteId));
    }

    private function verificar(Request $request, int $clienteId): void
    {
        $this->notas->verificarContexto($clienteId, $request->header('X-Notas-Contexto'));
    }

    private function datos(Request $request): array
    {
        if (is_string($request->input('texto'))) {
            $request->merge(['texto' => trim($request->input('texto'))]);
        }
        $data = $request->validate([
            'tipo' => ['required', 'in:GENERAL,CHECKLIST'],
            'texto' => ['required', 'string', 'max:10000'],
            'monto' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'fecha_objetivo' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $data['monto'] = $data['tipo'] === 'CHECKLIST' ? ($data['monto'] ?? null) : null;
        $data['fecha_objetivo'] = $data['tipo'] === 'CHECKLIST' ? ($data['fecha_objetivo'] ?? null) : null;
        return $data;
    }
}
