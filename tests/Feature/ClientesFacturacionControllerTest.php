<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientesFacturacionControllerTest extends TestCase
{
    public function test_store_guarda_facturacion_pendiente_por_defecto(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        $response = $this->post(route('clientes.store'), [
            'empresa' => 'ACME SAS',
            'codigo' => 'A100',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('codigo', 'A100')->first();

        $this->assertNotNull($cliente);
        $this->assertSame('PENDIENTE', $cliente->estado_facturacion);
        $this->assertNull($cliente->fecha_inicio_facturacion);
    }

    public function test_update_asigna_fecha_actual_cuando_pasa_a_activo(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 10,
            'empresa' => 'BETA SAS',
            'codigo' => 'B200',
            'estado_facturacion' => 'PENDIENTE',
            'fecha_inicio_facturacion' => null,
        ]);

        $response = $this->put(route('clientes.update', ['id' => 10]), [
            'empresa' => 'BETA SAS',
            'codigo' => 'B200',
            'estado_facturacion' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 10)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('ACTIVO', $cliente->estado_facturacion);
        $this->assertSame(now()->toDateString(), $cliente->fecha_inicio_facturacion);
    }

    public function test_update_no_borra_fecha_si_vuelve_a_pendiente(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 11,
            'empresa' => 'GAMMA SAS',
            'codigo' => 'C300',
            'estado_facturacion' => 'ACTIVO',
            'fecha_inicio_facturacion' => '2026-06-01',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 11]), [
            'empresa' => 'GAMMA SAS',
            'codigo' => 'C300',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 11)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('PENDIENTE', $cliente->estado_facturacion);
        $this->assertSame('2026-06-01', $cliente->fecha_inicio_facturacion);
    }

    public function test_store_acepta_multiples_correos_separados_por_coma(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        $response = $this->post(route('clientes.store'), [
            'empresa' => 'DELTA SAS',
            'codigo' => 'D400',
            'email' => 'correo1@empresa.com, correo2@empresa.com',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('codigo', 'D400')->first();

        $this->assertNotNull($cliente);
        $this->assertSame('correo1@empresa.com, correo2@empresa.com', $cliente->email);
    }

    public function test_update_acepta_multiples_correos_separados_por_punto_y_coma(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 12,
            'empresa' => 'EPSILON SAS',
            'codigo' => 'E500',
            'email' => 'correo@empresa.com',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 12]), [
            'empresa' => 'EPSILON SAS',
            'codigo' => 'E500',
            'email' => 'correo1@empresa.com; correo2@empresa.com',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 12)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('correo1@empresa.com; correo2@empresa.com', $cliente->email);
    }

    public function test_store_rechaza_lista_de_correos_con_elementos_invalidos(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();

        $response = $this->from(route('clientes.create'))->post(route('clientes.store'), [
            'empresa' => 'ZETA SAS',
            'codigo' => 'Z600',
            'email' => 'correo1@empresa.com, correo-invalido',
        ]);

        $response->assertRedirect(route('clientes.create'));
        $response->assertSessionHasErrors('email');

        $this->assertNull(DB::table('clientes_potenciales')->where('codigo', 'Z600')->first());
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
            $table->string('nit')->nullable();
            $table->string('dv')->nullable();
            $table->string('nombre')->nullable();
            $table->string('codigo')->nullable();
            $table->string('empresa')->nullable();
            $table->string('email')->nullable();
            $table->string('celular1')->nullable();
            $table->string('departamento')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_arriendo')->nullable();
            $table->string('ip_empresa')->nullable();
            $table->string('regimen')->nullable();
            $table->string('estado_facturacion')->nullable();
            $table->date('fecha_inicio_facturacion')->nullable();
        });
    }
}
