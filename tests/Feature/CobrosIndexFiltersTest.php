<?php

namespace Tests\Feature;

use App\Services\CobrosService;
use App\Services\CobroExtraordinarioService;
use App\Services\ClienteRetiradoService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformaPreviewService;
use App\Services\ProformasService;
use App\Services\ProformaStoreService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CobrosIndexFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('valores_externos');
        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->string('mes')->nullable();
            $table->integer('aÃ±o')->nullable();
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
        });
    }

    public function test_aplica_mes_y_anio_actuales_por_defecto_si_no_vienen_en_request(): void
    {
        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $expectedFilters = [
            'mes' => 'junio',
            'anio' => 2026,
            'proforma' => null,
            'codigo' => null,
            'buscar' => null,
            'orden_fecha' => null,
            'grupo_fecha' => null,
            'filtro_nota' => null,
            'filtro_envio' => null,
        ];

        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));
        $cobrosService->shouldReceive('getPeriodSummary')
            ->once()
            ->with($expectedFilters)
            ->andReturn($this->emptySummary());

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index'));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }

    public function test_respeta_mes_y_anio_seleccionados_por_el_usuario(): void
    {
        $cobrosService = Mockery::mock(CobrosService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $expectedFilters = [
            'mes' => '5',
            'anio' => 2024,
            'proforma' => null,
            'codigo' => null,
            'buscar' => null,
            'orden_fecha' => null,
            'grupo_fecha' => null,
            'filtro_nota' => null,
            'filtro_envio' => null,
        ];

        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));
        $cobrosService->shouldReceive('getPeriodSummary')
            ->once()
            ->with($expectedFilters)
            ->andReturn($this->emptySummary());

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index', ['mes' => '5', 'anio' => 2024]));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }

    public function test_propaga_filtro_codigo_y_buscar_hacia_el_servicio(): void
    {
        $cobrosService = Mockery::mock(CobrosService::class);

        $expectedFilters = [
            'mes' => null,
            'anio' => null,
            'proforma' => null,
            'codigo' => 'B340',
            'buscar' => 'Martha TF',
            'orden_fecha' => null,
            'grupo_fecha' => null,
            'filtro_nota' => null,
            'filtro_envio' => null,
        ];

        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->with($expectedFilters)
            ->andReturn(new LengthAwarePaginator([], 0, 15));
        $cobrosService->shouldReceive('getPeriodSummary')
            ->once()
            ->with($expectedFilters)
            ->andReturn($this->emptySummary());

        $this->bindCobrosControllerDependencies($cobrosService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index', [
            'codigo' => 'B340',
            'buscar' => 'Martha TF',
        ]));

        $response->assertOk();
        $response->assertViewHas('filters', $expectedFilters);
    }

    public function test_oculta_herramienta_limpiar_lote_a_usuario_operativo_en_produccion(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->andReturn(new LengthAwarePaginator([], 0, 15));
        $cobrosService->shouldReceive('getPeriodSummary')
            ->once()
            ->andReturn($this->emptySummary());

        $this->bindCobrosControllerDependencies($cobrosService);

        $response = $this->withSession([
            'idusuario' => 2,
            'rol_nombre' => 'user',
        ])->get(route('cobros.index'));

        $response->assertOk();
        $response->assertDontSee('Limpiar lote pendiente de envio');
        $response->assertViewHas('canClearPendingBatch', false);
    }

    public function test_muestra_herramienta_limpiar_lote_a_administrador_en_produccion(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('paginateCobros')
            ->once()
            ->andReturn(new LengthAwarePaginator([], 0, 15));
        $cobrosService->shouldReceive('getPeriodSummary')
            ->once()
            ->andReturn($this->emptySummary());

        $this->bindCobrosControllerDependencies($cobrosService);

        $response = $this->withSession([
            'idusuario' => 1,
            'rol_nombre' => 'admin',
        ])->get(route('cobros.index'));

        $response->assertOk();
        $response->assertSee('Limpiar lote pendiente de envio');
        $response->assertViewHas('canClearPendingBatch', true);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('valores_externos');

        parent::tearDown();
    }

    private function bindCobrosControllerDependencies(CobrosService $cobrosService): void
    {
        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(CobroExtraordinarioService::class, Mockery::mock(CobroExtraordinarioService::class));
        $this->app->instance(ClienteRetiradoService::class, Mockery::mock(ClienteRetiradoService::class));
        $this->app->instance(ProformaPreviewService::class, Mockery::mock(ProformaPreviewService::class));
        $this->app->instance(ProformaStoreService::class, Mockery::mock(ProformaStoreService::class));
        $this->app->instance(ProformaPdfService::class, Mockery::mock(ProformaPdfService::class));
        $this->app->instance(ProformasService::class, Mockery::mock(ProformasService::class));
        $this->app->instance(ProformaEmailService::class, Mockery::mock(ProformaEmailService::class));
        $this->app->instance(RevisarProformaCalculator::class, Mockery::mock(RevisarProformaCalculator::class));
    }

    private function emptySummary(): object
    {
        return (object) [
            'total_facturas' => 0,
            'total_notas_debito' => 0,
            'total_notas_credito' => 0,
            'total_documentos_soporte' => 0,
            'total_notas_ajuste' => 0,
            'total_acuses' => 0,
            'valor_facturas' => 0,
            'valor_documentos' => 0,
            'valor_acuse' => 0,
            'valor_mensualidad' => 0,
            'valor_total' => 0,
        ];
    }
}
