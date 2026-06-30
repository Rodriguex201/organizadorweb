<?php

namespace Tests\Feature;

use App\Services\DirectorioApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DirectorioApiServiceTest extends TestCase
{
    public function test_should_use_external_api_puede_forzarse_en_local_windows(): void
    {
        config([
            'services.directorio_api.force_api' => true,
        ]);

        $this->assertTrue(DirectorioApiService::shouldUseExternalApi());
    }

    public function test_should_use_external_api_con_bandera_false_conserva_comportamiento_actual(): void
    {
        config([
            'services.directorio_api.force_api' => false,
        ]);

        $expected = app()->environment('production') && PHP_OS_FAMILY === 'Linux';

        $this->assertSame($expected, DirectorioApiService::shouldUseExternalApi());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clientes_potenciales');
        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->string('codigo')->nullable();
            $table->string('empresa')->nullable();
        });
    }

    public function test_url_vacia_registra_log_y_no_lanza_excepcion(): void
    {
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 569,
            'codigo' => 'B549',
            'empresa' => 'VITTAL CONFORT',
        ]);

        config([
            'services.directorio_api.url' => '',
            'services.directorio_api.token' => 'token-demo',
            'services.directorio_api.timeout' => 10,
            'services.directorio_api.verify_ssl' => true,
        ]);

        Http::fake();
        Log::spy();

        app(DirectorioApiService::class)->notificarClienteCreado([
            'clienteId' => 569,
            'codigo' => 'B549',
            'empresa' => 'VITTAL CONFORT',
        ]);

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 569)->first();

        $this->assertNotNull($cliente);
        Http::assertNothingSent();
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Directorio API: configuracion faltante para URL.'
                    && $context['status'] === 'config_error'
                    && $context['error'] === 'DIRECTORIO_API_URL no esta configurado.'
                    && $context['cliente_id'] === 569
                    && $context['codigo'] === 'B549';
            });
    }

    public function test_en_produccion_token_vacio_registra_error_y_no_envia_peticion(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        config([
            'services.directorio_api.url' => 'https://directorio.local/api/directorios/clientes',
            'services.directorio_api.token' => '',
            'services.directorio_api.timeout' => 10,
            'services.directorio_api.verify_ssl' => true,
        ]);

        Http::fake();
        Log::spy();

        app(DirectorioApiService::class)->notificarClienteCreado([
            'clienteId' => 580,
            'codigo' => 'B580',
            'empresa' => 'TOKEN VACIO SAS',
        ]);

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Directorio API: configuracion faltante para token.'
                    && $context['status'] === 'config_error'
                    && $context['error'] === 'DIRECTORIO_API_TOKEN no esta configurado.'
                    && $context['cliente_id'] === 580
                    && $context['codigo'] === 'B580';
            });
    }

    public function test_api_200_registra_exito(): void
    {
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 570,
            'codigo' => 'B550',
            'empresa' => 'ACME SAS',
        ]);

        config([
            'services.directorio_api.url' => 'https://directorio.local/api/directorios/clientes',
            'services.directorio_api.token' => 'token-demo',
            'services.directorio_api.timeout' => 10,
            'services.directorio_api.verify_ssl' => true,
        ]);

        Http::fake([
            'https://directorio.local/api/directorios/clientes' => Http::response([
                'ok' => true,
                'message' => 'Directorio creado',
                'ruta' => '/mnt/directorios/B550__ACME SAS',
            ], 200),
        ]);

        Log::spy();

        app(DirectorioApiService::class)->notificarClienteCreado([
            'clienteId' => 570,
            'codigo' => 'B550',
            'empresa' => 'ACME SAS',
        ]);

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 570)->first();

        $this->assertNotNull($cliente);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://directorio.local/api/directorios/clientes'
                && $request->hasHeader('Authorization', 'Bearer token-demo')
                && $request['clienteId'] === 570
                && $request['codigo'] === 'B550'
                && $request['empresa'] === 'ACME SAS';
        });

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'Directorio API: response'
                    && $context['status'] === 'success'
                    && $context['http_status'] === 200
                    && $context['cliente_id'] === 570
                    && $context['codigo'] === 'B550';
            })
            ->once();
    }

    public function test_api_500_registra_error_y_cliente_sigue_guardado(): void
    {
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 571,
            'codigo' => 'B551',
            'empresa' => 'OMEGA SAS',
        ]);

        config([
            'services.directorio_api.url' => 'https://directorio.local/api/directorios/clientes',
            'services.directorio_api.token' => 'token-demo',
            'services.directorio_api.timeout' => 10,
            'services.directorio_api.verify_ssl' => true,
        ]);

        Http::fake([
            'https://directorio.local/api/directorios/clientes' => Http::response([
                'ok' => false,
                'message' => 'Fallo interno',
                'error' => 'No se pudo crear el directorio',
            ], 500),
        ]);

        Log::spy();

        app(DirectorioApiService::class)->notificarClienteCreado([
            'clienteId' => 571,
            'codigo' => 'B551',
            'empresa' => 'OMEGA SAS',
        ]);

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 571)->first();

        $this->assertNotNull($cliente);
        Http::assertSentCount(2);
    }

    public function test_timeout_o_excepcion_http_registra_exception_y_cliente_sigue_guardado(): void
    {
        DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 572,
            'codigo' => 'B552',
            'empresa' => 'SIGMA SAS',
        ]);

        config([
            'services.directorio_api.url' => 'https://directorio.local/api/directorios/clientes',
            'services.directorio_api.token' => 'token-demo',
            'services.directorio_api.timeout' => 10,
            'services.directorio_api.verify_ssl' => true,
        ]);

        Http::fake(function (): void {
            throw new \RuntimeException('Timeout simulado de API');
        });

        Log::spy();

        app(DirectorioApiService::class)->notificarClienteCreado([
            'clienteId' => 572,
            'codigo' => 'B552',
            'empresa' => 'SIGMA SAS',
        ]);

        $cliente = DB::table('clientes_potenciales')->where('idclientes_potenciales', 572)->first();

        $this->assertNotNull($cliente);
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Directorio API: exception'
                    && $context['status'] === 'exception'
                    && $context['cliente_id'] === 572
                    && $context['codigo'] === 'B552'
                    && $context['error'] === 'Timeout simulado de API'
                    && $context['exception_class'] === \RuntimeException::class
                    && is_string($context['stacktrace'])
                    && $context['stacktrace'] !== '';
            })
            ->once();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clientes_potenciales');

        parent::tearDown();
    }
}
