<?php

namespace Tests\Unit;

use App\Services\RevisarProformaCalculator;
use PHPUnit\Framework\TestCase;

class RevisarProformaCalculatorTest extends TestCase
{
    public function test_calcula_totales_de_revision_manual(): void
    {
        $service = new RevisarProformaCalculator();

        $resultado = $service->calculate([
            'numero_equipos' => 3,
            'valor_principal' => 100,
            'valor_terminal' => 50,
            'numero_equipos_extra' => 2,
            'valor_equipo_extra' => 30,
            'empleados' => 3,
            'valor_nomina' => 10,
            'numero_moviles' => 1,
            'valor_movil' => 20,
            'facturas' => 5,
            'nota_debito' => 1,
            'nota_credito' => 2,
            'soporte' => 4,
            'nota_ajuste' => 1,
            'acuse' => 3,
            'precio_factura' => 2,
            'precio_soporte' => 3,
            'precio_acuse' => 4,
            'otro_valor_extra' => 7,
            'valor_terminal_recepcion' => 8,
        ]);

        $this->assertSame(8.0, $resultado['total_facturas']);
        $this->assertSame(10.0, $resultado['valor_facturas']);
        $this->assertSame(5.0, $resultado['total_documentos']);
        $this->assertSame(12.0, $resultado['valor_documentos']);
        $this->assertSame(12.0, $resultado['valor_acuse']);
        $this->assertSame(270.0, $resultado['total_mensualidad']);
        $this->assertSame(319.0, $resultado['valor_total_proforma']);
    }

    public function test_no_agrega_extra_si_numero_equipos_extra_es_cero(): void
    {
        $service = new RevisarProformaCalculator();

        $resultado = $service->calculate([
            'numero_equipos' => 1,
            'valor_principal' => 100,
            'valor_terminal' => 50,
            'numero_equipos_extra' => 0,
            'valor_equipo_extra' => 99,
            'valor_nomina' => 25,
        ]);

        $this->assertSame(125.0, $resultado['total_mensualidad']);
    }
}
