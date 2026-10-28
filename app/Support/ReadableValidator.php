<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Validation with readable names for per-language fields: "name.bn" reads
 * "Name (Bangla)" and "types.0.name.en" reads "Types name (English)" instead
 * of the raw key. Names given by the form (custom attributes) still win.
 */
class ReadableValidator extends Validator
{
    public function getDisplayableAttribute($attribute)
    {
        $shown = parent::getDisplayableAttribute($attribute);

        if (! preg_match('/^(.+)\.([a-z]{2}(?:[-_][a-z]{2,4})?)$/i', (string) $attribute, $match)) {
            return $shown;
        }

        $language = app(Locales::class)->available()[$match[2]]['name'] ?? null;
        $raw = [$attribute, str_replace('_', ' ', Str::snake($attribute))];

        if ($language === null || ! in_array($shown, $raw, true)) {
            return $shown;
        }

        $base = Str::of(parent::getDisplayableAttribute($match[1]))
            ->replace(['.', '_'], ' ')
            ->replaceMatches('/\b\d+\b/', '')
            ->squish()
            ->ucfirst();

        return "{$base} ({$language})";
    }
}
