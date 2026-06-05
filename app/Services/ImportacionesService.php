<?php

namespace App\Services;

use App\Imports\GenericSpreadsheetImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class ImportacionesService
{
    public function __construct(
        private readonly CobrosService $cobrosService,
        private readonly RevisarProformaCalculator $revisarProformaCalculator,
    ) {
    }

    /**
     * @param array<string, UploadedFile|null> $files
     * @return array<string, mixed>
     */
    public function buildBatchFromUploads(array $files, string $mes, int $anio): array
    {
        $aggregated = [];
        $errors = [];
        $sources = [];

        foreach ($this->fileTypeMap() as $inputName => $metadata) {
            $file = $files[$inputName] ?? null;

            if (!$file instanceof UploadedFile) {
                continue;
            }

            $sources[] = [
                'input' => $inputName,
                'type' => $metadata['type'],
                'original_name' => $file->getClientOriginalName(),
                'extension' => strtolower((string) $file->getClientOriginalExtension()),
                'size' => $file->getSize(),
            ];

            try {
                $rows = Excel::toArray(
                    new GenericSpreadsheetImport(),
                    $file->getRealPath(),
                    null,
                    $this->resolveReaderType($file),
                );
            } catch (\Throwable $exception) {
                try {
                    $rows = [$this->readSpreadsheetFallback($file)];
                } catch (\Throwable $fallbackException) {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'row' => null,
                        'message' => 'No fue posible leer el archivo: ' . $fallbackException->getMessage(),
                    ];

                    continue;
                }
            }

            $sheetRows = $rows[0] ?? [];

            foreach ($sheetRows as $index => $row) {
                $rowNumber = $index + 1;

                if (!is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                if ($this->looksLikeHeader($row)) {
                    continue;
                }

                if ($metadata['type'] === 'soporte' && $this->shouldSkipSoporteRow($row)) {
                    continue;
                }

                $nit = $this->normalizeNit($row[2] ?? null);
                $emisorOriginal = $this->normalizeText($row[3] ?? null);

                if ($nit === '') {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'row' => $rowNumber,
                        'message' => 'La fila no contiene un NIT valido en la columna 3.',
                    ];
                    continue;
                }

                if ($emisorOriginal === '') {
                    $errors[] = [
                        'file' => $file->getClientOriginalName(),
                        'row' => $rowNumber,
                        'message' => 'La fila no contiene un emisor valido en la columna 4.',
                    ];
                    continue;
                }

                $emisor = $this->normalizeEmitter($emisorOriginal);
                $key = $nit . '|' . $emisor;

                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'entry_id' => $this->buildEntryIdentifier($nit, $emisor, $file->getClientOriginalName(), $rowNumber),
                        'nit' => $nit,
                        'emisor' => $emisor,
                        'emisor_original' => $emisorOriginal,
                        'facturas' => 0.0,
                        'nota_debito' => 0.0,
                        'nota_credito' => 0.0,
                        'soporte' => 0.0,
                        'nota_ajuste' => 0.0,
                        'acuse' => 0.0,
                        'sources' => [],
                        'rows' => [],
                    ];
                }

                $aggregated[$key]['sources'][] = $file->getClientOriginalName();
                $aggregated[$key]['rows'][] = $rowNumber;

                foreach ($metadata['columns'] as $targetKey => $columnIndex) {
                    $aggregated[$key][$targetKey] += $this->quantityFromCell($row[$columnIndex] ?? null);
                }
            }
        }

        foreach ($aggregated as &$entry) {
            $entry['sources'] = array_values(array_unique($entry['sources']));
            $entry['rows'] = array_values(array_unique($entry['rows']));
        }
        unset($entry);

        return [
            'periodo' => [
                'mes' => $this->normalizeMes($mes),
                'anio' => $anio,
            ],
            'sources' => $sources,
            'entries' => array_values($aggregated),
            'errors' => $errors,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    public function buildPreview(array $batch): array
    {
        $periodo = $batch['periodo'] ?? [];
        $mes = $this->normalizeMes((string) ($periodo['mes'] ?? ''));
        $anio = (int) ($periodo['anio'] ?? 0);
        $entries = is_array($batch['entries'] ?? null) ? $batch['entries'] : [];
        $parseErrors = is_array($batch['errors'] ?? null) ? $batch['errors'] : [];
        $manualAssignments = is_array($batch['manual_assignments'] ?? null) ? $batch['manual_assignments'] : [];
        $manualAssignmentsByNit = is_array($batch['manual_assignments_by_nit'] ?? null) ? $batch['manual_assignments_by_nit'] : [];

        $cobrosByNit = $this->loadCobrosByPeriodo($mes, $anio);
        $baseRecordsCount = array_sum(array_map('count', $cobrosByNit));
        $previewRows = [];
        $processErrors = [];

        if ($baseRecordsCount === 0 && $entries !== []) {
            return [
                'periodo' => [
                    'mes' => $mes,
                    'anio' => $anio,
                ],
                'sources' => $batch['sources'] ?? [],
                'rows' => [],
                'parse_errors' => $parseErrors,
                'process_errors' => [],
                'summary' => [
                    'total' => count($entries),
                    'ready' => 0,
                    'with_errors' => 0,
                    'parse_errors' => count($parseErrors),
                ],
                'requires_base_generation' => true,
                'base_generation_notice' => 'No existen registros base en valores_externos para el periodo seleccionado. Primero debes generarlos antes de importar los archivos.',
            ];
        }

        foreach ($entries as $entry) {
            $entryId = $this->resolveEntryIdentifier($entry);
            $nit = $this->normalizeNit($entry['nit'] ?? null);
            $emisor = $this->normalizeEmitter($entry['emisor'] ?? null);
            $matches = $cobrosByNit[$nit] ?? [];

            if ($matches === []) {
                $previewRows[] = $this->buildErrorPreviewRow(
                    $entry,
                    'No se encontro un registro en valores_externos para el NIT del periodo seleccionado.',
                    $entryId
                );
                $processErrors[] = [
                    'file' => implode(', ', (array) ($entry['sources'] ?? [])),
                    'row' => implode(', ', array_map('strval', (array) ($entry['rows'] ?? []))),
                    'message' => "Sin coincidencia para NIT {$nit}.",
                ];
                continue;
            }

            if (count($matches) > 1) {
                $assignedMatch = $this->resolveAssignedMatch(
                    $matches,
                    $manualAssignmentsByNit[$nit] ?? $manualAssignments[$entryId] ?? null
                );

                if ($assignedMatch !== null) {
                    $previewRows[] = $this->buildReadyPreviewRow($entry, $mes, $anio, $assignedMatch, true, $matches);
                    continue;
                }

                $previewRows[] = $this->buildErrorPreviewRow(
                    $entry,
                    'Multiples registros para el mismo NIT en el periodo.',
                    $entryId,
                    [
                        'entry_id' => $entryId,
                        'match_count' => count($matches),
                        'matches' => $matches,
                    ]
                );
                $processErrors[] = [
                    'file' => implode(', ', (array) ($entry['sources'] ?? [])),
                    'row' => implode(', ', array_map('strval', (array) ($entry['rows'] ?? []))),
                    'message' => "Multiples registros para el mismo NIT {$nit} en el periodo.",
                ];
                continue;
            }

            $match = $matches[0];
            $previewRows[] = $this->buildReadyPreviewRow($entry, $mes, $anio, $match);
        }

        return [
            'periodo' => [
                'mes' => $mes,
                'anio' => $anio,
            ],
            'sources' => $batch['sources'] ?? [],
            'rows' => $previewRows,
            'parse_errors' => $parseErrors,
            'process_errors' => $processErrors,
            'summary' => [
                'total' => count($previewRows),
                'ready' => count(array_filter($previewRows, fn (array $row) => $row['status'] === 'ready')),
                'with_errors' => count(array_filter($previewRows, fn (array $row) => $row['status'] !== 'ready')),
                'parse_errors' => count($parseErrors),
            ],
            'requires_base_generation' => false,
            'base_generation_notice' => null,
        ];
    }

    /**
     * @param array<string, mixed> $batch
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    public function processBatch(array $batch, array $preview, string $usuario, ?int $idUsuario = null): array
    {
        $rows = $this->consolidateReadyRows(
            is_array($preview['rows'] ?? null) ? $preview['rows'] : []
        );
        $errors = array_merge(
            is_array($preview['parse_errors'] ?? null) ? $preview['parse_errors'] : [],
            is_array($preview['process_errors'] ?? null) ? $preview['process_errors'] : [],
        );

        $processed = 0;
        $updated = 0;

        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== 'ready') {
                continue;
            }

            $processed++;

            try {
                $affected = DB::table('valores_externos')
                    ->where('id_cobro', (int) ($row['id_cobro'] ?? 0))
                    ->update($row['persist_payload'] ?? []);

                $updated += max($affected, 0);
            } catch (\Throwable $exception) {
                $errors[] = [
                    'file' => implode(', ', (array) ($row['sources'] ?? [])),
                    'row' => implode(', ', array_map('strval', (array) ($row['rows'] ?? []))),
                    'message' => sprintf(
                        'Error actualizando cobro %s: %s',
                        (string) ($row['id_cobro'] ?? 'N/D'),
                        $exception->getMessage(),
                    ),
                ];

                Log::error('Error procesando importacion lateral.', [
                    'id_cobro' => $row['id_cobro'] ?? null,
                    'nit' => $row['nit'] ?? null,
                    'emisor' => $row['emisor'] ?? null,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $logId = DB::table('importacion_extraccion_logs')->insertGetId([
            'fecha' => now(),
            'usuario_id' => $idUsuario,
            'usuario' => $usuario,
            'cantidad_registros' => $processed,
            'archivo_origen' => json_encode(array_map(
                fn (array $source) => $source['original_name'] ?? null,
                (array) ($batch['sources'] ?? [])
            ), JSON_UNESCAPED_UNICODE),
            'errores_encontrados' => json_encode($errors, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
            'log_id' => $logId,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function consolidateReadyRows(array $rows): array
    {
        $consolidated = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== 'ready') {
                continue;
            }

            $idCobro = (int) ($row['id_cobro'] ?? 0);
            if ($idCobro <= 0) {
                continue;
            }

            if (!isset($consolidated[$idCobro])) {
                $consolidated[$idCobro] = $row;
                continue;
            }

            $consolidated[$idCobro]['sources'] = array_values(array_unique(array_merge(
                (array) ($consolidated[$idCobro]['sources'] ?? []),
                (array) ($row['sources'] ?? []),
            )));
            $consolidated[$idCobro]['rows'] = array_values(array_unique(array_merge(
                array_map('strval', (array) ($consolidated[$idCobro]['rows'] ?? [])),
                array_map('strval', (array) ($row['rows'] ?? [])),
            )));
            $consolidated[$idCobro]['resolved_manually'] = (bool) (($consolidated[$idCobro]['resolved_manually'] ?? false) || ($row['resolved_manually'] ?? false));

            foreach (['facturas', 'nota_debito', 'nota_credito', 'soporte', 'nota_ajuste', 'acuse'] as $field) {
                $consolidated[$idCobro]['imported'][$field] = (float) ($consolidated[$idCobro]['imported'][$field] ?? 0)
                    + (float) ($row['imported'][$field] ?? 0);
            }
        }

        foreach ($consolidated as $idCobro => &$row) {
            $cobro = $this->cobrosService->findCobroById((int) $idCobro);
            if (!$cobro) {
                continue;
            }

            $input = $this->cobrosService->mapCobroToRevisionValues($cobro);
            $input['facturas'] = (float) ($row['imported']['facturas'] ?? 0);
            $input['nota_debito'] = (float) ($row['imported']['nota_debito'] ?? 0);
            $input['nota_credito'] = (float) ($row['imported']['nota_credito'] ?? 0);
            $input['soporte'] = (float) ($row['imported']['soporte'] ?? 0);
            $input['nota_ajuste'] = (float) ($row['imported']['nota_ajuste'] ?? 0);
            $input['acuse'] = (float) ($row['imported']['acuse'] ?? 0);

            $calculated = $this->revisarProformaCalculator->calculate($input);
            $row['calculated'] = [
                'valor_facturas' => (float) ($calculated['valor_facturas'] ?? 0),
                'valor_documentos' => (float) ($calculated['valor_documentos'] ?? 0),
                'valor_acuse' => (float) ($calculated['valor_acuse'] ?? 0),
                'valor_mensualidad' => (float) ($calculated['total_mensualidad'] ?? 0),
                'valor_total' => (float) ($calculated['valor_total_proforma'] ?? 0),
            ];
            $row['persist_payload'] = [
                'numero_facturas' => (float) ($row['imported']['facturas'] ?? 0),
                'numero_nota_debito' => (float) ($row['imported']['nota_debito'] ?? 0),
                'numero_nota_credito' => (float) ($row['imported']['nota_credito'] ?? 0),
                'numero_documento_soporte' => (float) ($row['imported']['soporte'] ?? 0),
                'numero_nota_ajuste' => (float) ($row['imported']['nota_ajuste'] ?? 0),
                'numero_acuse' => (float) ($row['imported']['acuse'] ?? 0),
                'valor_facturas' => (float) ($calculated['valor_facturas'] ?? 0),
                'valor_documentos' => (float) ($calculated['valor_documentos'] ?? 0),
                'valor_acuse' => (float) ($calculated['valor_acuse'] ?? 0),
                'valor_mensualidad' => (float) ($calculated['total_mensualidad'] ?? 0),
                'valor_total' => (float) ($calculated['valor_total_proforma'] ?? 0),
            ];
        }
        unset($row);

        return array_values($consolidated);
    }

    /**
     * @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    public function assignManualMatch(array $batch, string $entryId, int $idCobro): array
    {
        $periodo = $batch['periodo'] ?? [];
        $mes = $this->normalizeMes((string) ($periodo['mes'] ?? ''));
        $anio = (int) ($periodo['anio'] ?? 0);
        $entries = is_array($batch['entries'] ?? null) ? $batch['entries'] : [];
        $cobrosByNit = $this->loadCobrosByPeriodo($mes, $anio);

        foreach ($entries as $entry) {
            if ($this->resolveEntryIdentifier($entry) !== $entryId) {
                continue;
            }

            $nit = $this->normalizeNit($entry['nit'] ?? null);
            $matches = $cobrosByNit[$nit] ?? [];

            if (count($matches) <= 1) {
                throw new \InvalidArgumentException('El registro indicado ya no tiene multiples coincidencias para resolver.');
            }

            $assignedMatch = $this->resolveAssignedMatch($matches, $idCobro);
            if ($assignedMatch === null) {
                throw new \InvalidArgumentException('La coincidencia seleccionada no pertenece al NIT del registro importado.');
            }

            $batch['manual_assignments'] = is_array($batch['manual_assignments'] ?? null)
                ? $batch['manual_assignments']
                : [];
            $batch['manual_assignments_by_nit'] = is_array($batch['manual_assignments_by_nit'] ?? null)
                ? $batch['manual_assignments_by_nit']
                : [];
            $batch['manual_assignments_by_nit'][$nit] = $idCobro;

            $resolvedEntries = 0;
            foreach ($entries as $candidateEntry) {
                if ($this->normalizeNit($candidateEntry['nit'] ?? null) !== $nit) {
                    continue;
                }

                $candidateEntryId = $this->resolveEntryIdentifier($candidateEntry);
                $batch['manual_assignments'][$candidateEntryId] = $idCobro;
                $resolvedEntries++;
            }

            return [
                'batch' => $batch,
                'nit' => $nit,
                'resolved_entries' => $resolvedEntries,
            ];
        }

        throw new \InvalidArgumentException('No se encontro el registro importado que intentas resolver.');
    }

    /**
     * @return array<string, array{type:string,columns:array<string,int>}>
     */
    private function fileTypeMap(): array
    {
        return [
            'facturas_file' => [
                'type' => 'facturas',
                'columns' => [
                    'facturas' => 4,
                    'nota_debito' => 5,
                    'nota_credito' => 6,
                ],
            ],
            'soporte_file' => [
                'type' => 'soporte',
                'columns' => [
                    'soporte' => 4,
                    'nota_ajuste' => 5,
                ],
            ],
            'recepcion_file' => [
                'type' => 'recepcion',
                'columns' => [
                    'acuse' => 4,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadCobrosByPeriodo(string $mes, int $anio): array
    {
        $rows = DB::table('valores_externos as ve')
            ->leftJoin('clientes_potenciales as cp', 've.id_cliente', '=', 'cp.idclientes_potenciales')
            ->select([
                've.id_cobro',
                've.id_cliente',
                'cp.nit',
                'cp.dv',
                'cp.codigo',
                'cp.empresa',
                'cp.nombre',
                'cp.regimen',
                'cp.fecha_arriendo',
                'cp.fecha_retiro',
                'cp.retiro',
            ])
            ->whereRaw('LOWER(TRIM(ve.mes)) = ?', [$mes])
            ->where("ve.{$this->resolveYearColumn()}", $anio)
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $nit = $this->normalizeClienteNit($row->nit ?? null, $row->dv ?? null);
            if ($nit === '') {
                continue;
            }

            $grouped[$nit] ??= [];
            $grouped[$nit][] = [
                'id_cobro' => (int) $row->id_cobro,
                'id_cliente' => (int) ($row->id_cliente ?? 0),
                'cliente' => trim((string) ($row->empresa ?: $row->nombre ?: 'Sin nombre')),
                'codigo' => trim((string) ($row->codigo ?? '')),
                'nombre' => trim((string) ($row->nombre ?? '')),
                'empresa' => trim((string) ($row->empresa ?? '')),
                'regimen' => trim((string) ($row->regimen ?? '')),
                'fecha_arriendo' => $row->fecha_arriendo,
                'fecha_retiro' => $row->fecha_retiro,
                'estado' => ((int) ($row->retiro ?? 0) === 1 || trim((string) ($row->fecha_retiro ?? '')) !== '')
                    ? 'Retirado'
                    : 'Activo',
            ];
        }

        return $grouped;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $nit = Str::upper($this->normalizeText($row[2] ?? null));
        $emisor = Str::upper($this->normalizeText($row[3] ?? null));

        return in_array($nit, ['NIT', 'DOCUMENTO', 'IDENTIFICACION'], true)
            || str_contains($emisor, 'EMISOR');
    }

    /**
     * @param array<int, mixed> $row
     */
    private function shouldSkipSoporteRow(array $row): bool
    {
        return $this->quantityFromCell($row[4] ?? null) <= 0
            && $this->quantityFromCell($row[5] ?? null) <= 0;
    }

    private function normalizeNit(mixed $value): string
    {
        $normalized = preg_replace('/[^0-9A-Za-z]/', '', trim((string) $value));

        return strtoupper((string) $normalized);
    }

    private function normalizeText(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private function normalizeEmitter(mixed $value): string
    {
        $text = Str::upper($this->normalizeText($value));

        if (str_contains($text, 'PCS')) {
            return 'PCS';
        }

        if (str_contains($text, 'SMP')) {
            return 'SMP';
        }

        if (str_contains($text, 'SAS')) {
            return 'SAS';
        }

        return $text !== '' ? $text : 'SAS';
    }

    private function normalizeEmitterFromRegimen(mixed $value): string
    {
        $regimen = Str::upper($this->normalizeText($value));

        return match ($regimen) {
            'PCS' => 'PCS',
            'SMP' => 'SMP',
            default => 'SAS',
        };
    }

    private function normalizeClienteNit(mixed $nit, mixed $dv): string
    {
        return $this->normalizeNit(
            $this->normalizeText($nit) . $this->normalizeText($dv)
        );
    }

    private function normalizeMes(string $mes): string
    {
        $value = Str::lower(trim($mes));

        return in_array($value, CobrosService::MESES, true)
            ? $value
            : (CobrosService::MESES[(int) now()->format('n')] ?? 'enero');
    }

    private function resolveYearColumn(): string
    {
        if (Schema::hasColumn('valores_externos', 'año')) {
            return 'año';
        }

        if (Schema::hasColumn('valores_externos', 'aÃ±o')) {
            return 'aÃ±o';
        }

        return 'año';
    }

    private function quantityFromCell(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return max((float) $value, 0.0);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return 0.0;
        }

        $normalizedNumeric = str_replace([',', ' '], ['.', ''], $normalized);

        if (is_numeric($normalizedNumeric)) {
            return max((float) $normalizedNumeric, 0.0);
        }

        return 1.0;
    }

    private function resolveReaderType(UploadedFile $file): string
    {
        return match (strtolower((string) $file->getClientOriginalExtension())) {
            'csv' => ExcelFormat::CSV,
            'xls' => ExcelFormat::XLS,
            default => ExcelFormat::XLSX,
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheetFallback(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === 'csv') {
            $reader = new CsvReader();
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            $spreadsheet = $reader->load($path);
        } else {
            $reader = IOFactory::createReaderForFile($path);
            $spreadsheet = $reader->load($path);
        }

        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function buildErrorPreviewRow(array $entry, string $message, ?string $entryId = null, array $extra = []): array
    {
        return array_merge([
            'status' => 'error',
            'error_message' => $message,
            'entry_id' => $entryId ?? $this->resolveEntryIdentifier($entry),
            'id_cobro' => null,
            'id_cliente' => null,
            'nit' => $entry['nit'] ?? '',
            'emisor' => $entry['emisor'] ?? '',
            'cliente' => 'Sin coincidencia',
            'periodo' => null,
            'sources' => array_values((array) ($entry['sources'] ?? [])),
            'rows' => array_values((array) ($entry['rows'] ?? [])),
            'imported' => [
                'facturas' => (float) ($entry['facturas'] ?? 0),
                'nota_debito' => (float) ($entry['nota_debito'] ?? 0),
                'nota_credito' => (float) ($entry['nota_credito'] ?? 0),
                'soporte' => (float) ($entry['soporte'] ?? 0),
                'nota_ajuste' => (float) ($entry['nota_ajuste'] ?? 0),
                'acuse' => (float) ($entry['acuse'] ?? 0),
            ],
            'calculated' => [
                'valor_facturas' => 0.0,
                'valor_documentos' => 0.0,
                'valor_acuse' => 0.0,
                'valor_mensualidad' => 0.0,
                'valor_total' => 0.0,
            ],
            'persist_payload' => [],
        ], $extra);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $match
     * @return array<string, mixed>
     */
    private function buildReadyPreviewRow(array $entry, string $mes, int $anio, array $match, bool $resolvedManually = false, array $availableMatches = []): array
    {
        $cobro = $this->cobrosService->findCobroById((int) $match['id_cobro']);

        if (!$cobro) {
            return $this->buildErrorPreviewRow(
                $entry,
                'El cobro asociado no pudo cargarse para calcular los valores.'
            );
        }

        $input = $this->cobrosService->mapCobroToRevisionValues($cobro);
        $input['facturas'] = (float) ($entry['facturas'] ?? 0);
        $input['nota_debito'] = (float) ($entry['nota_debito'] ?? 0);
        $input['nota_credito'] = (float) ($entry['nota_credito'] ?? 0);
        $input['soporte'] = (float) ($entry['soporte'] ?? 0);
        $input['nota_ajuste'] = (float) ($entry['nota_ajuste'] ?? 0);
        $input['acuse'] = (float) ($entry['acuse'] ?? 0);

        $calculated = $this->revisarProformaCalculator->calculate($input);
        $nit = $this->normalizeNit($entry['nit'] ?? null);
        $emisor = $this->normalizeEmitter($entry['emisor'] ?? null);

        return [
            'status' => 'ready',
            'error_message' => null,
            'entry_id' => $this->resolveEntryIdentifier($entry),
            'resolved_manually' => $resolvedManually,
            'match_count' => $resolvedManually ? count($availableMatches) : 0,
            'matches' => $resolvedManually ? $availableMatches : [],
            'selected_codigo' => (string) ($match['codigo'] ?? ''),
            'selected_id_cobro' => (int) ($match['id_cobro'] ?? 0),
            'id_cobro' => (int) $match['id_cobro'],
            'id_cliente' => (int) ($match['id_cliente'] ?? 0),
            'nit' => $nit,
            'emisor' => $emisor,
            'cliente' => $match['cliente'],
            'periodo' => $mes . ' ' . $anio,
            'sources' => array_values((array) ($entry['sources'] ?? [])),
            'rows' => array_values((array) ($entry['rows'] ?? [])),
            'imported' => [
                'facturas' => (float) ($entry['facturas'] ?? 0),
                'nota_debito' => (float) ($entry['nota_debito'] ?? 0),
                'nota_credito' => (float) ($entry['nota_credito'] ?? 0),
                'soporte' => (float) ($entry['soporte'] ?? 0),
                'nota_ajuste' => (float) ($entry['nota_ajuste'] ?? 0),
                'acuse' => (float) ($entry['acuse'] ?? 0),
            ],
            'calculated' => [
                'valor_facturas' => (float) ($calculated['valor_facturas'] ?? 0),
                'valor_documentos' => (float) ($calculated['valor_documentos'] ?? 0),
                'valor_acuse' => (float) ($calculated['valor_acuse'] ?? 0),
                'valor_mensualidad' => (float) ($calculated['total_mensualidad'] ?? 0),
                'valor_total' => (float) ($calculated['valor_total_proforma'] ?? 0),
            ],
            'persist_payload' => [
                'numero_facturas' => (float) ($entry['facturas'] ?? 0),
                'numero_nota_debito' => (float) ($entry['nota_debito'] ?? 0),
                'numero_nota_credito' => (float) ($entry['nota_credito'] ?? 0),
                'numero_documento_soporte' => (float) ($entry['soporte'] ?? 0),
                'numero_nota_ajuste' => (float) ($entry['nota_ajuste'] ?? 0),
                'numero_acuse' => (float) ($entry['acuse'] ?? 0),
                'valor_facturas' => (float) ($calculated['valor_facturas'] ?? 0),
                'valor_documentos' => (float) ($calculated['valor_documentos'] ?? 0),
                'valor_acuse' => (float) ($calculated['valor_acuse'] ?? 0),
                'valor_mensualidad' => (float) ($calculated['total_mensualidad'] ?? 0),
                'valor_total' => (float) ($calculated['valor_total_proforma'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     * @return array<string, mixed>|null
     */
    private function resolveAssignedMatch(array $matches, mixed $assignedIdCobro): ?array
    {
        $assigned = (int) $assignedIdCobro;
        if ($assigned <= 0) {
            return null;
        }

        foreach ($matches as $match) {
            if ((int) ($match['id_cobro'] ?? 0) === $assigned) {
                return $match;
            }
        }

        return null;
    }

    private function resolveEntryIdentifier(array $entry): string
    {
        $existing = trim((string) ($entry['entry_id'] ?? ''));

        return $existing !== ''
            ? $existing
            : $this->buildEntryIdentifier(
                (string) ($entry['nit'] ?? ''),
                (string) ($entry['emisor'] ?? ''),
                implode('|', (array) ($entry['sources'] ?? [])),
                implode('|', array_map('strval', (array) ($entry['rows'] ?? []))),
            );
    }

    private function buildEntryIdentifier(string $nit, string $emisor, string $source, string|int $row): string
    {
        return sha1($nit . '|' . $emisor . '|' . $source . '|' . (string) $row);
    }
}
