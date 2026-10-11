<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;

/**
 * Web app manifest, so the site can be added to a phone's home screen
 * with the agency's name, icon and color.
 */
class ManifestController extends Controller
{
    public function __invoke(Branding $branding): JsonResponse
    {
        $colors = $branding->colors();

        return response()->json(array_filter([
            'name' => setting_t('branding.site_name'),
            'short_name' => setting_t('branding.site_name'),
            'description' => setting_t('branding.short_description'),
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $colors['primary'],
            'icons' => [
                ['src' => $branding->iconUrl(192), 'sizes' => '192x192', 'type' => 'image/webp'],
                ['src' => $branding->iconUrl(512), 'sizes' => '512x512', 'type' => 'image/webp'],
            ],
        ]), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
