<?php

namespace Tests\Feature;

use App\Services\CobrosService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class CobrosIndexFiltersTest extends TestCase
{
    public function test_aplica_mes_y_anio_actuales_por_defecto_si_no_vienen_en_request(): void
    {
        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $expectedFilters = [
            'mes' => 'mayo',
            'anio' => 2026,
            'proforma' => null,
            'buscar' => null,
            'orden_fecha' => null,
            'grupo_fecha' => null,
            'filtro_nota' => null,
            'filtro_envio' => null,
        ];

        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index'));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }

    public function test_respeta_mes_y_anio_seleccionados_por_el_usuario(): void
    {
        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $expectedFilters = [
            'mes' => '5',
            'anio' => 2024,
            'proforma' => null,
            'buscar' => null,
            'orden_fecha' => null,
            'grupo_fecha' => null,
            'filtro_nota' => null,
            'filtro_envio' => null,
        ];

        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index', ['mes' => '5', 'anio' => 2024]));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }
}
