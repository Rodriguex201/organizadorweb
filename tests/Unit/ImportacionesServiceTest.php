<?php

namespace Tests\Unit;

use App\Services\CobrosService;
use App\Services\ClienteValorTotalCalculator;
use App\Services\ImportacionesService;
use App\Services\RevisarProformaCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_build_batch_ignores_soporte_rows_without_documentos(): void
    {
        $service = $this->makeService();

        $xlsxPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imp-xlsx-soporte-' . uniqid() . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['A', 'B', 'NIT', 'EMISOR', 'SOPORTE', 'AJUSTE'],
            [1, 2, '901111111', 'SAS', 0, null],
            [1, 2, '901222222', 'SAS', null, 0],
            [1, 2, '901333333', 'SAS', 0, 1],
            [1, 2, '901444444', 'SAS', 2, 0],
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

        $this->assertCount(2, $batch['entries']);
        $this->assertSame(['901333333', '901444444'], array_column($batch['entries'], 'nit'));
        $this->assertSame(0.0, $batch['entries'][0]['soporte']);
        $this->assertSame(1.0, $batch['entries'][0]['nota_ajuste']);
        $this->assertSame(2.0, $batch['entries'][1]['soporte']);
        $this->assertSame(0.0, $batch['entries'][1]['nota_ajuste']);
        $this->assertCount(0, $batch['errors']);
    }

    public function test_build_preview_shows_base_generation_notice_when_period_has_no_base_rows(): void
    {
        $this->createImportPreviewTables();

        $service = $this->makeService();

        $preview = $service->buildPreview([
            'periodo' => ['mes' => 'junio', 'anio' => 2026],
            'sources' => [],
            'entries' => [[
                'nit' => '900123456',
                'emisor' => 'SAS',
                'facturas' => 2.0,
                'nota_debito' => 0.0,
                'nota_credito' => 0.0,
                'soporte' => 1.0,
                'nota_ajuste' => 0.0,
                'acuse' => 1.0,
                'sources' => ['facturas.csv'],
                'rows' => [2],
            ]],
            'errors' => [],
        ]);

        $this->assertTrue($preview['requires_base_generation']);
        $this->assertSame([], $preview['rows']);
        $this->assertSame(1, $preview['summary']['total']);
        $this->assertStringContainsString('No existen registros base', (string) $preview['base_generation_notice']);
    }

    public function test_build_preview_matches_existing_base_using_nit_and_dv(): void
    {
        $this->createImportPreviewTables();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(77)
            ->andReturn((object) ['id_cobro' => 77]);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->andReturn([
                'numero_equipos' => 0,
                'valor_principal' => 0,
                'valor_terminal' => 0,
                'numero_equipos_extra' => 0,
                'valor_equipo_extra' => 0,
                'empleados' => 0,
                'valor_nomina' => 0,
                'numero_moviles' => 0,
                'valor_movil' => 0,
                'facturas' => 0,
                'nota_debito' => 0,
                'nota_credito' => 0,
                'soporte' => 0,
                'nota_ajuste' => 0,
                'acuse' => 0,
                'otro_valor_extra' => 0,
                'otro_valor_extra_2' => 0,
                'precio_factura' => 0,
                'precio_soporte' => 0,
                'precio_acuse' => 0,
            ]);

        $service = new ImportacionesService(
            $cobrosService,
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );

        \DB::table('clientes_potenciales')->insert([
            'idclientes_potenciales' => 15,
            'nit' => '900770401',
            'dv' => '8',
            'empresa' => 'RM SOFT CASA DE SOFTWARE SAS',
            'nombre' => 'RM SOFT CASA DE SOFTWARE SAS',
            'regimen' => 'SAS',
        ]);

        \DB::table('valores_externos')->insert([
            'id_cobro' => 77,
            'id_cliente' => '15',
            'mes' => 'junio',
            'aÃ±o' => 2026,
        ]);

        $preview = $service->buildPreview([
            'periodo' => ['mes' => 'junio', 'anio' => 2026],
            'sources' => [],
            'entries' => [[
                'nit' => '9007704018',
                'emisor' => 'SAS',
                'facturas' => 2.0,
                'nota_debito' => 0.0,
                'nota_credito' => 0.0,
                'soporte' => 1.0,
                'nota_ajuste' => 0.0,
                'acuse' => 1.0,
                'sources' => ['facturas.csv'],
                'rows' => [2],
            ]],
            'errors' => [],
        ]);

        $this->assertFalse($preview['requires_base_generation']);
        $this->assertSame(1, $preview['summary']['ready']);
        $this->assertSame(0, $preview['summary']['with_errors']);
        $this->assertSame(77, $preview['rows'][0]['id_cobro']);
    }

    public function test_build_preview_marks_duplicate_nit_matches_as_ambiguous(): void
    {
        $this->createImportPreviewTables();

        $service = $this->makeService();

        \DB::table('clientes_potenciales')->insert([
            [
                'idclientes_potenciales' => 15,
                'nit' => '900770401',
                'dv' => '8',
                'empresa' => 'Cliente A',
                'nombre' => 'Cliente A',
                'regimen' => 'SAS',
            ],
            [
                'idclientes_potenciales' => 16,
                'nit' => '900770401',
                'dv' => '8',
                'empresa' => 'Cliente B',
                'nombre' => 'Cliente B',
                'regimen' => 'PCS',
            ],
        ]);

        \DB::table('valores_externos')->insert([
            [
                'id_cobro' => 77,
                'id_cliente' => '15',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
            [
                'id_cobro' => 78,
                'id_cliente' => '16',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
        ]);

        $preview = $service->buildPreview([
            'periodo' => ['mes' => 'junio', 'anio' => 2026],
            'sources' => [],
            'entries' => [[
                'nit' => '9007704018',
                'emisor' => 'SAS',
                'facturas' => 2.0,
                'nota_debito' => 0.0,
                'nota_credito' => 0.0,
                'soporte' => 1.0,
                'nota_ajuste' => 0.0,
                'acuse' => 1.0,
                'sources' => ['facturas.csv'],
                'rows' => [2],
            ]],
            'errors' => [],
        ]);

        $this->assertFalse($preview['requires_base_generation']);
        $this->assertSame(0, $preview['summary']['ready']);
        $this->assertSame(1, $preview['summary']['with_errors']);
        $this->assertSame('error', $preview['rows'][0]['status']);
        $this->assertStringContainsString('Multiples registros para el mismo NIT', (string) $preview['rows'][0]['error_message']);
    }

    public function test_assign_manual_match_resolves_ambiguous_entry(): void
    {
        $this->createImportPreviewTables();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(78)
            ->andReturn((object) ['id_cobro' => 78]);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->andReturn([
                'numero_equipos' => 0,
                'valor_principal' => 0,
                'valor_terminal' => 0,
                'numero_equipos_extra' => 0,
                'valor_equipo_extra' => 0,
                'empleados' => 0,
                'valor_nomina' => 0,
                'numero_moviles' => 0,
                'valor_movil' => 0,
                'facturas' => 0,
                'nota_debito' => 0,
                'nota_credito' => 0,
                'soporte' => 0,
                'nota_ajuste' => 0,
                'acuse' => 0,
                'otro_valor_extra' => 0,
                'otro_valor_extra_2' => 0,
                'precio_factura' => 0,
                'precio_soporte' => 0,
                'precio_acuse' => 0,
            ]);

        $service = new ImportacionesService(
            $cobrosService,
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );

        \DB::table('clientes_potenciales')->insert([
            [
                'idclientes_potenciales' => 15,
                'nit' => '900770401',
                'dv' => '8',
                'empresa' => 'Cliente A',
                'nombre' => 'Cliente A',
                'codigo' => 'A001',
                'regimen' => 'SAS',
            ],
            [
                'idclientes_potenciales' => 16,
                'nit' => '900770401',
                'dv' => '8',
                'empresa' => 'Cliente B',
                'nombre' => 'Cliente B',
                'codigo' => 'A002',
                'regimen' => 'PCS',
            ],
        ]);

        \DB::table('valores_externos')->insert([
            [
                'id_cobro' => 77,
                'id_cliente' => '15',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
            [
                'id_cobro' => 78,
                'id_cliente' => '16',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
        ]);

        $batch = [
            'periodo' => ['mes' => 'junio', 'anio' => 2026],
            'sources' => [],
            'entries' => [[
                'entry_id' => 'entry-1',
                'nit' => '9007704018',
                'emisor' => 'SAS',
                'facturas' => 2.0,
                'nota_debito' => 0.0,
                'nota_credito' => 0.0,
                'soporte' => 1.0,
                'nota_ajuste' => 0.0,
                'acuse' => 1.0,
                'sources' => ['facturas.csv'],
                'rows' => [2],
            ]],
            'errors' => [],
        ];

        $assignmentResult = $service->assignManualMatch($batch, 'entry-1', 78);
        $preview = $service->buildPreview($assignmentResult['batch']);

        $this->assertSame(1, $preview['summary']['ready']);
        $this->assertSame(0, $preview['summary']['with_errors']);
        $this->assertTrue($preview['rows'][0]['resolved_manually']);
        $this->assertSame(78, $preview['rows'][0]['id_cobro']);
        $this->assertSame('9007704018', $assignmentResult['nit']);
        $this->assertSame(1, $assignmentResult['resolved_entries']);
    }

    public function test_assign_manual_match_resolves_all_entries_with_same_nit(): void
    {
        $this->createImportPreviewTables();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->twice()
            ->with(78)
            ->andReturn((object) ['id_cobro' => 78]);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->twice()
            ->andReturn([
                'numero_equipos' => 0,
                'valor_principal' => 0,
                'valor_terminal' => 0,
                'numero_equipos_extra' => 0,
                'valor_equipo_extra' => 0,
                'empleados' => 0,
                'valor_nomina' => 0,
                'numero_moviles' => 0,
                'valor_movil' => 0,
                'facturas' => 0,
                'nota_debito' => 0,
                'nota_credito' => 0,
                'soporte' => 0,
                'nota_ajuste' => 0,
                'acuse' => 0,
                'otro_valor_extra' => 0,
                'otro_valor_extra_2' => 0,
                'precio_factura' => 0,
                'precio_soporte' => 0,
                'precio_acuse' => 0,
            ]);

        $service = new ImportacionesService(
            $cobrosService,
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );

        \DB::table('clientes_potenciales')->insert([
            [
                'idclientes_potenciales' => 15,
                'nit' => '16137989',
                'dv' => '6',
                'empresa' => 'Cliente A',
                'nombre' => 'Cliente A',
                'codigo' => 'A217',
                'regimen' => 'SAS',
            ],
            [
                'idclientes_potenciales' => 16,
                'nit' => '16137989',
                'dv' => '6',
                'empresa' => 'Cliente B',
                'nombre' => 'Cliente B',
                'codigo' => 'A223',
                'regimen' => 'SAS',
            ],
        ]);

        \DB::table('valores_externos')->insert([
            [
                'id_cobro' => 77,
                'id_cliente' => '15',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
            [
                'id_cobro' => 78,
                'id_cliente' => '16',
                'mes' => 'junio',
                'aÃ±o' => 2026,
            ],
        ]);

        $batch = [
            'periodo' => ['mes' => 'junio', 'anio' => 2026],
            'sources' => [],
            'entries' => [
                [
                    'entry_id' => 'entry-facturas',
                    'nit' => '161379896',
                    'emisor' => 'LEONARDO ALZATE ARIAS',
                    'facturas' => 2.0,
                    'nota_debito' => 0.0,
                    'nota_credito' => 0.0,
                    'soporte' => 0.0,
                    'nota_ajuste' => 0.0,
                    'acuse' => 0.0,
                    'sources' => ['ResumenCombinado.xlsx'],
                    'rows' => [36],
                ],
                [
                    'entry_id' => 'entry-soporte',
                    'nit' => '161379896',
                    'emisor' => 'FERRETORNILLOS EL EBANISTA',
                    'facturas' => 0.0,
                    'nota_debito' => 0.0,
                    'nota_credito' => 0.0,
                    'soporte' => 5.0,
                    'nota_ajuste' => 0.0,
                    'acuse' => 0.0,
                    'sources' => ['ResumenDocumentoSoporte.xlsx'],
                    'rows' => [56],
                ],
            ],
            'errors' => [],
        ];

        $assignmentResult = $service->assignManualMatch($batch, 'entry-facturas', 78);
        $preview = $service->buildPreview($assignmentResult['batch']);

        $this->assertSame('161379896', $assignmentResult['nit']);
        $this->assertSame(2, $assignmentResult['resolved_entries']);
        $this->assertSame(2, $preview['summary']['ready']);
        $this->assertSame(0, $preview['summary']['with_errors']);
        $this->assertTrue($preview['rows'][0]['resolved_manually']);
        $this->assertTrue($preview['rows'][1]['resolved_manually']);
        $this->assertSame(78, $preview['rows'][0]['id_cobro']);
        $this->assertSame(78, $preview['rows'][1]['id_cobro']);
    }

    public function test_process_batch_consolidates_ready_rows_with_same_id_cobro(): void
    {
        $this->createProcessBatchTables();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(501)
            ->andReturn((object) ['id_cobro' => 501]);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->andReturn($this->baseRevisionValues([
                'valor_principal' => 100.0,
                'precio_factura' => 10.0,
                'precio_soporte' => 5.0,
                'precio_acuse' => 3.0,
            ]));

        $service = new ImportacionesService(
            $cobrosService,
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );

        \DB::table('valores_externos')->insert([
            'id_cobro' => 501,
            'id_cliente' => '77',
            'mes' => 'junio',
            'aÃƒÂ±o' => 2026,
            'numero_facturas' => 0,
            'numero_nota_debito' => 0,
            'numero_nota_credito' => 0,
            'numero_documento_soporte' => 0,
            'numero_nota_ajuste' => 0,
            'numero_acuse' => 0,
            'valor_facturas' => 0,
            'valor_documentos' => 0,
            'valor_acuse' => 0,
            'valor_mensualidad' => 100,
            'valor_total' => 100,
        ]);

        $result = $service->processBatch(
            ['sources' => [], 'periodo' => ['mes' => 'junio', 'anio' => 2026]],
            ['rows' => [
                $this->readyProcessRow(501, [
                    'facturas' => 2.0,
                    'nota_credito' => 1.0,
                    'soporte' => 3.0,
                ], [
                    'numero_facturas' => 2.0,
                    'numero_nota_debito' => 0.0,
                    'numero_nota_credito' => 1.0,
                    'numero_documento_soporte' => 3.0,
                    'numero_nota_ajuste' => 0.0,
                    'numero_acuse' => 0.0,
                    'valor_facturas' => 30.0,
                    'valor_documentos' => 15.0,
                    'valor_acuse' => 0.0,
                    'valor_mensualidad' => 100.0,
                    'valor_total' => 145.0,
                ], 'facturas.xlsx', 10),
                $this->readyProcessRow(501, [
                    'acuse' => 4.0,
                ], [
                    'numero_facturas' => 0.0,
                    'numero_nota_debito' => 0.0,
                    'numero_nota_credito' => 0.0,
                    'numero_documento_soporte' => 0.0,
                    'numero_nota_ajuste' => 0.0,
                    'numero_acuse' => 4.0,
                    'valor_facturas' => 0.0,
                    'valor_documentos' => 0.0,
                    'valor_acuse' => 12.0,
                    'valor_mensualidad' => 100.0,
                    'valor_total' => 112.0,
                ], 'eventos.xlsx', 20),
            ], 'parse_errors' => [], 'process_errors' => []],
            'tester',
            99,
        );

        $row = \DB::table('valores_externos')->where('id_cobro', 501)->first();
        $log = \DB::table('importacion_extraccion_logs')->latest('id')->first();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2.0, (float) $row->numero_facturas);
        $this->assertSame(1.0, (float) $row->numero_nota_credito);
        $this->assertSame(3.0, (float) $row->numero_documento_soporte);
        $this->assertSame(4.0, (float) $row->numero_acuse);
        $this->assertSame(30.0, (float) $row->valor_facturas);
        $this->assertSame(15.0, (float) $row->valor_documentos);
        $this->assertSame(12.0, (float) $row->valor_acuse);
        $this->assertSame(157.0, (float) $row->valor_total);
        $this->assertSame(1, (int) $log->cantidad_registros);
    }

    public function test_process_batch_does_not_double_count_valor_mensualidad_or_valor_total(): void
    {
        $this->createProcessBatchTables();

        $cobrosService = Mockery::mock(CobrosService::class);
        $cobrosService->shouldReceive('findCobroById')
            ->once()
            ->with(777)
            ->andReturn((object) ['id_cobro' => 777]);
        $cobrosService->shouldReceive('mapCobroToRevisionValues')
            ->once()
            ->andReturn($this->baseRevisionValues([
                'valor_principal' => 500.0,
                'precio_factura' => 20.0,
                'precio_acuse' => 2.0,
            ]));

        $service = new ImportacionesService(
            $cobrosService,
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );

        \DB::table('valores_externos')->insert([
            'id_cobro' => 777,
            'id_cliente' => '88',
            'mes' => 'junio',
            'aÃƒÂ±o' => 2026,
            'numero_facturas' => 0,
            'numero_nota_debito' => 0,
            'numero_nota_credito' => 0,
            'numero_documento_soporte' => 0,
            'numero_nota_ajuste' => 0,
            'numero_acuse' => 0,
            'valor_facturas' => 0,
            'valor_documentos' => 0,
            'valor_acuse' => 0,
            'valor_mensualidad' => 500,
            'valor_total' => 500,
        ]);

        $service->processBatch(
            ['sources' => [], 'periodo' => ['mes' => 'junio', 'anio' => 2026]],
            ['rows' => [
                $this->readyProcessRow(777, ['facturas' => 3.0], [
                    'numero_facturas' => 3.0,
                    'numero_nota_debito' => 0.0,
                    'numero_nota_credito' => 0.0,
                    'numero_documento_soporte' => 0.0,
                    'numero_nota_ajuste' => 0.0,
                    'numero_acuse' => 0.0,
                    'valor_facturas' => 60.0,
                    'valor_documentos' => 0.0,
                    'valor_acuse' => 0.0,
                    'valor_mensualidad' => 500.0,
                    'valor_total' => 560.0,
                ], 'facturas.xlsx', 5),
                $this->readyProcessRow(777, ['acuse' => 7.0], [
                    'numero_facturas' => 0.0,
                    'numero_nota_debito' => 0.0,
                    'numero_nota_credito' => 0.0,
                    'numero_documento_soporte' => 0.0,
                    'numero_nota_ajuste' => 0.0,
                    'numero_acuse' => 7.0,
                    'valor_facturas' => 0.0,
                    'valor_documentos' => 0.0,
                    'valor_acuse' => 14.0,
                    'valor_mensualidad' => 500.0,
                    'valor_total' => 514.0,
                ], 'eventos.xlsx', 6),
            ], 'parse_errors' => [], 'process_errors' => []],
            'tester',
            10,
        );

        $row = \DB::table('valores_externos')->where('id_cobro', 777)->first();

        $this->assertSame(500.0, (float) $row->valor_mensualidad);
        $this->assertSame(60.0, (float) $row->valor_facturas);
        $this->assertSame(14.0, (float) $row->valor_acuse);
        $this->assertSame(574.0, (float) $row->valor_total);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('importacion_extraccion_logs');
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        parent::tearDown();
    }

    private function makeService(): ImportacionesService
    {
        return new ImportacionesService(
            Mockery::mock(CobrosService::class),
            new RevisarProformaCalculator(new ClienteValorTotalCalculator()),
        );
    }

    private function createImportPreviewTables(): void
    {
        Schema::dropIfExists('valores_externos');
        Schema::dropIfExists('clientes_potenciales');

        Schema::create('clientes_potenciales', function (Blueprint $table): void {
            $table->increments('idclientes_potenciales');
            $table->string('nit')->nullable();
            $table->string('dv')->nullable();
            $table->string('empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->string('codigo')->nullable();
            $table->string('regimen')->nullable();
            $table->string('fecha_arriendo')->nullable();
            $table->string('fecha_retiro')->nullable();
            $table->integer('retiro')->nullable();
        });

        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->integer('id_cobro')->primary();
            $table->string('id_cliente')->nullable();
            $table->string('mes')->nullable();
            $table->integer('aÃ±o')->nullable();
        });
    }
    private function createProcessBatchTables(): void
    {
        Schema::dropIfExists('importacion_extraccion_logs');
        Schema::dropIfExists('valores_externos');

        Schema::create('valores_externos', function (Blueprint $table): void {
            $table->integer('id_cobro')->primary();
            $table->string('id_cliente')->nullable();
            $table->string('mes')->nullable();
            $table->integer('aÃƒÂ±o')->nullable();
            $table->float('numero_facturas')->default(0);
            $table->float('numero_nota_debito')->default(0);
            $table->float('numero_nota_credito')->default(0);
            $table->float('numero_documento_soporte')->default(0);
            $table->float('numero_nota_ajuste')->default(0);
            $table->float('numero_acuse')->default(0);
            $table->float('valor_facturas')->default(0);
            $table->float('valor_documentos')->default(0);
            $table->float('valor_acuse')->default(0);
            $table->float('valor_mensualidad')->default(0);
            $table->float('valor_total')->default(0);
        });

        Schema::create('importacion_extraccion_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('fecha')->nullable();
            $table->integer('usuario_id')->nullable();
            $table->string('usuario')->nullable();
            $table->integer('cantidad_registros')->default(0);
            $table->text('archivo_origen')->nullable();
            $table->text('errores_encontrados')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param array<string, float> $overrides
     * @return array<string, float>
     */
    private function baseRevisionValues(array $overrides = []): array
    {
        return array_merge([
            'numero_equipos' => 0.0,
            'valor_principal' => 0.0,
            'valor_terminal' => 0.0,
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
            'precio_factura' => 0.0,
            'precio_soporte' => 0.0,
            'precio_acuse' => 0.0,
        ], $overrides);
    }

    /**
     * @param array<string, float> $imported
     * @param array<string, float> $persistPayload
     * @return array<string, mixed>
     */
    private function readyProcessRow(int $idCobro, array $imported, array $persistPayload, string $source, int $row): array
    {
        return [
            'status' => 'ready',
            'id_cobro' => $idCobro,
            'sources' => [$source],
            'rows' => [$row],
            'imported' => array_merge([
                'facturas' => 0.0,
                'nota_debito' => 0.0,
                'nota_credito' => 0.0,
                'soporte' => 0.0,
                'nota_ajuste' => 0.0,
                'acuse' => 0.0,
            ], $imported),
            'persist_payload' => $persistPayload,
        ];
    }
}

