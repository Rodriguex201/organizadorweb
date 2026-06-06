<?php

namespace Tests\Feature;

use App\Services\ClienteRetiradoService;
use App\Services\CobroExtraordinarioService;
use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformasService;
use App\Services\ProformaStoreService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobrosPendingBatchCleanupAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_bloquea_limpieza_directa_a_usuario_operativo_en_produccion(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $this->bindCobrosControllerDependencies();

        $response = $this->withSession([
            'idusuario' => 2,
            'rol_nombre' => 'user',
            'cobros.proformas_listas_para_envio' => [
                'grupo' => 7,
                'proformas' => [['id' => 1]],
            ],
        ])->post(route('cobros.lote-pendiente.limpiar'));

        $response->assertForbidden();
    }

    public function test_permite_limpieza_directa_a_administrador_en_produccion(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $this->bindCobrosControllerDependencies();

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
            'cobros.proformas_listas_para_envio' => [
                'grupo' => 7,
                'proformas' => [['id' => 1]],
            ],
        ])->post(route('cobros.lote-pendiente.limpiar'));

        $response->assertRedirect(route('cobros.index'));
        $response->assertSessionHas('status', 'Lote pendiente de envio limpiado correctamente.');
        $response->assertSessionMissing('cobros.proformas_listas_para_envio');
    }

    private function bindCobrosControllerDependencies(): void
    {
        $this->app->instance(CobrosService::class, Mockery::mock(CobrosService::class));
        $this->app->instance(CobroExtraordinarioService::class, Mockery::mock(CobroExtraordinarioService::class));
        $this->app->instance(ClienteRetiradoService::class, Mockery::mock(ClienteRetiradoService::class));
        $this->app->instance(ProformaPreviewService::class, Mockery::mock(ProformaPreviewService::class));
        $this->app->instance(ProformaStoreService::class, Mockery::mock(ProformaStoreService::class));
        $this->app->instance(ProformaPdfService::class, Mockery::mock(ProformaPdfService::class));
        $this->app->instance(ProformasService::class, Mockery::mock(ProformasService::class));
        $this->app->instance(ProformaEmailService::class, Mockery::mock(ProformaEmailService::class));
        $this->app->instance(RevisarProformaCalculator::class, Mockery::mock(RevisarProformaCalculator::class));
    }
}
