<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Extra URL sources added by later modules: classes with an urls() method
     * returning list<array{loc: string, lastmod: ?string}>.
     *
     * @var list<class-string>
     */
    public static array $sources = [];

    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = Page::published()
                ->get()
                ->filter(fn (Page $page) => $page->isVisibleToPublic() && ! ($page->seo['noindex'] ?? false))
                ->map(fn (Page $page) => ['loc' => $page->url(), 'lastmod' => $page->updated_at?->toAtomString()])
                ->values()
                ->all();

            foreach (Service::active()->orderBy('sort')->get() as $service) {
                $urls[] = ['loc' => $service->url(), 'lastmod' => $service->updated_at?->toAtomString()];
            }

            $urls[] = ['loc' => route('packages.index'), 'lastmod' => null];

            foreach (Package::published()->get() as $package) {
                if (! ($package->seo['noindex'] ?? false)) {
                    $urls[] = ['loc' => $package->url(), 'lastmod' => $package->updated_at?->toAtomString()];
                }
            }

            foreach (Post::live()->latest('published_at')->get() as $post) {
                if (! ($post->seo['noindex'] ?? false)) {
                    $urls[] = ['loc' => $post->url(), 'lastmod' => $post->updated_at?->toAtomString()];
                }
            }

            foreach (GalleryAlbum::published()->whereHas('items')->newest()->get() as $album) {
                $urls[] = ['loc' => $album->url(), 'lastmod' => $album->updated_at?->toAtomString()];
            }

            foreach (self::$sources as $source) {
                array_push($urls, ...app($source)->urls());
            }

            return view('site.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->isProduction() && config('app.search_indexing')
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /api', 'Disallow: /preview', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
