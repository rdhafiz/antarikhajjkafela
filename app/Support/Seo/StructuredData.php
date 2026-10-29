<?php

namespace App\Support\Seo;

use App\Support\Branding;

/**
 * JSON-LD for every page: the agency (TravelAgency), the website, the
 * page breadcrumbs and any page-specific or admin-provided schema.
 */
class StructuredData
{
    public function __construct(private readonly Branding $branding) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forPage(Seo $seo): array
    {
        $graph = [$this->organization(), $this->website()];

        $hasBreadcrumbs = count($seo->breadcrumbs) > 1;
        $graph[] = $this->webPage($seo, $hasBreadcrumbs);

        if ($hasBreadcrumbs) {
            $graph[] = $this->breadcrumbs($seo->breadcrumbs, $seo->resolvedCanonical());
        }

        foreach ($seo->schema as $schema) {
            $graph[] = $schema;
        }

        array_push($graph, ...JsonLd::nodes((string) settings('seo.schema')));

        return $graph;
    }

    public function toJson(Seo $seo): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->forPage($seo)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(): array
    {
        $logo = $this->branding->logoUrl();
        $phones = array_values(array_filter([settings('contact.primary_phone'), settings('contact.secondary_phone')]));

        return array_filter([
            '@type' => 'TravelAgency',
            '@id' => url('/#organization'),
            'name' => setting_t('branding.site_name'),
            'alternateName' => array_values(array_unique(array_filter((array) settings('branding.site_name')))),
            'description' => setting_t('branding.short_description'),
            'url' => url('/'),
            'logo' => $logo,
            'image' => $logo,
            'telephone' => $phones[0] ?? null,
            'email' => settings('contact.primary_email'),
            'foundingDate' => '2016',
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => setting_t('contact.address', 'en'),
                'addressLocality' => 'Dhaka',
                'postalCode' => '1000',
                'addressCountry' => 'BD',
            ]),
            'contactPoint' => array_map(fn ($phone) => [
                '@type' => 'ContactPoint',
                'telephone' => $phone,
                'contactType' => 'customer service',
                'areaServed' => 'BD',
                'availableLanguage' => ['Bengali', 'English'],
            ], $phones),
            'sameAs' => array_values(array_filter(settings()->group('social'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/#website'),
            'url' => url('/'),
            'name' => setting_t('branding.site_name'),
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'publisher' => ['@id' => url('/#organization')],
        ];
    }

    /**
     * The page itself, typed by the controller (WebPage, AboutPage, ContactPage, CollectionPage…).
     *
     * @return array<string, mixed>
     */
    private function webPage(Seo $seo, bool $hasBreadcrumbs): array
    {
        $url = $seo->resolvedCanonical();
        $image = $seo->resolvedImage();

        return array_filter([
            '@type' => $seo->pageType,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $seo->resolvedTitle(),
            'description' => $seo->resolvedDescription(),
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'isPartOf' => ['@id' => url('/#website')],
            'about' => ['@id' => url('/#organization')],
            'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image] : null,
            'breadcrumb' => $hasBreadcrumbs ? ['@id' => $url.'#breadcrumb'] : null,
        ]);
    }

    /**
     * @param  list<array{label: string, url: string|null}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items, string $url): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['label'],
                'item' => $item['url'],
            ]))->all(),
        ];
    }
}
