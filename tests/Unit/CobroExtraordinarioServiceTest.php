<?php

namespace Tests\Unit;

use App\Services\ClienteRetiradoService;
use App\Services\CobroExtraordinarioService;
use App\Services\RevisarProformaCalculator;
use Mockery;
use Tests\TestCase;

class CobroExtraordinarioServiceTest extends TestCase
{
    public function test_no_crea_cobro_extraordinario_para_cliente_retirado(): void
    {
        $calculator = Mockery::mock(RevisarProformaCalculator::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);

        $cliente = (object) [
            'id' => 15,
            'fecha_retiro' => '2026-05-01',
        ];

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->with($cliente)
            ->andReturn(true);

        $calculator->shouldNotReceive('calculate');

        $service = new CobroExtraordinarioService($calculator, $clienteRetiradoService);

        $resultado = $service->createCobro($cliente, [
            'mes' => 'junio',
            'anio' => 2026,
        ]);

        $this->assertFalse($resultado['created']);
        $this->assertFalse($resultado['duplicated']);
        $this->assertTrue($resultado['blocked']);
        $this->assertSame(
            'No es posible generar cobros extraordinarios para clientes retirados.',
            $resultado['message']
        );
    }
}
