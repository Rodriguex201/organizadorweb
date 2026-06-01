<?php

namespace Tests\Feature;

use App\Services\ClienteRetiradoService;
use App\Services\CobroExtraordinarioService;
use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\ProformasService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobrosRetiradosBlockTest extends TestCase
{
    public function test_bloquea_generacion_individual_de_proforma_para_cliente_retirado(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $extraordinarioService = Mockery::mock(CobroExtraordinarioService::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $cobro = (object) ['id_cobro' => 77];

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(77)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(true);

        $storeService->shouldNotReceive('storeFromCobro');

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(CobroExtraordinarioService::class, $extraordinarioService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.store', ['id' => 77]));

        $response->assertRedirect(route('cobros.proforma.preview', 77));
        $response->assertSessionHas('status', 'No es posible generar proformas para clientes retirados.');
        $response->assertSessionHas('status_type', 'warning');
    }

    public function test_bloquea_regeneracion_de_proforma_para_cliente_retirado(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $extraordinarioService = Mockery::mock(CobroExtraordinarioService::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $cobro = (object) ['id_cobro' => 88];

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(88)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(true);

        $storeService->shouldNotReceive('regenerateFromCobro');
        $pdfService->shouldNotReceive('generateForProformaId');

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(CobroExtraordinarioService::class, $extraordinarioService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.regenerar', ['id' => 88]), [
            'redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cobros.show', 88));
        $response->assertSessionHas('status', 'No es posible regenerar proformas para clientes retirados.');
        $response->assertSessionHas('status_type', 'warning');
    }
}
