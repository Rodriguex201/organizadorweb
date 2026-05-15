<?php

namespace App\Exports;

use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\BeforeWriting;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProformasDashboardExcelExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles
{
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $currencyColumnIndexes = [],
        private readonly ?int $totalsRowIndex = null,
    ) {
        $this->logExportDebug('construct', [
            'headings_count' => count($this->headings),
            'rows_count' => count($this->rows),
            'currency_column_indexes' => $this->currencyColumnIndexes,
            'totals_row_index' => $this->totalsRowIndex,
        ]);
    }

    public function array(): array
    {
        $this->logExportDebug('array.start', [
            'headings_count' => count($this->headings),
            'rows_count' => count($this->rows),
        ]);

        $array = [
            $this->headings,
            ...$this->rows,
        ];

        $this->logExportDebug('array.finish', [
            'sheet_rows_count' => count($array),
        ]);

        return $array;
    }

    public function styles(Worksheet $sheet): array
    {
        $this->logExportDebug('styles.start', [
            'worksheet_title' => $sheet->getTitle(),
        ]);

        $styles = [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];

        if ($this->totalsRowIndex !== null) {
            $styles[$this->totalsRowIndex] = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '1E293B'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ];
        }

        $this->logExportDebug('styles.finish', [
            'styles_rows' => array_keys($styles),
        ]);

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function (): void {
                $this->logExportDebug('event.before_export');
            },
            BeforeWriting::class => function (): void {
                $this->logExportDebug('event.before_writing');
            },
            BeforeSheet::class => function (): void {
                $this->logExportDebug('event.before_sheet');
            },
            AfterSheet::class => function (AfterSheet $event): void {
                $this->logExportDebug('event.after_sheet.start');
                $sheet = $event->sheet->getDelegate();
                $rowCount = max(count($this->rows) + 1, 1);
                $columnCount = max(count($this->headings), 1);
                $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
                $fullRange = "A1:{$lastColumn}{$rowCount}";

                $sheet->setAutoFilter($fullRange);
                $sheet->freezePane('A2');

                foreach ($this->currencyColumnIndexes as $columnIndex) {
                    $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                    $sheet->getStyle("{$columnLetter}2:{$columnLetter}{$rowCount}")
                        ->getNumberFormat()
                        ->setFormatCode('[$$-es-CO] #,##0.00');
                }

                if ($this->totalsRowIndex !== null) {
                    $sheet->getStyle("A{$this->totalsRowIndex}:{$lastColumn}{$this->totalsRowIndex}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('E2E8F0');
                }

                $this->logExportDebug('event.after_sheet.finish', [
                    'row_count' => $rowCount,
                    'column_count' => $columnCount,
                    'range' => $fullRange,
                ]);
            },
        ];
    }

    private function logExportDebug(string $stage, array $context = []): void
    {
        Log::info('proformas.dashboard.export.excel.'.$stage, array_merge([
            'ts_micro' => sprintf('%.6f', microtime(true)),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ], $context));
    }
}
