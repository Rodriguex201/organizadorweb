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
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $expectedFilters = [
            'nro_prof' => null,
            'codigo' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => (int) now()->format('n'),
            'anio' => (int) now()->format('Y'),
            'estado' => null,
            'envio' => null,
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
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $expectedFilters = [
            'nro_prof' => null,
            'codigo' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => 5,
            'anio' => 2024,
            'estado' => null,
            'envio' => null,
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

    public function test_volver_al_listado_limpia_solo_filtro_estado_si_el_estado_ya_no_coincide(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(123)
            ->andReturn((object) [
                'id' => 123,
                'estado' => 4,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);

        $this->withSession([
            'proformas.estado' => 3,
            'proformas.mes' => 4,
            'proformas.anio' => 2026,
        ]);

        $response = $this->get(route('proformas.back-to-index', ['id' => 123]));

        $response->assertRedirect(route('proformas.index'));
        $response->assertSessionHas('warning');
        $response->assertSessionMissing('proformas.estado');
        $response->assertSessionHas('proformas.mes', 4);
        $response->assertSessionHas('proformas.anio', 2026);
    }

    public function test_volver_al_listado_conserva_filtro_estado_si_sigue_coincidiendo(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(456)
            ->andReturn((object) [
                'id' => 456,
                'estado' => 3,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);

        $this->withSession([
            'proformas.estado' => 3,
        ]);

        $response = $this->get(route('proformas.back-to-index', ['id' => 456]));

        $response->assertRedirect(route('proformas.index'));
        $response->assertSessionMissing('warning');
        $response->assertSessionHas('proformas.estado', 3);
    }

    public function test_aplica_filtro_envio_cuando_llega_en_request(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $expectedFilters = [
            'nro_prof' => null,
            'codigo' => null,
            'nit' => null,
            'empresa' => null,
            'emisora' => null,
            'mes' => 5,
            'anio' => 2024,
            'estado' => null,
            'envio' => '0',
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

        $response = $this->get(route('proformas.index', ['mes' => '5', 'anio' => 2024, 'envio' => '0']));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
        $response->assertSessionHas('proformas.envio', '0');
    }
}
