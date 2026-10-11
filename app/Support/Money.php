<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use NumberFormatter;

/**
 * Prices in the site currency (BDT) with South Asian digit grouping
 * (6,19,999), in Bangla digits on Bangla pages.
 */
class Money
{
    public const SYMBOLS = ['BDT' => '৳', 'USD' => '$', 'AED' => 'AED ', 'SAR' => 'SAR ', 'INR' => '₹', 'PKR' => 'Rs '];

    public static function format(float|int|string|null $amount, ?string $locale = null): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $currency = settings('localization.currency') ?: 'BDT';
        $locale ??= app()->getLocale();

        return (self::SYMBOLS[$currency] ?? $currency.' ').self::number((float) $amount, $locale);
    }

    /**
     * Formatted amount for HTML. The currency symbol (৳ sits in the Bengali
     * Unicode block) uses the system font, so English pages never download a
     * Bangla web font just for the symbol.
     */
    public static function html(float|int|string|null $amount, ?string $locale = null): HtmlString
    {
        $text = self::format($amount, $locale);
        $currency = settings('localization.currency') ?: 'BDT';
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        if ($text === '' || ! str_starts_with($text, $symbol)) {
            return new HtmlString(e($text));
        }

        return new HtmlString('<span class="currency-symbol">'.e($symbol).'</span>'.e(substr($text, strlen($symbol))));
    }

    public static function number(float|int $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale === 'bn' ? 'bn_BD' : 'en_IN', NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, fmod((float) $value, 1.0) === 0.0 ? 0 : 2);

            return (string) $formatter->format($value);
        }

        return number_format((float) $value);
    }

    /**
     * Localized digits for any number (e.g. "40" → "৪০" in Bangla).
     */
    public static function digits(int|string $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'bn' ? strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : (string) $value;
    }
}
