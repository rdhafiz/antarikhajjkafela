<?php

namespace App\Domain\Booking;

use App\Models\Service;
use App\Support\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The travel date a booking asks for, set per service:
 *  - month_year (Umrah): a month and year, from the current month on;
 *  - hajj_year (Hajj): the Hajj year. Bangladesh closes registration for a
 *    Hajj in late September of the year before (Hajj 2027 closed on
 *    24 September 2026), so from October the first open year is two ahead.
 */
class TravelDates
{
    public const MODES = [
        'none' => 'Nothing',
        'month_year' => 'Travel month and year (Umrah)',
        'hajj_year' => 'Hajj year (Hajj)',
    ];

    /** Registration for next year's Hajj is closed from this month on. */
    public const HAJJ_REGISTRATION_CLOSES_AFTER = 9;

    private const YEARS = 3;

    public function mode(?Service $service): string
    {
        $mode = $service?->travel_date ?? 'none';

        return array_key_exists($mode, self::MODES) ? $mode : 'none';
    }

    /**
     * @return list<int>
     */
    public function years(string $mode, ?Carbon $now = null): array
    {
        $now ??= now();
        $first = match ($mode) {
            'hajj_year' => $now->year + ($now->month > self::HAJJ_REGISTRATION_CLOSES_AFTER ? 2 : 1),
            'month_year' => $now->year,
            default => null,
        };

        return $first === null ? [] : range($first, $first + self::YEARS - 1);
    }

    /**
     * What the booking form needs: the mode, the years, month names in the
     * visitor's language, and the first month that may be chosen this year.
     *
     * @return array{mode: string, years: list<int>, months: array<int, string>, currentYear: int, currentMonth: int}
     */
    public function form(?Service $service): array
    {
        $mode = $this->mode($service);

        return [
            'mode' => $mode,
            'years' => $this->years($mode),
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => Dates::format(Carbon::create(2000, $m, 1), 'F')])->all(),
            'currentYear' => now()->year,
            'currentMonth' => now()->month,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(string $mode, ?int $year): array
    {
        if ($mode === 'none') {
            return [];
        }

        $rules = ['travel_year' => ['required', 'integer', Rule::in($this->years($mode))]];

        if ($mode === 'month_year') {
            $from = $year === now()->year ? now()->month : 1;
            $rules['travel_month'] = ['required', 'integer', 'min:'.$from, 'max:12'];
        }

        return $rules;
    }

    /**
     * "November 2026", "Hajj 2028" or null, in the given language.
     */
    public static function label(?int $month, ?int $year, ?string $locale = null): ?string
    {
        if (! $year) {
            return null;
        }

        return $month
            ? Dates::format(Carbon::create($year, $month, 1), 'F Y', $locale)
            : __('Hajj :year', ['year' => Dates::format(Carbon::create($year, 1, 1), 'Y', $locale)], $locale);
    }
}
