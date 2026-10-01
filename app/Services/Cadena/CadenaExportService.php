<?php

namespace App\Services\Cadena;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Stateless export of a prepared preview. Never invokes the legacy importer or database. */
class CadenaExportService
{
    private const BASE = ['Integracion', 'Canal', 'Identificación emisor', 'Emisor'];

    public function prepare(array $preview, array $files): array
    {
        $names = $raw = [];
        $validation = new CadenaValidacionService();
        foreach ($files as $file) {
            foreach ($file['hojas'] ?? [] as $sheet) {
                foreach ($sheet['filas'] as $row) {
                    $v = $row['valores'];
                    $raw[$this->originKey($file + ['hoja' => $sheet['hoja'], 'fila' => $row['fila']])] = $v;
                    try {
                        $nit = $validation->nit($v['nit'] ?? $v['receptor'] ?? '');
                        // nombre es el emisor; no atribuirlo al receptor de eventos.
                        if ($file['categoria'] !== 'eventos' && trim($v['nombre'] ?? '') !== '') {
                            $names[$file['categoria']][$nit['nit_base']] ??= $v['nombre'];
                        }
                    } catch (\InvalidArgumentException) {
                        // Invalid rows remain in the audit, never repair an identity for export.
                    }
                }
            }
        }
        $tables = [];
        $specs = [
            'facturas' => ['Resumen.xlsx', ['Facturas de venta', 'Nota debito', 'Nota credito'], ['01', '92', '91']],
            'soporte' => ['ResumenDocumentoSoporte.xlsx', ['Documento soporte adquisiciones', 'Nota de ajuste documento soporte adquisiciones'], ['05', '95']],
            'eventos' => ['ResumenEventos.xlsx', ['Acuse', 'Recibo', 'Aceptación expresa', 'Aceptación tácita', 'Reclamo'], ['032', '030', '033', '034', '031']],
        ];
        $omitted = [];
        foreach ($specs as $category => [$filename, $headers, $codes]) {
            $sources = array_values(array_filter($preview['archivos'], fn ($s) => $s['categoria'] === $category && $s['estado'] === 'Leído'));
            if ($sources === []) {
                continue; // No empty workbook for an absent category.
            }
            $rows = [];
            foreach ($preview['clientes'] as $group) {
                $counts = $group['cantidades'];
                if (!array_intersect($codes, array_keys($counts))) {
                    continue;
                }
                if (!$group['dv_valido'] || ($category === 'soporte' && $group['cliente_id'] === null)) {
                    $omitted[] = [$category, $group['nit_base'].'-'.$group['dv'], $group['estado'],
                        !$group['dv_valido'] ? 'No exportado: DV inválido.' : 'No exportado: soporte requiere cliente resuelto.'];
                    continue;
                }
                $name = $category === 'facturas' || $group['cliente_id'] === null
                    ? ($names[$category][$group['nit_base']] ?? null) : null;
                $name ??= $group['cliente_id'] !== null ? $group['cliente'] : $group['nit_base'].'-'.$group['dv'];
                // Text NIT with separator matches the legacy normalizer (base + DV).
                $row = ['RM SOFT', 'RM SOFT', $group['nit_base'].'-'.$group['dv'], $name];
                foreach ($codes as $code) {
                    $row[] = $counts[$code] ?? null; // Do not invent explicit zeros.
                }
                $rows[] = $row;
            }
            usort($rows, fn ($a, $b) => strcmp($a[2], $b[2]));
            // Principal downloads use the historical column contract, without scope metadata.
            $tables[$filename] = ['headers' => array_merge(self::BASE, $headers), 'rows' => $rows, 'sheet' => 'Resumen'];
            if ($category === 'facturas') {
                // Alternative name of the SAME combined result, never import both.
                $tables['ResumenCombinado.xlsx'] = $tables[$filename];
            }
        }
        $audit = [];
        $supportAudit = [];
        $supportFiles = array_column(array_filter($preview['archivos'], fn ($s) => $s['categoria'] === 'soporte'), 'archivo');
        foreach ($preview['auditoria'] as $entry) {
            $audit[] = [$entry['archivo'], $entry['hoja'], $entry['fila'], $entry['estado'], $entry['detalle']];
            if (($entry['categoria'] ?? null) === 'soporte') {
                $v = $raw[$this->originKey($entry)] ?? [];
                $supportAudit[] = [$entry['archivo'], $entry['fila'], $v['nit'] ?? '', $v['nombre'] ?? '',
                    $v['receptor'] ?? '', '', $v['tipo'] ?? '', $entry['estado'] === 'Contada' ? 'Sí' : 'No',
                    $entry['estado'].': '.$entry['detalle'].' [Hoja: '.$entry['hoja'].']'];
            }
        }
        foreach ($omitted as $entry) {
            $audit[] = ['', $entry[0], '', 'No exportado: '.$entry[1], $entry[2].'. '.$entry[3]];
        }
        $tables['AuditoriaCadena.xlsx'] = ['sheet' => 'Auditoría', 'headers' => ['Archivo origen', 'Hoja', 'Fila Excel', 'Resultado', 'Motivo'], 'rows' => $audit];
        if ($supportFiles !== []) {
            $tables['AuditoriaDocumentoSoporte.xlsx'] = ['sheet' => 'Auditoría', 'headers' => ['Archivo origen', 'Fila Excel', 'Nit Emisor', 'Razón Social Emisor', 'Nit Receptor', 'Razón Social Receptor', 'Tipo Documento', 'Fila contada', 'Motivo'], 'rows' => $supportAudit];
        }
        $ready = $errors = [];
        foreach ($tables as $filename => $table) {
            $split = $table['sheet'] === 'Auditoría' && count($table['rows']) > 40000;
            $chunks = $split ? array_chunk($table['rows'], 40000) : [$table['rows']];
            foreach ($chunks as $index => $rows) {
                $name = $split ? substr($filename, 0, -5).'_'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'.xlsx' : $filename;
                $part = array_replace($table, ['rows' => $rows]);
                try {
                    $this->checkSize($part);
                    $ready[$name] = $part;
                } catch (\RuntimeException $exception) {
                    $errors[$name] = $this->errorMessage($name, count($rows), $exception->getMessage());
                }
            }
        }
        return ['tables' => $ready, 'omitidos' => $omitted, 'errores' => $errors];
    }

