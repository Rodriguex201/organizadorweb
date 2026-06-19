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
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class CobrosMassGenerationProtectedProformasTest extends TestCase
{
    public function test_generacion_masiva_contabiliza_proformas_protegidas_como_omitidas_y_no_regenera_pdf(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobroExtraordinarioService = Mockery::mock(CobroExtraordinarioService::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $candidatoProtegido = (object) [
            'id_cobro' => 14877,
            'codigo' => 'B292',
            'empresa' => 'Cliente protegido',
            'nombre' => 'Cliente protegido',
            'estado_facturacion' => 'ACTIVO',
        ];
        $candidatoNuevo = (object) [
            'id_cobro' => 15057,
            'codigo' => 'B548',
            'empresa' => 'Cliente nuevo',
            'nombre' => 'Cliente nuevo',
            'estado_facturacion' => 'ACTIVO',
        ];
        $cobroProtegido = (object) ['id_cobro' => 14877, 'cliente_empresa' => 'Cliente protegido'];
        $cobroNuevo = (object) ['id_cobro' => 15057, 'cliente_empresa' => 'Cliente nuevo'];

        $cobrosService->shouldReceive('buildMassGenerationDebugSnapshot')
            ->once()
            ->andReturn(['llegan_a_generacion' => 2]);

        $cobrosService->shouldReceive('findCobroCandidatesForMassGeneration')
            ->once()
            ->with(Mockery::type('array'), 27)
            ->andReturn(new Collection([$candidatoProtegido, $candidatoNuevo]));

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(14877)
            ->andReturn($cobroProtegido);

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(15057)
            ->andReturn($cobroNuevo);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->twice()
            ->andReturn(false);

        $storeService->shouldReceive('storeFromCobro')
            ->once()
            ->with($cobroProtegido, [], false, true)
            ->andReturn([
                'created' => false,
                'duplicated' => false,
                'protected' => true,
                'omitted' => true,
                'proforma_id' => 2989,
                'message' => 'Proforma ya enviada/facturada. Se omite de la generación masiva.',
            ]);

        $storeService->shouldReceive('storeFromCobro')
            ->once()
            ->with($cobroNuevo, [], false, true)
            ->andReturn([
                'created' => true,
                'duplicated' => false,
                'proforma_id' => 3001,
                'message' => 'Proforma guardada correctamente en sg_proform y sg_proford.',
            ]);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(3001)
            ->andReturn([
                'absolute_path' => 'C:\\tmp\\proforma-3001.pdf',
                'filename' => 'proforma-3001.pdf',
            ]);

        $proformasService->shouldReceive('findProformaById')
            ->once()
            ->with(3001)
            ->andReturn((object) [
                'id' => 3001,
                'nro_prof' => '5006',
                'estado' => 2,
                'enviado' => 0,
                'emp' => 'Cliente nuevo',
            ]);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(CobroExtraordinarioService::class, $cobroExtraordinarioService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->post(route('cobros.proformas-masivo', ['grupo' => 27]), [
            'mes' => 'junio',
            'anio' => 2026,
        ]);

        $response->assertRedirect(route('cobros.index', ['mes' => 'junio', 'anio' => 2026]));
        $response->assertSessionHas('status_type', 'success');
        $response->assertSessionHas('status', 'Generacion masiva grupo 27 finalizada. Generadas: 1. Actualizadas: 0. Omitidas protegidas: 1. Omitidas: 1. Fallidas: 0.');
    }

    public function test_reinicia_lote_pendiente_del_mismo_grupo_y_conserva_solo_la_corrida_actual(): void
    {
        $this->withoutMiddleware();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobroExtraordinarioService = Mockery::mock(CobroExtraordinarioService::class);
        $clienteRetiradoService = Mockery::mock(ClienteRetiradoService::class);
        $previewService = Mockery::mock(ProformaPreviewService::class);
        $storeService = Mockery::mock(ProformaStoreService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $proformasService = Mockery::mock(ProformasService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $calculatorService = Mockery::mock(RevisarProformaCalculator::class);

        $candidatoActualizado = (object) [
            'id_cobro' => 15060,
            'codigo' => 'B560',
            'empresa' => 'Cliente actualizado',
            'nombre' => 'Cliente actualizado',
            'estado_facturacion' => 'ACTIVO',
        ];
        $cobroActualizado = (object) ['id_cobro' => 15060, 'cliente_empresa' => 'Cliente actualizado'];
        $proformaActualizada = (object) [
            'id' => 4002,
            'nro_prof' => '5102',
            'estado' => 2,
            'enviado' => 0,
            'emp' => 'Cliente actualizado',
        ];

        $cobrosService->shouldReceive('buildMassGenerationDebugSnapshot')
            ->once()
            ->andReturn(['llegan_a_generacion' => 1]);

        $cobrosService->shouldReceive('findCobroCandidatesForMassGeneration')
            ->once()
            ->with(Mockery::type('array'), 27)
            ->andReturn(new Collection([$candidatoActualizado]));

        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(15060)
            ->andReturn($cobroActualizado);

        $clienteRetiradoService->shouldReceive('estaRetirado')
            ->once()
            ->andReturn(false);

        $storeService->shouldReceive('storeFromCobro')
            ->once()
            ->with($cobroActualizado, [], false, true)
            ->andReturn([
                'created' => false,
                'duplicated' => true,
                'proforma_id' => 4002,
                'message' => 'La proforma ya existia para NIT, mes, año y emisora. Se actualizo cabecera y detalle con los valores vigentes.',
            ]);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(4002)
            ->andReturn([
                'absolute_path' => 'C:\\tmp\\proforma-4002.pdf',
                'filename' => 'proforma-4002.pdf',
            ]);

        $proformasService->shouldReceive('findProformaById')
            ->once()
            ->with(4002)
            ->andReturn($proformaActualizada);

        $this->app->instance(CobrosService::class, $cobrosService);
        $this->app->instance(CobroExtraordinarioService::class, $cobroExtraordinarioService);
        $this->app->instance(ClienteRetiradoService::class, $clienteRetiradoService);
        $this->app->instance(ProformaPreviewService::class, $previewService);
        $this->app->instance(ProformaStoreService::class, $storeService);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformasService::class, $proformasService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(RevisarProformaCalculator::class, $calculatorService);

        $response = $this->withSession([
            'cobros.proformas_listas_para_envio' => [
                'grupo' => 27,
                'filters' => ['mes' => 'mayo', 'anio' => 2026],
                'proformas' => [
                    ['id' => 9999, 'empresa' => 'Lote anterior'],
                ],
            ],
        ])->post(route('cobros.proformas-masivo', ['grupo' => 27]), [
            'mes' => 'junio',
            'anio' => 2026,
        ]);

        $response->assertRedirect(route('cobros.index', ['mes' => 'junio', 'anio' => 2026]));
        $response->assertSessionHas('cobros.proformas_listas_para_envio', function (array $payload): bool {
            return (int) ($payload['grupo'] ?? 0) === 27
                && ($payload['filters']['mes'] ?? null) === 'junio'
                && (int) ($payload['filters']['anio'] ?? 0) === 2026
                && ($payload['proformas'] ?? []) === [
                    ['id' => 4002, 'empresa' => 'Cliente actualizado'],
                ];
        });
    }
}
