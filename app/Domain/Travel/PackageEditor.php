<?php

namespace App\Domain\Travel;

use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\PackageType;
use App\Models\SlugRedirect;
use App\Models\User;
use App\PageBuilder\ContentProcessor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a package with its flights, sections and the nested
 * types → prices → hotels from validated admin input.
 */
class PackageEditor
{
    public function __construct(private readonly ContentProcessor $content) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Package $package, array $data, User $user): Package
    {
        DB::transaction(function () use ($package, $data, $user) {
            $oldSlug = $package->slug;
            $slug = $this->uniqueSlug(Str::slug($data['name']['en'] ?? '') ?: 'package', $package->id);

            $flights = $this->content->sanitize(PackageSchema::flights(), ['flights' => $data['flights'] ?? []])['flights'];
            $sections = $this->content->sanitize(PackageSchema::sections(), ['sections' => $data['sections'] ?? []])['sections'];

            $package->fill([
                'service_id' => $data['service_id'],
                'name' => array_filter($data['name']),
                'slug' => $slug,
                'summary' => array_filter($data['summary'] ?? []),
                'banner_id' => $data['banner_id'] ?? null,
                'booking_starts_on' => $data['booking_starts_on'] ?? null,
                'booking_ends_on' => $data['booking_ends_on'] ?? null,
                'duration_days' => $data['duration_days'] ?? null,
                'status' => $data['status'],
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'show_price' => (bool) ($data['show_price'] ?? true),
                'advance_type' => $data['advance_type'] ?? 'none',
                'advance_value' => ($data['advance_type'] ?? 'none') === 'none' ? null : ($data['advance_value'] ?? null),
                'flights' => $flights,
                'sections' => $sections,
                'seo' => [
                    'title' => filled($data['seo']['title'] ?? null) ? trim($data['seo']['title']) : null,
                    'description' => filled($data['seo']['description'] ?? null) ? trim($data['seo']['description']) : null,
                    'image' => $data['seo']['image'] ?? null,
                    'noindex' => (bool) ($data['seo']['noindex'] ?? false),
                    'schema' => filled($data['seo']['schema'] ?? null) ? trim($data['seo']['schema']) : null,
                ],
                'updated_by' => $user->id,
            ])->save();

            if ($oldSlug && $oldSlug !== $slug) {
                SlugRedirect::remember('package', $oldSlug, $package->id);
            }

            $this->syncTypes($package, $data['types'] ?? []);

            $package->forceFill(['min_price' => $package->prices()->min('price')])->save();
        });

        Cache::forget('sitemap.xml');

        return $package->refresh();
    }

    /**
     * Copy a package (as a draft) with all its types and prices.
     */
    public function duplicate(Package $package, User $user): Package
    {
        return DB::transaction(function () use ($package, $user) {
            $copy = $package->replicate(['slug']);
            $copy->setTranslations('name', collect($package->getTranslations('name'))->map(fn ($n) => "{$n} (copy)")->all());
            $copy->slug = $this->uniqueSlug(Str::slug($copy->getTranslation('name', 'en')) ?: 'package', null);
            $copy->status = Package::STATUS_DRAFT;
            $copy->is_featured = false;
            $copy->updated_by = $user->id;
            $copy->save();

            foreach ($package->types()->with('prices')->get() as $type) {
                $newType = $copy->types()->create($type->only(['code', 'sort']) + [
                    'name' => $type->getTranslations('name'),
                    'short_description' => $type->getTranslations('short_description'),
                ]);

                foreach ($type->prices as $price) {
                    $newType->prices()->create($price->only(['price', 'unit', 'hotels', 'sort']) + [
                        'title' => $price->getTranslations('title'),
                        'short_description' => $price->getTranslations('short_description'),
                    ]);
                }
            }

            return $copy;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $types
     */
    private function syncTypes(Package $package, array $types): void
    {
        $clean = $this->content->sanitize(PackageSchema::types(), ['types' => $types])['types'];
        $keptTypes = [];
        $keptPrices = [];

        foreach (array_values($clean) as $typeIndex => $typeData) {
            $rawType = $types[$typeIndex] ?? [];
            $type = isset($rawType['id']) ? $package->types()->whereKey($rawType['id'])->first() : null;

            $typeAttributes = [
                'code' => $typeData['code'],
                'name' => array_filter($typeData['name']),
                'short_description' => array_filter($typeData['short_description'] ?? []),
                'sort' => $typeIndex,
            ];

            $type ? $type->update($typeAttributes) : $type = $package->types()->create($typeAttributes);
            $keptTypes[] = $type->id;

            foreach (array_values($typeData['prices'] ?? []) as $priceIndex => $priceData) {
                $rawPrice = $rawType['prices'][$priceIndex] ?? [];
                $price = isset($rawPrice['id']) ? PackagePrice::whereKey($rawPrice['id'])->whereIn('package_type_id', $package->types()->pluck('id'))->first() : null;

                $priceAttributes = [
                    'package_type_id' => $type->id,
                    'title' => array_filter($priceData['title']),
                    'short_description' => array_filter($priceData['short_description'] ?? []),
                    'price' => $priceData['price'] ?? 0,
                    'unit' => array_key_exists($priceData['unit'] ?? '', Package::UNITS) ? $priceData['unit'] : 'person',
                    'hotels' => $priceData['hotels'] ?? [],
                    'sort' => $priceIndex,
                ];

                $price ? $price->update($priceAttributes) : $price = PackagePrice::create($priceAttributes);
                $keptPrices[] = $price->id;
            }
        }

        $typeIds = $package->types()->pluck('id');
        PackagePrice::whereIn('package_type_id', $typeIds)->whereNotIn('id', $keptPrices)->get()->each->delete();
        PackageType::where('package_id', $package->id)->whereNotIn('id', $keptTypes)->get()->each->delete();
    }

    private function uniqueSlug(string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $i = 2;

        while (Package::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Admin payload for a package.
     *
     * @return array<string, mixed>
     */
    public static function present(Package $package): array
    {
        $package->loadMissing('types.prices', 'service');

        return [
            'id' => $package->id,
            'service_id' => $package->service_id,
            'service' => $package->service ? ['id' => $package->service->id, 'name' => $package->service->getTranslations('name')] : null,
            'name' => (object) $package->getTranslations('name'),
            'slug' => $package->slug,
            'url' => $package->url(),
            'summary' => (object) $package->getTranslations('summary'),
            'banner_id' => $package->banner_id,
            'booking_starts_on' => $package->booking_starts_on?->toDateString(),
            'booking_ends_on' => $package->booking_ends_on?->toDateString(),
            'booking_state' => $package->bookingState(),
            'duration_days' => $package->duration_days,
            'status' => $package->status,
            'is_featured' => $package->is_featured,
            'show_price' => $package->show_price ?? true,
            'advance_type' => $package->advance_type,
            'advance_value' => $package->advance_value !== null ? (float) $package->advance_value : null,
            'min_price' => $package->min_price !== null ? (float) $package->min_price : null,
            'flights' => $package->flights ?? [],
            'sections' => $package->sections ?? [],
            'seo' => [
                'title' => $package->seo['title'] ?? null,
                'description' => $package->seo['description'] ?? null,
                'image' => $package->seo['image'] ?? null,
                'noindex' => (bool) ($package->seo['noindex'] ?? false),
                'schema' => $package->seo['schema'] ?? null,
            ],
            'types' => $package->types->map(fn (PackageType $type) => [
                'id' => $type->id,
                '_id' => 't'.$type->id,
                'code' => $type->code,
                'name' => (object) $type->getTranslations('name'),
                'short_description' => (object) $type->getTranslations('short_description'),
                'prices' => $type->prices->map(fn (PackagePrice $price) => [
                    'id' => $price->id,
                    '_id' => 'p'.$price->id,
                    'title' => (object) $price->getTranslations('title'),
                    'short_description' => (object) $price->getTranslations('short_description'),
                    'price' => (float) $price->price,
                    'unit' => $price->unit,
                    'hotels' => $price->hotels ?? [],
                ])->values(),
            ])->values(),
            'updated_at' => $package->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Validation rules for the nested parts, including the ids that tie
     * submitted types and prices to existing rows.
     *
     * @return array<string, mixed>
     */
    /**
     * Readable names for those fields ("Price (BDT)", "Heading (Bangla)"),
     * instead of keys like "types.0.prices.0.price".
     *
     * @return array<string, string>
     */
    public function nestedAttributes(): array
    {
        $names = [
            ...$this->content->attributes(PackageSchema::flights(), 'nested'),
            ...$this->content->attributes(PackageSchema::types(), 'nested'),
            ...$this->content->attributes(PackageSchema::sections(), 'nested'),
        ];

        return Arr::mapWithKeys($names, fn ($name, $key) => [Str::after($key, 'nested.') => $name]);
    }

    public function nestedRules(): array
    {
        $rules = [
            ...$this->content->rules(PackageSchema::flights(), 'nested'),
            ...$this->content->rules(PackageSchema::types(), 'nested'),
            ...$this->content->rules(PackageSchema::sections(), 'nested'),
        ];
        unset($rules['nested']);

        $rules = Arr::mapWithKeys($rules, fn ($rule, $key) => [Str::after($key, 'nested.') => $rule]);
        $rules['types.*.id'] = ['nullable', 'integer'];
        $rules['types.*.prices.*.id'] = ['nullable', 'integer'];

        return $rules;
    }
}
