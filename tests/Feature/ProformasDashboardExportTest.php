<?php

namespace Tests\Feature;

use App\Exports\ProformasDashboardExcelExport;
use App\Http\Controllers\ProformasController;
use App\Services\ClienteCrecimientoReportService;
use App\Services\EmpresaActivacionService;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Mockery;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProformasDashboardExportTest extends TestCase
{
    public function test_dashboard_inicial_no_ejecuta_consultas_y_muestra_estado_vacio(): void
    {
        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);
        $crecimientoService = Mockery::mock(ClienteCrecimientoReportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);
        $service->shouldIgnoreMissing();

        $service->shouldNotReceive('normalizePeriodoFilters');
        $service->shouldNotReceive('getDashboardData');
        $service->shouldNotReceive('paginateDashboardProformas');

        $exportService->shouldReceive('getModalOptions')
            ->once()
            ->withArgs(function (array $filters): bool {
                return isset($filters['mes'], $filters['anio'])
                    && is_int($filters['mes'])
                    && is_int($filters['anio'])
                    && array_key_exists('estado', $filters)
                    && $filters['estado'] === null;
            })
            ->andReturn([
                'column_groups' => [],
                'defaults' => [
                    'summary' => [],
                    'detailed' => [],
                ],
                'filters' => [
                    'mes' => (int) now()->format('n'),
                    'anio' => (int) now()->format('Y'),
                    'estado' => null,
                ],
                'scopes' => [],
                'modes' => [],
                'formats' => [],
            ]);

        $request = Request::create(route('proformas.dashboard'), 'GET');

        $controller = new ProformasController(
            $service,
            $pdfService,
            $emailService,
            $exportService,
            $crecimientoService,
            $activacionService,
        );

        $view = $controller->dashboard($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('proformas.dashboard', $view->name());
        $this->assertFalse($view->getData()['hasSearched']);
        $this->assertSame((int) now()->format('n'), $view->getData()['filters']['mes']);
        $this->assertSame((int) now()->format('Y'), $view->getData()['filters']['anio']);
        $this->assertNull($view->getData()['filters']['estado']);
        $this->assertSame(0, $view->getData()['dashboard']['total_proformas']);
    }

    public function test_dashboard_aplica_filtro_estado_y_expone_opciones_de_exportacion(): void
    {
        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $exportService = Mockery::mock(ProformaDashboardExportService::class);
        $crecimientoService = Mockery::mock(ClienteCrecimientoReportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);
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
            ->with(5, 2026, 3, null)
            ->andReturn([
                'total_proformas' => 0,
                'total_generadas' => 0,
                'total_enviadas' => 0,
                'total_pagadas' => 0,
                'total_facturadas' => 0,
                'suma_total_vtotal' => 0,
                'suma_total_por_estado' => [],
                'total_periodo_filtrado' => 0,
            ]);

        $service->shouldReceive('paginateDashboardProformas')
            ->once()
            ->with(5, 2026, 3, null)
            ->andReturn(new LengthAwarePaginator([], 0, 15, 1, [
                'path' => route('proformas.dashboard'),
                'pageName' => 'page',
            ]));

        $exportService->shouldReceive('getModalOptions')
            ->once()
            ->with([
                'mes' => 5,
                'anio' => 2026,
                'estado' => 3,
                'grupo_fecha' => null,
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
            $crecimientoService,
            $activacionService,
        );

        $view = $controller->dashboard($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('proformas.dashboard', $view->name());
        $this->assertSame([
            'mes' => 5,
            'anio' => 2026,
            'estado' => 3,
            'grupo_fecha' => null,
        ], $view->getData()['filters']);
        $this->assertTrue($view->getData()['hasSearched']);
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
                'grupo_fecha' => null,
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

    public function test_fecha_de_retiro_valida_usa_el_formato_del_exportador(): void
    {
        $service = $this->makeRealExportService();

        $this->assertSame(
            '18/06/2026',
            $this->columnValue($service, 'cliente_fecha_retiro', '2026-06-18'),
        );
    }

    public function test_fecha_de_retiro_nula_o_invalida_exporta_celda_vacia(): void
    {
        $service = $this->makeRealExportService();

        $this->assertSame('', $this->columnValue($service, 'cliente_fecha_retiro', null));
        $this->assertSame('', $this->columnValue($service, 'cliente_fecha_retiro', 'fecha-no-valida'));
    }

    public function test_motivo_de_retiro_por_id_exporta_el_nombre_del_catalogo(): void
    {
        $this->createRetiroCatalog();
        DB::table('conceptos_r')->insert([
            'id_retiro' => 2,
            'conceptosretiro' => 'cambio contador',
        ]);
        $service = $this->makeRealExportService();

        $this->assertSame(
            'cambio contador',
            $this->columnValue($service, 'cliente_motivo_retiro', '2'),
        );
    }

    public function test_motivo_de_retiro_historico_conserva_el_texto_original(): void
    {
        $this->createRetiroCatalog();
        $service = $this->makeRealExportService();

        $this->assertSame(
            'Cierre empresa',
            $this->columnValue($service, 'cliente_motivo_retiro', 'Cierre empresa'),
        );
        $this->assertSame('', $this->columnValue($service, 'cliente_motivo_retiro', null));
    }

    public function test_motivo_de_retiro_con_id_sin_catalogo_conserva_el_valor_original(): void
    {
        $this->createRetiroCatalog();
        DB::table('conceptos_r')->insert([
            'id_retiro' => 1,
            'conceptosretiro' => 'costoso',
        ]);
        $service = $this->makeRealExportService();

        $this->assertSame(
            '999',
            $this->columnValue($service, 'cliente_motivo_retiro', '999'),
        );
    }

    public function test_nuevas_columnas_aparecen_en_datos_cliente_y_respetan_el_orden_en_excel(): void
    {
        $this->createRetiroCatalog();
        DB::table('conceptos_r')->insert([
            'id_retiro' => 2,
            'conceptosretiro' => 'cambio contador',
        ]);
        $service = $this->makeRealExportService();
        $options = $service->getModalOptions();
        $clienteGroup = collect($options['column_groups'])->firstWhere('key', 'cliente');

        $this->assertNotNull($clienteGroup);
        $this->assertContains('cliente_fecha_retiro', array_column($clienteGroup['columns'], 'key'));
        $this->assertContains('cliente_motivo_retiro', array_column($clienteGroup['columns'], 'key'));

        $selected = $this->invokePrivateMethod($service, 'sanitizeSelectedColumns', [[
            'cliente_motivo_retiro',
            'cliente_fecha_retiro',
        ], ProformaDashboardExportService::EXPORT_MODE_DETAILED]);
        $definitions = $this->invokePrivateMethod($service, 'columnDefinitions');
        $row = (object) [
            'cliente_motivo_retiro' => '2',
            'cliente_fecha_retiro' => '2026-06-18',
        ];
        $headings = array_map(fn (string $key) => $definitions[$key]['label'], $selected);
        $values = array_map(fn (string $key) => ($definitions[$key]['value'])($row), $selected);
        $excel = new ProformasDashboardExcelExport($headings, [$values]);

        $this->assertSame([
            ['Motivo de retiro', 'Fecha de retiro'],
            ['cambio contador', '18/06/2026'],
        ], $excel->array());
    }

    private function makeRealExportService(): ProformaDashboardExportService
    {
        return new ProformaDashboardExportService(Mockery::mock(ProformasService::class));
    }

    private function columnValue(ProformaDashboardExportService $service, string $key, mixed $value): mixed
    {
        $definitions = $this->invokePrivateMethod($service, 'columnDefinitions');

        return ($definitions[$key]['value'])((object) [$key => $value]);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function createRetiroCatalog(): void
    {
        Schema::dropIfExists('conceptos_r');
        Schema::create('conceptos_r', function (Blueprint $table): void {
            $table->integer('id_retiro')->primary();
            $table->string('conceptosretiro');
        });
    }
}
