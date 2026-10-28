<?php

namespace App\Http\Controllers\Site;

use App\Domain\Pages\PageRenderer;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\SlugRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function __construct(private readonly PageRenderer $renderer) {}

    public function home(): Response
    {
        $page = Page::where('system_key', 'home')->first();

        abort_if(! $page || ! $page->isPublished(), 404);

        return $this->show($page);
    }

    public function slug(string $slug): Response|RedirectResponse
    {
        if ($slug === 'home') {
            return redirect('/', 301);
        }

        $page = Page::where('slug', $slug)->first();

        // Service pages such as /hajj and /umrah share the same address space.
        if ($page === null && ($service = Service::active()->where('slug', $slug)->first())) {
            return app(PackageController::class)->service($service);
        }

        // A service that changed its address: the old one redirects.
        if ($page === null && ($moved = SlugRedirect::target('service', $slug)) && ($service = Service::active()->find($moved))) {
            return redirect()->to(url($service->slug), 301);
        }

        abort_if($page === null, 404);

        if ($page->isCampaign() && $page->isPublished() && $page->campaignState() === 'expired') {
            return redirect()->to($this->expiredRedirect(), 302);
        }

        abort_unless($page->isVisibleToPublic(), 404);

        return $this->show($page);
    }

    /**
     * Signed preview link from the admin: shows drafts and unpublished campaigns.
     */
    public function preview(Page $page): Response
    {
        return $this->show($page, preview: true);
    }

    private function show(Page $page, bool $preview = false): Response
    {
        $seoData = $page->seo ?? [];
        $locale = app()->getLocale();
        $title = $page->getTranslation('title', $locale);

        $seo = seo()
            ->title($seoData['title'][$locale] ?? ($page->isHome() ? null : $title), appendSiteName: ! $page->isHome() || filled($seoData['title'][$locale] ?? null))
            ->description($seoData['description'][$locale] ?? null)
            ->canonical($page->url())
            ->pageType(match ($page->system_key) {
                'about' => 'AboutPage',
                'contact' => 'ContactPage',
                'gallery' => 'ImageGallery',
                'blog', 'reviews' => 'CollectionPage',
                default => 'WebPage',
            })
            ->noindex($preview || ($seoData['noindex'] ?? false));

        if (! empty($seoData['image']) && ($image = Media::find($seoData['image']))) {
            $seo->image($image->url('og'));
        }

        if (! $page->isHome()) {
            $seo->breadcrumb(__('Home'), url('/'))->breadcrumb($title, $page->url());
        }

        // Rendered after the SEO details are set: sections such as the page banner read the breadcrumbs.
        $rendered = $this->renderer->render($page);

        foreach ($rendered['schema'] as $schema) {
            $seo->schema($schema);
        }

        return response()->view('site.page', [
            'page' => $page,
            'html' => $rendered['html'],
            'darkTop' => $rendered['dark_top'],
            'footerCta' => ! $rendered['ends_with_cta'],
            'preview' => $preview,
        ]);
    }

    private function expiredRedirect(): string
    {
        $target = settings('campaigns.expired_redirect_page');
        $page = $target ? Page::published()->whereKey($target)->first() : null;

        return $page && $page->isVisibleToPublic() ? $page->url() : url('/');
    }
}
