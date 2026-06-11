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
                    'citycodigo',
                    'citynomb',
                    'citydepto',
                    'cityNdepto',
                ])
            ->where(function ($query) use ($termino): void {
                $query->where('citynomb', 'like', "%{$termino}%")
                    ->orWhere('citydepto', 'like', "%{$termino}%")
                    ->orWhere('cityNdepto', 'like', "%{$termino}%");
            })
            ->orderBy('citynomb')
            ->limit(10)
            ->get()
            ->map(function ($city) {
                return [
                    'code' => (string) $city->citycodigo,
                    'citynomb' => $city->citynomb,
                    'label' => $this->formatCityLabel($city->citynomb, $city->cityNdepto ?: $city->citydepto),
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

    private function formatCityLabel(mixed $cityName, mixed $departmentName): string
    {
        $cityName = trim((string) $cityName);
        $departmentName = trim((string) $departmentName);

        if ($cityName === '') {
            return $departmentName;
        }

        if ($departmentName === '') {
            return $cityName;
        }

        $cityNameUpper = mb_strtoupper($cityName, 'UTF-8');
        $departmentNameUpper = mb_strtoupper($departmentName, 'UTF-8');

        if (str_ends_with($cityNameUpper, ', ' . $departmentNameUpper)) {
            return $cityName;
        }

        return "{$cityName}, {$departmentName}";
    }
}
