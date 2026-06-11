<?php

namespace Tests\Unit;

use App\Services\ClienteRetiradoService;
use App\Services\CobrosService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class CobrosServiceSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sg_proform');
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('empresa')->nullable();
            $table->string('email')->nullable();
            $table->string('nit')->nullable();
            $table->string('regimen')->nullable();
            $table->string('fecha_arriendo')->nullable();
            $table->text('nota_cobro')->nullable();
        });

        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->increments('id_cobro');
            $table->unsignedInteger('id_cliente')->nullable();
            $table->string('mes')->nullable();
            $table->integer('año')->nullable();
            $table->integer('numero_facturas')->nullable();
            $table->integer('numero_nota_debito')->nullable();
            $table->integer('numero_nota_credito')->nullable();
            $table->integer('numero_documento_soporte')->nullable();
            $table->integer('numero_nota_ajuste')->nullable();
            $table->integer('numero_acuse')->nullable();
            $table->decimal('valor_facturas', 12, 2)->nullable();
            $table->decimal('valor_documentos', 12, 2)->nullable();
            $table->decimal('valor_acuse', 12, 2)->nullable();
            $table->decimal('valor_mensualidad', 12, 2)->nullable();
            $table->decimal('valor_total', 12, 2)->nullable();
            $table->string('Proforma')->nullable();
        });
    }

    public function test_period_summary_reuses_the_listing_filter_query(): void
    {
        $service = new CobrosService(new ClienteRetiradoService());
        $filters = [
            'mes' => 'junio',
            'anio' => 2026,
            'proforma' => null,
            'codigo' => null,
            'buscar' => null,
            'orden_fecha' => null,
            'grupo_fecha' => '7',
            'filtro_nota' => 'con',
            'filtro_envio' => null,
        ];

        $method = new ReflectionMethod($service, 'buildFilteredCobrosQuery');
        $method->setAccessible(true);

        $query = $method->invoke($service, $filters);

        $this->assertStringContainsString('left join "clientes_potenciales" as "cp"', $query->toSql());
        $this->assertStringContainsString('cp.fecha_arriendo', $query->toSql());
        $this->assertStringContainsString('"cp"."nota_cobro" is not null', $query->toSql());
        $this->assertSame(['junio', '2026', 7], $query->getBindings());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sg_proform');
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        parent::tearDown();
    }
}
