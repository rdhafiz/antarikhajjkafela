<?php

namespace App\Http\Controllers\Site;

use App\Domain\Travel\PackagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Package;
use App\Models\Service;
use App\Models\SlugRedirect;
use App\Support\Seo\SeoText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class PackageController extends Controller
{
    public function __construct(private readonly PackagePresenter $presenter) {}

    /**
     * All published packages, optionally filtered by service (?service=hajj).
     */
    public function index(Request $request): Response
    {
        $services = Service::listed()->orderBy('sort')->get();
        $current = $services->firstWhere('slug', $request->query('service'));

        seo()->title(__('All packages'))
            ->description(__('Hajj, Umrah and holiday tour packages from Dhaka: compare flights, hotels, durations and prices.'))
            ->canonical(route('packages.index'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb(__('Packages'), route('packages.index'));

        $packages = $this->listing($current?->id)->load('types.prices');

        seo()->pageType('CollectionPage')->schema($this->itemList($packages));

        return response()->view('site.packages.index', [
            'service' => null,
            'services' => $services,
            'current' => $current,
            'packages' => $packages,
            'cards' => $this->cards($packages),
        ]);
    }

    /**
     * A service's own page, e.g. /hajj (reached through the page route).
     */
    public function service(Service $service): Response
    {
        $locale = app()->getLocale();

        $shareImage = ! empty($service->seo['image']) ? Media::find($service->seo['image']) : null;

        seo()->title(SeoText::single($service->seo['title'] ?? null, $locale) ?? $service->getTranslation('name', $locale))
            ->description(SeoText::single($service->seo['description'] ?? null, $locale) ?? $service->getTranslation('summary', $locale))
            ->canonical($service->url())
            ->image($shareImage?->url('og') ?? $service->image?->url('lg'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($service->getTranslation('name', $locale), $service->url());

        $packages = $this->listing($service->id)->load('types.prices');
        $durations = $packages->pluck('duration_days')->filter();

        seo()->schema($this->serviceSchema($service, $packages))->schema($this->itemList($packages))->customSchema($service->seo['schema'] ?? null);

        return response()->view('site.packages.service', [
            'service' => $service,
            'packages' => $packages,
            'cards' => $this->cards($packages),
            'about' => $this->splitDescription($service->getTranslation('description', $locale)),
            'stats' => [
                'count' => $packages->count(),
                'from' => $this->shownPrices($packages)->min(),
                'days' => $durations->isEmpty() ? null : [$durations->min(), $durations->max()],
            ],
        ]);
    }

    /**
     * The service description as plain paragraphs plus quoted verses or hadith:
     * a paragraph that is a quotation (optionally after a "… said:" lead-in) is a
     * quote, and a closing "(…)" is its source.
     *
     * @return array{paragraphs: list<string>, quotes: list<array{lead: ?string, text: string, source: ?string}>}
     */
    private function splitDescription(?string $description): array
    {
        $paragraphs = [];
        $quotes = [];

        foreach (preg_split('/\R+/u', trim((string) $description)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?:([^"“]{2,160}?):\s*)?["“](.+)["”]\s*(?:\((.+)\))?\s*$/us', $line, $match)) {
                $quotes[] = [
                    'lead' => filled($match[1]) ? trim($match[1]) : null,
                    'text' => trim($match[2]),
                    'source' => isset($match[3]) ? trim($match[3]) : null,
                ];
            } else {
                $paragraphs[] = $line;
            }
        }

        return ['paragraphs' => $paragraphs, 'quotes' => $quotes];
    }

    public function show(string $slug): Response|RedirectResponse
    {
        $package = Package::published()->where('slug', $slug)->first();

        if (! $package) {
            $target = SlugRedirect::target('package', $slug);
            $moved = $target ? Package::published()->find($target) : null;

            abort_unless($moved, 404);

            return redirect()->to($moved->url(), 301);
        }

        $locale = app()->getLocale();
        $data = $this->presenter->detail($package);

        seo()->title(SeoText::single($package->seo['title'] ?? null, $locale) ?? $data['name'])
            ->description(SeoText::single($package->seo['description'] ?? null, $locale) ?? $data['summary'])
            ->canonical($package->url())
            ->image((! empty($package->seo['image']) ? Media::find($package->seo['image'])?->url('og') : null) ?? $package->banner?->url('lg'))
            ->type('product')
            ->pageType('ItemPage')
            ->noindex((bool) ($package->seo['noindex'] ?? false))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($package->service->getTranslation('name', $locale), $package->service->url())
            ->breadcrumb($data['name'], $package->url())
            ->schema($this->presenter->schema($package))
            ->customSchema($package->seo['schema'] ?? null);

        $related = Package::published()
            ->with('banner', 'service')
            ->where('service_id', $package->service_id)
            ->whereKeyNot($package->id)
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->limit(3)
            ->get();

        return response()->view('site.packages.show', [
            'package' => $package,
            'p' => $data,
            'related' => $related,
            'relatedCards' => $this->cards($related),
        ]);
    }

    /**
     * A service (Hajj, Umrah) as schema.org Service, with the price range of its packages.
     *
     * @param  Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function serviceSchema(Service $service, Collection $packages): array
    {
        $locale = app()->getLocale();
        $prices = $this->shownPrices($packages);

        return array_filter([
            '@type' => 'Service',
            '@id' => $service->url().'#service',
            'name' => $service->getTranslation('name', $locale),
            'serviceType' => $service->getTranslation('name', 'en'),
            'description' => $service->getTranslation('summary', $locale),
            'url' => $service->url(),
            'provider' => ['@id' => url('/#organization')],
            'areaServed' => ['@type' => 'Country', 'name' => 'Bangladesh'],
            'offers' => $prices->isEmpty() ? null : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => settings('localization.currency') ?: 'BDT',
                'lowPrice' => (float) $prices->min(),
                'highPrice' => (float) $prices->max(),
                'offerCount' => $packages->count(),
            ],
        ]);
    }

    /**
     * The packages shown on a page, as a schema.org ItemList.
     *
     * @param  Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function itemList(Collection $packages): array
    {
        $locale = app()->getLocale();

        return [
            '@type' => 'ItemList',
            'itemListElement' => $packages->values()->map(fn (Package $package, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $package->url(),
                'name' => $package->getTranslation('name', $locale),
            ])->all(),
        ];
    }

    /**
     * Listing card data (options, hotels, flight) for each package, by package id.
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, array<string, mixed>>
     */
    private function cards(Collection $packages): Collection
    {
        return $packages->mapWithKeys(fn (Package $package) => [$package->id => $this->presenter->card($package)]);
    }

    /**
     * @return Collection<int, Package>
     */
    /**
     * Starting prices of the packages that show their price.
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, mixed>
     */
    private function shownPrices(Collection $packages): Collection
    {
        return $packages->where('show_price', true)->pluck('min_price')->filter(fn ($price) => $price > 0);
    }

    private function listing(?int $serviceId): Collection
    {
        return Package::published()
            ->with('banner', 'service')
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->orderBy('min_price')
            ->get();
    }
}
