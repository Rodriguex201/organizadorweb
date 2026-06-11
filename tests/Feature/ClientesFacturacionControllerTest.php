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
        $this->createCitiesTable();

        $response = $this->post(route('clientes.store'), $this->validClientPayload([
            'empresa' => 'ACME SAS',
            'codigo' => 'A100',
        ]));

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
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 10,
            'nit' => '900000001',
            'dv' => '1',
            'nombre' => 'BETA CONTACTO',
            'empresa' => 'BETA SAS',
            'celular1' => '3000000001',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.2',
            'regimen' => 'SAS',
            'codigo' => 'B200',
            'estado_facturacion' => 'PENDIENTE',
            'fecha_inicio_facturacion' => null,
        ]);

        $response = $this->put(route('clientes.update', ['id' => 10]), $this->validClientPayload([
            'nit' => '900000001',
            'dv' => '1',
            'nombre' => 'BETA CONTACTO',
            'empresa' => 'BETA SAS',
            'celular1' => '3000000001',
            'codigo' => 'B200',
            'estado_facturacion' => 'ACTIVO',
        ]));

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
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 11,
            'nit' => '900000002',
            'dv' => '2',
            'nombre' => 'GAMMA CONTACTO',
            'empresa' => 'GAMMA SAS',
            'celular1' => '3000000002',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.3',
            'regimen' => 'SAS',
            'codigo' => 'C300',
            'estado_facturacion' => 'ACTIVO',
            'fecha_inicio_facturacion' => '2026-06-01',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 11]), $this->validClientPayload([
            'nit' => '900000002',
            'dv' => '2',
            'nombre' => 'GAMMA CONTACTO',
            'empresa' => 'GAMMA SAS',
            'celular1' => '3000000002',
            'codigo' => 'C300',
            'estado_facturacion' => 'PENDIENTE',
        ]));

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
        $this->createCitiesTable();

        $response = $this->post(route('clientes.store'), $this->validClientPayload([
            'empresa' => 'DELTA SAS',
            'codigo' => 'D400',
            'email' => 'correo1@empresa.com, correo2@empresa.com',
        ]));

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('codigo', 'D400')->first();

        $this->assertNotNull($cliente);
        $this->assertSame('correo1@empresa.com, correo2@empresa.com', $cliente->email);
    }

    public function test_update_acepta_multiples_correos_separados_por_punto_y_coma(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 12,
            'nit' => '900000003',
            'dv' => '3',
            'nombre' => 'EPSILON CONTACTO',
            'empresa' => 'EPSILON SAS',
            'celular1' => '3000000003',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.4',
            'regimen' => 'SAS',
            'codigo' => 'E500',
            'email' => 'correo@empresa.com',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 12]), $this->validClientPayload([
            'nit' => '900000003',
            'dv' => '3',
            'nombre' => 'EPSILON CONTACTO',
            'empresa' => 'EPSILON SAS',
            'celular1' => '3000000003',
            'codigo' => 'E500',
            'email' => 'correo1@empresa.com; correo2@empresa.com',
            'estado_facturacion' => 'PENDIENTE',
        ]));

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 12)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('correo1@empresa.com; correo2@empresa.com', $cliente->email);
    }

    public function test_store_rechaza_lista_de_correos_con_elementos_invalidos(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        $response = $this->from(route('clientes.create'))->post(route('clientes.store'), $this->validClientPayload([
            'empresa' => 'ZETA SAS',
            'codigo' => 'Z600',
            'email' => 'correo1@empresa.com, correo-invalido',
        ]));

        $response->assertRedirect(route('clientes.create'));
        $response->assertSessionHasErrors('email');

        $this->assertNull(DB::table('clientes_potenciales')->where('codigo', 'Z600')->first());
    }

    public function test_store_rechaza_ciudad_escrita_manualmente_sin_seleccion_del_catalogo(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        $response = $this->from(route('clientes.create'))->post(route('clientes.store'), $this->validClientPayload([
            'codigo' => 'M700',
            'departamento' => 'PEREIRA, RISARALDA',
            'ciudad_codigo' => '',
        ]));

        $response->assertRedirect(route('clientes.create'));
        $response->assertSessionHasErrors('ciudad_codigo');

        $this->assertNull(DB::table('clientes_potenciales')->where('codigo', 'M700')->first());
    }

    public function test_store_guarda_ciudad_oficial_desde_citycodigo(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        $response = $this->post(route('clientes.store'), $this->validClientPayload([
            'codigo' => 'F650',
            'departamento' => 'PEREIRA, RISARALDA',
            'ciudad_codigo' => '66001',
        ]));

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('codigo', 'F650')->first();

        $this->assertNotNull($cliente);
        $this->assertSame('PEREIRA, RISARALDA', $cliente->departamento);
    }

    public function test_edit_precarga_ciudad_visible_y_ciudad_codigo_oculto(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 20,
            'nit' => '900000020',
            'dv' => '0',
            'nombre' => 'CLIENTE EDICION',
            'empresa' => 'OMEGA SAS',
            'celular1' => '3000000020',
            'email' => 'omega@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.20',
            'regimen' => 'SAS',
            'codigo' => 'O200',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $controller = app(\App\Http\Controllers\ClientesController::class);
        $view = $controller->edit(20);

        $this->assertSame('clientes.edit', $view->name());
        $this->assertSame([
            'code' => '11001',
            'label' => 'BOGOTA, CUNDINAMARCA',
        ], $view->getData()['selectedCity']);
    }

    public function test_update_permite_guardar_si_no_modifica_la_ciudad(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 21,
            'nit' => '900000021',
            'dv' => '1',
            'nombre' => 'CLIENTE ESTABLE',
            'empresa' => 'SIGMA SAS',
            'celular1' => '3000000021',
            'email' => 'sigma@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.21',
            'regimen' => 'SAS',
            'codigo' => 'S210',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 21]), $this->validClientPayload([
            'nit' => '900000021',
            'dv' => '1',
            'nombre' => 'CLIENTE ESTABLE',
            'empresa' => 'SIGMA SAS ACTUALIZADA',
            'celular1' => '3000000021',
            'email' => 'sigma@empresa.com',
            'codigo' => 'S210',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'ciudad_codigo' => '11001',
        ]));

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 21)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('SIGMA SAS ACTUALIZADA', $cliente->empresa);
        $this->assertSame('BOGOTA, CUNDINAMARCA', $cliente->departamento);
    }

    public function test_update_permite_cambiar_ciudad_si_selecciona_una_valida(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 22,
            'nit' => '900000022',
            'dv' => '2',
            'nombre' => 'CLIENTE CAMBIO',
            'empresa' => 'TAU SAS',
            'celular1' => '3000000022',
            'email' => 'tau@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.22',
            'regimen' => 'SAS',
            'codigo' => 'T220',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->put(route('clientes.update', ['id' => 22]), $this->validClientPayload([
            'nit' => '900000022',
            'dv' => '2',
            'nombre' => 'CLIENTE CAMBIO',
            'empresa' => 'TAU SAS',
            'celular1' => '3000000022',
            'email' => 'tau@empresa.com',
            'codigo' => 'T220',
            'departamento' => 'PEREIRA, RISARALDA',
            'ciudad_codigo' => '66001',
        ]));

        $response->assertRedirect(route('clientes.index'));

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 22)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('PEREIRA, RISARALDA', $cliente->departamento);
    }

    public function test_update_rechaza_cambiar_ciudad_sin_seleccionar_resultado(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 23,
            'nit' => '900000023',
            'dv' => '3',
            'nombre' => 'CLIENTE SIN SELECCION',
            'empresa' => 'UPSILON SAS',
            'celular1' => '3000000023',
            'email' => 'upsilon@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.23',
            'regimen' => 'SAS',
            'codigo' => 'U230',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->from(route('clientes.edit', ['id' => 23]))->put(route('clientes.update', ['id' => 23]), $this->validClientPayload([
            'nit' => '900000023',
            'dv' => '3',
            'nombre' => 'CLIENTE SIN SELECCION',
            'empresa' => 'UPSILON SAS',
            'celular1' => '3000000023',
            'email' => 'upsilon@empresa.com',
            'codigo' => 'U230',
            'departamento' => 'PEREIRA, RISARALDA',
            'ciudad_codigo' => '',
        ]));

        $response->assertRedirect(route('clientes.edit', ['id' => 23]));
        $response->assertSessionHasErrors([
            'ciudad_codigo' => 'Debes buscar y seleccionar una ciudad valida del catalogo.',
        ]);
    }

    public function test_update_rechaza_texto_manual_de_ciudad_sin_seleccion_valida(): void
    {
        $this->withoutMiddleware();
        $this->createClientesTable();
        $this->createCitiesTable();

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 24,
            'nit' => '900000024',
            'dv' => '4',
            'nombre' => 'CLIENTE TEXTO LIBRE',
            'empresa' => 'PHI SAS',
            'celular1' => '3000000024',
            'email' => 'phi@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.24',
            'regimen' => 'SAS',
            'codigo' => 'P240',
            'estado_facturacion' => 'PENDIENTE',
        ]);

        $response = $this->from(route('clientes.edit', ['id' => 24]))->put(route('clientes.update', ['id' => 24]), $this->validClientPayload([
            'nit' => '900000024',
            'dv' => '4',
            'nombre' => 'CLIENTE TEXTO LIBRE',
            'empresa' => 'PHI SAS',
            'celular1' => '3000000024',
            'email' => 'phi@empresa.com',
            'codigo' => 'P240',
            'departamento' => 'PEREIRA, RISARALDA',
            'ciudad_codigo' => '99999',
        ]));

        $response->assertRedirect(route('clientes.edit', ['id' => 24]));
        $response->assertSessionHasErrors([
            'ciudad_codigo' => 'Debes buscar y seleccionar una ciudad valida del catalogo.',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clientes_potenciales');
        Schema::dropIfExists('xxxxcity');

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

    private function createCitiesTable(): void
    {
        Schema::dropIfExists('xxxxcity');

        Schema::create('xxxxcity', function (Blueprint $table): void {
            $table->string('citycodigo')->primary();
            $table->string('citynomb');
            $table->string('cityindica')->nullable();
            $table->string('citypais')->nullable();
            $table->string('citydepto');
            $table->string('cityNdepto')->nullable();
            $table->string('cityiso')->nullable();
        });

        DB::table('xxxxcity')->insert([
            [
                'citycodigo' => '11001',
                'citynomb' => 'BOGOTA',
                'cityindica' => null,
                'citypais' => 'CO',
                'citydepto' => '11',
                'cityNdepto' => 'CUNDINAMARCA',
                'cityiso' => null,
            ],
            [
                'citycodigo' => '66001',
                'citynomb' => 'PEREIRA',
                'cityindica' => null,
                'citypais' => 'CO',
                'citydepto' => '66',
                'cityNdepto' => 'RISARALDA',
                'cityiso' => null,
            ],
        ]);
    }

    private function validClientPayload(array $overrides = []): array
    {
        return array_merge([
            'nit' => '900123456',
            'dv' => '7',
            'nombre' => 'CONTACTO PRINCIPAL',
            'codigo' => 'A100',
            'empresa' => 'EMPRESA DEMO SAS',
            'celular1' => '3001234567',
            'email' => 'demo@empresa.com',
            'departamento' => 'BOGOTA, CUNDINAMARCA',
            'ciudad_codigo' => '11001',
            'fecha_inicio' => '2026-06-01',
            'fecha_arriendo' => '2026-06-02',
            'ip_empresa' => '10.0.0.1',
            'regimen' => 'SAS',
            'estado_facturacion' => 'PENDIENTE',
        ], $overrides);
    }
}
