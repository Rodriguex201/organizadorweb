<?php

namespace App\Http\Controllers;

use App\Services\ProformaCarteraExportService;
use App\Services\ProformaCarteraService;
use App\Services\ProformasService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformaCarteraController extends Controller
{
    private const FILTER_KEYS = [
        'modo',
        'codigo',
        'empresa',
        'nit',
        'fecha_desde',
        'fecha_hasta',
        'estado',
        'solo_acumuladas',
    ];

    public function __construct(
        private readonly ProformaCarteraService $proformaCarteraService,
        private readonly ProformaCarteraExportService $proformaCarteraExportService,
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'modo' => ['nullable', 'in:por_cobrar'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:60'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'integer', 'in:'.ProformasService::ESTADO_GENERADA.','.ProformasService::ESTADO_ENVIADA],
            'solo_acumuladas' => ['nullable', 'in:1,on,true'],
        ]);

        $filters = $this->proformaCarteraService->resolveFilters($validated);

        return view('proformas.cartera', [
            'filters' => $filters,
            'cartera' => $this->proformaCarteraService->paginateCartera($filters),
            'summary' => $this->proformaCarteraService->getSummary($filters),
            'isPorCobrarMode' => $this->proformaCarteraService->isPorCobrarMode($filters),
            'meses' => ProformasService::MESES,
            'estados' => [
                ProformasService::ESTADO_GENERADA => 'Generada',
                ProformasService::ESTADO_ENVIADA => 'Enviada',
            ],
            'proformaCarteraService' => $this->proformaCarteraService,
            'exportFilters' => $request->only(self::FILTER_KEYS),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'modo' => ['nullable', 'in:por_cobrar'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:60'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'estado' => ['nullable', 'integer', 'in:'.ProformasService::ESTADO_GENERADA.','.ProformasService::ESTADO_ENVIADA],
            'solo_acumuladas' => ['nullable', 'in:1,on,true'],
        ]);

        return $this->proformaCarteraExportService->download(
            $this->proformaCarteraService->resolveFilters($validated)
        );
    }
}
