<?php

namespace Tests\Unit;

use App\Services\ClienteRetiradoService;
use App\Services\ClienteValorTotalCalculator;
use App\Services\CobrosBasePeriodoService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CobrosBasePeriodoServiceTest extends TestCase
{
    public function test_generate_creates_only_missing_base_rows_for_active_clients(): void
    {
        $this->createTables();

        DB::table('clientes_potenciales')->insert([
            [
                'idclientes_potenciales' => 1,
                'vlrprincipal' => 1000,
                'numequipos' => 1,
                'vlrterminal' => 0,
                'vlrnomina' => 0,
                'numero_empleados' => 0,
                'numeromoviles' => 0,
                'vlrmovil' => 0,
                'vlrfactura' => 100,
                'vlrsoporte' => 50,
                'vlrecepcion' => 25,
                'vlrextra' => 10,
                'vlrextra2' => 5,
                'fecha_retiro' => null,
                'retirado' => 0,
            ],
            [
                'idclientes_potenciales' => 2,
                'vlrprincipal' => 2000,
                'numequipos' => 2,
                'vlrterminal' => 0,
                'vlrnomina' => 0,
                'numero_empleados' => 0,
                'numeromoviles' => 0,
                'vlrmovil' => 0,
                'vlrfactura' => 100,
                'vlrsoporte' => 50,
                'vlrecepcion' => 25,
                'vlrextra' => 0,
                'vlrextra2' => 0,
                'fecha_retiro' => null,
                'retirado' => 0,
            ],
            [
                'idclientes_potenciales' => 3,
                'vlrprincipal' => 3000,
                'numequipos' => 3,
                'vlrterminal' => 0,
                'vlrnomina' => 0,
                'numero_empleados' => 0,
                'numeromoviles' => 0,
                'vlrmovil' => 0,
                'vlrfactura' => 100,
                'vlrsoporte' => 50,
                'vlrecepcion' => 25,
                'vlrextra' => 0,
                'vlrextra2' => 0,
                'fecha_retiro' => '2026-05-01',
                'retirado' => 1,
            ],
        ]);

        DB::table('valores_externos')->insert([
            'id_cobro' => 99,
            'id_cliente' => '2',
            'mes' => 'junio',
            'año' => 2026,
            'numero_facturas' => 0,
            'numero_nota_debito' => 0,
            'numero_nota_credito' => 0,
            'numero_documento_soporte' => 0,
            'numero_nota_ajuste' => 0,
            'numero_acuse' => 0,
            'valor_extra' => 0,
            'valor_extra2' => 0,
            'valor_facturas' => 0,
            'valor_documentos' => 0,
            'valor_acuse' => 0,
            'valor_mensualidad' => 0,
            'valor_total' => 0,
            'Proforma' => 0,
        ]);

        $service = new CobrosBasePeriodoService(
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
            new ClienteRetiradoService(),
        );

        $result = $service->generate('junio', 2026);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped_existing']);
        $this->assertSame(2, $result['total_active_clients']);
        $this->assertSame(2, DB::table('valores_externos')->where('mes', 'junio')->where('año', 2026)->count());
        $this->assertSame(1, DB::table('valores_externos')->where('id_cliente', '1')->count());
        $this->assertSame(0, DB::table('valores_externos')->where('id_cliente', '3')->count());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        parent::tearDown();
    }

    private function createTables(): void
    {
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->decimal('vlrprincipal', 12, 2)->nullable();
            $table->decimal('numequipos', 12, 2)->nullable();
            $table->decimal('vlrterminal', 12, 2)->nullable();
            $table->decimal('vlrnomina', 12, 2)->nullable();
            $table->decimal('numero_empleados', 12, 2)->nullable();
            $table->decimal('numeromoviles', 12, 2)->nullable();
            $table->decimal('vlrmovil', 12, 2)->nullable();
            $table->decimal('vlrfactura', 12, 2)->nullable();
            $table->decimal('vlrsoporte', 12, 2)->nullable();
            $table->decimal('vlrecepcion', 12, 2)->nullable();
            $table->decimal('vlrextra', 12, 2)->nullable();
            $table->decimal('vlrextra2', 12, 2)->nullable();
            $table->string('fecha_retiro')->nullable();
            $table->integer('retirado')->nullable();
        });

        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->integer('id_cobro')->primary();
            $table->string('id_cliente')->nullable();
            $table->string('mes')->nullable();
            $table->integer('año')->nullable();
            $table->decimal('numero_facturas', 12, 2)->nullable();
            $table->decimal('numero_nota_debito', 12, 2)->nullable();
            $table->decimal('numero_nota_credito', 12, 2)->nullable();
            $table->decimal('numero_documento_soporte', 12, 2)->nullable();
            $table->decimal('numero_nota_ajuste', 12, 2)->nullable();
            $table->decimal('numero_acuse', 12, 2)->nullable();
            $table->decimal('valor_extra', 12, 2)->nullable();
            $table->decimal('valor_extra2', 12, 2)->nullable();
            $table->decimal('valor_facturas', 12, 2)->nullable();
            $table->decimal('valor_documentos', 12, 2)->nullable();
            $table->decimal('valor_acuse', 12, 2)->nullable();
            $table->decimal('valor_mensualidad', 12, 2)->nullable();
            $table->decimal('valor_total', 12, 2)->nullable();
            $table->integer('Proforma')->nullable();
        });
    }
}
