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
            fn ($cell) => is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) && ! self::isText($cell) ? "'".$cell : $cell,
            $cells,
        ));
    }

    /**
     * Keep a phone number's leading zero in Excel, which otherwise reads the
     * column as a number and drops it. ="0171…" is the one form Excel treats
     * as text when it parses a CSV.
     */
    public static function phone(?string $number): string
    {
        $number = trim((string) $number);

        return $number === '' ? '' : '="'.preg_replace('/[^0-9+\-\s()]/', '', $number).'"';
    }

    /**
     * A cell this class wrapped itself. The character set is narrow enough
     * that a visitor-entered value can never pass as one.
     */
    private static function isText(string $cell): bool
    {
        return (bool) preg_match('/^="[0-9+\-\s()]*"$/', $cell);
    }
}
