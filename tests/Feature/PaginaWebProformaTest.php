<?php

namespace Tests\Feature;

use App\Http\Controllers\ClientesController;
use App\Models\Concepto;
use App\Services\ClienteValorTotalCalculator;
use App\Services\CobroExtraordinarioService;
use App\Services\CobrosBasePeriodoService;
use App\Services\CobrosService;
use App\Services\ConceptosConfigService;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class PaginaWebProformaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'pagina_web_tests', 'database.connections.pagina_web_tests' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('pagina_web_tests');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            foreach (['nombre', 'empresa', 'nit', 'codigo', 'contacto', 'celular1', 'celular2', 'email', 'direccion', 'regimen', 'modalidad', 'categoria', 'fecha_arriendo', 'fecha_retiro'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['vlrprincipal', 'vlrpaginaweb', 'numequipos', 'vlrterminal', 'vlrnomina', 'numero_empleados', 'numeromoviles', 'vlrmovil', 'vlrfactura', 'vlrsoporte', 'vlrecepcion', 'vlrextra', 'vlrextra2', 'nominaterminal', 'valor_total'] as $field) {
                $table->integer($field)->nullable()->default(0);
            }
        });
        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->integer('id_cobro')->primary();
            $table->string('id_cliente');
            $table->string('mes');
            $table->integer('año');
            $table->decimal('vlrpaginaweb', 10, 1)->nullable();
            foreach (['numero_facturas', 'numero_nota_debito', 'numero_nota_credito', 'numero_documento_soporte', 'numero_nota_ajuste', 'numero_acuse', 'valor_extra', 'valor_extra2', 'valor_facturas', 'valor_documentos', 'valor_acuse', 'valor_mensualidad', 'valor_total', 'Proforma'] as $field) {
                $table->double($field)->default(0);
            }
        });
        Schema::create('sg_proform', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('id_cobro')->nullable();
            foreach (['nit', 'emp', 'emisora', 'fpago', 'rpdf', 'npdf', 'hpdf'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['mes', 'anio', 'nro_prof', 'estado', 'vlr_mens', 'vlr_nom', 'vlr_fe', 'vlr_rec', 'vlr_sop', 'vext1', 'vext2', 'vtotal', 'cfe', 'csop', 'crec', 'cnom'] as $field) {
                $table->double($field)->default(0);
            }
        });
        Schema::create('sg_proford', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('proforma_id');
            $table->string('ref_codigo');
            $table->string('descripcion');
            $table->double('cantidad');
            $table->double('vr_unidad');
            $table->double('vr_parcial');
            $table->integer('orden');
            $table->string('moneda');
        });
        Schema::create('conceptos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 150);
            $table->string('cuenta', 30)->nullable();
            $table->boolean('activo')->default(true);
        });
        DB::table('conceptos')->insert([
            ['codigo' => '0010', 'nombre' => 'Mensualidad SaaS', 'cuenta' => null, 'activo' => true],
            ['codigo' => '0103', 'nombre' => 'SERVICIO PAGINA WEB', 'cuenta' => null, 'activo' => true],
        ]);

        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 1, 'empresa' => 'Cliente prueba', 'nombre' => 'Cliente prueba',
            'nit' => '900123456', 'codigo' => 'PRUEBA', 'regimen' => 'SAS',
            'vlrprincipal' => 1000, 'numequipos' => 2, 'vlrterminal' => 100, 'vlrpaginaweb' => 250,
        ]);
    }

    public function test_periodo_preview_y_persistencia_separan_pagina_web_sin_cambiar_0010(): void
    {
        $this->app->make(CobrosBasePeriodoService::class)->generate('septiembre', 2026);
        $cobros = $this->app->make(CobrosService::class);
        $cobro = $cobros->findCobroById(1);
        $this->assertSame(1350.0, (float) $cobro->valor_total);
        $this->assertSame(250.0, (float) $cobro->vlrpaginaweb);
        $this->assertSame(250.0, $cobros->mapCobroToRevisionValues($cobro)['valor_pagina_web']);
        $preview = $this->app->make(ProformaPreviewService::class)->buildFromCobro($cobro);
        $lines = array_column($preview['detalle']['lineas'], null, 'codigo');
        $this->assertSame(1100.0, $lines['0010']['valor_parcial']);
        $this->assertSame(250.0, $lines['0103']['valor_parcial']);
        $this->assertSame(1.0, $lines['0103']['cantidad']);
        $this->assertSame(1350.0, $preview['detalle']['total_preview']);
        $this->assertSame(1350.0, $preview['detalle']['total_calculado']);

        $result = $this->app->make(ProformaStoreService::class)->storeFromCobro($cobro);
        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('sg_proford', ['proforma_id' => $result['proforma_id'], 'ref_codigo' => '0103', 'descripcion' => 'SERVICIO PAGINA WEB', 'cantidad' => 1, 'vr_unidad' => 250, 'vr_parcial' => 250]);
        $this->assertDatabaseHas('sg_proford', ['ref_codigo' => '0010', 'vr_parcial' => 1100]);
        $this->assertDatabaseHas('sg_proform', ['id' => $result['proforma_id'], 'vtotal' => 1350]);

        $html = view('proformas.pdf', [
            'cabecera' => DB::table('sg_proform')->first(),
            'detalle' => DB::table('sg_proford')->orderBy('orden')->get(),
            'logo_path' => null, 'mes_nombre' => 'Septiembre', 'fecha_emision' => '2026-09-18',
        ])->render();
        $this->assertStringContainsString('SERVICIO PAGINA WEB', $html);
        $this->assertStringContainsString('0103', $html);

        DB::table('clientes_potenciales')->where('idclientes_potenciales', 1)->update(['vlrpaginaweb' => 999]);
        $regenerated = $this->app->make(ProformaStoreService::class)->regenerateFromCobro($cobros->findCobroById(1));
        $this->assertSame($result['proforma_id'], $regenerated['proforma_id']);
        $this->assertSame(1, DB::table('sg_proford')->where('proforma_id', $result['proforma_id'])->where('ref_codigo', '0103')->count());
        $this->assertDatabaseHas('sg_proford', ['proforma_id' => $result['proforma_id'], 'ref_codigo' => '0103', 'vr_parcial' => 250]);

        DB::table('valores_externos')->where('id_cobro', 1)->update(['vlrpaginaweb' => 0]);
        $this->app->make(ProformaStoreService::class)->regenerateFromCobro($cobros->findCobroById(1));
        $this->assertSame(0, DB::table('sg_proford')->where('proforma_id', $result['proforma_id'])->where('ref_codigo', '0103')->count());
        $this->assertDatabaseHas('valores_externos', ['id_cobro' => 1, 'vlrpaginaweb' => 0, 'valor_total' => 1100]);

    }

    public function test_cero_omite_linea_y_web_sola_no_crea_0010(): void
    {
        $preview = $this->app->make(ProformaPreviewService::class);
        $cobro = (object) ['id_cobro' => 1, 'cliente_vlrprincipal' => 1000, 'vlrpaginaweb' => 0, 'cliente_vlrpaginaweb' => 250];
        $result = $preview->buildFromCobro($cobro);
        $this->assertSame(['0010'], array_column($result['detalle']['lineas'], 'codigo'));
        $this->assertSame(1000.0, $result['detalle']['total_calculado']);
        $cobro->cliente_vlrprincipal = 0;
        $cobro->vlrpaginaweb = null;
        $result = $preview->buildFromCobro($cobro);
        $this->assertSame(['0103'], array_column($result['detalle']['lineas'], 'codigo'));
        $this->assertSame(250.0, $result['detalle']['total_calculado']);
    }

    public function test_extraordinario_y_revision_conservan_el_cargo_y_permiten_ponerlo_en_cero(): void
    {
        $extra = $this->app->make(CobroExtraordinarioService::class);
        $cliente = $extra->findCliente(1);
        $this->assertSame(250, (int) $cliente->vlrpaginaweb);
        $result = $extra->createCobro($cliente, ['mes' => 'septiembre', 'anio' => 2026]);
        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('valores_externos', ['id_cobro' => $result['id_cobro'], 'vlrpaginaweb' => 250, 'valor_total' => 1350]);

        $this->withoutMiddleware();
        $this->withoutVite();
        $response = $this->post(route('cobros.revisar.guardar', ['id' => $result['id_cobro']]), [
            'accion' => 'guardar', 'valor_principal' => 1000, 'numero_equipos' => 2,
            'valor_terminal' => 100, 'valor_pagina_web' => 0,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes_potenciales', ['idclientes_potenciales' => 1, 'vlrpaginaweb' => 250]);
        $this->assertDatabaseHas('valores_externos', ['id_cobro' => $result['id_cobro'], 'vlrpaginaweb' => 0, 'valor_total' => 1100]);
    }

    public function test_concepto_protegido_y_cuenta_sin_asignar(): void
    {
        $concepto = Concepto::where('codigo', '0103')->firstOrFail();
        $this->assertNull($concepto->cuenta);
        $service = $this->app->make(ConceptosConfigService::class);
        $this->assertFalse($service->delete($concepto)['deleted']);
        $this->expectException(ValidationException::class);
        $service->update($concepto, ['codigo' => '0104', 'nombre' => 'SERVICIO PAGINA WEB', 'activo' => true]);
    }

    public function test_exportacion_ofrece_importe_y_total_cliente_lo_incluye(): void
    {
        $service = $this->app->make(ProformaDashboardExportService::class);
        $method = new ReflectionMethod($service, 'columnDefinitions');
        $definitions = $method->invoke($service);
        $column = $definitions['cliente_valor_pagina_web'];
        $this->assertSame('Valor Página web', $column['label']);
        $this->assertSame('vlrpaginaweb', $column['client_field']);
        $this->assertSame(250.0, ($column['value'])((object) ['cliente_valor_pagina_web' => 250]));
        $this->assertSame(1350.0, (new ClienteValorTotalCalculator())->calculate([
            'vlrprincipal' => 1000, 'numequipos' => 2, 'vlrterminal' => 100, 'vlrpaginaweb' => 250,
        ]));
    }

    public function test_formulario_cliente_mapea_guarda_y_suma_pagina_web(): void
    {
        $controller = $this->app->make(ClientesController::class);
        $mapping = (new ReflectionMethod($controller, 'resolveColumnMapping'))->invoke($controller);
        $catalogos = array_fill_keys(['clases', 'modalidad', 'llego', 'tipos_cliente'], ['options' => [], 'by_id' => [], 'ids' => []]);
        $payload = (new ReflectionMethod($controller, 'buildPayload'))->invoke($controller, [
            'vlrprincipal' => 1000, 'numequipos' => 2, 'vlrterminal' => 100,
            'vlrpaginaweb' => 250, 'valor_total' => 1,
        ], $mapping, $catalogos);
        $this->assertSame(250.0, $payload['vlrpaginaweb']);
        $this->assertSame(1350.0, $payload['valor_total']);
        DB::table('clientes_potenciales')->where('idclientes_potenciales', 1)->update($payload);
        $this->assertDatabaseHas('clientes_potenciales', ['vlrpaginaweb' => 250, 'valor_total' => 1350]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $html = view('clientes.partials.proforma-fields', [
            'mapping' => $mapping, 'cliente' => DB::table('clientes_potenciales')->first(),
        ])->render();
        $this->assertStringContainsString('name="vlrpaginaweb"', $html);
        $this->assertStringContainsString('Valor Página web', $html);
        $this->assertMatchesRegularExpression('/name="vlrpaginaweb"[^>]*value="250"[^>]*data-proforma-input/s', $html);
    }

}
