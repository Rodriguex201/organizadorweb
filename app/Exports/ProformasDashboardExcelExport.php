<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
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
    }

    public function array(): array
    {
        return [
            $this->headings,
            ...$this->rows,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
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

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
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
            },
        ];
    }
}
