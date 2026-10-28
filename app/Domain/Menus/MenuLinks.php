<?php

namespace App\Domain\Menus;

use App\Models\Package;
use App\Models\Page;
use App\Models\Service;

/**
 * The kinds of destinations a menu item can point to, and how each one
 * becomes a URL. Records (pages, services, packages) are linked by id, so
 * menu links follow them when their address changes.
 */
class MenuLinks
{
    public const NONE = 'none';

    public const URL = 'url';

    public const ROUTE = 'route';

    public const PAGE = 'page';

    public const SERVICE = 'service';

    public const PACKAGE = 'package';

    /** Types whose target is a record id or route key (and therefore required). */
    public const TARGETED = [self::ROUTE, self::PAGE, self::SERVICE, self::PACKAGE];

    /**
     * Link types and their selectable targets, for the admin menu builder.
     *
     * @return list<array{type: string, label: string, targets: list<array{value: string, label: string}>|null}>
     */
    public function types(): array
    {
        return [
            [
                'type' => self::ROUTE,
                'label' => 'Website page',
                'targets' => collect(config('menus.routes'))
                    ->map(fn ($route, $key) => ['value' => (string) $key, 'label' => $route['label']])
                    ->values()
                    ->all(),
            ],
            [
                'type' => self::PAGE,
                'label' => 'Custom page',
                'targets' => Page::where('type', Page::TYPE_CUSTOM)->orderBy('id')->get()
                    ->map(fn (Page $page) => ['value' => (string) $page->id, 'label' => $page->getTranslation('title', 'en').($page->isPublished() ? '' : ' (draft)')])
                    ->all(),
            ],
            [
                'type' => self::SERVICE,
                'label' => 'Service',
                'targets' => Service::orderBy('sort')->get()
                    ->map(fn (Service $service) => ['value' => (string) $service->id, 'label' => $service->getTranslation('name', 'en')])
                    ->all(),
            ],
            [
                'type' => self::PACKAGE,
                'label' => 'Package',
                'targets' => Package::orderBy('service_id')->orderBy('sort')->get()
                    ->map(fn (Package $package) => ['value' => (string) $package->id, 'label' => $package->getTranslation('name', 'en').($package->isPublished() ? '' : ' (draft)')])
                    ->all(),
            ],
            ['type' => self::URL, 'label' => 'Custom link', 'targets' => null],
            ['type' => self::NONE, 'label' => 'Dropdown heading (no link)', 'targets' => null],
        ];
    }

    /**
     * @return list<string>
     */
    public function typeKeys(): array
    {
        return [self::ROUTE, self::PAGE, self::SERVICE, self::PACKAGE, self::URL, self::NONE];
    }

    public function isValidTarget(string $type, ?string $target): bool
    {
        if (! in_array($type, self::TARGETED, true)) {
            return true;
        }

        if (blank($target)) {
            return false;
        }

        return match ($type) {
            self::ROUTE => array_key_exists($target, config('menus.routes')),
            self::PAGE => Page::whereKey((int) $target)->where('type', Page::TYPE_CUSTOM)->exists(),
            self::SERVICE => Service::whereKey((int) $target)->exists(),
            self::PACKAGE => Package::whereKey((int) $target)->exists(),
        };
    }

    public function resolve(string $type, ?string $target, ?string $url): ?string
    {
        return match ($type) {
            self::ROUTE => isset(config('menus.routes')[$target]) ? url(config("menus.routes.{$target}.path")) : null,
            self::PAGE => ($page = Page::find((int) $target)) && $page->isVisibleToPublic() ? $page->url() : null,
            // Hidden from menus until the service has a published package (like its page).
            self::SERVICE => ($service = Service::listed()->find((int) $target)) ? $service->url() : null,
            self::PACKAGE => ($package = Package::find((int) $target)) && $package->isPublished() ? $package->url() : null,
            self::URL => $this->normalizeUrl($url),
            default => null,
        };
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }
}
