<?php

namespace Tests\Unit;

use App\Services\ConceptosCatalogService;
use App\Services\ProformaPreviewService;
use App\Services\RevisarProformaCalculator;
use PHPUnit\Framework\TestCase;

class ProformaPreviewServiceTest extends TestCase
{
    public function test_agrega_linea_de_terminales_extra_y_total_consistente(): void
    {
        $catalogo = $this->createMock(ConceptosCatalogService::class);
        $catalogo->method('findByCodes')
            ->willReturn([
                '0010' => ['codigo' => '0010', 'nombre' => 'Mensualidad SaaS', 'cuenta' => '4130', 'activo' => 1],
                '0011' => ['codigo' => '0011', 'nombre' => 'SERVICIO CLOUD TERMINALES EXTRA', 'cuenta' => '4131', 'activo' => 1],
                '0099' => ['codigo' => '0099', 'nombre' => 'Nomina electronica', 'cuenta' => '4132', 'activo' => 1],
                '0081' => ['codigo' => '0081', 'nombre' => 'Facturacion electronica', 'cuenta' => '4133', 'activo' => 1],
                '0101' => ['codigo' => '0101', 'nombre' => 'Recepcion compras', 'cuenta' => '4134', 'activo' => 1],
                '0102' => ['codigo' => '0102', 'nombre' => 'Soporte electronico', 'cuenta' => '4135', 'activo' => 1],
                'EXTRA' => ['codigo' => 'EXTRA', 'nombre' => 'Cargo extra manual', 'cuenta' => '4199', 'activo' => 1],
            ]);

        $service = new ProformaPreviewService(
            new RevisarProformaCalculator(),
            $catalogo,
        );

        $cobro = (object) [
            'id_cobro' => 10,
            'mes' => 'mayo',
            'año' => '2026',
            'Proforma' => 0,
            'cliente_regimen' => 'SAS',
            'cliente_id' => 1,
            'cliente_empresa' => 'Cliente Demo',
            'cliente_nit' => '123',
            'cliente_vlrprincipal' => 100,
            'cliente_numequipos' => 3,
            'cliente_vlrterminal' => 50,
            'cliente_numextra' => 2,
            'cliente_vlrextrae' => 30,
            'cliente_vlrnomina' => 10,
            'cliente_vlrfactura' => 2,
            'cliente_vlrsoporte' => 3,
            'cliente_vlrecepcion' => 4,
            'numero_facturas' => 5,
            'numero_nota_debito' => 1,
            'numero_nota_credito' => 2,
            'numero_documento_soporte' => 4,
            'numero_nota_ajuste' => 1,
            'numero_acuse' => 3,
            'valor_facturas' => 10,
            'valor_documentos' => 12,
            'valor_acuse' => 12,
            'valor_extra' => 7,
            'valor_total' => 0,
        ];

        $preview = $service->buildFromCobro($cobro);
        $lineas = $preview['detalle']['lineas'];

        $lineaExtra = null;
        foreach ($lineas as $linea) {
            if (($linea['codigo'] ?? null) === '0011') {
                $lineaExtra = $linea;
                break;
            }
        }

        $this->assertNotNull($lineaExtra);
        $this->assertSame('SERVICIO CLOUD TERMINALES EXTRA', $lineaExtra['concepto']);
        $this->assertSame(2.0, $lineaExtra['cantidad']);
        $this->assertSame(30.0, $lineaExtra['valor_unitario']);
        $this->assertSame(60.0, $lineaExtra['valor_parcial']);
        $this->assertSame(311.0, $preview['detalle']['total_preview']);
        $this->assertSame(311.0, $preview['detalle']['total_calculado']);
    }

    public function test_no_falla_si_el_concepto_de_terminales_extra_no_existe_en_bd(): void
    {
        $catalogo = $this->createMock(ConceptosCatalogService::class);
        $catalogo->method('findByCodes')
            ->willReturn([
                '0010' => ['codigo' => '0010', 'nombre' => 'Mensualidad SaaS', 'cuenta' => '4130', 'activo' => 1],
                '0099' => ['codigo' => '0099', 'nombre' => 'Nomina electronica', 'cuenta' => '4132', 'activo' => 1],
            ]);
        $catalogo->method('resolve')
            ->willReturn([
                'codigo' => '0011',
                'nombre' => 'Concepto no configurado',
                'cuenta' => null,
                'activo' => null,
                'exists' => false,
            ]);

        $service = new ProformaPreviewService(
            new RevisarProformaCalculator(),
            $catalogo,
        );

        $cobro = (object) [
            'id_cobro' => 11,
            'mes' => 'mayo',
            'año' => '2026',
            'Proforma' => 0,
            'cliente_regimen' => 'SAS',
            'cliente_vlrprincipal' => 100,
            'cliente_numequipos' => 2,
            'cliente_vlrterminal' => 50,
            'cliente_numextra' => 1,
            'cliente_vlrextrae' => 30,
            'cliente_vlrnomina' => 10,
        ];

        $preview = $service->buildFromCobro($cobro);
        $lineas = $preview['detalle']['lineas'];

        $lineaExtra = null;
        foreach ($lineas as $linea) {
            if (($linea['cantidad'] ?? null) === 1.0 && ($linea['valor_unitario'] ?? null) === 30.0) {
                $lineaExtra = $linea;
                break;
            }
        }

        $this->assertNotNull($lineaExtra);
        $this->assertSame('Concepto no configurado', $lineaExtra['concepto']);
    }
}
