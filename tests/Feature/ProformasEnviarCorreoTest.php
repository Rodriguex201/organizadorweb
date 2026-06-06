<?php

namespace Tests\Feature;

use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Mockery;
use Tests\TestCase;

class ProformasEnviarCorreoTest extends TestCase
{
    public function test_envio_manual_no_enviado_usa_mismo_servicio_y_devuelve_estado_actualizado(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $proformaInicial = (object) [
            'id' => 10,
            'enviado' => 0,
            'estado' => ProformasService::ESTADO_GENERADA,
            'nro_prof' => 'PF-10',
            'rpdf' => 'proformas',
            'npdf' => 'pf-10.pdf',
        ];

        $proformaActualizada = (object) [
            'id' => 10,
            'enviado' => 1,
            'estado' => ProformasService::ESTADO_ENVIADA,
            'fecha_envio' => '2026-05-20 15:30:00',
            'intentos_envio' => 2,
        ];

        $destinatarios = [
            'original' => 'correo1@gmail.com,correo2@gmail.com',
            'emails' => ['correo1@gmail.com', 'correo2@gmail.com'],
            'count' => 2,
            'invalidos' => [],
        ];

        $service->shouldReceive('findProformaById')
            ->twice()
            ->with(10)
            ->andReturn($proformaInicial, $proformaActualizada);
        $service->shouldReceive('canSendProforma')->once()->with($proformaInicial)->andReturn(true);
        $service->shouldReceive('registrarEnvioExitoso')->once()->with(10);

        $emailService->shouldReceive('resolveDestinatarios')
            ->once()
            ->with($proformaInicial, '[ENVIO MANUAL PROFORMA]')
            ->andReturn($destinatarios);
        $emailService->shouldReceive('sendProforma')
            ->once()
            ->with($proformaInicial, Mockery::on(function (array $options) use ($destinatarios): bool {
                return ($options['log_prefix'] ?? null) === '[ENVIO MANUAL PROFORMA]'
                    && ($options['destinatarios'] ?? null) === $destinatarios;
            }));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->postJson(route('proformas.enviar', ['id' => 10]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'proforma' => [
                    'id' => 10,
                    'enviado' => 1,
                    'estado' => ProformasService::ESTADO_ENVIADA,
                    'fecha_envio' => '2026-05-20 15:30:00',
                    'intentos_envio' => 2,
                ],
            ]);
    }

    public function test_reenvio_usa_prefijo_reenvio_y_preserva_estado_superior(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $proformaInicial = (object) [
            'id' => 11,
            'enviado' => 1,
            'estado' => ProformasService::ESTADO_FACTURADA,
            'nro_prof' => 'PF-11',
            'rpdf' => 'proformas',
            'npdf' => 'pf-11.pdf',
        ];

        $proformaActualizada = (object) [
            'id' => 11,
            'enviado' => 1,
            'estado' => ProformasService::ESTADO_FACTURADA,
            'fecha_envio' => '2026-05-20 16:45:00',
            'intentos_envio' => 5,
        ];

        $destinatarios = [
            'original' => 'correo1@gmail.com, correo1@gmail.com',
            'emails' => ['correo1@gmail.com'],
            'count' => 1,
            'invalidos' => [],
        ];

        $service->shouldReceive('findProformaById')
            ->twice()
            ->with(11)
            ->andReturn($proformaInicial, $proformaActualizada);
        $service->shouldReceive('canSendProforma')->once()->with($proformaInicial)->andReturn(true);
        $service->shouldReceive('registrarEnvioExitoso')->once()->with(11);

        $emailService->shouldReceive('resolveDestinatarios')
            ->once()
            ->with($proformaInicial, '[REENVIO PROFORMA]')
            ->andReturn($destinatarios);
        $emailService->shouldReceive('sendProforma')
            ->once()
            ->with($proformaInicial, Mockery::on(function (array $options) use ($destinatarios): bool {
                return ($options['log_prefix'] ?? null) === '[REENVIO PROFORMA]'
                    && ($options['destinatarios'] ?? null) === $destinatarios;
            }));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->postJson(route('proformas.enviar', ['id' => 11]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'proforma' => [
                    'id' => 11,
                    'enviado' => 1,
                    'estado' => ProformasService::ESTADO_FACTURADA,
                    'fecha_envio' => '2026-05-20 16:45:00',
                    'intentos_envio' => 5,
                ],
            ]);
    }

    public function test_si_todos_los_destinatarios_son_invalidos_registra_intento_fallido_y_responde_error(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $proformaInicial = (object) [
            'id' => 12,
            'enviado' => 0,
            'estado' => ProformasService::ESTADO_GENERADA,
            'nro_prof' => 'PF-12',
            'rpdf' => 'proformas',
            'npdf' => 'pf-12.pdf',
        ];

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(12)
            ->andReturn($proformaInicial);
        $service->shouldReceive('canSendProforma')->once()->with($proformaInicial)->andReturn(true);
        $service->shouldReceive('registrarIntentoFallido')->once()->with(12);

        $emailService->shouldReceive('resolveDestinatarios')
            ->once()
            ->with($proformaInicial, '[ENVIO MANUAL PROFORMA]')
            ->andThrow(new \RuntimeException('El cliente no tiene correos validos registrados en clientes_potenciales.email. Motivo: todos los destinatarios fueron descartados tras la validacion.'));
        $emailService->shouldNotReceive('sendProforma');

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->postJson(route('proformas.enviar', ['id' => 12]));

        $response->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'message' => 'No se pudo enviar el correo: El cliente no tiene correos validos registrados en clientes_potenciales.email. Motivo: todos los destinatarios fueron descartados tras la validacion.',
            ]);
    }
}
