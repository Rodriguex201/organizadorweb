<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use App\Models\ClientePotencial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientesReactivarControllerTest extends TestCase
{
    public function test_retirar_marca_estado_facturacion_como_inactivo(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTables();

        DB::table('conceptos_r')->insert([
            'id_retiro' => 2,
            'conceptosretiro' => 'Fin de contrato',
        ]);

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 425,
            'codigo' => 'B430',
            'nombre' => 'CLIENTE RETIRO',
            'retiro' => 0,
            'fecha_retiro' => null,
            'tipoRetiro' => null,
            'freact' => null,
            'mreact' => null,
            'estado_facturacion' => ClientePotencial::ESTADO_FACTURACION_ACTIVO,
        ]);

        $response = $this->patch(route('clientes.retirar', ['id' => 425]), [
            'motivo_retiro' => '2',
            'fecha_retiro' => '2026-06-18',
            'cliente_retiro_id' => 425,
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('status', 'Cliente marcado como retirado.');

        $cliente = DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', 425)
            ->first();

        $this->assertNotNull($cliente);
        $this->assertSame(1, (int) $cliente->retiro);
        $this->assertSame('2026-06-18', $cliente->fecha_retiro);
        $this->assertSame('2', $cliente->tipoRetiro);
        $this->assertSame(ClientePotencial::ESTADO_FACTURACION_INACTIVO, $cliente->estado_facturacion);
    }

    public function test_reactivar_limpia_campos_de_retiro_y_deja_cliente_activo(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTables();

        DB::table('conceptos_r')->insert([
            'id_retiro' => 2,
            'conceptosretiro' => 'Fin de contrato',
        ]);

        DB::table('motivos_re')->insert([
            'id' => 1,
            'nombre' => 'Correccion administrativa',
            'activo' => 1,
        ]);

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 424,
            'codigo' => 'B429',
            'nombre' => 'JUAN STEVAN PEREZ GUTIERREZ TECH JP',
            'retiro' => 1,
            'fecha_retiro' => '2026-05-01',
            'tipoRetiro' => '2',
            'freact' => null,
            'mreact' => null,
            'estado_facturacion' => ClientePotencial::ESTADO_FACTURACION_INACTIVO,
        ]);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->patch(route('clientes.reactivar', ['id' => 424]), [
            'motivo_reactivacion' => '1',
            'observacion_reactivacion' => 'Se reactiva por validacion de cartera.',
            'cliente_reactivacion_id' => 424,
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('status', 'Cliente reactivado correctamente.');

        $cliente = DB::table('clientes_potenciales')
            ->where('idclientes_potenciales', 424)
            ->first();

        $this->assertNotNull($cliente);
        $this->assertSame(0, (int) $cliente->retiro);
        $this->assertNull($cliente->fecha_retiro);
        $this->assertNull($cliente->tipoRetiro);
        $this->assertNotNull($cliente->freact);
        $this->assertSame('Correccion administrativa', $cliente->mreact);
        $this->assertSame(ClientePotencial::ESTADO_FACTURACION_ACTIVO, $cliente->estado_facturacion);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clientes_potenciales');
        Schema::dropIfExists('motivos_re');
        Schema::dropIfExists('conceptos_r');

        parent::tearDown();
    }

    private function createClientesTables(): void
    {
        Schema::dropIfExists('clientes_potenciales');
        Schema::dropIfExists('motivos_re');
        Schema::dropIfExists('conceptos_r');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->integer('retiro')->nullable();
            $table->string('fecha_retiro')->nullable();
            $table->string('tipoRetiro')->nullable();
            $table->string('freact')->nullable();
            $table->string('mreact')->nullable();
            $table->string('estado_facturacion')->nullable();
        });

        Schema::create('motivos_re', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->integer('activo')->default(1);
        });

        Schema::create('conceptos_r', function (Blueprint $table): void {
            $table->increments('id_retiro');
            $table->string('conceptosretiro')->nullable();
        });
    }
}
