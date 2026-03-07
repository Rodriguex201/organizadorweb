<?php

namespace App\Http\Controllers;

use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProformasController extends Controller
{
    public function __construct(
        private readonly ProformasService $proformasService,
        private readonly ProformaPdfService $proformaPdfService,
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'nro_prof' => ['nullable', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:60'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'emisora' => ['nullable', 'string', 'max:20'],
            'mes' => ['nullable', 'string', 'max:20'],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'estado' => ['nullable', 'integer', 'min:0'],
        ]);

        $filters = [
            'nro_prof' => $validated['nro_prof'] ?? null,
            'nit' => $validated['nit'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'emisora' => $validated['emisora'] ?? null,
            'mes' => $validated['mes'] ?? null,
            'anio' => $validated['anio'] ?? null,
            'estado' => $validated['estado'] ?? null,
        ];

        return view('proformas.index', [
            'proformas' => $this->proformasService->paginateProformas($filters),
            'filters' => $filters,
            'estados' => ProformasService::ESTADOS,
            'meses' => ProformasService::MESES,
            'proformasService' => $this->proformasService,
        ]);
    }

    public function showPdf(Request $request, int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId(
            $id,
            $request->boolean('regenerar'),
        );

        return response()->file($resultado['absolute_path'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }

    public function downloadPdf(int $id): BinaryFileResponse
    {
        $resultado = $this->proformaPdfService->generateForProformaId($id);

        return response()->download($resultado['absolute_path'], $resultado['filename'], [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function show(int $id): View
    {
        return view('proformas.show', ['proformaId' => $id]);
    }
}
