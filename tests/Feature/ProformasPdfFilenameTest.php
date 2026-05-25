<?php

namespace Tests\Feature;

use App\Services\ProformaDashboardExportService;
use App\Services\ProformaEmailService;
use App\Services\ProformaPdfService;
use App\Services\ProformasService;
use Mockery;
use Tests\TestCase;

class ProformasPdfFilenameTest extends TestCase
{
    public function test_show_pdf_usa_nombre_amigable_en_content_disposition(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(15, false)
            ->andReturn([
                'absolute_path' => __FILE__,
                'filename' => 'proforma-interna.pdf',
            ]);

        $pdfService->shouldReceive('buildBrowserFilename')
            ->once()
            ->with(15)
            ->andReturn('PROFORMA_TECH_JP_MAYO_2026.pdf');

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->get(route('proformas.pdf.show', ['id' => 15]));

        $response->assertOk();
        $this->assertSame(
            'inline; filename="PROFORMA_TECH_JP_MAYO_2026.pdf"; filename*=UTF-8\'\'PROFORMA_TECH_JP_MAYO_2026.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_download_pdf_usa_nombre_amigable_en_descarga(): void
    {
        $this->withoutMiddleware();

        $service = Mockery::mock(ProformasService::class);
        $pdfService = Mockery::mock(ProformaPdfService::class);
        $emailService = Mockery::mock(ProformaEmailService::class);
        $dashboardExportService = Mockery::mock(ProformaDashboardExportService::class);

        $pdfService->shouldReceive('generateForProformaId')
            ->once()
            ->with(25)
            ->andReturn([
                'absolute_path' => __FILE__,
                'filename' => 'proforma-interna.pdf',
            ]);

        $pdfService->shouldReceive('buildBrowserFilename')
            ->once()
            ->with(25)
            ->andReturn('PROFORMA_SIN_EMPRESA_MAYO_2026.pdf');

        $this->app->instance(ProformasService::class, $service);
        $this->app->instance(ProformaPdfService::class, $pdfService);
        $this->app->instance(ProformaEmailService::class, $emailService);
        $this->app->instance(ProformaDashboardExportService::class, $dashboardExportService);

        $response = $this->get(route('proformas.pdf.download', ['id' => 25]));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment; filename=PROFORMA_SIN_EMPRESA_MAYO_2026.pdf',
            (string) $response->headers->get('content-disposition')
        );
    }
}
