<?php

namespace Tests\Feature;

use App\Services\TarifaConfigService;
use Mockery;
use Tests\TestCase;

class ConfiguracionTarifaControllerTest extends TestCase
{
    public function test_guarda_el_arreglo_tarifas_con_notacion_de_corchetes(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(TarifaConfigService::class);
        $service->shouldReceive('updateMany')
            ->once()
            ->with([
                'vlrprincipal' => ['valor' => '1500.50', 'activo' => '1'],
                'numequipos' => ['valor' => '2', 'activo' => '0'],
            ]);

        $this->app->instance(TarifaConfigService::class, $service);

        $response = $this->withSession(['rol_nombre' => 'admin'])
            ->put(route('configuracion.tarifas.update'), [
                'tarifas' => [
                    'vlrprincipal' => ['valor' => '1500.50', 'activo' => '1'],
                    'numequipos' => ['valor' => '2', 'activo' => '0'],
                ],
            ]);

        $response->assertRedirect(route('configuracion.tarifas.index'));
        $response->assertSessionHas('status', 'Tarifas guardadas correctamente.');
    }
}
