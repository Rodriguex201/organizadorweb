<?php

namespace Tests\Feature;

use App\Http\Controllers\ProformasController;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProformasDashboardExportTest extends TestCase
{
    public function test_dashboard_aplica_filtro_estado_y_expone_opciones_de_exportacion(): void
    {
        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);
        $service->shouldIgnoreMissing();

        $service->shouldReceive('normalizePeriodoFilters')
            ->once()
            ->with('5', 2026)
            ->andReturn([
                'mes' => 5,
                'anio' => 2026,
            ]);

        $service->shouldReceive('getDashboardData')
            ->once()
            ->with(5, 2026, 3)
            ->andReturn([
                'total_proformas' => 0,
                'total_generadas' => 0,
                'total_enviadas' => 0,
                'total_pagadas' => 0,
                'total_facturadas' => 0,
                'suma_total_vtotal' => 0,
                'suma_total_por_estado' => [],
                'total_periodo_filtrado' => 0,
                'ultimas_proformas' => collect(),
            ]);

        $exportService->shouldReceive('getModalOptions')
            ->once()
            ->with([
                'mes' => 5,
                'anio' => 2026,
                'estado' => 3,
            ])
            ->andReturn([
                'column_groups' => [],
                'defaults' => [
                    'summary' => [],
                    'detailed' => [],
                ],
                'filters' => [
                    'mes' => 5,
                    'anio' => 2026,
                    'estado' => 3,
                ],
                'scopes' => [],
                'modes' => [],
                'formats' => [],
            ]);

        $request = Request::create(route('proformas.dashboard', [
            'mes' => '5',
            'anio' => 2026,
            'estado' => 3,
        ]), 'GET');

        $controller = new ProformasController(
            $service,
            $pdfService,
            $emailService,
            $exportService,
        );

        $view = $controller->dashboard($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('proformas.dashboard', $view->name());
        $this->assertSame([
            'mes' => 5,
            'anio' => 2026,
            'estado' => 3,
        ], $view->getData()['filters']);
        $this->assertArrayHasKey('exportOptions', $view->getData());
    }

    public function test_export_dashboard_resuelve_filtros_y_prepara_descarga_excel_ajax(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);

        $validatedPayload = [
            'dashboard_mes' => '5',
            'dashboard_anio' => 2026,
            'dashboard_estado' => 3,
            'scope' => 'current_filters',
            'anio' => 2026,
            'mes_desde' => 5,
            'mes_hasta' => 5,
            'estado' => 3,
            'mode' => 'detailed',
            'format' => 'xlsx',
            'columns' => ['cliente_codigo', 'proforma_numero'],
        ];
        $resolvedFilters = [
            'scope' => 'current_filters',
            'mes' => 5,
            'anio' => 2026,
            'estado' => 3,
            'mes_desde' => 5,
            'mes_hasta' => 5,
            'debug_minimal' => false,
            'debug_limit' => null,
        ];

        $exportService->shouldReceive('resolveFilters')
            ->once()
            ->with($validatedPayload, [
                'mes' => '5',
                'anio' => 2026,
                'estado' => 3,
            ])
            ->andReturn($resolvedFilters);

        $exportService->shouldReceive('prepareTemporaryDownload')
            ->once()
            ->with($resolvedFilters, ['cliente_codigo', 'proforma_numero'], 'detailed', 'xlsx')
            ->andReturn([
                'token' => 'fake-token',
                'filename' => 'proformas.xlsx',
                'record_count' => 12,
                'duration_ms' => 345,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $exportService);

        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('proformas.dashboard.export'), $validatedPayload);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'message' => 'Excel generado correctamente.',
            'filename' => 'proformas.xlsx',
            'record_count' => 12,
            'duration_ms' => 345,
        ]);
    }

    public function test_export_dashboard_devuelve_json_amigable_si_falla_la_exportacion_ajax(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);

        $validatedPayload = [
            'dashboard_mes' => '5',
            'dashboard_anio' => 2026,
            'dashboard_estado' => 3,
            'scope' => 'current_filters',
            'anio' => 2026,
            'mes_desde' => 5,
            'mes_hasta' => 5,
            'estado' => 3,
            'mode' => 'detailed',
            'format' => 'xlsx',
            'columns' => ['cliente_codigo', 'proforma_numero'],
        ];
        $resolvedFilters = [
            'scope' => 'current_filters',
            'mes' => 5,
            'anio' => 2026,
            'estado' => 3,
            'mes_desde' => 5,
            'mes_hasta' => 5,
            'debug_minimal' => false,
            'debug_limit' => null,
        ];

        $exportService->shouldReceive('resolveFilters')
            ->once()
            ->andReturn($resolvedFilters);

        $exportService->shouldReceive('download')
            ->never();

        $exportService->shouldReceive('prepareTemporaryDownload')
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $exportService);

        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('proformas.dashboard.export'), $validatedPayload);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se pudo generar el archivo Excel. Verifica los filtros e inténtalo nuevamente.',
        ]);
    }

    public function test_download_dashboard_export_descarga_archivo_temporal(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);

        $exportService->shouldReceive('downloadTemporaryFile')
            ->once()
            ->with('fake-token')
            ->andReturn(response()->download(__FILE__, 'proformas.xlsx'));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $exportService);

        $response = $this->get(route('proformas.dashboard.export.download', ['token' => 'fake-token']));

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertOk();
    }
}
