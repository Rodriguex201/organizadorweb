<?php

namespace Tests\Feature;

use App\Http\Controllers\EstadoCuentaProformasController;
use App\Services\EstadoCuentaProformasService;
use App\Services\ProformaEmailService;
use App\Services\ProformasService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EstadoCuentaProformasControllerTest extends TestCase
{
    public function test_index_muestra_filtros_por_defecto_y_advertencia(): void
    {
        $estadoCuentaService = Mockery::mock(EstadoCuentaProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $proformasService = Mockery::mock(ProformasService::class);

        $defaultFilters = [
            'busqueda' => null,
            'nit' => null,
            'estado' => 'default',
        ];

        $estadoCuentaService->shouldReceive('defaultFilters')->once()->andReturn($defaultFilters);
        $estadoCuentaService->shouldReceive('hasSearchCriteria')->once()->with($defaultFilters)->andReturn(false);
        $estadoCuentaService->shouldReceive('searchProformas')->once()->with([], 15)->andReturn(new LengthAwarePaginator([], 0, 15));
        $estadoCuentaService->shouldReceive('resolveDefaultDestinatarios')->once()->with(null)->andReturn('');
        $estadoCuentaService->shouldReceive('estadoFilterOptions')->once()->andReturn([
            'default' => 'Generadas, Enviadas y Facturadas',
            '4' => 'Pagada',
        ]);

        $this->app->instance(EstadoCuentaProformasService::class, $estadoCuentaService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformasService::class, $proformasService);

        /** @var EstadoCuentaProformasController $controller */
        $controller = $this->app->make(EstadoCuentaProformasController::class);
        $request = Request::create(route('proformas.estado-cuenta.index'), 'GET');
        $response = $controller->index($request);

        $this->assertSame('proformas.estado-cuenta', $response->name());
        $this->assertSame($defaultFilters, $response->getData()['filters']);
        $this->assertFalse($response->getData()['hasSearched']);
        $this->assertSame('Documento informativo generado a partir de proformas existentes. No modifica estados ni genera facturación.', $response->getData()['warningMessage']);
    }

    public function test_pdf_depura_duplicados_y_genera_con_la_proforma_vigente(): void
    {
        $this->withoutMiddleware();

        $estadoCuentaService = Mockery::mock(EstadoCuentaProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $proformasService = Mockery::mock(ProformasService::class);

        $seleccion = collect([(object) ['id' => 10], (object) ['id' => 11]]);
        $depurada = collect([(object) ['id' => 11, 'nro_prof' => '0004133']]);
        $payload = [
            'empresa' => 'WILSON FERNANDO CASAS RIVERA',
            'nit' => '4514343',
            'cantidad_proformas' => 1,
            'total_acumulado' => 175460.0,
            'periodo_antiguo' => 'Enero 2026',
            'periodo_reciente' => 'Enero 2026',
            'fecha_generacion' => now(),
            'proformas' => [
                [
                    'nro_prof' => '0004133',
                    'periodo' => 'Enero 2026',
                    'estado' => 'Generada',
                    'valor' => 175460.0,
                    'emisora' => 'SAS',
                ],
            ],
        ];
        $pdfWrapper = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfWrapper->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdfWrapper->shouldReceive('output')->once()->andReturn('pdf-depurado');
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('proformas.estado-cuenta-pdf', Mockery::type('array'))
            ->andReturn($pdfWrapper);

        $estadoCuentaService->shouldReceive('findSelectedProformas')->once()->with([10, 11])->andReturn($seleccion);
        $estadoCuentaService->shouldReceive('depurateSelection')->once()->with($seleccion)->andReturn([
            'ok' => true,
            'message' => null,
            'proformas' => $depurada,
            'warnings' => [[
                'periodo' => 'Enero 2026',
                'emisora' => 'SAS',
            ]],
            'excluded_count' => 1,
        ]);
        $estadoCuentaService->shouldReceive('buildEstadoCuentaPayload')->once()->with($depurada)->andReturn($payload);
        $estadoCuentaService->shouldReceive('browserFilename')->once()->with($payload)->andReturn('ESTADO_CUENTA_CONSOLIDADO_WILSON_4514343.pdf');

        $this->app->instance(EstadoCuentaProformasService::class, $estadoCuentaService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformasService::class, $proformasService);

        $response = $this
            ->post(route('proformas.estado-cuenta.pdf'), [
                'accion' => 'pdf',
                'proformas' => [10, 11],
            ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_generar_y_enviar_reutiliza_servicio_de_correo_con_pdf_temporal(): void
    {
        $this->withoutMiddleware();

        $estadoCuentaService = Mockery::mock(EstadoCuentaProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $proformasService = Mockery::mock(ProformasService::class);

        $seleccion = new Collection([
            (object) [
                'id' => 25,
                'nit' => '900123456',
                'nro_prof' => 'PF-25',
            ],
        ]);

        $payload = [
            'empresa' => 'ACME SAS',
            'nit' => '900123456',
            'cantidad_proformas' => 1,
            'total_acumulado' => 120000.0,
            'periodo_antiguo' => 'Enero 2026',
            'periodo_reciente' => 'Enero 2026',
            'fecha_generacion' => now(),
            'proformas' => [
                [
                    'nro_prof' => 'PF-25',
                    'periodo' => 'Enero 2026',
                    'estado' => 'Generada',
                    'valor' => 120000.0,
                    'emisora' => 'SAS',
                ],
            ],
        ];

        $destinatarios = [
            'original' => 'cartera@acme.com;tesoreria@acme.com',
            'emails' => ['cartera@acme.com', 'tesoreria@acme.com'],
            'count' => 2,
            'invalidos' => [],
        ];

        $pdfWrapper = Mockery::mock(DomPdfWrapper::class);
        $pdfWrapper->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        $pdfWrapper->shouldReceive('output')->once()->andReturn('pdf-binario-temporal');
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('proformas.estado-cuenta-pdf', Mockery::type('array'))
            ->andReturn($pdfWrapper);

        $estadoCuentaService->shouldReceive('findSelectedProformas')->once()->with([25])->andReturn($seleccion);
        $estadoCuentaService->shouldReceive('depurateSelection')->once()->with($seleccion)->andReturn([
            'ok' => true,
            'message' => null,
            'proformas' => $seleccion,
            'warnings' => [],
            'excluded_count' => 0,
        ]);
        $estadoCuentaService->shouldReceive('buildEstadoCuentaPayload')->once()->with($seleccion)->andReturn($payload);
        $estadoCuentaService->shouldReceive('browserFilename')->once()->with($payload)->andReturn('ESTADO_CUENTA_CONSOLIDADO_ACME_SAS_900123456.pdf');

        $emailService->shouldReceive('resolveDestinatariosFromRaw')
            ->once()
            ->with('cartera@acme.com;tesoreria@acme.com', '[ENVIO ESTADO CUENTA]')
            ->andReturn($destinatarios);
        $emailService->shouldReceive('sendDocument')
            ->once()
            ->with(
                Mockery::on(function (array $documento): bool {
                    return ($documento['filename'] ?? null) === 'ESTADO_CUENTA_CONSOLIDADO_ACME_SAS_900123456.pdf'
                        && ($documento['contents'] ?? null) === 'pdf-binario-temporal'
                        && ($documento['contexto'] ?? null) === 'estado_cuenta_consolidado';
                }),
                Mockery::on(function (array $options) use ($destinatarios): bool {
                    return ($options['destinatarios'] ?? null) === $destinatarios
                        && ($options['log_prefix'] ?? null) === '[ENVIO ESTADO CUENTA]';
                })
            );

        $this->app->instance(EstadoCuentaProformasService::class, $estadoCuentaService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformasService::class, $proformasService);

        $response = $this->from(route('proformas.estado-cuenta.index'))
            ->post(route('proformas.estado-cuenta.pdf'), [
                'accion' => 'enviar',
                'proformas' => [25],
                'destinatarios' => 'cartera@acme.com;tesoreria@acme.com',
            ]);

        $response->assertRedirect(route('proformas.estado-cuenta.index'));
        $response->assertSessionHas('status', 'Estado de cuenta enviado por correo correctamente.');
    }
}
