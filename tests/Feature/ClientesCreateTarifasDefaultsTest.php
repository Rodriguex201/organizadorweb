<?php

namespace Tests\Feature;

use App\Services\TarifaConfigService;
use Mockery;
use Tests\TestCase;

class ClientesCreateTarifasDefaultsTest extends TestCase
{
    public function test_carga_tarifas_configuradas_como_defaults_en_el_formulario_de_creacion(): void
    {
        $defaults = [
            'vlrprincipal' => '150000',
            'numequipos' => '3',
            'vlracuse' => '9000',
        ];

        $service = Mockery::mock(TarifaConfigService::class);
        $service->shouldReceive('clientCreateDefaults')
            ->once()
            ->andReturn($defaults);

        $this->app->instance(TarifaConfigService::class, $service);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('clientes.create'));

        $response->assertOk();
        $response->assertViewHas('tarifasDefaults', $defaults);
        $response->assertSee('Restaurar tarifas predeterminadas');
        $response->assertSee('data-default-value="150000"', false);
        $response->assertSee('data-default-value="3"', false);
        $response->assertSee('data-default-value="9000"', false);
    }
}
