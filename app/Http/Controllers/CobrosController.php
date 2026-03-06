<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CobrosController extends Controller
{
    public function __construct(private readonly CobrosService $cobrosService)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'proforma' => ['nullable', 'string', 'max:100'],
        ]);

        $filters = [
            'mes' => $validated['mes'] ?? null,
            'anio' => $validated['anio'] ?? $validated['ano'] ?? null,
            'proforma' => $validated['proforma'] ?? null,
        ];

        $cobros = $this->cobrosService->paginateCobros($filters);

        return view('cobros.index', [
            'cobros' => $cobros,
            'filters' => $filters,
            'meses' => CobrosService::MESES,
        ]);
    }
}
