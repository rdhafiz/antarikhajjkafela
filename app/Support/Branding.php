<?php

namespace App\Support;

use App\Models\Media;

/**
 * Brand assets and colors from the settings, resolved once per request.
 * When no logo or icon has been uploaded yet, the bundled brand files are used.
 */
class Branding
{
    /** @var array<string, Media|null> */
    private array $media = [];

    /**
     * @return array{primary: string, secondary: string, accent: string}
     */
    public function colors(): array
    {
        return [
            'primary' => settings('theme.primary_color'),
            'secondary' => settings('theme.secondary_color'),
            'accent' => settings('theme.accent_color'),
        ];
    }

    public function logo(): ?Media
    {
        return $this->media('branding.logo');
    }

    public function appIcon(): ?Media
    {
        return $this->media('branding.app_icon');
    }

    public function favicon(): ?Media
    {
        return $this->media('branding.favicon') ?? $this->appIcon();
    }

    public function logoUrl(): string
    {
        return $this->logo()?->url('md') ?? asset('images/brand/logo-880.webp');
    }

    public function logoSrcset(): string
    {
        return $this->logo()?->srcset()
            ?: asset('images/brand/logo-360.webp').' 360w, '.asset('images/brand/logo-440.webp').' 440w, '.asset('images/brand/logo-880.webp').' 880w';
    }

    /**
     * Square brand icon at roughly the requested size.
     */
    public function iconUrl(int $size = 192): string
    {
        $variant = match (true) {
            $size <= 32 => '32',
            $size <= 192 => '192',
            default => '512',
        };

        if ($icon = $this->appIcon()) {
            return $icon->url($variant);
        }

        $bundled = match (true) {
            $size <= 96 => 'icon-96.webp',
            $size <= 192 => 'icon-192.webp',
            default => 'icon-512.webp',
        };

        return asset("images/brand/{$bundled}");
    }

    public function faviconUrl(int $size = 32): string
    {
        return $this->favicon()?->url($size <= 32 ? '32' : '192') ?? $this->iconUrl($size);
    }

    private function media(string $path): ?Media
    {
        if (! array_key_exists($path, $this->media)) {
            $id = settings($path);
            $this->media[$path] = $id ? Media::find($id) : null;
        }

        return $this->media[$path];
    }
}
