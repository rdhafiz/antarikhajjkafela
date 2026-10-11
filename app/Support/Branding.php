<?php

namespace App\Support;

use App\Models\Media;

/**
 * Brand assets and colors from the settings, resolved once per request.
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

    private function media(string $path): ?Media
    {
        if (! array_key_exists($path, $this->media)) {
            $id = settings($path);
            $this->media[$path] = $id ? Media::find($id) : null;
        }

        return $this->media[$path];
    }
}
