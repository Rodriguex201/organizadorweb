<?php

namespace App\Services\Cadena;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use RuntimeException;

class CadenaSpreadsheetReader
{
    private const EVENT_SHEET_ALIASES = ['eventosfefacturacio'];

    /** Prefer the explicitly identified events sheet; other books retain header detection. */
    private function selectedSheets(array $names, string $category): array
    {
        if ($category !== 'eventos') { return $names; }
        $known = array_values(array_filter($names, fn ($name) => in_array($this->normalize($name), self::EVENT_SHEET_ALIASES, true)));
        return $known !== [] ? $known : $names;
    }

    private const ALIASES = [
        'nit' => ['nitemisor', 'suppliernit'],
        'dv' => ['dv', 'dvemisor', 'supplierdv', 'digitoverificacion'],
        'nombre' => ['razonsocialemisor', 'supplierbusinessname'],
        'tipo' => ['tipodocumento', 'codigotipodocumento', 'documenttypecode'],
        'numero' => ['numerodocumento', 'nrodocumento', 'documentid'],
        'prefijo' => ['prefijo', 'prefix'],
        'producto' => ['subproducto', 'partnershipid'],
        'receptor' => ['nitreceptor', 'receivernit'],
        'dv_receptor' => ['dvreceptor', 'receiverdv'],
        'evento' => ['codigoevento', 'eventcode'],
        'cantidad' => ['cantidadtransacciones', 'transactionscount'],
        'evento_id' => ['eventid', 'idevento', 'cude'],
    ];

