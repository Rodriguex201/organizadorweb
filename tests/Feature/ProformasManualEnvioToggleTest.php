<?php

namespace Tests\Feature;

use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Mockery;
use Tests\TestCase;

class ProformasManualEnvioToggleTest extends TestCase
{
    public function test_marcar_enviada_responde_json_y_no_toca_estado(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $service->shouldReceive('marcarEnvioManual')
            ->once()
            ->with(15)
            ->andReturn([
                'ok' => true,
                'message' => 'Proforma marcada como enviada manualmente.',
                'proforma' => [
                    'id' => 15,
                    'enviado' => 1,
                    'fecha_envio' => '2026-05-20 14:00:00',
                    'estado' => 2,
                ],
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->postJson(route('proformas.marcar-enviada', ['id' => 15]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'proforma' => [
                    'id' => 15,
                    'enviado' => 1,
                    'estado' => 2,
                ],
            ]);
    }

    public function test_marcar_no_enviada_responde_json_y_limpia_fecha_envio(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $service->shouldReceive('marcarNoEnviada')
            ->once()
            ->with(22)
            ->andReturn([
                'ok' => true,
                'message' => 'Proforma marcada como no enviada.',
                'proforma' => [
                    'id' => 22,
                    'enviado' => 0,
                    'fecha_envio' => null,
                    'estado' => 4,
                ],
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->postJson(route('proformas.marcar-no-enviada', ['id' => 22]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'proforma' => [
                    'id' => 22,
                    'enviado' => 0,
                    'fecha_envio' => null,
                    'estado' => 4,
                ],
            ]);
    }
}
