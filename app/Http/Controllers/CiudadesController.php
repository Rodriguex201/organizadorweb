<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CiudadesController extends Controller
{
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));

        if (mb_strlen($termino) < 3) {
            return response()->json([
                'message' => 'Escribe al menos 3 caracteres para buscar ciudades.',
                'results' => [],
            ], 422);
        }

        try {
            $resultados = DB::table('xxxxcity')
                ->select([
                'citynomb',
                'citydepto',
            ])
            ->where(function ($query) use ($termino): void {
                $query->where('citynomb', 'like', "%{$termino}%")
                    ->orWhere('citydepto', 'like', "%{$termino}%");
            })
            ->orderBy('citynomb')
            ->limit(10)
            ->get()
            ->map(static function ($city) {
                return [
                    'citynomb' => $city->citynomb,
                    'label' => $city->citynomb,
                ];
            })
                ->values();
        } catch (\Throwable) {
            return response()->json([
                'message' => 'No fue posible consultar las ciudades en este momento.',
                'results' => [],
            ], 500);
        }

        return response()->json([
            'results' => $resultados,
        ]);
    }
}
