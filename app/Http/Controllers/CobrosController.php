<?php

namespace App\Http\Controllers;

use App\Services\CobrosService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            'debug' => ['nullable'],
        ]);


        $filters = [
            'mes' => $validated['mes'] ?? null,
            'anio' => $validated['anio'] ?? $validated['ano'] ?? null,
            'proforma' => $validated['proforma'] ?? null,
        ];


        if ($request->boolean('debug')) {
            dd($this->cobrosService->debugSnapshot($filters));
        }


        $cobros = $this->cobrosService->paginateCobros($filters);

        return view('cobros.index', [
            'cobros' => $cobros,
            'filters' => $filters,

            'meses' => CobrosService::MESES,

        ]);
    }

    public function show(int $id): View
    {
        $cobro = $this->cobrosService->findCobroById($id);

        if (!$cobro) {
            throw new NotFoundHttpException('Cobro no encontrado.');
        }

        return view('cobros.show', [
            'cobro' => $cobro,
        ]);
    }

}