    public function read(string $path, string $extension, string $category): array
    {
        if ($extension === 'xlsx') {
            return $this->readXlsx($path, $category);
        }
        $allowed = ['csv' => 'Csv', 'xlsx' => 'Xlsx', 'xls' => 'Xls'];
        if (!isset($allowed[$extension])) {
            throw new RuntimeException('Formato no permitido.');
        }
        $reader = IOFactory::createReader($allowed[$extension]);
        $reader->setReadDataOnly(true);
        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
            $reader->setEscapeCharacter('');
            // PhpSpreadsheet detects comma, semicolon and tab separators.
        }
        // Inspect dimensions before allocating all spreadsheet cells.
        $estimatedCells = 0;
        $sheetInfo = $reader->listWorksheetInfo($path);
        $selected = $this->selectedSheets(array_column($sheetInfo, 'worksheetName'), $category);
        $reader->setLoadSheetsOnly($selected);
        foreach ($sheetInfo as $info) {
            if (!in_array($info['worksheetName'], $selected, true)) { continue; }
            $estimatedCells += $info['totalRows'] * $info['totalColumns'];
            if ($info['totalRows'] > 50000 || $info['totalColumns'] > 150 || $estimatedCells > 1000000) {
                throw new RuntimeException('El archivo supera los límites de tamaño de las hojas. Divídelo en archivos más pequeños.');
            }
        }
        $book = $reader->load($path);
        try {
            $sheets = [];
            $ignored = [];
            $total = 0;
            foreach ($book->getWorksheetIterator() as $sheet) {
                $height = $sheet->getHighestDataRow();
                $width = $sheet->getHighestDataColumn();
                $columns = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($width);
                if ($height > 50000 || $columns > 150 || ($total += $height * $columns) > 1000000) {
                    throw new RuntimeException('El archivo supera el límite de 50.000 filas por hoja, 150 columnas o 1.000.000 de celdas. Divídelo en archivos más pequeños.');
                }
                $rows = $sheet->rangeToArray('A1:'.$width.$height, null, false, false, false);
                $recognized = $this->recognize($rows, $category);
                if ($recognized === null) {
                    $ignored[] = $sheet->getTitle();
                    continue;
                }
                $sheets[] = ['hoja' => $sheet->getTitle()] + $recognized;
            }
            if ($sheets === []) {
                throw new RuntimeException('Encabezados no reconocidos para '.$category.'. Se requieren '.($category === 'eventos' ? 'Nit Receptor y Código Evento.' : 'Nit Emisor, Razón Social Emisor y Tipo Documento. Facturas también requiere Sub Producto, Prefijo y Número Documento.'));
            }

            return ['hojas' => $sheets, 'ignoradas' => $ignored];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** Stream stored XML cells, never the rectangular range declared by Excel. */
    private function readXlsx(string $path, string $category): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo XLSX.');
        }
        try {
            $bytes = 0;
            if ($zip->numFiles > 2000) {
                throw new RuntimeException('Demasiadas entradas en el archivo XLSX.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $bytes += $stat['size'];
                if ($stat['size'] > 67108864 || $bytes > 268435456) {
                    throw new RuntimeException('El contenido descomprimido del XLSX supera el límite de seguridad.');
                }
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $xml = $this->xmlStream($path, 'xl/sharedStrings.xml');
                try {
                    while ($this->nextXml($xml)) {
                        if ($xml->nodeType === \XMLReader::ELEMENT && $xml->localName === 'si') {
                            $strings[] = $this->xmlText($this->xmlElement($xml));
                            if (count($strings) > 500000) {
                                throw new RuntimeException('Demasiados textos compartidos en el XLSX.');
                            }
                        }
                    }
                } finally {
                    $xml->close();
                }
            }
            $relations = [];
            $xml = $this->xmlStream($path, 'xl/_rels/workbook.xml.rels');
            try {
                while ($this->nextXml($xml)) {
                    if ($xml->nodeType === \XMLReader::ELEMENT && $xml->localName === 'Relationship'
                        && str_ends_with((string) $xml->getAttribute('Type'), '/worksheet')) {
                        if ($xml->getAttribute('TargetMode') === 'External') {
                            throw new RuntimeException('No se permiten hojas externas.');
                        }
                        $target = (string) $xml->getAttribute('Target');
                        $part = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                        if (str_contains($part, '..') || !preg_match('~^xl/[a-zA-Z0-9_/.-]+\.xml$~D', $part)) {
                            throw new RuntimeException('Referencia de hoja XLSX inválida.');
                        }
                        $relations[$xml->getAttribute('Id')] = $part;
                    }
                }
            } finally {
                $xml->close();
            }
            $sheetParts = [];
            $xml = $this->xmlStream($path, 'xl/workbook.xml');
            try {
                while ($this->nextXml($xml)) {
                    if ($xml->nodeType === \XMLReader::ELEMENT && $xml->localName === 'sheet') {
                        $id = $xml->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                        if (isset($relations[$id])) {
                            $sheetParts[(string) $xml->getAttribute('name')] = $relations[$id];
                        }
                    }
                }
            } finally {
                $xml->close();
            }
            $sheets = $ignored = [];
            $cells = 0;
            $contentCells = 0;
            $selected = $this->selectedSheets(array_keys($sheetParts), $category);
            foreach ($sheetParts as $name => $part) {
                if (!in_array($name, $selected, true)) { $ignored[] = $name; continue; }
                $rows = $columns = $placeholders = [];
                $headerRow = null;
                $retainedColumns = [];
                $lastRow = -1;
                $xml = $this->xmlStream($path, $part);
                try {
                    while ($this->nextXml($xml)) {
                        if ($xml->nodeType !== \XMLReader::ELEMENT || $xml->localName !== 'c') {
                            continue;
                        }
                        if (++$cells > 2000000) {
                            throw new RuntimeException('El XLSX supera 2.000.000 de celdas almacenadas.');
                        }
                        $address = (string) $xml->getAttribute('r');
                        if (!preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/D', $address, $match)) {
                            throw new RuntimeException('Dirección de celda XLSX inválida.');
                        }
                        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($match[1]) - 1;
                        $row = (int) $match[2] - 1;
                        if ($row < $lastRow) {
                            throw new RuntimeException('Filas XLSX fuera de orden.');
                        }
                        if ($row !== $lastRow && $headerRow === null && $lastRow < 25 && isset($rows[$lastRow])) {
                            if ($this->recognize([$lastRow => $rows[$lastRow]], $category) !== null) {
                                $headerRow = $lastRow;
                                foreach ($rows[$lastRow] as $index => $label) {
                                    foreach (self::ALIASES as $aliases) {
                                        if (in_array($this->normalize($label), $aliases, true)) {
                                            $retainedColumns[$index] = true;
                                        }
                                    }
                                }
                            }
                        }
                        $lastRow = $row;
                        $type = $xml->getAttribute('t');
                        $cell = $this->xmlElement($xml);
                        $formulas = $cell->xpath('./*[local-name()="f"]');
                        $values = $cell->xpath('./*[local-name()="v"]');
                        $value = (string) ($values[0] ?? '');
                        if ($formulas !== []) {
                            $value = '='.(string) $formulas[0];
                        } elseif ($type === 's') {
                            if (!ctype_digit($value) || !array_key_exists((int) $value, $strings)) {
                                throw new RuntimeException('Referencia de texto compartido inválida.');
                            }
                            $value = $strings[(int) $value];
                        } elseif ($type === 'inlineStr') {
                            $value = $this->xmlText($cell);
                        }
                        if (trim($value) === '') {
                            continue;
                        }
                        if ($row >= 50000 || $column >= 16384) {
                            throw new RuntimeException('Celda con contenido fuera de los límites de filas o columnas de Excel.');
                        }
                        // Excel table placeholders are not data columns unless another cell uses them.
                        if ($row === 0 && preg_match('/^Columna[0-9]+$/D', $value)) {
                            $placeholders[$column] = $value;
                            continue;
                        }
                        if (++$contentCells > 1000000) {
                            throw new RuntimeException('El XLSX supera 1.000.000 de celdas con contenido efectivo.');
                        }
                        $columns[$column] = true;
                        if (count($columns) > 150) {
                            throw new RuntimeException('La hoja supera 150 columnas con contenido efectivo.');
                        }
                        // Validate all stored content, but retain only the selected schema's cells.
                        if ($headerRow !== null && $row > $headerRow && !isset($retainedColumns[$column])) {
                            $rows[$row][-1] = 'contenido fuera del esquema';
                            continue;
                        }
                        if ($headerRow === null && $row >= 25) {
                            continue;
                        }
                        if (isset($rows[$row][$column])) {
                            throw new RuntimeException('Celda XLSX repetida: '.$address);
                        }
                        $rows[$row][$column] = $value;
                    }
                } finally {
                    $xml->close();
                }
                foreach ($placeholders as $column => $label) {
                    if (isset($columns[$column])) {
                        $rows[0][$column] = $label;
                    }
                }
                ksort($rows);
                $recognized = $this->recognize($rows, $category);
                if ($recognized === null) {
                    $ignored[] = $name;
                } else {
                    $sheets[] = ['hoja' => $name] + $recognized;
                }
                unset($rows);
            }
            if ($sheets === []) {
                throw new RuntimeException('Encabezados no reconocidos para '.$category.'.');
            }

            return ['hojas' => $sheets, 'ignoradas' => $ignored];
        } finally {
            $zip->close();
        }
    }