    private function originKey(array $source): string
    {
        return json_encode([$source['categoria'] ?? '', $source['hash'] ?? '', $source['archivo'], $source['hoja'], $source['fila']], JSON_THROW_ON_ERROR);
    }

    public function errorMessage(string $filename, int $rows, string $reason): string
    {
        return $filename.' — '.$rows.' filas. '.$reason.' Límites: 50.000 filas, 450.000 celdas (incluido encabezado), 32.767 caracteres/celda; auditorías: máximo 40.000 filas por parte.';
    }

    private function checkSize(array $table): void
    {
        if (count($table['rows']) > 50000 || (count($table['rows']) + 1) * count($table['headers']) > 450000) {
            throw new \RuntimeException('Demasiadas filas para una descarga. Divide la carga.');
        }
        foreach ($table['rows'] as $row) {
            foreach ($row as $value) {
                if (is_string($value) && mb_strlen($value) > 32767) {
                    throw new \RuntimeException('Una celda excede el límite de texto de Excel.');
                }
            }
        }
    }

    /** Stream worksheet XML to private temporary files; no full cell grid in memory. */
    public function write(array $table, string $period, string $destination): void
    {
        $this->checkSize($table);
        $xlsx = tempnam(sys_get_temp_dir(), 'cadena-xlsx-');
        $xmlPath = tempnam(sys_get_temp_dir(), 'cadena-xml-');
        if ($xlsx === false || $xmlPath === false) {
            if ($xlsx !== false) { unlink($xlsx); }
            if ($xmlPath !== false) { unlink($xmlPath); }
            throw new \RuntimeException('No hay espacio temporal para la descarga.');
        }
        $zip = new \ZipArchive();
        $opened = false;
        $xml = null;
        try {
            // PhpSpreadsheet produces the package/styles/properties, with only its header row.
            $template = $this->workbook(array_replace($table, ['rows' => []]), $period);
            try {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($template))->save($xlsx);
            } finally {
                $template->disconnectWorksheets();
            }
            if ($zip->open($xlsx) !== true) {
                throw new \RuntimeException('No fue posible preparar el XLSX.');
            }
            $opened = true;
            $templateXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            preg_match('/<c\b[^>]*r="A1"[^>]*s="(\d+)"/', $templateXml, $match);
            $headerStyle = $match[1] ?? '0';
            $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($table['headers']));
            $range = 'A1:'.$last.(count($table['rows']) + 1);
            $xml = new \XMLWriter();
            if (!$xml->openUri($xmlPath)) {
                throw new \RuntimeException('No fue posible escribir la hoja.');
            }
            $xml->startDocument('1.0', 'UTF-8', 'yes');
            $xml->startElement('worksheet');
            $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('dimension'); $xml->writeAttribute('ref', $range); $xml->endElement();
            $xml->startElement('sheetViews'); $xml->startElement('sheetView'); $xml->writeAttribute('workbookViewId', '0');
            $xml->startElement('pane');
            foreach (['ySplit' => '1', 'topLeftCell' => 'A2', 'activePane' => 'bottomLeft', 'state' => 'frozen'] as $k => $v) { $xml->writeAttribute($k, $v); }
            $xml->endElement(); $xml->endElement(); $xml->endElement();
            $xml->startElement('cols');
            foreach ($table['headers'] as $c => $_) {
                $xml->startElement('col');
                $xml->writeAttribute('min', (string) ($c + 1)); $xml->writeAttribute('max', (string) ($c + 1));
                $width = $table['sheet'] === 'Auditoría' && $c === count($table['headers']) - 1 ? 100 : ($c === 3 ? 48 : 24);
                $xml->writeAttribute('width', (string) $width); $xml->writeAttribute('customWidth', '1'); $xml->endElement();
            }
            $xml->endElement();
            $xml->startElement('sheetData');
            foreach (array_merge([$table['headers']], $table['rows']) as $r => $row) {
                $xml->startElement('row'); $xml->writeAttribute('r', (string) ($r + 1));
                if ($r === 0) { $xml->writeAttribute('ht', '42'); $xml->writeAttribute('customHeight', '1'); }
                foreach ($row as $c => $value) {
                    if ($value === null) { continue; }
                    $numeric = is_int($value) || is_float($value);
                    $xml->startElement('c');
                    $xml->writeAttribute('r', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1).($r + 1));
                    if ($r === 0) { $xml->writeAttribute('s', $headerStyle); }
                    $xml->writeAttribute('t', $numeric ? 'n' : 'inlineStr');
                    if ($numeric) {
                        $xml->writeElement('v', (string) $value);
                    } else {
                        $xml->startElement('is'); $xml->startElement('t'); $xml->writeAttribute('xml:space', 'preserve');
                        $xml->text(\PhpOffice\PhpSpreadsheet\Shared\StringHelper::controlCharacterPHP2OOXML((string) $value));
                        $xml->endElement(); $xml->endElement();
                    }
                    $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement();
            $xml->startElement('autoFilter'); $xml->writeAttribute('ref', $range); $xml->endElement();
            $xml->endElement(); $xml->endDocument(); $xml->flush(); $xml = null;
            if (!$zip->addFile($xmlPath, 'xl/worksheets/sheet1.xml') || !$zip->close()) {
                throw new \RuntimeException('No fue posible finalizar el XLSX.');
            }
            $opened = false;
            if ($destination === 'php://output') {
                readfile($xlsx);
            } elseif (!copy($xlsx, $destination)) {
                throw new \RuntimeException('No fue posible guardar el XLSX.');
            }
        } finally {
            $xml = null;
            if ($opened) { $zip->close(); }
            unlink($xmlPath);
            unlink($xlsx);
        }
    }

    public function workbook(array $table, string $period): Spreadsheet
    {
        $this->checkSize($table);
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('OrganizadorWeb')->setTitle($period)
            ->setDescription('Resumen de Cadena. Período seleccionado: '.$period.'. Eventos: E=032, F=030, G=033, H=034, I=031.');
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($table['sheet']);
        foreach (array_merge([$table['headers']], $table['rows']) as $r => $row) {
            foreach ($row as $c => $value) {
                if ($value === null) {
                    continue;
                }
                // All untrusted text is explicit text, never interpreted as Excel formula.
                $sheet->setCellValueExplicit([$c + 1, $r + 1], $value,
                    is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($table['headers']));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('4338CA');
        $sheet->getStyle('A1:'.$last.'1')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(42);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(48);
        if ($table['sheet'] === 'Auditoría') {
            $sheet->getColumnDimension($last)->setWidth(100);
            $sheet->getStyle('A2:'.$last.max(2, count($table['rows']) + 1))->getAlignment()->setWrapText(true);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.(count($table['rows']) + 1));
        return $book;
    }
}
