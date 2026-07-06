<?php

namespace Tests\Feature;

use App\Services\ClienteRetiradoService;
use App\Services\CobroExtraordinarioService;
use App\Services\CobrosService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformaStoreService;
use App\Services\ProformasService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CobrosRevisionFlowTest extends TestCase
{
    public function test_recalcular_en_revision_retorna_la_vista_con_facturacion_cliente(): void
    {
        $this->withoutMiddleware();
        view()->share('errors', new ViewErrorBag());

        $cobro = $this->makeCobro();
        $formData = $this->makeFormData([
            'precio_factura' => 1500.0,
            'precio_soporte' => 230.0,
            'precio_acuse' => 340.0,
            'valor_total_proforma' => 9320.0,
        ]);

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(501)
            ->andReturn($cobro);

        $calculator = Mockery::mock(RevisarProformaCalculator::class);
        $calculator->shouldReceive('calculate')
            ->once()
            ->andReturn($formData);

        $storeService = Mockery::mock(ProformaStoreService::class);
        $storeService->shouldReceive('findExistingProformaIdFromCobro')
            ->once()
            ->with($cobro)
            ->andReturn(null);

        $this->mockSchemaColumns();
        $this->mockClienteQueriesForRecalcular();
        $this->bindControllerDependencies($cobrosService, $calculator, $storeService);

        $response = $this->post(route('cobros.revisar.guardar', ['id' => 501]), [
            'accion' => 'recalcular',
            'numero_equipos' => 2,
            'valor_principal' => 5000,
            'valor_terminal' => 1000,
            'precio_factura' => 1500,
        ]);

        $response->assertOk();
        $response->assertViewIs('cobros.revisar');
        $response->assertViewHas('formData', $formData);
        $response->assertViewHas('facturacionCliente', [
            'estado' => 'ACTIVO',
            'es_pendiente' => false,
            'fecha_inicio' => '2026-06-01',
            'cliente_id' => 25,
        ]);
    }

    public function test_guardar_revision_mantiene_el_flujo_actual(): void
    {
        $this->withoutMiddleware();
        view()->share('errors', new ViewErrorBag());

        $cobro = $this->makeCobro();
        $formData = $this->makeFormData([
            'precio_factura' => 1200.0,
            'precio_soporte' => 230.0,
            'precio_acuse' => 340.0,
        ]);

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->twice()
            ->with(501)
            ->andReturn($cobro);
        $cobrosService->shouldReceive('updateCobroRevision')
            ->once()
            ->with(501, $formData)
            ->andReturn(true);
        $cobrosService->shouldReceive('updateClienteRevision')
            ->once()
            ->with(25, $formData)
            ->andReturn(true);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->with($cobro)
            ->andReturn($formData);

        $calculator = Mockery::mock(RevisarProformaCalculator::class);
        $calculator->shouldReceive('calculate')
            ->once()
            ->andReturn($formData);

        $storeService = Mockery::mock(ProformaStoreService::class);
        $storeService->shouldIgnoreMissing();

        $this->mockSchemaColumns();
        $this->mockClienteQueriesForPersistedActions();
        $this->mockSnapshotQueries();
        $this->bindControllerDependencies($cobrosService, $calculator, $storeService);

        $response = $this->post(route('cobros.revisar.guardar', ['id' => 501]), [
            'accion' => 'guardar',
            'numero_equipos' => 2,
            'valor_principal' => 5000,
            'valor_terminal' => 1000,
        ]);

        $response->assertRedirect(route('cobros.revisar', 501));
        $response->assertSessionHas('status_type', 'success');
    }

    public function test_generar_proforma_desde_revision_mantiene_el_flujo_actual(): void
    {
        $this->withoutMiddleware();
        view()->share('errors', new ViewErrorBag());

        $cobro = $this->makeCobro();
        $formData = $this->makeFormData([
            'precio_factura' => 1200.0,
            'precio_soporte' => 230.0,
            'precio_acuse' => 340.0,
            'otro_valor_extra' => 0.0,
        ]);

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->times(3)
            ->with(501)
            ->andReturn($cobro);
        $cobrosService->shouldReceive('updateCobroRevision')
            ->once()
            ->with(501, $formData)
            ->andReturn(true);
        $cobrosService->shouldReceive('updateClienteRevision')
            ->once()
            ->with(25, $formData)
            ->andReturn(true);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->with($cobro)
            ->andReturn($formData);

        $calculator = Mockery::mock(RevisarProformaCalculator::class);
        $calculator->shouldReceive('calculate')
            ->once()
            ->andReturn($formData);

        $storeService = Mockery::mock(ProformaStoreService::class);
        $storeService->shouldReceive('storeFromCobro')
            ->once()
            ->with($cobro, [
                'codigo_concepto_extra' => '',
                'descripcion_concepto_extra' => '',
            ])
            ->andReturn([
                'blocked' => false,
                'duplicated' => false,
                'message' => 'Proforma generada correctamente.',
            ]);

        $this->mockSchemaColumns();
        $this->mockClienteQueriesForPersistedActions();
        $this->mockSnapshotQueries();
        $this->bindControllerDependencies($cobrosService, $calculator, $storeService);

        $response = $this->post(route('cobros.revisar.guardar', ['id' => 501]), [
            'accion' => 'generar',
            'numero_equipos' => 2,
            'valor_principal' => 5000,
            'valor_terminal' => 1000,
        ]);

        $response->assertRedirect(route('cobros.proforma.preview', 501));
        $response->assertSessionHas('status_type', 'success');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function bindControllerDependencies(
        CobrosService $cobrosService,
        RevisarProformaCalculator $calculator,
        ProformaStoreService $storeService
    ): void {
        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(RevisarProformaCalculator::class, $calculator);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(CobroExtraordinarioService::class, $this->ignoreMissingMock(CobroExtraordinarioService::class));
        $this->app->instance(ClienteRetiradoService::class, $this->ignoreMissingMock(ClienteRetiradoService::class));
        $this->app->instance(ProformaPreviewService::class, $this->ignoreMissingMock(ProformaPreviewService::class));
        $this->app->instance(ProformaPdfService::class, $this->ignoreMissingMock(ProformaPdfService::class));
        $this->app->instance(ProformasService::class, $this->ignoreMissingMock(ProformasService::class));
        $this->app->instance(ProformaEmailService::class, $this->ignoreMissingMock(ProformaEmailService::class));
    }

    private function ignoreMissingMock(string $className): object
    {
        $mock = Mockery::mock($className);
        $mock->shouldIgnoreMissing();

        return $mock;
    }

    private function mockSchemaColumns(): void
    {
        Schema::shouldReceive('hasColumn')
            ->zeroOrMoreTimes()
            ->andReturn(true);
    }

    private function mockClienteQueriesForRecalcular(): void
    {
        $updateQuery = Mockery::mock();
        $updateQuery->shouldReceive('where')->once()->with('idclientes_potenciales', 25)->andReturnSelf();
        $updateQuery->shouldReceive('update')->once()->with(['vlrfactura' => 1500.0])->andReturn(1);

        $selectQuery = Mockery::mock();
        $selectQuery->shouldReceive('where')->once()->with('idclientes_potenciales', 25)->andReturnSelf();
        $selectQuery->shouldReceive('select')->once()->andReturnSelf();
        $selectQuery->shouldReceive('first')->once()->andReturn((object) [
            'vlrfactura' => 1500.0,
            'vlrsoporte' => 230.0,
            'vlrecepcion' => 340.0,
            'numextra' => 0.0,
            'vlrextrae' => 0.0,
        ]);

        DB::shouldReceive('table')->once()->with('clientes_potenciales')->andReturn($updateQuery);
        DB::shouldReceive('table')->once()->with('clientes_potenciales')->andReturn($selectQuery);
    }

    private function mockClienteQueriesForPersistedActions(): void
    {
        $selectQuery = Mockery::mock();
        $selectQuery->shouldReceive('where')->once()->with('idclientes_potenciales', 25)->andReturnSelf();
        $selectQuery->shouldReceive('select')->once()->andReturnSelf();
        $selectQuery->shouldReceive('first')->once()->andReturn((object) [
            'vlrfactura' => 1200.0,
            'vlrsoporte' => 230.0,
            'vlrecepcion' => 340.0,
            'numextra' => 0.0,
            'vlrextrae' => 0.0,
        ]);

        DB::shouldReceive('table')->once()->with('clientes_potenciales')->andReturn($selectQuery);
    }

    private function mockSnapshotQueries(): void
    {
        $valoresQuery = Mockery::mock();
        $valoresQuery->shouldReceive('where')->once()->with('id_cobro', 501)->andReturnSelf();
        $valoresQuery->shouldReceive('first')->once()->andReturn((object) ['id_cobro' => 501]);

        $clienteQuery = Mockery::mock();
        $clienteQuery->shouldReceive('where')->once()->with('idclientes_potenciales', 25)->andReturnSelf();
        $clienteQuery->shouldReceive('first')->once()->andReturn((object) ['idclientes_potenciales' => 25]);

        DB::shouldReceive('table')->once()->with('valores_externos')->andReturn($valoresQuery);
        DB::shouldReceive('table')->once()->with('clientes_potenciales')->andReturn($clienteQuery);
    }

    private function makeCobro(): object
    {
        return (object) [
            'id_cobro' => 501,
            'id_cliente' => 25,
            'cliente_estado_facturacion' => 'ACTIVO',
            'cliente_fecha_inicio_facturacion' => '2026-06-01',
            'cliente_codigo' => 'CL-25',
            'cliente_nombre' => 'Cliente Demo',
            'mes' => 'junio',
        ];
    }

    private function makeFormData(array $overrides = []): array
    {
        return array_merge([
            'numero_equipos' => 2.0,
            'valor_principal' => 5000.0,
            'valor_terminal' => 1000.0,
            'numero_equipos_extra' => 0.0,
            'valor_equipo_extra' => 0.0,
            'empleados' => 0.0,
            'valor_nomina' => 0.0,
            'numero_moviles' => 0.0,
            'valor_movil' => 0.0,
            'facturas' => 0.0,
            'nota_debito' => 0.0,
            'nota_credito' => 0.0,
            'soporte' => 0.0,
            'nota_ajuste' => 0.0,
            'acuse' => 0.0,
            'otro_valor_extra' => 0.0,
            'otro_valor_extra_2' => 0.0,
            'precio_factura' => 1200.0,
            'precio_soporte' => 230.0,
            'precio_acuse' => 340.0,
            'total_facturas' => 0.0,
            'valor_facturas' => 0.0,
            'total_documentos' => 0.0,
            'valor_documentos' => 0.0,
            'valor_acuse' => 0.0,
            'total_mensualidad' => 6000.0,
            'valor_total_proforma' => 6000.0,
        ], $overrides);
    }
}
