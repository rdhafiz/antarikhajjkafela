<?php

namespace App\Support;

/**
 * Helpers for CSV exports that staff open in Excel.
 */
class Csv
{
    /**
     * Neutralise cells that a spreadsheet would run as a formula
     * (visitor-entered names or messages starting with =, +, -, @…).
     *
     * @param  array<int, mixed>  $cells
     * @return list<mixed>
     */
    public static function row(array $cells): array
    {
        return array_values(array_map(
            fn ($cell) => is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell,
            $cells,
        ));
    }
}
