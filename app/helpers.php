<?php

use App\Domain\Settings\Settings;
use App\Support\Seo\Seo;

if (! function_exists('settings')) {
    /**
     * Get the settings service, or a single setting value by "group.key" path.
     */
    function settings(?string $path = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $path === null ? $settings : $settings->get($path, $default);
    }
}

if (! function_exists('setting_t')) {
    /**
     * Get a translatable setting in the current language.
     */
    function setting_t(string $path, ?string $locale = null): ?string
    {
        return app(Settings::class)->translated($path, $locale);
    }
}

if (! function_exists('admin_url')) {
    /**
     * Absolute URL of an admin portal page, e.g. admin_url('bookings/12').
     */
    function admin_url(string $path = ''): string
    {
        return url(trim(config('app.admin_prefix'), '/').'/admin'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('seo')) {
    /**
     * SEO details of the current page.
     */
    function seo(): Seo
    {
        return app(Seo::class);
    }
}
