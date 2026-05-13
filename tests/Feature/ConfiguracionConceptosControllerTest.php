<?php

namespace Tests\Feature;

use App\Models\Concepto;
use App\Services\ConceptosConfigService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ConfiguracionConceptosControllerTest extends TestCase
{
    public function test_bloquea_cambio_de_codigo_cuando_el_servicio_lo_valida(): void
    {
        $this->withoutMiddleware();
        Schema::dropIfExists('conceptos');
        Schema::create('conceptos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 150);
            $table->string('cuenta', 30)->nullable();
            $table->boolean('activo')->default(true);
        });

        $service = Mockery::mock(ConceptosConfigService::class);
        $service->shouldReceive('update')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'codigo' => 'No se puede cambiar el código.',
            ]));

        $this->app->instance(ConceptosConfigService::class, $service);

        $concepto = new Concepto([
            'id' => 15,
            'codigo' => '0010',
            'nombre' => 'Mensualidad',
            'activo' => true,
        ]);
        $concepto->exists = true;
        Route::bind('concepto', fn () => $concepto);

        $response = $this->from(route('configuracion.conceptos.index'))
            ->withSession(['rol_nombre' => 'admin'])
            ->put(route('configuracion.conceptos.update', 15), [
                'codigo' => 'CAMBIO',
                'nombre' => 'Mensualidad actualizada',
                'cuenta' => '',
                'activo' => '1',
                'form_mode' => 'edit',
                'concepto_id' => 15,
            ]);

        $response->assertRedirect(route('configuracion.conceptos.index'));
        $response->assertSessionHasErrors('codigo');
    }

    public function test_muestra_warning_cuando_no_se_puede_eliminar_un_concepto(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ConceptosConfigService::class);
        $service->shouldReceive('delete')
            ->once()
            ->andReturn([
                'deleted' => false,
                'message' => 'Solo se permite desactivarlo.',
                'status_type' => 'warning',
            ]);

        $this->app->instance(ConceptosConfigService::class, $service);

        $concepto = new Concepto([
            'id' => 20,
            'codigo' => '0011',
            'nombre' => 'Terminales extra',
            'activo' => true,
        ]);
        $concepto->exists = true;
        Route::bind('concepto', fn () => $concepto);

        $response = $this->withSession(['rol_nombre' => 'admin'])
            ->delete(route('configuracion.conceptos.destroy', 20));

        $response->assertRedirect(route('configuracion.conceptos.index'));
        $response->assertSessionHas('status_type', 'warning');
        $response->assertSessionHas('status', 'Solo se permite desactivarlo.');
    }

    public function test_puede_activar_o_desactivar_un_concepto_desde_el_controlador(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ConceptosConfigService::class);
        $service->shouldReceive('toggleActive')
            ->once()
            ->andReturn(false);

        $this->app->instance(ConceptosConfigService::class, $service);

        $concepto = new Concepto([
            'id' => 25,
            'codigo' => '0101',
            'nombre' => 'Recepción',
            'activo' => true,
        ]);
        $concepto->exists = true;
        Route::bind('concepto', fn () => $concepto);

        $response = $this->withSession(['rol_nombre' => 'admin'])
            ->patch(route('configuracion.conceptos.toggle', 25));

        $response->assertRedirect(route('configuracion.conceptos.index'));
        $response->assertSessionHas('status', 'Concepto desactivado correctamente.');
        $response->assertSessionHas('status_type', 'success');
    }
}
