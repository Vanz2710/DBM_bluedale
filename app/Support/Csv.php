<?php

namespace App\Support;

class Csv
{
    /**
     * Neutralize spreadsheet formula injection: Excel/Sheets/LibreOffice treat a
     * cell starting with =, +, -, or @ as a formula when the CSV is opened. Since
     * these exports include free-text fields users control (names, remarks,
     * notes, etc.), prefix such values with an apostrophe so they render as
     * plain text instead of executing.
     */
    public static function safe(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    /**
     * Apply safe() to every value in a row array before passing it to fputcsv().
     */
    public static function row(array $row): array
    {
        return array_map([self::class, 'safe'], $row);
    }
}
