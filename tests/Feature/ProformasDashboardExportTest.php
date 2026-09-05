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
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'dashboard_export_tests',
            'database.connections.dashboard_export_tests' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('dashboard_export_tests');
    }

    public function test_modal_muestra_los_dos_origenes_y_marca_los_grupos_de_columnas(): void
    {
        $this->withoutExceptionHandling();
        $this->withoutMiddleware();
        $this->withoutVite();
        Schema::create('configuracion_estados_proforma', function (Blueprint $table): void {
            $table->integer('estado_codigo')->primary();
            $table->string('estado_nombre');
            $table->string('color_fondo');
            $table->string('color_texto');
            $table->boolean('activo');
        });
        DB::table('configuracion_estados_proforma')->insert([
            ['estado_codigo' => 2, 'estado_nombre' => 'Generada', 'color_fondo' => '#DBEAFE', 'color_texto' => '#1D4ED8', 'activo' => 1],
            ['estado_codigo' => 3, 'estado_nombre' => 'Enviada', 'color_fondo' => '#E0E7FF', 'color_texto' => '#3730A3', 'activo' => 1],
            ['estado_codigo' => 4, 'estado_nombre' => 'Pagada', 'color_fondo' => '#D1FAE5', 'color_texto' => '#047857', 'activo' => 1],
            ['estado_codigo' => 6, 'estado_nombre' => 'Facturada', 'color_fondo' => '#F3E8FF', 'color_texto' => '#7E22CE', 'activo' => 1],
        ]);

        $this->get('/proformas/dashboard')
            ->assertOk()
            ->assertSee('Exportar por')
            ->assertSee('Período de proformas')
            ->assertSee('Clientes retirados por fecha de retiro')
            ->assertSee('data-column-section="cliente"', false)
            ->assertSee('data-column-section="cliente_valores"', false)
            ->assertSee('data-column-section="proforma"', false);
    }

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

    public function test_modo_retirados_incluye_cliente_de_2026_sin_proformas(): void
    {
        $this->createRetiredClientsFixture();
        $service = $this->makeRealExportService();
        $rows = $this->retiredDatasetRows($service, ['cliente_codigo', 'cliente_fecha_retiro']);

        $this->assertContains(['SIN-PROFORMA', '15/03/2026'], $rows);
    }

    public function test_modo_retirados_devuelve_una_fila_aunque_existan_varias_proformas_historicas(): void
    {
        $this->createRetiredClientsFixture();
        $service = $this->makeRealExportService();
        $rows = $this->retiredDatasetRows($service, ['cliente_codigo']);

        $this->assertSame(1, collect($rows)->where(0, 'CON-HISTORIAL')->count());
    }

    public function test_modo_retirados_excluye_clientes_fuera_del_anio_o_con_fecha_invalida(): void
    {
        $this->createRetiredClientsFixture();
        $service = $this->makeRealExportService();
        $rows = $this->retiredDatasetRows($service, ['cliente_codigo']);
        $codes = array_column($rows, 0);

        $this->assertNotContains('RETIRO-2025', $codes);
        $this->assertNotContains('FECHA-INVALIDA', $codes);
    }

    public function test_modo_retirados_resuelve_motivo_de_catalogo_y_conserva_texto_historico(): void
    {
        $this->createRetiredClientsFixture();
        DB::table('conceptos_r')->insert(['id_retiro' => 2, 'conceptosretiro' => 'Cambio de contador']);
        $service = $this->makeRealExportService();
        $rows = $this->retiredDatasetRows($service, ['cliente_codigo', 'cliente_motivo_retiro']);
        $reasons = collect($rows)->mapWithKeys(fn (array $row) => [$row[0] => $row[1]])->all();

        $this->assertSame('Cambio de contador', $reasons['SIN-PROFORMA']);
        $this->assertSame('Cierre histórico', $reasons['CON-HISTORIAL']);
    }

    public function test_modo_retirados_descarta_columnas_de_proforma_en_backend(): void
    {
        $service = $this->makeRealExportService();
        $selected = $this->invokePrivateMethod($service, 'sanitizeSelectedColumns', [[
            'proforma_numero',
            'cliente_motivo_retiro',
            'proforma_estado',
            'cliente_fecha_retiro',
        ], ProformaDashboardExportService::EXPORT_MODE_DETAILED, ProformaDashboardExportService::SOURCE_RETIRED_CLIENTS]);

        $this->assertSame(['cliente_motivo_retiro', 'cliente_fecha_retiro'], $selected);

        $this->expectException(\InvalidArgumentException::class);
        $this->invokePrivateMethod($service, 'sanitizeSelectedColumns', [[
            'proforma_numero',
            'proforma_estado',
        ], ProformaDashboardExportService::EXPORT_MODE_DETAILED, ProformaDashboardExportService::SOURCE_RETIRED_CLIENTS]);
    }

    public function test_modo_retirados_aplica_solo_anio_de_retiro_en_filtros_resueltos(): void
    {
        $proformas = Mockery::mock(ProformasService::class);
        $proformas->shouldReceive('normalizeGrupoFechaFilter')->twice()->andReturn(2);
        $service = new ProformaDashboardExportService($proformas);
        $filters = $service->resolveFilters([
            'export_source' => ProformaDashboardExportService::SOURCE_RETIRED_CLIENTS,
            'scope' => ProformaDashboardExportService::SCOPE_MONTHLY_RANGE,
            'anio' => 2026,
            'mes_desde' => 8,
            'mes_hasta' => 9,
            'estado' => 4,
            'grupo_fecha' => 2,
        ], ['mes' => 9, 'anio' => 2026, 'estado' => 4, 'grupo_fecha' => 2]);

        $this->assertSame(ProformaDashboardExportService::SOURCE_RETIRED_CLIENTS, $filters['source']);
        $this->assertSame(2026, $filters['anio']);
        $this->assertNull($filters['mes']);
        $this->assertNull($filters['mes_desde']);
        $this->assertNull($filters['mes_hasta']);
        $this->assertNull($filters['estado']);
        $this->assertNull($filters['grupo_fecha']);
    }

    public function test_modo_actual_conserva_filtro_y_una_fila_por_proforma(): void
    {
        Schema::create('sg_proform', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nro_prof');
            $table->integer('anio');
            $table->integer('mes');
            $table->integer('estado')->nullable();
        });
        DB::table('sg_proform')->insert([
            ['nro_prof' => 'SEP-1', 'anio' => 2026, 'mes' => 9, 'estado' => 2],
            ['nro_prof' => 'SEP-2', 'anio' => 2026, 'mes' => 9, 'estado' => 3],
            ['nro_prof' => 'AGO-1', 'anio' => 2026, 'mes' => 8, 'estado' => 2],
        ]);
        $proformas = Mockery::mock(ProformasService::class);
        $proformas->shouldReceive('normalizeGrupoFechaFilter')->once()->with(null)->andReturnNull();
        $service = new ProformaDashboardExportService($proformas);
        $dataset = $this->invokePrivateMethod($service, 'buildDataset', [[
            'scope' => ProformaDashboardExportService::SCOPE_CURRENT_FILTERS,
            'anio' => 2026,
            'mes' => 9,
            'estado' => null,
            'grupo_fecha' => null,
        ], ['proforma_numero']]);
        $rows = $dataset['rows'];
        array_pop($rows);

        $this->assertSame(2, $dataset['record_count']);
        $this->assertSame([['SEP-2'], ['SEP-1']], $rows);
    }

    private function makeRealExportService(): ProformaDashboardExportService
    {
        return new ProformaDashboardExportService(Mockery::mock(ProformasService::class));
    }

    private function retiredDatasetRows(ProformaDashboardExportService $service, array $columns): array
    {
        $dataset = $this->invokePrivateMethod($service, 'buildDataset', [[
            'source' => ProformaDashboardExportService::SOURCE_RETIRED_CLIENTS,
            'anio' => 2026,
        ], $columns]);
        $rows = $dataset['rows'];
        array_pop($rows);

        return $rows;
    }

    private function createRetiredClientsFixture(): void
    {
        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->integer('idclientes_potenciales')->primary();
            $table->string('codigo')->nullable();
            $table->string('nit')->nullable();
            $table->string('fecha_retiro')->nullable();
            $table->string('tipoRetiro')->nullable();
        });
        Schema::create('sg_proform', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nit')->nullable();
            $table->integer('anio');
            $table->integer('mes');
        });
        $this->createRetiroCatalog();
        DB::table('clientes_potenciales')->insert([
            ['idclientes_potenciales' => 1, 'codigo' => 'SIN-PROFORMA', 'nit' => '100', 'fecha_retiro' => '2026-03-15', 'tipoRetiro' => '2'],
            ['idclientes_potenciales' => 2, 'codigo' => 'CON-HISTORIAL', 'nit' => '900', 'fecha_retiro' => '2026-08-20', 'tipoRetiro' => 'Cierre histórico'],
            ['idclientes_potenciales' => 3, 'codigo' => 'RETIRO-2025', 'nit' => '300', 'fecha_retiro' => '2025-12-31', 'tipoRetiro' => '2'],
            ['idclientes_potenciales' => 4, 'codigo' => 'FECHA-INVALIDA', 'nit' => '400', 'fecha_retiro' => 'sin-fecha', 'tipoRetiro' => '2'],
        ]);
        DB::table('sg_proform')->insert([
            ['nit' => '900', 'anio' => 2024, 'mes' => 1],
            ['nit' => '900', 'anio' => 2025, 'mes' => 6],
        ]);
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
