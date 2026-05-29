<?php

namespace Tests\Unit;

use App\Services\CobrosService;
use App\Services\ClienteValorTotalCalculator;
use App\Services\ImportacionesService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Http\UploadedFile;
use Mockery;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportacionesServiceTest extends TestCase
{
    public function test_build_batch_from_semicolon_csv(): void
    {
        $service = $this->makeService();

        $csv = implode(PHP_EOL, [
            'A;B;NIT;EMISOR;FACTURAS;ND;NC',
            '1;2;900123456;SAS;2;1;0',
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'imp-csv-');
        file_put_contents($csvPath, $csv);
        $file = new UploadedFile($csvPath, 'facturas.csv', 'text/csv', null, true);

        $batch = $service->buildBatchFromUploads([
            'facturas_file' => $file,
            'soporte_file' => null,
            'recepcion_file' => null,
        ], 'mayo', 2026);

        @unlink($csvPath);

        $this->assertCount(1, $batch['entries']);
        $this->assertSame('900123456', $batch['entries'][0]['nit']);
        $this->assertSame('SAS', $batch['entries'][0]['emisor']);
        $this->assertSame(2.0, $batch['entries'][0]['facturas']);
        $this->assertSame(1.0, $batch['entries'][0]['nota_debito']);
        $this->assertSame(0.0, $batch['entries'][0]['nota_credito']);
        $this->assertCount(0, $batch['errors']);
    }

    public function test_build_batch_from_xlsx_supports_soporte_and_recepcion(): void
    {
        $service = $this->makeService();

        $xlsxPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imp-xlsx-' . uniqid() . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['A', 'B', 'NIT', 'EMISOR', 'SOPORTE', 'AJUSTE'],
            [1, 2, '901555777', 'PCS', 3, 1],
        ]);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $file = new UploadedFile($xlsxPath, 'soporte.xlsx', null, null, true);

        $batch = $service->buildBatchFromUploads([
            'facturas_file' => null,
            'soporte_file' => $file,
            'recepcion_file' => null,
        ], 'mayo', 2026);

        @unlink($xlsxPath);

        $this->assertCount(1, $batch['entries']);
        $this->assertSame('901555777', $batch['entries'][0]['nit']);
        $this->assertSame('PCS', $batch['entries'][0]['emisor']);
        $this->assertSame(3.0, $batch['entries'][0]['soporte']);
        $this->assertSame(1.0, $batch['entries'][0]['nota_ajuste']);
        $this->assertCount(0, $batch['errors']);
    }

    private function makeService(): ImportacionesService
    {
        return new ImportacionesService(
            Mockery::mock(CobrosService::class),
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );
    }
}
