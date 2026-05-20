<?php

namespace App\Services;

use App\Exports\ProformasCarteraExcelExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Facades\Excel;

class ProformaCarteraExportService
{
    public function __construct(
        private readonly ProformaCarteraService $proformaCarteraService,
    ) {
    }

    public function download(array $filters): BinaryFileResponse
    {
        $rows = $this->proformaCarteraService->getRowsForExport($filters);

        $headings = [
            'Codigo',
            'Empresa',
            'NIT',
            'Email',
            'Celular',
            'Meses pendientes',
            'Cantidad proformas',
            'Valor total deuda',
            'Ultima proforma',
            'Estado actual',
            'Dias vencido',
            'Nota',
        ];

        $dataRows = $rows->map(function (object $row): array {
            return [
                $row->codigo ?: 'N/D',
                $row->empresa ?: 'N/D',
                $row->nit ?: 'N/D',
                $row->email ?: 'N/D',
                $row->celular ?: 'N/D',
                (int) ($row->meses_pendientes ?? 0),
                (int) ($row->cantidad_proformas ?? 0),
                (float) ($row->valor_total_deuda ?? 0),
                $this->ultimaProformaLabel($row),
                $this->proformaCarteraService->estadoLabel($row->estado_actual ?? null),
                (int) ($row->dias_vencido ?? 0),
                $row->nota ?: 'N/D',
            ];
        })->all();

        $dataRows[] = [
            'Totales',
            '',
            '',
            '',
            '',
            '',
            (int) $rows->sum(fn (object $row) => (int) ($row->cantidad_proformas ?? 0)),
            (float) $rows->sum(fn (object $row) => (float) ($row->valor_total_deuda ?? 0)),
            '',
            '',
            '',
            '',
        ];

        return Excel::download(
            new ProformasCarteraExcelExport($headings, $dataRows, [8], count($dataRows) + 1),
            $this->buildFilename($filters)
        );
    }

    private function buildFilename(array $filters): string
    {
        $parts = ['proformas-cartera-pendiente'];

        if (($filters['fecha_desde'] ?? null) !== null || ($filters['fecha_hasta'] ?? null) !== null) {
            $parts[] = sprintf(
                '%s-%s',
                $filters['fecha_desde'] ?? 'inicio',
                $filters['fecha_hasta'] ?? 'fin'
            );
        } else {
            $parts[] = now()->format('Ymd-His');
        }

        return implode('-', $parts).'.xlsx';
    }

    private function ultimaProformaLabel(object $row): string
    {
        $mes = ProformasService::MESES[(int) ($row->ultima_proforma_mes ?? 0)] ?? null;
        $periodLabel = $mes !== null
            ? ucfirst($mes).' '.($row->ultima_proforma_anio ?? 'N/D')
            : 'Periodo N/D';

        $numero = trim((string) ($row->ultima_proforma_numero ?? ''));

        return $numero !== '' ? $periodLabel.' - '.$numero : $periodLabel.' - #'.($row->ultima_proforma_id ?? 'N/D');
    }
}
