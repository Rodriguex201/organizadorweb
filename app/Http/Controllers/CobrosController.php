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
        $filters = $request->validate([
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'proforma' => ['nullable', 'string', 'max:100'],
        ]);

        $cobros = $this->cobrosService->paginateCobros($filters);

        return view('cobros.index', [
            'cobros' => $cobros,
            'filters' => $filters,
        ]);
    }
}
