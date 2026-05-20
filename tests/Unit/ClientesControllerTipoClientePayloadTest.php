<?php

namespace Tests\Unit;

use App\Http\Controllers\ClientesController;
use App\Services\ClienteValorTotalCalculator;
use App\Services\TarifaConfigService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ClientesControllerTipoClientePayloadTest extends TestCase
{
    #[Test]
    public function construye_tipo_cliente_como_entero_cuando_la_columna_es_int(): void
    {
        $controller = new ClientesController(
            app(ClienteValorTotalCalculator::class),
            app(TarifaConfigService::class),
        );

        $method = new ReflectionMethod($controller, 'buildPayload');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $controller,
            ['tipo_cliente_id' => '1'],
            ['tipo_cliente' => 'tipo_cliente_id'],
            [
                'clases' => ['options' => [], 'by_id' => [], 'ids' => []],
                'modalidad' => ['options' => [], 'by_id' => [], 'ids' => []],
                'llego' => ['options' => [], 'by_id' => [], 'ids' => []],
                'tipos_cliente' => [
                    'options' => [
                        ['id' => 1, 'label' => 'Nuevo'],
                    ],
                    'by_id' => [
                        '1' => ['id' => 1, 'label' => 'Nuevo'],
                    ],
                    'ids' => ['1'],
                ],
            ],
        );

        $this->assertSame(1, $payload['tipo_cliente_id']);
    }

    #[Test]
    public function normaliza_etiquetas_legacy_de_tipo_cliente_al_id_del_catalogo(): void
    {
        $controller = new ClientesController(
            app(ClienteValorTotalCalculator::class),
            app(TarifaConfigService::class),
        );

        $method = new ReflectionMethod($controller, 'buildPayload');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $controller,
            ['tipo_cliente_id' => 'NUEVO'],
            ['tipo_cliente' => 'tipo_cliente_id'],
            [
                'clases' => ['options' => [], 'by_id' => [], 'ids' => []],
                'modalidad' => ['options' => [], 'by_id' => [], 'ids' => []],
                'llego' => ['options' => [], 'by_id' => [], 'ids' => []],
                'tipos_cliente' => [
                    'options' => [
                        ['id' => 1, 'label' => 'Nuevo'],
                        ['id' => 2, 'label' => 'Cambio empresa'],
                    ],
                    'by_id' => [
                        '1' => ['id' => 1, 'label' => 'Nuevo'],
                        '2' => ['id' => 2, 'label' => 'Cambio empresa'],
                    ],
                    'ids' => ['1', '2'],
                ],
            ],
        );

        $this->assertSame(1, $payload['tipo_cliente_id']);
    }
}
