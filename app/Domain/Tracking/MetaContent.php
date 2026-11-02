<?php

namespace App\Domain\Tracking;

use App\Models\Package;
use App\Models\Page;
use App\Models\Service;

/**
 * How packages and services are described in Meta events, browser or server.
 * Names are the English ones, so reports read the same in every language.
 */
class MetaContent
{
    /** Lead form fields that make a page a service page (air tickets, visa). */
    private const SERVICE_FIELDS = ['trip_type', 'from_city', 'visa_country', 'visa_type'];

    /**
     * @return array<string, mixed>
     */
    public static function package(?Package $package): array
    {
        return $package ? [
            'content_type' => 'product',
            'content_ids' => [(string) $package->id],
            'content_name' => $package->getTranslation('name', 'en'),
        ] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function packageView(Package $package): array
    {
        return self::package($package) + array_filter([
            'content_category' => $package->service?->getTranslation('name', 'en'),
            'value' => $package->show_price && $package->min_price > 0 ? (float) $package->min_price : null,
            'currency' => $package->show_price && $package->min_price > 0 ? (settings('localization.currency') ?: 'BDT') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceView(Service $service): array
    {
        return [
            'content_type' => 'product_group',
            'content_ids' => ['service-'.$service->id],
            'content_name' => $service->getTranslation('name', 'en'),
            'content_category' => 'service',
        ];
    }

    /**
     * Air Tickets and Visa are custom pages built around their quote form; other pages aren't services.
     *
     * @return array<string, mixed>|null
     */
    public static function pageView(Page $page): ?array
    {
        if ($page->type !== Page::TYPE_CUSTOM) {
            return null;
        }

        $isService = $page->sections
            ->where('component', 'lead_form')
            ->where('is_visible', true)
            ->contains(fn ($section) => collect($section->content['fields'] ?? [])->pluck('field')->intersect(self::SERVICE_FIELDS)->isNotEmpty());

        return $isService ? [
            'content_type' => 'product_group',
            'content_ids' => ['page-'.$page->id],
            'content_name' => $page->getTranslation('title', 'en'),
            'content_category' => 'service',
        ] : null;
    }
}
