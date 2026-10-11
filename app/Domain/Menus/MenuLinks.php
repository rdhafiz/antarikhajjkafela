<?php

namespace App\Domain\Menus;

/**
 * The kinds of destinations a menu item can point to, and how each one
 * becomes a URL. Modules built later (pages, services, packages, blog
 * categories) register their own types here.
 */
class MenuLinks
{
    public const NONE = 'none';

    public const URL = 'url';

    public const ROUTE = 'route';

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
            ['type' => self::URL, 'label' => 'Custom link', 'targets' => null],
            ['type' => self::NONE, 'label' => 'Dropdown heading (no link)', 'targets' => null],
        ];
    }

    /**
     * @return list<string>
     */
    public function typeKeys(): array
    {
        return array_column($this->types(), 'type');
    }

    /**
     * @return list<string>
     */
    public function targetsFor(string $type): array
    {
        return match ($type) {
            self::ROUTE => array_map('strval', array_keys(config('menus.routes'))),
            default => [],
        };
    }

    public function resolve(string $type, ?string $target, ?string $url): ?string
    {
        return match ($type) {
            self::ROUTE => isset(config('menus.routes')[$target]) ? url(config("menus.routes.{$target}.path")) : null,
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
