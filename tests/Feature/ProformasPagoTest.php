<?php

namespace Tests\Feature;

use App\Services\EstadoProformaConfigService;
use App\Services\ProformasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProformasPagoTest extends TestCase
{
    private string $originalDefaultConnection;

    private array $originalSqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalSqliteConnection = (array) config('database.connections.sqlite', []);

        DB::purge($this->originalDefaultConnection);
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::setDefaultConnection('sqlite');
        DB::purge('sqlite');

        $this->withoutMiddleware();
        Storage::fake('local');
        Carbon::setTestNow('2026-08-29 10:30:00');

        Schema::dropIfExists('configuracion_estados_proforma');
        Schema::dropIfExists('sg_proform');
        Schema::dropIfExists('valores_externos');

        Schema::create('sg_proform', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_cobro')->nullable();
            $table->integer('estado');
            $table->date('fpag')->nullable();
            $table->string('fpago')->nullable();
            $table->string('comprobante_pago')->nullable();
            $table->string('nit')->nullable();
            $table->integer('mes')->nullable();
            $table->integer('anio')->nullable();
            $table->string('emisora')->nullable();
        });

        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->increments('id_cobro');
            $table->integer('Proforma')->nullable();
        });

        Schema::create('configuracion_estados_proforma', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('estado_codigo')->unique();
            $table->string('estado_nombre');
            $table->string('color_fondo');
            $table->string('color_texto');
            $table->boolean('activo')->default(true);
        });

        foreach (EstadoProformaConfigService::DEFAULTS as $codigo => $estado) {
            DB::table('configuracion_estados_proforma')->insert([
                'estado_codigo' => $codigo,
                'estado_nombre' => $estado['estado_nombre'],
                'color_fondo' => $estado['color_fondo'],
                'color_texto' => $estado['color_texto'],
                'activo' => 1,
            ]);
        }
    }

    public function test_falla_al_pagar_sin_fpago(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fpago');

        $this->assertProformaSinPagar($proformaId);
    }

    public function test_falla_al_pagar_con_metodo_no_permitido(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TARJETA',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fpago');

        $this->assertProformaSinPagar($proformaId);
    }

    public function test_acepta_efectivo(): void
    {
        $this->assertMetodoPagoAceptado('EFECTIVO');
    }

    public function test_acepta_transferencia(): void
    {
        $this->assertMetodoPagoAceptado('TRANSFERENCIA');
    }

    public function test_acepta_consignacion(): void
    {
        $this->assertMetodoPagoAceptado('CONSIGNACIÓN');
    }

    public function test_transferencia_sin_comprobante_es_rechazada(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TRANSFERENCIA',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante_pago');

        $this->assertProformaSinPagar($proformaId);
    }

    public function test_consignacion_sin_comprobante_es_rechazada(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'CONSIGNACIÓN',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante_pago');

        $this->assertProformaSinPagar($proformaId);
    }

    public function test_efectivo_sin_comprobante_es_aceptado_y_persiste_null(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'EFECTIVO',
        ])->assertOk();

        $this->assertDatabaseHas('sg_proform', [
            'id' => $proformaId,
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'EFECTIVO',
            'comprobante_pago' => null,
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_archivo_con_extension_no_permitida_es_rechazado(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TRANSFERENCIA',
            'comprobante_pago' => UploadedFile::fake()->create('comprobante.exe', 100, 'application/octet-stream'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante_pago');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_archivo_superior_a_diez_mb_es_rechazado(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TRANSFERENCIA',
            'comprobante_pago' => UploadedFile::fake()->create('comprobante.pdf', 10 * 1024 + 1, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante_pago');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_comprobante_valido_se_almacena_y_persiste_como_ruta_relativa(): void
    {
        $proformaId = $this->crearProforma();

        $response = $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TRANSFERENCIA',
            'comprobante_pago' => $this->comprobantePdfValido(),
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $relativePath = (string) DB::table('sg_proform')->where('id', $proformaId)->value('comprobante_pago');

        $this->assertMatchesRegularExpression(
            '#^proformas/comprobantes/'.$proformaId.'/[0-9a-f-]{36}\.pdf$#',
            $relativePath,
        );
        Storage::disk('local')->assertExists($relativePath);
        $this->assertSame(route('proformas.comprobante-pago.show', ['id' => $proformaId]), $response->json('comprobante_url'));
    }

    public function test_fallo_de_base_de_datos_elimina_el_archivo_recien_almacenado(): void
    {
        $proformaId = $this->crearProforma();
        Schema::drop('valores_externos');

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'CONSIGNACIÓN',
            'comprobante_pago' => $this->comprobantePdfValido(),
        ])->assertInternalServerError();

        $this->assertSame([], Storage::disk('local')->allFiles('proformas/comprobantes/'.$proformaId));
        $this->assertProformaSinPagar($proformaId);
    }

    public function test_endpoint_de_visualizacion_solo_sirve_a_roles_autorizados(): void
    {
        $proformaId = $this->crearProforma();
        $relativePath = 'proformas/comprobantes/'.$proformaId.'/comprobante.pdf';
        Storage::disk('local')->put($relativePath, '%PDF-1.4 comprobante');
        DB::table('sg_proform')->where('id', $proformaId)->update([
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpag' => '2026-08-29',
            'fpago' => 'TRANSFERENCIA',
            'comprobante_pago' => $relativePath,
        ]);

        $this->withMiddleware();
        $url = route('proformas.comprobante-pago.show', ['id' => $proformaId]);

        $this->get($url)->assertRedirect('/login');
        $this->withSession(['idusuario' => 9, 'rol_nombre' => 'consulta'])
            ->get($url)
            ->assertForbidden();
        $response = $this->withSession(['idusuario' => 9, 'rol_nombre' => 'user'])
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_persiste_estado_fecha_y_metodo_y_conserva_sincronizacion_externa(): void
    {
        $proformaId = $this->crearProforma();

        $this->enviarPago($proformaId, [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => 'TRANSFERENCIA',
            'comprobante_pago' => $this->comprobantePdfValido(),
        ])->assertOk();

        $this->assertDatabaseHas('sg_proform', [
            'id' => $proformaId,
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpag' => '2026-08-29',
            'fpago' => 'TRANSFERENCIA',
        ]);
        $this->assertDatabaseHas('valores_externos', [
            'id_cobro' => 100,
            'Proforma' => ProformasService::ESTADO_PAGADA,
        ]);
    }

    public function test_otro_cambio_de_estado_funciona_sin_fpago(): void
    {
        $proformaId = $this->crearProforma();

        $this->patchJson(route('proformas.estado.update', ['id' => $proformaId]), [
            'estado' => ProformasService::ESTADO_ENVIADA,
        ])->assertOk();

        $this->assertDatabaseHas('sg_proform', [
            'id' => $proformaId,
            'estado' => ProformasService::ESTADO_ENVIADA,
            'fpag' => null,
            'fpago' => null,
            'comprobante_pago' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        Schema::dropIfExists('configuracion_estados_proforma');
        Schema::dropIfExists('sg_proform');
        Schema::dropIfExists('valores_externos');

        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite' => $this->originalSqliteConnection,
        ]);
        DB::setDefaultConnection($this->originalDefaultConnection);

        parent::tearDown();
    }

    private function crearProforma(): int
    {
        DB::table('valores_externos')->insert([
            'id_cobro' => 100,
            'Proforma' => ProformasService::ESTADO_GENERADA,
        ]);

        return (int) DB::table('sg_proform')->insertGetId([
            'id_cobro' => 100,
            'estado' => ProformasService::ESTADO_GENERADA,
            'fpag' => null,
            'fpago' => null,
            'comprobante_pago' => null,
            'nit' => '900123456',
            'mes' => 8,
            'anio' => 2026,
            'emisora' => 'SAS',
        ]);
    }

    private function assertMetodoPagoAceptado(string $metodoPago): void
    {
        $proformaId = $this->crearProforma();

        $payload = [
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => $metodoPago,
        ];

        if ($metodoPago !== 'EFECTIVO') {
            $payload['comprobante_pago'] = $this->comprobantePdfValido();
        }

        $this->enviarPago($proformaId, $payload)->assertOk();

        $this->assertDatabaseHas('sg_proform', [
            'id' => $proformaId,
            'estado' => ProformasService::ESTADO_PAGADA,
            'fpago' => $metodoPago,
        ]);
    }

    private function assertProformaSinPagar(int $proformaId): void
    {
        $this->assertDatabaseHas('sg_proform', [
            'id' => $proformaId,
            'estado' => ProformasService::ESTADO_GENERADA,
            'fpag' => null,
            'fpago' => null,
            'comprobante_pago' => null,
        ]);
    }

    private function enviarPago(int $proformaId, array $payload)
    {
        return $this->post(
            route('proformas.estado.update', ['id' => $proformaId]),
            ['_method' => 'PATCH'] + $payload,
            [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );
    }

    private function comprobantePdfValido(): UploadedFile
    {
        return UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');
    }
}
