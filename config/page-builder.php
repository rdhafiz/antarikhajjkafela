<?php

use App\Domain\Blog\BlogListing;
use App\Domain\Gallery\GalleryListing;
use App\Domain\Reviews\ReviewListing;
use App\PageBuilder\Components;

return [

    /*
    | Every section design available in the page builder.
    */
    'components' => [
        Components\HeroComponent::class,
        Components\PageBannerComponent::class,
        Components\AboutComponent::class,
        Components\ImageTextComponent::class,
        Components\RichTextComponent::class,
        Components\FeaturesComponent::class,
        Components\StatsComponent::class,
        Components\ProcessComponent::class,
        Components\QuoteComponent::class,
        Components\TeamComponent::class,
        Components\FaqComponent::class,
        Components\VideoComponent::class,
        Components\LogosComponent::class,
        Components\PricingComponent::class,
        Components\CtaComponent::class,
        Components\ContactInfoComponent::class,
        Components\ContactFormComponent::class,
        Components\LeadFormComponent::class,
        Components\CountdownComponent::class,
        Components\PackageShowcaseComponent::class,
        Components\TestimonialsComponent::class,
        Components\GalleryPreviewComponent::class,
        Components\LatestPostsComponent::class,
        Components\PageListingComponent::class,
    ],

    /*
    | Default pages. Their slugs are fixed; pages with a listing get the
    | non-removable "page_listing" section, filled by the provider below.
    */
    'default_pages' => [
        'home' => ['slug' => 'home', 'title' => ['en' => 'Home', 'bn' => 'হোম'], 'listing' => false],
        'about' => ['slug' => 'about', 'title' => ['en' => 'About us', 'bn' => 'আমাদের সম্পর্কে'], 'listing' => false],
        'services' => ['slug' => 'services', 'title' => ['en' => 'Services', 'bn' => 'সেবাসমূহ'], 'listing' => false],
        'contact' => ['slug' => 'contact', 'title' => ['en' => 'Contact us', 'bn' => 'যোগাযোগ'], 'listing' => false],
        'gallery' => ['slug' => 'gallery', 'title' => ['en' => 'Gallery', 'bn' => 'গ্যালারি'], 'listing' => true],
        'reviews' => ['slug' => 'reviews', 'title' => ['en' => 'Reviews', 'bn' => 'রিভিউ'], 'listing' => true],
        'blog' => ['slug' => 'blog', 'title' => ['en' => 'Blog & news', 'bn' => 'ব্লগ ও সংবাদ'], 'listing' => true],
    ],

    /*
    | Listing providers for default pages, keyed by system key. Each provider
    | has view() and data(Page $page).
    */
    'listings' => [
        'blog' => BlogListing::class,
        'reviews' => ReviewListing::class,
        'gallery' => GalleryListing::class,
    ],

    /*
    | Slugs pages cannot use because the site already routes them.
    */
    'reserved_slugs' => [
        'admin', 'ahk', 'api', 'build', 'storage', 'images', 'language', 'sanctum', 'up', 'styleguide',
        'sitemap.xml', 'robots.txt', 'site.webmanifest', 'forms', 'preview',
        'hajj', 'umrah', 'packages', 'package', 'booking', 'bookings', 'payments', 'blog', 'gallery', 'reviews', 'services',
    ],

];
