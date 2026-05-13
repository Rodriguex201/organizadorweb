<?php

namespace Tests\Unit;

use App\Services\ClienteValorTotalCalculator;
use PHPUnit\Framework\TestCase;

class ClienteValorTotalCalculatorTest extends TestCase
{
    public function test_calcula_total_con_formula_de_creacion_de_cliente(): void
    {
        $calculator = new ClienteValorTotalCalculator();

        $total = $calculator->calculate([
            'vlrprincipal' => 100,
            'numequipos' => 3,
            'vlrterminal' => 50,
            'numextra' => 2,
            'vlrextrae' => 30,
            'vlrnomina' => 10,
            'numeromoviles' => 4,
            'vlrmovil' => 20,
        ]);

        $this->assertSame(350.0, $total);
    }

    public function test_no_descuenta_por_debajo_de_un_equipo_base(): void
    {
        $calculator = new ClienteValorTotalCalculator();

        $total = $calculator->calculate([
            'vlrprincipal' => 100,
            'numequipos' => 0,
            'vlrterminal' => 50,
        ]);

        $this->assertSame(100.0, $total);
    }
}
