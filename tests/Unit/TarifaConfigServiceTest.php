<?php

namespace Tests\Unit;

use App\Services\TarifaConfigService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TarifaConfigServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('config_tarifas');
        Schema::create('config_tarifas', function (Blueprint $table): void {
            $table->id();
            $table->string('categoria', 80)->default('global');
            $table->string('clave', 100);
            $table->decimal('valor', 15, 2)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['categoria', 'clave']);
        });
    }

    public function test_client_create_defaults_solo_retorna_tarifas_activas_y_mapea_recepcion(): void
    {
        $service = new TarifaConfigService();

        $service->updateMany([
            'vlrprincipal' => ['valor' => 250000, 'activo' => '1'],
            'numequipos' => ['valor' => 4, 'activo' => '1'],
            'vlrrecepcion' => ['valor' => 12000, 'activo' => '1'],
            'vlrsoporte' => ['valor' => 5000, 'activo' => '0'],
        ]);

        $defaults = $service->clientCreateDefaults();

        $this->assertSame('250000', $defaults['vlrprincipal']);
        $this->assertSame('4', $defaults['numequipos']);
        $this->assertSame('12000', $defaults['vlracuse']);
        $this->assertArrayNotHasKey('vlrrecepcion', $defaults);
        $this->assertArrayNotHasKey('vlrsoporte', $defaults);
    }
}