    private function xmlStream(string $path, string $part): \XMLReader
    {
        $xml = new \XMLReader();
        if (!@$xml->open('zip://'.str_replace('\\', '/', realpath($path)).'#'.$part, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No fue posible leer una parte del XLSX.');
        }

        return $xml;
    }

    private function nextXml(\XMLReader $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $next = $xml->read();
            if (libxml_get_errors() !== [] || ($next && $xml->nodeType === \XMLReader::DOC_TYPE)) {
                throw new RuntimeException('XML malformado o DTD no permitido en XLSX.');
            }

            return $next;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xmlElement(\XMLReader $xml): \SimpleXMLElement
    {
        $text = $xml->readOuterXml();
        if (strlen($text) > 131072) {
            throw new RuntimeException('Texto de celda excesivamente grande.');
        }
        $element = @simplexml_load_string($text, \SimpleXMLElement::class, LIBXML_NONET);
        if ($element === false) {
            throw new RuntimeException('Contenido de celda XLSX inválido.');
        }

        return $element;
    }

    private function xmlText(\SimpleXMLElement $element): string
    {
        return implode('', array_map('strval', $element->xpath('.//*[local-name()="t"]')));
    }

    public function recognize(array $rows, string $category): ?array
    {
        $required = $category === 'eventos' ? ['receptor', 'evento'] : ['nit', 'nombre', 'tipo'];
        if ($category === 'facturas') {
            $required = array_merge($required, ['producto', 'numero', 'prefijo']);
        }
        foreach (array_filter($rows, fn ($index) => $index < 25, ARRAY_FILTER_USE_KEY) as $index => $header) {
            $map = [];
            foreach ($header as $column => $value) {
                $normalized = $this->normalize((string) $value);
                foreach (self::ALIASES as $key => $aliases) {
                    if (in_array($normalized, $aliases, true)) {
                        if (isset($map[$key])) {
                            throw new RuntimeException('Encabezado ambiguo o repetido: '.$key.' en fila '.($index + 1));
                        }
                        $map[$key] = $column;
                    }
                }
            }
            if (array_diff($required, array_keys($map)) !== []) {
                continue;
            }
            $data = [];
            foreach ($rows as $rowIndex => $row) {
                if ($rowIndex <= $index) {
                    continue;
                }
                if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                    continue;
                }
                $values = [];
                foreach ($map as $key => $column) {
                    $values[$key] = trim((string) ($row[$column] ?? ''));
                }
                $data[] = ['fila' => $rowIndex + 1, 'valores' => $values];
            }

            return ['encabezados' => $header, 'fila_encabezado' => $index + 1, 'filas' => $data];
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower($value), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z0-9]/', '', $value);
    }
}
