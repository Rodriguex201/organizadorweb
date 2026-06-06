<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientesActivarFacturacionControllerTest extends TestCase
{
    public function test_activar_facturacion_actualiza_estado_y_fecha_si_estaba_vacia(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 25,
            'empresa' => 'DELTA SAS',
            'estado_facturacion' => 'PENDIENTE',
            'fecha_inicio_facturacion' => null,
        ]);

        $response = $this->from(route('cobros.show', ['id' => 10]))
            ->patch(route('clientes.activar-facturacion', ['id' => 25]));

        $response->assertRedirect(route('cobros.show', ['id' => 10]));
        $response->assertSessionHas('status', 'Cliente activado para facturacion correctamente.');

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 25)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('ACTIVO', $cliente->estado_facturacion);
        $this->assertSame(now()->toDateString(), $cliente->fecha_inicio_facturacion);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clientes_potenciales');

        parent::tearDown();
    }

    private function createClientesTable(): void
    {
        Schema::dropIfExists('clientes_potenciales');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->string('empresa')->nullable();
            $table->string('estado_facturacion')->nullable();
            $table->date('fecha_inicio_facturacion')->nullable();
        });
    }
}
