<?php

namespace App\Support;

final class GrupoFechaHelper
{
    public static function arriendoCutDaySql(string $column): string
    {
        $firstSegment = "SUBSTRING_INDEX(TRIM(COALESCE({$column}, '')), '-', 1)";
        $lastSegment = "SUBSTRING_INDEX(TRIM(COALESCE({$column}, '')), '-', -1)";

        return "CASE
            WHEN CHAR_LENGTH({$firstSegment}) = 4
                THEN CAST({$lastSegment} AS UNSIGNED)
            ELSE CAST({$firstSegment} AS UNSIGNED)
        END";
    }

    public static function normalize(null|string|int $grupoFecha): ?int
    {
        if ($grupoFecha === null) {
            return null;
        }

        $valor = trim((string) $grupoFecha);

        if (!in_array($valor, ['7', '27'], true)) {
            return null;
        }

        return (int) $valor;
    }
}
