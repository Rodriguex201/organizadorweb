<?php

namespace App\Http\Controllers;

use App\Services\ClienteProformaWhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteProformaWhatsappController extends Controller
{
    public function __construct(private readonly ClienteProformaWhatsappService $service) {}

    public function buscar(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        return response()->json(['data' => $this->service->buscar($data['q'] ?? '')])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['grupo' => ['nullable', 'integer', 'in:7,27'], 'pagina' => ['nullable', 'integer', 'min:1']]);
        return response()->json($this->service->listado(isset($data['grupo']) ? (int) $data['grupo'] : null, (int) ($data['pagina'] ?? 1)))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, int $clienteId): JsonResponse
    {
        $this->service->agregar($clienteId, $this->datos($request));
        return response()->json(['ok' => true], 201);
    }

    public function update(Request $request, int $clienteId): JsonResponse
    {
        $this->service->editar($clienteId, $this->datos($request));
        return response()->json(['ok' => true]);
    }

    public function destroy(int $clienteId): JsonResponse
    {
        $this->service->eliminar($clienteId);
        return response()->json(['ok' => true]);
    }

    private function datos(Request $request): array
    {
        $alternativo = $request->input('whatsapp_alternativo');
        $request->merge(['whatsapp_alternativo' => $request->input('telefono_fuente') === 'ALTERNATIVO'
            ? (is_string($alternativo) ? ClienteProformaWhatsappService::normalizarAlternativo($alternativo) : $alternativo)
            : null]);
        return $request->validate(ClienteProformaWhatsappService::reglas());
    }
}
