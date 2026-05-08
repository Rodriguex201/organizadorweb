<?php

namespace Tests\Unit;

use App\Services\NumeroALetrasService;
use PHPUnit\Framework\TestCase;

class NumeroALetrasServiceTest extends TestCase
{
    public function test_convierte_valor_entero_a_pesos_colombianos_en_letras(): void
    {
        $service = new NumeroALetrasService();

        $this->assertSame(
            'QUINIENTOS OCHO MIL TRESCIENTOS SETENTA Y CUATRO PESOS M/CTE',
            $service->toColombianPesos(508374)
        );

        $this->assertSame(
            'CUATROCIENTOS VEINTIÚN MIL OCHOCIENTOS SETENTA PESOS M/CTE',
            $service->toColombianPesos(421870)
        );

        $this->assertSame(
            'CIENTO CUARENTA Y CINCO MIL PESOS M/CTE',
            $service->toColombianPesos(145000)
        );
    }

    public function test_agrega_centavos_en_formato_xx_100(): void
    {
        $service = new NumeroALetrasService();

        $this->assertSame(
            'QUINIENTOS OCHO MIL TRESCIENTOS SETENTA Y CUATRO PESOS CON 50/100 M/CTE',
            $service->toColombianPesos(508374.50)
        );
    }

    public function test_soporta_cero_y_millones(): void
    {
        $service = new NumeroALetrasService();

        $this->assertSame(
            'CERO PESOS M/CTE',
            $service->toColombianPesos(0)
        );

        $this->assertSame(
            'UN MILLÓN DOSCIENTOS MIL PESOS M/CTE',
            $service->toColombianPesos(1200000)
        );
    }
}
