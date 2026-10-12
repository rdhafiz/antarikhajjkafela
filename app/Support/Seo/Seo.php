<?php

namespace App\Support\Seo;

use App\Models\Media;
use App\Support\Branding;

/**
 * SEO details of the page being rendered. Controllers fill what they know;
 * anything left empty falls back to the default SEO settings.
 */
class Seo
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $image = null;

    public ?string $canonical = null;

    public string $type = 'website';

    /** schema.org type of the page itself (WebPage, AboutPage, ContactPage, CollectionPage…). */
    public string $pageType = 'WebPage';

    public bool $noindex = false;

    /** Whether the site name is appended to the title. */
    public bool $appendSiteName = true;

    /** @var list<array{label: string, url: string|null}> */
    public array $breadcrumbs = [];

    /** @var list<array<string, mixed>> */
    public array $schema = [];

    public function title(?string $title, bool $appendSiteName = true): static
    {
        $this->title = $title;
        $this->appendSiteName = $appendSiteName;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description ? trim(preg_replace('/\s+/', ' ', strip_tags($description))) : null;

        return $this;
    }

    public function image(?string $url): static
    {
        $this->image = $url;

        return $this;
    }

    public function canonical(?string $url): static
    {
        $this->canonical = $url;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function pageType(string $type): static
    {
        $this->pageType = $type;

        return $this;
    }

    public function noindex(bool $noindex = true): static
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function breadcrumb(string $label, ?string $url = null): static
    {
        $this->breadcrumbs[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function schema(array $schema): static
    {
        $this->schema[] = $schema;

        return $this;
    }

    public function resolvedTitle(): string
    {
        $site = setting_t('branding.site_name');

        if (blank($this->title)) {
            return setting_t('seo.meta_title') ?: $site;
        }

        return $this->appendSiteName && $this->title !== $site ? "{$this->title} | {$site}" : $this->title;
    }

    public function resolvedDescription(): ?string
    {
        $description = $this->description ?: setting_t('seo.meta_description') ?: setting_t('branding.short_description');

        return $description ? str($description)->limit(160)->toString() : null;
    }

    public function resolvedCanonical(): string
    {
        return $this->canonical ?? url()->current();
    }

    /**
     * The share image: the page's own, then the default from the settings, then the logo.
     */
    public function resolvedImage(): ?string
    {
        return $this->image
            ?? (($og = settings('seo.og_image')) ? Media::find($og)?->url('og') : null)
            ?? app(Branding::class)->logoUrl();
    }
}
