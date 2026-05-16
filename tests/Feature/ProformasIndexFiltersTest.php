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
            'filtro_nota' => null,
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
            'filtro_nota' => null,
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

        $response->assertRedirect(route('proformas.index', [
            'mes' => 4,
            'anio' => 2026,
        ]));
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

        $response->assertRedirect(route('proformas.index', [
            'estado' => 3,
        ]));
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
            'filtro_nota' => null,
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

    public function test_ignora_filtros_stale_en_sesion_cuando_entra_limpio_a_proformas(): void
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
            'filtro_nota' => null,
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

        $this->withSession([
            'proformas.numero' => 'PF-999',
            'proformas.empresa' => 'Empresa vieja',
            'proformas.estado' => 3,
            'proformas.envio' => '1',
            'proformas.filtro_nota' => 'con',
        ]);

        $response = $this->get(route('proformas.index'));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
        $this->assertTrue(session()->exists('proformas.numero'));
        $this->assertTrue(session()->exists('proformas.empresa'));
        $this->assertTrue(session()->exists('proformas.estado'));
        $this->assertTrue(session()->exists('proformas.envio'));
        $this->assertTrue(session()->exists('proformas.filtro_nota'));
        $this->assertNull(session('proformas.numero'));
        $this->assertNull(session('proformas.empresa'));
        $this->assertNull(session('proformas.estado'));
        $this->assertNull(session('proformas.envio'));
        $this->assertNull(session('proformas.filtro_nota'));
    }

    public function test_aplica_filtro_nota_cuando_llega_en_request(): void
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
            'filtro_nota' => 'con',
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

        $response = $this->get(route('proformas.index', ['mes' => '5', 'anio' => 2024, 'filtro_nota' => 'con']));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
        $response->assertSessionHas('proformas.filtro_nota', 'con');
    }

    public function test_volver_al_listado_reutiliza_filtros_originales_validos(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(789)
            ->andReturn((object) [
                'id' => 789,
                'estado' => 3,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);

        $this->withSession([
            'proformas.estado' => 3,
            'proformas.filtros_originales' => [
                'empresa' => 'Acme',
                'envio' => '0',
                'page' => 4,
                'from' => 'detalle',
            ],
        ]);

        $response = $this->get(route('proformas.back-to-index', ['id' => 789]));

        $response->assertRedirect(route('proformas.index', [
            'empresa' => 'Acme',
            'envio' => '0',
        ]));
    }
}
