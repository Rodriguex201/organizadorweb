<?php

namespace Tests\Feature;

use App\Services\CobrosService;
use App\Services\ClienteRetiradoService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\ProformasService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobrosRegenerateProformaTest extends TestCase
{
    public function test_regenera_proforma_y_redirige_al_detalle(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);

        $cobro = (object) ['id_cobro' => 77];

        $cobrosService->shouldReceive('findCobroById')
            ->twice()
            ->with(77)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(false);

        $storeService->shouldReceive('regenerateFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn([
                'proforma_id' => 901,
                'duplicated' => true,
            ]);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(901, true)
            ->andReturn([
                'absolute_path' => 'C:\\tmp\\proforma.pdf',
                'filename' => 'proforma.pdf',
            ]);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.regenerar', ['id' => 77]), [
            'redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cobros.show', 77));
        $response->assertSessionHas('status_type', 'success');
        $response->assertSessionHas('status');
    }

    public function test_regenera_proforma_y_redirige_a_revision(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);

        $cobro = (object) ['id_cobro' => 88];

        $cobrosService->shouldReceive('findCobroById')
            ->twice()
            ->with(88)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(false);

        $storeService->shouldReceive('regenerateFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn([
                'proforma_id' => 345,
                'duplicated' => true,
            ]);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(345, true)
            ->andReturn([
                'absolute_path' => 'C:\\tmp\\proforma.pdf',
                'filename' => 'proforma.pdf',
            ]);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.regenerar', ['id' => 88]), [
            'redirect_to' => 'revisar',
        ]);

        $response->assertRedirect(route('cobros.revisar', 88));
        $response->assertSessionHas('status_type', 'success');
        $response->assertSessionHas('status');
    }

    public function test_regeneracion_bloqueada_por_id_cobro_invalido_muestra_advertencia(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);

        $cobro = (object) ['id_cobro' => 99];

        $cobrosService->shouldReceive('findCobroById')
            ->twice()
            ->with(99)
            ->andReturn($cobro);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cobro)
            ->andReturn(false);

        $storeService->shouldReceive('regenerateFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn([
                'proforma_id' => null,
                'duplicated' => false,
                'blocked' => true,
                'message' => 'No se puede generar la proforma porque el cobro no tiene un id_cobro valido.',
            ]);

        $pdfService->shouldNotReceive('generateForProformaId');

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proforma.regenerar', ['id' => 99]), [
            'redirect_to' => 'show',
        ]);

        $response->assertRedirect(route('cobros.show', 99));
        $response->assertSessionHas('status_type', 'warning');
        $response->assertSessionHas('status', 'No se puede generar la proforma porque el cobro no tiene un id_cobro valido.');
    }
}
