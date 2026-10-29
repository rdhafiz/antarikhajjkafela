<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Media;

/**
 * Brand assets and colors from the settings, resolved once per request.
 * When no logo or icon has been uploaded yet, the bundled brand files are used.
 *
 * Hajj & Umrah pages (the services, their packages and bookings) belong to the
 * agency's division Antarik Hajj Kafela: after usePilgrimage() the logo and the
 * website name are the division's.
 */
class Branding
{
    /** @var array<string, Media|null> */
    private array $media = [];

    private bool $pilgrimage = false;

    /**
     * Show this request's page as the Hajj & Umrah division.
     */
    public function usePilgrimage(bool $pilgrimage = true): static
    {
        $this->pilgrimage = $pilgrimage;

        return $this;
    }

    /**
     * The branding for something about a booking (its emails): the division's for
     * Hajj and Umrah packages. A copy, so the page being rendered keeps its own.
     */
    public function forBooking(?Booking $booking): static
    {
        $copy = clone $this;
        $copy->pilgrimage = (bool) $booking?->isPilgrimage();

        return $copy;
    }

    public function isPilgrimage(): bool
    {
        return $this->pilgrimage;
    }

    /**
     * The name shown with the logo, in titles and in share previews.
     */
    public function siteName(): string
    {
        return $this->pilgrimage ? $this->divisionName() : $this->agencyName();
    }

    public function agencyName(): string
    {
        return (string) setting_t('branding.site_name');
    }

    public function divisionName(): string
    {
        return (string) (setting_t('branding.hajj_name') ?: 'Antarik Hajj Kafela');
    }

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
        return $this->media($this->pilgrimage ? 'branding.hajj_logo' : 'branding.logo');
    }

    /**
     * Background image for page headers that have none of their own.
     */
    public function defaultBanner(): ?Media
    {
        return $this->media('branding.default_banner');
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
        return $this->logo()?->url('md') ?? $this->bundled($this->bundledLogo().'-880.webp');
    }

    /**
     * The agency's own logo, whichever page is shown (the organisation in structured data).
     */
    public function agencyLogoUrl(): string
    {
        return $this->media('branding.logo')?->url('md') ?? $this->bundled('logo-880.webp');
    }

    public function logoSrcset(): string
    {
        $file = $this->bundledLogo();

        return $this->logo()?->srcset()
            ?: $this->bundled("{$file}-360.webp").' 360w, '.$this->bundled("{$file}-440.webp").' 440w, '.$this->bundled("{$file}-880.webp").' 880w';
    }

    /**
     * The logo for dark backgrounds (transparent header over a photo, mobile
     * menu): the uploaded light logo, or the bundled one while the bundled
     * logo is in use. Null when a custom logo has no light version.
     * The Hajj & Umrah logo is the same on dark and light backgrounds.
     *
     * @return array{src: string, srcset: string}|null
     */
    public function lightLogo(): ?array
    {
        if ($this->pilgrimage) {
            return ['src' => $this->logoUrl(), 'srcset' => $this->logoSrcset()];
        }

        if ($light = $this->media('branding.logo_light')) {
            return ['src' => $light->url('md'), 'srcset' => $light->srcset()];
        }

        if ($this->logo() === null) {
            return [
                'src' => $this->bundled('logo-light-880.webp'),
                'srcset' => $this->bundled('logo-light-360.webp').' 360w, '.$this->bundled('logo-light-440.webp').' 440w, '.$this->bundled('logo-light-880.webp').' 880w',
            ];
        }

        return null;
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

        return $this->bundled($bundled);
    }

    public function faviconUrl(int $size = 32): string
    {
        return $this->favicon()?->url($size <= 32 ? '32' : '192') ?? $this->iconUrl($size);
    }

    /**
     * A bundled brand file, versioned by its content: the files keep their
     * names, so browsers would otherwise show a cached old logo or favicon.
     */
    private function bundledLogo(): string
    {
        return $this->pilgrimage ? 'hajj-logo' : 'logo';
    }

    private function bundled(string $file): string
    {
        static $versions = [];

        $versions[$file] ??= substr((string) @md5_file(public_path("images/brand/{$file}")), 0, 8);

        return asset("images/brand/{$file}").($versions[$file] !== '' ? "?v={$versions[$file]}" : '');
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
