<?php

namespace Tests\Feature;

use App\Services\ClienteRetiradoService;
use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\ProformasService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobrosStoreProformaTest extends TestCase
{
    public function test_generacion_bloqueada_por_id_cobro_invalido_muestra_advertencia(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $cobro = (object) ['id_cobro' => 120];

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(120)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(false);

        $storeService->shouldReceive('storeFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn([
                'proforma_id' => null,
                'duplicated' => false,
                'blocked' => true,
                'message' => 'No se puede generar la proforma porque el cobro no tiene un id_cobro valido.',
            ]);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.store', ['id' => 120]));

        $response->assertRedirect(route('cobros.proforma.preview', 120));
        $response->assertSessionHas('status_type', 'warning');
        $response->assertSessionHas('status', 'No se puede generar la proforma porque el cobro no tiene un id_cobro valido.');
    }
}
