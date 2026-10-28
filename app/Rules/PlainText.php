<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Short text from the public forms (names, cities, subjects): no links, no
 * HTML and no spreadsheet formula start (= + - @), which only spam or tests
 * send and which would turn into live formulas in an exported sheet.
 */
class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|info|xyz|ru|io|bd)\b|[<>])~i', $value)) {
            $fail(__('Please use plain text here, without links or < >.'));

            return;
        }

        if (preg_match('/^\s*[=+\-@]/', $value)) {
            $fail(__('Please start with a letter or number, not = + - or @.'));
        }
    }
}
