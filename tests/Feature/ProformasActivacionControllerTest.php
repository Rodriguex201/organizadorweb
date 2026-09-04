<?php

namespace Tests\Feature;

use App\Services\EmpresaActivacionService;
use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ProformasActivacionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Aislar incluso si existe configuración Laravel cacheada de producción.
        config([
            'database.default' => 'activation_tests',
            'database.connections.activation_tests' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge('activation_tests');
        // Mantener autenticación/roles activos; omitir solo CSRF en solicitudes de prueba.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    private function prepareGlobalActivation(?string $codigo = 'A091'): \Mockery\MockInterface
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->integer('idclientes_potenciales')->primary();
            $table->string('codigo')->nullable();
            $table->string('empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nit')->nullable();
        });
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 91, 'codigo' => $codigo,
            'empresa' => 'MGI COMPUTERS S A S', 'nombre' => 'MGI', 'nit' => '900123456',
        ]);
        $this->withSession(['idusuario' => 99, 'rol_id' => 1, 'usuario' => 'admin']);
        $this->app->instance(ProformasService::class, Mockery::mock(ProformasService::class));
        $activation = Mockery::mock(EmpresaActivacionService::class);
        $this->app->instance(EmpresaActivacionService::class, $activation);

        return $activation;
    }

    public function test_buscar_cliente_por_codigo_sin_proformas(): void
    {
        $this->prepareGlobalActivation();
        $this->assertFalse(Schema::hasTable('sg_proform'));
        $this->assertFalse(Schema::hasTable('valores_externos'));
        $this->getJson('/proformas/activacion/clientes?q=a091')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 91)
            ->assertJsonPath('data.0.codigo', 'A091')
            ->assertJsonPath('data.0.empresa', 'MGI COMPUTERS S A S')
            ->assertJsonPath('data.0.nit', '900123456');
    }

    public function test_buscar_cliente_por_nombre(): void
    {
        $this->prepareGlobalActivation();
        $this->getJson('/proformas/activacion/clientes?q=computers')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 91);
        $this->getJson('/proformas/activacion/clientes?q=z')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_consultar_activacion_global_ignora_codigo_del_navegador(): void
    {
        $activation = $this->prepareGlobalActivation();
        $activation->shouldReceive('obtenerDetalle')->once()->with('A091')
            ->andReturn(['codigo' => 'A091', 'base' => 'a091']);
        $this->getJson('/proformas/activacion/clientes/91?codigo=B999&id_cliente=999&id_proforma=100')
            ->assertOk()->assertJsonPath('data.codigo', 'A091');
    }

    public function test_cliente_sin_codigo_no_consulta_ni_actualiza_servidores(): void
    {
        $this->prepareGlobalActivation('   ');
        $this->getJson('/proformas/activacion/clientes/91?codigo=A091')
            ->assertStatus(422)->assertJsonPath('message', 'El cliente seleccionado no tiene un código de empresa válido para gestionar la activación.');
        $this->postJson('/proformas/activacion/clientes/91', ['codigo' => 'A091', 'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-30'])
            ->assertStatus(422);
        $this->postJson('/proformas/activacion/clientes/91/eventos', ['codigo' => 'A091', 'fecha_fin' => '2026-06-30'])
            ->assertStatus(422);
    }

    public function test_cliente_inexistente_devuelve_404(): void
    {
        $this->prepareGlobalActivation();
        $this->getJson('/proformas/activacion/clientes/999')->assertNotFound();
    }

    public function test_codigo_invalido_no_consulta_servidores(): void
    {
        $this->prepareGlobalActivation('A091/otra');
        $this->getJson('/proformas/activacion/clientes/91')->assertStatus(422);
    }

    public function test_guardar_activacion_global_resuelve_codigo_backend_y_conserva_eventos(): void
    {
        $activation = $this->prepareGlobalActivation();
        $activation->shouldReceive('guardarActivacion')->once()
            ->with('A091', '2026-06-01', '2026-06-30', 'admin (99)')
            ->andReturn(['codigo' => 'A091', 'eventos_licencia' => ['existe' => true]]);
        $this->postJson('/proformas/activacion/clientes/91', [
            'codigo' => 'B999', 'id_cliente' => 999, 'id_proforma' => 100,
            'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-30',
        ])->assertOk()->assertJsonPath('data.codigo', 'A091')->assertJsonPath('data.eventos_licencia.existe', true);
    }

    public function test_actualizar_eventos_global_usa_codigo_backend(): void
    {
        $activation = $this->prepareGlobalActivation();
        $activation->shouldReceive('actualizarLicenciaEventos')->once()
            ->with('A091', '2026-06-30', 'admin (99)')
            ->andReturn(['empresa' => 'A091', 'fecha_vencimiento_nueva' => '2026-06-30']);
        $this->postJson('/proformas/activacion/clientes/91/eventos', [
            'codigo' => 'B999', 'fecha_fin' => '2026-06-30',
        ])->assertOk()->assertJsonPath('data.empresa', 'A091');
    }

    public function test_validaciones_globales_rechazan_fechas_invalidas(): void
    {
        $this->prepareGlobalActivation();
        $this->postJson('/proformas/activacion/clientes/91', [
            'fecha_inicio' => '2026-06-30', 'fecha_fin' => '2026-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
        $this->postJson('/proformas/activacion/clientes/91/eventos', ['fecha_fin' => 'incorrecta'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_fin');
    }

    public function test_usuario_no_admin_no_puede_usar_ninguna_ruta_global(): void
    {
        $this->withSession(['idusuario' => 99, 'rol_id' => 2]);
        $this->getJson('/proformas/activacion/clientes?q=A091')->assertForbidden();
        $this->getJson('/proformas/activacion/clientes/91')->assertForbidden();
        $this->postJson('/proformas/activacion/clientes/91', [])->assertForbidden();
        $this->postJson('/proformas/activacion/clientes/91/eventos', [])->assertForbidden();
    }

    public function test_rutas_globales_requieren_autenticacion(): void
    {
        $this->get('/proformas/activacion/clientes?q=A091')->assertRedirect('/login');
        $this->get('/proformas/activacion/clientes/91')->assertRedirect('/login');
        $this->post('/proformas/activacion/clientes/91', [])->assertRedirect('/login');
        $this->post('/proformas/activacion/clientes/91/eventos', [])->assertRedirect('/login');
    }

    public function test_listado_sin_filas_muestra_entrada_global_y_un_solo_formulario(): void
    {
        $this->withoutExceptionHandling();
        $this->withoutVite();
        $this->prepareGlobalActivation();
        $response = $this->get('/proformas');
        $response->assertOk()->assertSee('id="activacion-global-abrir"', false)
            ->assertSee('id="activacion-buscar-cliente"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="activacion-form"'));
        $this->assertStringNotContainsString('!menu || !menuItems || tableRows.length === 0', $response->getContent());
    }

    public function test_filtro_sin_resultados_conserva_activacion_global(): void
    {
        $this->withoutVite();
        $this->prepareGlobalActivation();
        $service = $this->app->make(ProformasService::class);
        $service->shouldReceive('normalizePeriodoFilters')->once()->with(null, null)
            ->andReturn(['mes' => null, 'anio' => null]);
        $service->shouldReceive('paginateProformas')->once()
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15));
        $this->get('/proformas?empresa=sin-resultados')
            ->assertOk()->assertSee('No hay proformas para los filtros seleccionados.')
            ->assertSee('id="activacion-global-abrir"', false)
            ->assertSee('id="activacion-form"', false);
    }

    public function test_consulta_activacion_devuelve_datos_detectados(): void
    {
        $this->withoutMiddleware();
        $this->withSession([
            'rol_id' => 1,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(31)
            ->andReturn((object) [
                'id' => 31,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('obtenerDetalle')
            ->once()
            ->with('B476')
            ->andReturn([
                'codigo' => 'B476',
                'conexion' => 'mysql_213',
                'base' => 'b476',
                'servidor' => 'Servidor 213',
                'servidor_badge' => '213',
                'fecha_inicio_actual' => '2026-05-01',
                'fecha_fin_actual' => '2026-05-31',
                'fecha_inicio_global_actual' => '2026-05-01',
                'fecha_fin_global_actual' => '2026-05-31',
                'sincronizado' => true,
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->getJson(route('proformas.activacion.show', ['id' => 31]));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'codigo' => 'B476',
                    'conexion' => 'mysql_213',
                    'base' => 'b476',
                    'servidor' => 'Servidor 213',
                    'fecha_inicio_actual' => '2026-05-01',
                    'fecha_fin_actual' => '2026-05-31',
                    'sincronizado' => true,
                ],
            ]);
    }

    public function test_consulta_activacion_responde_error_amigable_si_no_hay_empresa(): void
    {
        $this->withoutMiddleware();
        $this->withSession([
            'rol_id' => 1,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(32)
            ->andReturn((object) [
                'id' => 32,
                'codigo' => 'B999',
            ]);

        $activacionService->shouldReceive('obtenerDetalle')
            ->once()
            ->with('B999')
            ->andThrow(new \RuntimeException('No fue posible encontrar la empresa en los servidores configurados.'));

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->getJson(route('proformas.activacion.show', ['id' => 32]));

        $response->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'message' => 'No fue posible encontrar la empresa en los servidores configurados.',
            ]);
    }

    public function test_guardar_activacion_actualiza_y_devuelve_datos_sincronizados(): void
    {
        $this->withoutMiddleware();

        $this->withSession([
            'rol_id' => 1,
            'usuario' => 'admin',
            'idusuario' => 99,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(33)
            ->andReturn((object) [
                'id' => 33,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('guardarActivacion')
            ->once()
            ->with('B476', '2026-06-01', '2026-06-30', 'admin (99)')
            ->andReturn([
                'codigo' => 'B476',
                'conexion' => 'mysql_167',
                'base' => 'b476',
                'servidor' => 'Servidor 167',
                'servidor_badge' => '167',
                'fecha_inicio_actual' => '2026-06-01',
                'fecha_fin_actual' => '2026-06-30',
                'fecha_inicio_global_actual' => '2026-06-01',
                'fecha_fin_global_actual' => '2026-06-30',
                'sincronizado' => true,
                'eventos_licencia' => [
                    'existe' => true,
                    'empresa' => 'B476',
                    'fecha_vencimiento_actual' => '2026-05-31',
                    'consulta_estado' => 'found',
                    'mensaje' => 'Licencia de Eventos encontrada.',
                ],
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->postJson(route('proformas.activacion.update', ['id' => 33]), [
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'La activación de la empresa se actualizó correctamente.',
                'data' => [
                    'codigo' => 'B476',
                    'conexion' => 'mysql_167',
                    'servidor' => 'Servidor 167',
                    'fecha_inicio_actual' => '2026-06-01',
                    'fecha_fin_actual' => '2026-06-30',
                    'sincronizado' => true,
                    'eventos_licencia' => [
                        'existe' => true,
                        'empresa' => 'B476',
                        'fecha_vencimiento_actual' => '2026-05-31',
                        'consulta_estado' => 'found',
                        'mensaje' => 'Licencia de Eventos encontrada.',
                    ],
                ],
            ]);
    }

    public function test_actualizar_licencia_eventos_devuelve_resumen_de_cambio(): void
    {
        $this->withoutMiddleware();

        $this->withSession([
            'rol_id' => 1,
            'usuario' => 'admin',
            'idusuario' => 99,
        ]);

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);
        $activacionService = Mockery::mock(EmpresaActivacionService::class);

        $service->shouldReceive('findProformaById')
            ->once()
            ->with(34)
            ->andReturn((object) [
                'id' => 34,
                'codigo' => 'B476',
            ]);

        $activacionService->shouldReceive('actualizarLicenciaEventos')
            ->once()
            ->with('B476', '2026-06-30', 'admin (99)')
            ->andReturn([
                'empresa' => 'B476',
                'fecha_vencimiento_anterior' => '2026-05-31',
                'fecha_vencimiento_nueva' => '2026-06-30',
            ]);

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);
        $this->app->instance(EmpresaActivacionService::class, $activacionService);

        $response = $this->postJson(route('proformas.activacion.eventos.update', ['id' => 34]), [
            'fecha_fin' => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'La licencia de Eventos se actualizó correctamente.',
                'data' => [
                    'empresa' => 'B476',
                    'fecha_vencimiento_anterior' => '2026-05-31',
                    'fecha_vencimiento_nueva' => '2026-06-30',
                ],
            ]);
    }
}
