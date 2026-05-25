<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class GenericSpreadsheetImport implements ToArray, WithCustomCsvSettings
{
    /**
     * @return array<string, string>
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'escape_character' => '\\',
            'input_encoding' => 'UTF-8',
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $array
     */
    public function array(array $array): void
    {
    }
}
