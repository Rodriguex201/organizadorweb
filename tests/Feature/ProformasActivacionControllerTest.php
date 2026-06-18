<?php

namespace Tests\Feature;

use App\Services\EmpresaActivacionService;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Mockery;
use Tests\TestCase;

class ProformasActivacionControllerTest extends TestCase
{
    public function test_consulta_activacion_devuelve_datos_detectados(): void
    {
        $this->withoutMiddleware();
        $this->withSession([
            'rol_id' => 1,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(31)
            ->andReturn((object) [
                'id' => 31,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('obtenerDetalle')
            ->once()
            ->with('B476')
            ->andReturn([
                'codigo' => 'B476',
                'conexion' => 'mysql_213',
                'base' => 'b476',
                'servidor' => 'Servidor 213',
                'servidor_badge' => '213',
                'fecha_inicio_actual' => '2026-05-01',
                'fecha_fin_actual' => '2026-05-31',
                'fecha_inicio_global_actual' => '2026-05-01',
                'fecha_fin_global_actual' => '2026-05-31',
                'sincronizado' => true,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->getJson(route('proformas.activacion.show', ['id' => 31]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'codigo' => 'B476',
                    'conexion' => 'mysql_213',
                    'base' => 'b476',
                    'servidor' => 'Servidor 213',
                    'fecha_inicio_actual' => '2026-05-01',
                    'fecha_fin_actual' => '2026-05-31',
                    'sincronizado' => true,
                ],
            ]);
    }

    public function test_consulta_activacion_responde_error_amigable_si_no_hay_empresa(): void
    {
        $this->withoutMiddleware();
        $this->withSession([
            'rol_id' => 1,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(32)
            ->andReturn((object) [
                'id' => 32,
                'codigo' => 'B999',
            ]);

        $activacionService->shouldReceive('obtenerDetalle')
            ->once()
            ->with('B999')
            ->andThrow(new \RuntimeException('No fue posible encontrar la empresa en los servidores configurados.'));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->getJson(route('proformas.activacion.show', ['id' => 32]));

        $response->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'message' => 'No fue posible encontrar la empresa en los servidores configurados.',
            ]);
    }

    public function test_guardar_activacion_actualiza_y_devuelve_datos_sincronizados(): void
    {
        $this->withoutMiddleware();

        $this->withSession([
            'rol_id' => 1,
            'usuario' => 'admin',
            'idusuario' => 99,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(33)
            ->andReturn((object) [
                'id' => 33,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('guardarActivacion')
            ->once()
            ->with('B476', '2026-06-01', '2026-06-30', 'admin (99)')
            ->andReturn([
                'codigo' => 'B476',
                'conexion' => 'mysql_167',
                'base' => 'b476',
                'servidor' => 'Servidor 167',
                'servidor_badge' => '167',
                'fecha_inicio_actual' => '2026-06-01',
                'fecha_fin_actual' => '2026-06-30',
                'fecha_inicio_global_actual' => '2026-06-01',
                'fecha_fin_global_actual' => '2026-06-30',
                'sincronizado' => true,
                'eventos_licencia' => [
                    'existe' => true,
                    'empresa' => 'B476',
                    'fecha_vencimiento_actual' => '2026-05-31',
                    'consulta_estado' => 'found',
                    'mensaje' => 'Licencia de Eventos encontrada.',
                ],
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->postJson(route('proformas.activacion.update', ['id' => 33]), [
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'La activación de la empresa se actualizó correctamente.',
                'data' => [
                    'codigo' => 'B476',
                    'conexion' => 'mysql_167',
                    'servidor' => 'Servidor 167',
                    'fecha_inicio_actual' => '2026-06-01',
                    'fecha_fin_actual' => '2026-06-30',
                    'sincronizado' => true,
                    'eventos_licencia' => [
                        'existe' => true,
                        'empresa' => 'B476',
                        'fecha_vencimiento_actual' => '2026-05-31',
                        'consulta_estado' => 'found',
                        'mensaje' => 'Licencia de Eventos encontrada.',
                    ],
                ],
            ]);
    }

    public function test_actualizar_licencia_eventos_devuelve_resumen_de_cambio(): void
    {
        $this->withoutMiddleware();

        $this->withSession([
            'rol_id' => 1,
            'usuario' => 'admin',
            'idusuario' => 99,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(34)
            ->andReturn((object) [
                'id' => 34,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('actualizarLicenciaEventos')
            ->once()
            ->with('B476', '2026-06-30', 'admin (99)')
            ->andReturn([
                'empresa' => 'B476',
                'fecha_vencimiento_anterior' => '2026-05-31',
                'fecha_vencimiento_nueva' => '2026-06-30',
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->postJson(route('proformas.activacion.eventos.update', ['id' => 34]), [
            'fecha_fin' => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'La licencia de Eventos se actualizó correctamente.',
                'data' => [
                    'empresa' => 'B476',
                    'fecha_vencimiento_anterior' => '2026-05-31',
                    'fecha_vencimiento_nueva' => '2026-06-30',
                ],
            ]);
    }
}
