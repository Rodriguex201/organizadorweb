<?php

namespace App\Support;

final class GrupoFechaHelper
{
    /**
     * Grupos oficiales de facturacion por fecha de arriendo.
     *
     * 7 y 27 conservan el comportamiento historico de dia exacto.
     * 10 y 20 agrupan rangos intermedios sin duplicar la logica en servicios.
     */
    private const GROUPS = [
        7 => ['label' => 'Grupo 7', 'type' => 'exact', 'day' => 7],
        10 => ['label' => 'Grupo 10', 'type' => 'range', 'from' => 8, 'to' => 16],
        20 => ['label' => 'Grupo 20', 'type' => 'range', 'from' => 17, 'to' => 26],
        27 => ['label' => 'Grupo 27', 'type' => 'exact', 'day' => 27],
    ];

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

    public static function allowedGroups(): array
    {
        return array_keys(self::GROUPS);
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::allowedGroups());
    }

    public static function isAllowed(null|string|int $grupoFecha): bool
    {
        return self::normalize($grupoFecha) !== null;
    }

    public static function label(null|string|int $grupoFecha): string
    {
        $normalized = self::normalize($grupoFecha);

        return $normalized !== null ? self::GROUPS[$normalized]['label'] : 'Grupo';
    }

    public static function applyGrupoFechaConstraint($query, string $column, null|string|int $grupoFecha): void
    {
        $normalized = self::normalize($grupoFecha);

        if ($normalized === null) {
            return;
        }

        $rule = self::GROUPS[$normalized];
        $daySql = self::arriendoCutDaySql($column);

        if ($rule['type'] === 'range') {
            $query->whereRaw("{$daySql} BETWEEN ? AND ?", [(int) $rule['from'], (int) $rule['to']]);

            return;
        }

        $query->whereRaw("{$daySql} = ?", [(int) $rule['day']]);
    }

    public static function normalize(null|string|int $grupoFecha): ?int
    {
        if ($grupoFecha === null) {
            return null;
        }

        $valor = trim((string) $grupoFecha);

        if ($valor === '' || !ctype_digit($valor)) {
            return null;
        }

        $grupo = (int) $valor;

        return array_key_exists($grupo, self::GROUPS) ? $grupo : null;
    }
}
