<?php

namespace Tests\Feature;

use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ProformasIndexFiltersTest extends TestCase
{
    public function test_aplica_mes_y_anio_actuales_por_defecto_si_no_vienen_en_request(): void
    {
        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $expectedFilters = [
            'nro_prof' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => (int) now()->format('n'),
            'anio' => (int) now()->format('Y'),
            'estado' => null,
        ];

        $service->shouldReceive('normalizePeriodoFilters')
            ->once()
            ->with(null, null)
            ->andReturn([
                'mes' => $expectedFilters['mes'],
                'anio' => $expectedFilters['anio'],
            ]);

        $service->shouldReceive('paginateProformas')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);

        $response = $this->get(route('proformas.index'));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }

    public function test_respeta_mes_y_anio_seleccionados_por_el_usuario(): void
    {
        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $expectedFilters = [
            'nro_prof' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => 5,
            'anio' => 2024,
            'estado' => null,
        ];

        $service->shouldReceive('normalizePeriodoFilters')
            ->once()
            ->with('5', 2024)
            ->andReturn([
                'mes' => $expectedFilters['mes'],
                'anio' => $expectedFilters['anio'],
            ]);

        $service->shouldReceive('paginateProformas')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);

        $response = $this->get(route('proformas.index', ['mes' => '5', 'anio' => 2024]));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }
}
