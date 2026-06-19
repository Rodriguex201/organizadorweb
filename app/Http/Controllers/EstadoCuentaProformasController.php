<?php

namespace App\Http\Controllers;

use App\Services\EstadoCuentaProformasService;
use App\Services\ProformaEmailService;
use App\Services\ProformasService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EstadoCuentaProformasController extends Controller
{
    private const ADVERTENCIA = 'Documento informativo generado a partir de proformas existentes. No modifica estados ni genera facturación.';

    public function __construct(
        private readonly EstadoCuentaProformasService $estadoCuentaService,
        private readonly ProformaEmailService $proformaEmailService,
        private readonly ProformasService $proformasService,
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:60'],
            'estado' => ['nullable', 'string', 'in:default,2,3,4,6,todas'],
            'destinatarios' => ['nullable', 'string', 'max:2000'],
        ]);

        $filters = array_merge($this->estadoCuentaService->defaultFilters(), [
            'busqueda' => $validated['busqueda'] ?? null,
            'nit' => $validated['nit'] ?? null,
            'estado' => $validated['estado'] ?? 'default',
        ]);

        $hasSearched = $this->estadoCuentaService->hasSearchCriteria($filters);
        $proformas = $hasSearched
            ? $this->estadoCuentaService->searchProformas($filters)
            : $this->estadoCuentaService->searchProformas([], 15);

        $destinatarios = trim((string) ($validated['destinatarios'] ?? ''));

        if ($destinatarios === '') {
            $destinatarios = $this->estadoCuentaService->resolveDefaultDestinatarios($hasSearched ? $proformas : null);
        }

        return view('proformas.estado-cuenta', [
            'filters' => $filters,
            'proformas' => $proformas,
            'estadoOptions' => $this->estadoCuentaService->estadoFilterOptions(),
            'hasSearched' => $hasSearched,
            'warningMessage' => self::ADVERTENCIA,
            'defaultDestinatarios' => $destinatarios,
            'proformasService' => $this->proformasService,
        ]);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'accion' => ['required', 'string', 'in:pdf,enviar'],
            'proformas' => ['required', 'array', 'min:1'],
            'proformas.*' => ['integer', 'distinct'],
            'destinatarios' => ['nullable', 'string', 'max:2000'],
        ]);

        $proformas = $this->estadoCuentaService->findSelectedProformas($validated['proformas'] ?? []);
        $selection = $this->estadoCuentaService->depurateSelection($proformas);

        if (!($selection['ok'] ?? false)) {
            return back()
                ->withInput()
                ->with('status', $selection['message'])
                ->with('status_type', 'error');
        }

        $proformasDepuradas = $selection['proformas'] ?? collect();
        $payload = $this->estadoCuentaService->buildEstadoCuentaPayload($proformasDepuradas);
        $pdfBinary = Pdf::loadView('proformas.estado-cuenta-pdf', [
            'estadoCuenta' => $payload,
            'warningMessage' => self::ADVERTENCIA,
        ])->setPaper('a4')->output();

        $filename = $this->estadoCuentaService->browserFilename($payload);

        if (($validated['accion'] ?? 'pdf') === 'enviar') {
            try {
                $destinatarios = $this->proformaEmailService->resolveDestinatariosFromRaw(
                    (string) ($validated['destinatarios'] ?? ''),
                    '[ENVIO ESTADO CUENTA]'
                );

                Log::info('[ENVIO ESTADO CUENTA] DATOS PREVIOS', [
                    'nit' => $payload['nit'] ?? null,
                    'empresa' => $payload['empresa'] ?? null,
                    'cantidad_proformas' => $payload['cantidad_proformas'] ?? 0,
                    'email_original_cliente' => $destinatarios['original'],
                    'correo_final_a_enviar' => $destinatarios['emails'],
                    'cantidad_correos' => $destinatarios['count'],
                ]);

                $this->proformaEmailService->sendDocument([
                    'filename' => $filename,
                    'contents' => $pdfBinary,
                    'contexto' => 'estado_cuenta_consolidado',
                ], [
                    'destinatarios' => $destinatarios,
                    'subject' => 'Estado de cuenta consolidado - '.($payload['empresa'] ?? 'Cliente'),
                    'text' => "Cordial saludo,\n\nAdjuntamos el estado de cuenta consolidado solicitado.\n\n".self::ADVERTENCIA."\n\nCordialmente,\nRM Soft",
                    'log_prefix' => '[ENVIO ESTADO CUENTA]',
                ]);
            } catch (\Throwable $exception) {
                Log::error('[ENVIO ESTADO CUENTA] CATCH', [
                    'nit' => $payload['nit'] ?? null,
                    'empresa' => $payload['empresa'] ?? null,
                    'mensaje_error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                    'payload' => [
                        'email_original_cliente' => $destinatarios['original'] ?? null,
                        'correo_final_a_enviar' => $destinatarios['emails'] ?? [],
                        'cantidad_correos' => $destinatarios['count'] ?? 0,
                    ],
                ]);

                report($exception);

                return back()
                    ->withInput()
                    ->with('status', 'No se pudo enviar el correo: '.$exception->getMessage())
                    ->with('status_type', 'error');
            }

            return back()
                ->withInput()
                ->with('status', 'Estado de cuenta enviado por correo correctamente.')
                ->with('status_type', 'success');
        }

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
