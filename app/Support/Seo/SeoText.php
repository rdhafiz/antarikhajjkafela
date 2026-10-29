<?php

namespace App\Support\Seo;

/**
 * SEO titles and descriptions are one text each, in whichever language the
 * admin writes them. Values saved before that were {en, bn} pairs: this picks
 * the one in the given (default) language, else whichever is filled.
 */
class SeoText
{
    public static function single(mixed $value, string $locale): ?string
    {
        if (is_array($value)) {
            $value = filled($value[$locale] ?? null) ? $value[$locale] : collect($value)->first(fn ($v) => filled($v));
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
