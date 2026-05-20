<?php

namespace Tests\Feature;

use App\Http\Controllers\ProformaCarteraController;
use App\Services\ProformaCarteraExportService;
use App\Services\ProformaCarteraService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;
use Illuminate\View\View;

class ProformasCarteraControllerTest extends TestCase
{
    public function test_cartera_renderiza_vista_con_filtros_y_resumen(): void
    {
        $service = Mockery::mock(ProformaCarteraService::class);
        $exportService = Mockery::mock(ProformaCarteraExportService::class);
        $filters = [
            'codigo' => 'RM',
            'empresa' => 'Soft',
            'nit' => '900',
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-05-31',
            'estado' => 2,
            'solo_acumuladas' => true,
        ];
        $paginator = new LengthAwarePaginator(
            new Collection(),
            0,
            15,
            1,
            ['path' => route('proformas.cartera.index')]
        );

        $service->shouldReceive('resolveFilters')
            ->once()
            ->andReturn($filters);
        $service->shouldReceive('paginateCartera')
            ->once()
            ->with($filters)
            ->andReturn($paginator);
        $service->shouldReceive('getSummary')
            ->once()
            ->with($filters)
            ->andReturn([
                'empresas_con_deuda' => 3,
                'total_cartera' => 1250000,
                'promedio_deuda' => 416666.67,
                'cantidad_proformas_pendientes' => 7,
            ]);

        $request = Request::create(route('proformas.cartera.index', $filters), 'GET');
        $controller = new ProformaCarteraController($service, $exportService);

        $view = $controller->index($request);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('proformas.cartera', $view->name());
        $this->assertSame($filters, $view->getData()['filters']);
        $this->assertSame($paginator, $view->getData()['cartera']);
        $this->assertSame(3, $view->getData()['summary']['empresas_con_deuda']);
    }

    public function test_export_cartera_resuelve_filtros_y_descarga_excel(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformaCarteraService::class);
        $exportService = Mockery::mock(ProformaCarteraExportService::class);
        $validatedPayload = [
            'codigo' => 'RM',
            'estado' => 3,
            'solo_acumuladas' => '1',
        ];
        $resolvedFilters = [
            'codigo' => 'RM',
            'empresa' => '',
            'nit' => '',
            'fecha_desde' => null,
            'fecha_hasta' => null,
            'estado' => 3,
            'solo_acumuladas' => true,
        ];

        $service->shouldReceive('resolveFilters')
            ->once()
            ->with($validatedPayload)
            ->andReturn($resolvedFilters);
        $exportService->shouldReceive('download')
            ->once()
            ->with($resolvedFilters)
            ->andReturn(response()->download(__FILE__, 'cartera.xlsx'));

        $this->app->instance(ProformaCarteraService::class, $service);
        $this->app->instance(ProformaCarteraExportService::class, $exportService);

        $response = $this->post(route('proformas.cartera.export'), $validatedPayload);

        $response->assertOk();
        $response->assertDownload('cartera.xlsx');
    }
}
