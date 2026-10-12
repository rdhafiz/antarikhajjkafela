<?php

namespace App\Http\Controllers\Site;

use App\Domain\Travel\PackagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Service;
use App\Models\SlugRedirect;
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
        $services = Service::active()->orderBy('sort')->get();
        $current = $services->firstWhere('slug', $request->query('service'));

        seo()->title(__('Hajj & Umrah packages'))
            ->description(__('Compare our Hajj and Umrah packages from Bangladesh: hotels, flights, durations and prices.'))
            ->canonical(route('packages.index'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb(__('Packages'), route('packages.index'));

        return response()->view('site.packages.index', [
            'service' => null,
            'services' => $services,
            'current' => $current,
            'packages' => $this->listing($current?->id),
        ]);
    }

    /**
     * A service's own page, e.g. /hajj (reached through the page route).
     */
    public function service(Service $service): Response
    {
        $locale = app()->getLocale();

        seo()->title($service->seo['title'][$locale] ?? $service->getTranslation('name', $locale))
            ->description($service->seo['description'][$locale] ?? $service->getTranslation('summary', $locale))
            ->canonical($service->url())
            ->image($service->image?->url('og'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($service->getTranslation('name', $locale), $service->url());

        $packages = $this->listing($service->id);
        $durations = $packages->pluck('duration_days')->filter();

        return response()->view('site.packages.service', [
            'service' => $service,
            'packages' => $packages,
            'about' => $this->splitDescription($service->getTranslation('description', $locale)),
            'stats' => [
                'count' => $packages->count(),
                'from' => $packages->pluck('min_price')->filter(fn ($price) => $price > 0)->min(),
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

        seo()->title($package->seo['title'][$locale] ?? $data['name'])
            ->description($package->seo['description'][$locale] ?? $data['summary'])
            ->canonical($package->url())
            ->image($package->banner?->url('og') ?? $package->banner?->url('lg'))
            ->type('product')
            ->noindex((bool) ($package->seo['noindex'] ?? false))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($package->service->getTranslation('name', $locale), $package->service->url())
            ->breadcrumb($data['name'], $package->url())
            ->schema($this->presenter->schema($package));

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
        ]);
    }

    /**
     * @return Collection<int, Package>
     */
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
