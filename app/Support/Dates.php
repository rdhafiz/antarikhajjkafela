<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Dates for visitors: month names in the current language and, on Bangla
 * pages, Bangla digits ("10 August 2025" → "১০ আগস্ট ২০২৫").
 */
class Dates
{
    public static function format(?DateTimeInterface $date, string $format = 'j F Y', ?string $locale = null): ?string
    {
        if (! $date) {
            return null;
        }

        $locale ??= app()->getLocale();

        return Money::digits(Carbon::instance($date)->locale($locale)->translatedFormat($format), $locale);
    }
}
