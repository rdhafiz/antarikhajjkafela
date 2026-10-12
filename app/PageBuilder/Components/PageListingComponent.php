<?php

namespace App\PageBuilder\Components;

use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Component;

/**
 * The fixed listing on Gallery, Reviews and Blog pages. Admins can move it
 * between other sections but not remove it; each module supplies the
 * listing view and data for its page.
 */
class PageListingComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'page_listing';
    }

    public function name(): string
    {
        return 'Page listing';
    }

    public function description(): string
    {
        return 'The list of items for this page (albums, reviews or posts).';
    }

    public function icon(): string
    {
        return 'list';
    }

    public function isSystem(): bool
    {
        return true;
    }

    public function repeatable(): bool
    {
        return false;
    }

    /**
     * Optional heading. When the listing opens the page (no banner above it),
     * it is the page's main heading, with the page title as the fallback.
     */
    public function fields(): array
    {
        return $this->headingFields();
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $provider = config("page-builder.listings.{$page->system_key}");

        return $provider ? app($provider)->data($page) + ['listingView' => app($provider)->view()] : ['listingView' => null];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
