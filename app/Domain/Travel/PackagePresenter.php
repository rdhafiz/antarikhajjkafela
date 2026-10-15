<?php

namespace App\Domain\Travel;

use App\Models\Media;
use App\Models\Package;
use App\Models\PackagePrice;
use App\PageBuilder\ContentProcessor;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Package data for the public pages, in the current language.
 */
class PackagePresenter
{
    public function __construct(private readonly ContentProcessor $content) {}

    /**
     * Everything the detail page shows. When the package hides its price,
     * every amount is null and 'showPrice' is false.
     *
     * @return array<string, mixed>
     */
    public function detail(Package $package): array
    {
        $locale = app()->getLocale();
        $package->loadMissing('types.prices', 'service', 'banner');

        $hotelFields = collect(PackageSchema::types()[0]->children())->firstWhere('key', 'prices')->children();
        $hotelFields = collect($hotelFields)->firstWhere('key', 'hotels')->children();

        $media = $this->media($package);
        $show = $package->show_price;

        return [
            'name' => $package->getTranslation('name', $locale),
            'summary' => $package->getTranslation('summary', $locale),
            'service' => $package->service,
            'banner' => $package->banner,
            'duration' => $package->duration_days,
            'from' => $show ? $package->min_price : null,
            'showPrice' => $show,
            'booking' => $this->booking($package),
            'flights' => $this->content->localize(PackageSchema::flights(), ['flights' => $package->flights], $locale, $media)['flights'],
            'types' => $package->types->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->getTranslation('name', $locale),
                'description' => $type->getTranslation('short_description', $locale),
                'prices' => $type->prices->map(fn (PackagePrice $price) => [
                    'id' => $price->id,
                    'title' => $price->getTranslation('title', $locale),
                    'description' => $price->getTranslation('short_description', $locale),
                    'price' => $show ? (float) $price->price : null,
                    'unit' => $price->unit,
                    'hotels' => array_map(fn ($hotel) => $this->content->localize($hotelFields, $hotel, $locale, $media), $price->hotels ?? []),
                ])->all(),
            ])->filter(fn ($type) => $type['prices'] !== [])->values()->all(),
            'sections' => $this->content->localize(PackageSchema::sections(), ['sections' => $package->sections], $locale, $media)['sections'],
        ];
    }

    /**
     * @return array{state: string, label: string, starts: Carbon|null, ends: Carbon|null}
     */
    public function booking(Package $package): array
    {
        $state = $package->bookingState();
        $date = fn (?Carbon $d) => Dates::format($d);

        $label = match ($state) {
            'upcoming' => __('Booking opens :date', ['date' => $date($package->booking_starts_on)]),
            'closed' => __('Booking closed'),
            default => $package->booking_ends_on ? __('Booking open until :date', ['date' => $date($package->booking_ends_on)]) : __('Booking open'),
        };

        return ['state' => $state, 'label' => $label, 'starts' => $package->booking_starts_on, 'ends' => $package->booking_ends_on];
    }

    /**
     * A package as a listing card: each type's cheapest option with its hotels,
     * and the first flight.
     *
     * @return array<string, mixed>
     */
    public function card(Package $package): array
    {
        $locale = app()->getLocale();
        $package->loadMissing('types.prices');

        $hotelFields = collect(PackageSchema::types()[0]->children())->firstWhere('key', 'prices')->children();
        $hotelFields = collect($hotelFields)->firstWhere('key', 'hotels')->children();
        $flightFields = PackageSchema::flights();

        $tiers = $package->types->map(function ($type) use ($locale, $hotelFields) {
            $price = $type->prices->sortBy('price')->first();

            if (! $price) {
                return null;
            }

            return [
                'name' => $type->getTranslation('name', $locale),
                'option' => $price->id,
                'price' => (float) $price->price,
                'unit' => self::unitLabel($price->unit),
                'hotels' => array_map(
                    fn ($hotel) => $this->content->localize($hotelFields, $hotel, $locale, collect()),
                    array_slice($price->hotels ?? [], 0, 2),
                ),
            ];
        })->filter()->sortByDesc('price')->values()
            // Ordered by price either way; hidden prices are left out.
            ->map(fn ($tier) => $package->show_price ? $tier : [...$tier, 'price' => null])
            ->all();

        $flight = $this->content->localize($flightFields, ['flights' => array_slice($package->flights ?? [], 0, 1)], $locale, collect())['flights'][0] ?? null;

        return [
            'tiers' => $tiers,
            'showPrice' => $package->show_price,
            'flight' => $flight,
            'state' => $package->bookingState(),
        ];
    }

    public static function unitLabel(string $unit): string
    {
        return match ($unit) {
            'room' => __('per room'),
            'group' => __('per group'),
            default => __('per person'),
        };
    }

    /**
     * JSON-LD for a package (a product with an aggregate offer).
     *
     * @return array<string, mixed>
     */
    public function schema(Package $package): array
    {
        $prices = $package->show_price ? $package->prices()->pluck('price') : collect();

        return array_filter([
            '@type' => 'Product',
            'name' => $package->getTranslation('name', app()->getLocale()),
            'description' => $package->getTranslation('summary', app()->getLocale()) ?: null,
            'image' => $package->banner?->url('lg'),
            'category' => $package->service?->getTranslation('name', 'en'),
            'brand' => ['@id' => url('/#organization')],
            'offers' => $prices->isEmpty() ? null : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => settings('localization.currency') ?: 'BDT',
                'lowPrice' => (float) $prices->min(),
                'highPrice' => (float) $prices->max(),
                'offerCount' => $prices->count(),
                'availability' => match ($package->bookingState()) {
                    'upcoming' => 'https://schema.org/PreOrder',
                    'closed' => 'https://schema.org/SoldOut',
                    default => 'https://schema.org/InStock',
                },
                'url' => $package->url(),
            ],
        ]);
    }

    /**
     * @return Collection<int, Media>
     */
    private function media(Package $package): Collection
    {
        $ids = $this->content->mediaIds(PackageSchema::sections(), ['sections' => $package->sections]);

        return $ids === [] ? collect() : Media::whereIn('id', array_unique($ids))->get()->keyBy('id');
    }

    public static function price(float $amount): string
    {
        return Money::format($amount);
    }
}
