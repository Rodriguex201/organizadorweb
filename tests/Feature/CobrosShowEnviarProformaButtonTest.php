<?php

namespace Tests\Feature;

use App\Http\Controllers\CobrosController;
use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\ProformasService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobrosShowEnviarProformaButtonTest extends TestCase
{
    public function test_show_entrega_proforma_no_enviada_para_mostrar_boton_enviar(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $cobro = (object) [
            'id_cobro' => 77,
            'mes' => 'mayo',
            'año' => 2026,
            'Proforma' => 1,
        ];

        $proforma = (object) [
            'id' => 901,
            'enviado' => 0,
            'estado' => ProformasService::ESTADO_GENERADA,
            'nro_prof' => 'PF-901',
            'rpdf' => 'proformas',
            'npdf' => 'pf-901.pdf',
        ];

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(77)
            ->andReturn($cobro);

        $storeService->shouldReceive('findExistingProformaIdFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn(901);

        $proformasService->shouldReceive('findProformaById')
            ->once()
            ->with(901)
            ->andReturn($proforma);

        $proformasService->shouldReceive('canSendProforma')
            ->once()
            ->with($proforma)
            ->andReturn(true);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $view = $this->app->make(CobrosController::class)->show(77);
        $data = $view->getData();

        $this->assertSame(901, $data['proformaPersistidaId']);
        $this->assertSame($proforma, $data['proformaPersistida']);
        $this->assertTrue($data['canSendPersistedProforma']);
        $this->assertSame(0, (int) ($data['proformaPersistida']->enviado ?? 0));
    }

    public function test_show_entrega_proforma_enviada_para_mostrar_boton_reenviar(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $cobro = (object) [
            'id_cobro' => 78,
            'mes' => 'mayo',
            'año' => 2026,
            'Proforma' => 1,
        ];

        $proforma = (object) [
            'id' => 902,
            'enviado' => 1,
            'estado' => ProformasService::ESTADO_FACTURADA,
            'nro_prof' => 'PF-902',
            'rpdf' => 'proformas',
            'npdf' => 'pf-902.pdf',
        ];

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(78)
            ->andReturn($cobro);

        $storeService->shouldReceive('findExistingProformaIdFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn(902);

        $proformasService->shouldReceive('findProformaById')
            ->once()
            ->with(902)
            ->andReturn($proforma);

        $proformasService->shouldReceive('canSendProforma')
            ->once()
            ->with($proforma)
            ->andReturn(true);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $view = $this->app->make(CobrosController::class)->show(78);
        $data = $view->getData();

        $this->assertSame(902, $data['proformaPersistidaId']);
        $this->assertSame($proforma, $data['proformaPersistida']);
        $this->assertTrue($data['canSendPersistedProforma']);
        $this->assertSame(1, (int) ($data['proformaPersistida']->enviado ?? 0));
    }
}
