<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Media;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;

/**
 * Application-wide data the admin SPA needs once after signing in.
 */
class BootstrapController extends Controller
{
    public function __invoke(Locales $locales): JsonResponse
    {
        return response()->json([
            'app' => [
                'name' => setting_t('branding.site_name', 'en'),
                'url' => url('/'),
                'logo' => Media::find(settings('branding.logo'))?->url('sm'),
                'icon' => Media::find(settings('branding.app_icon'))?->url('192'),
            ],
            'locales' => array_values($locales->available()),
            'default_locale' => $locales->default(),
            'currency' => settings('localization.currency'),
            'admin_theme' => settings()->group('admin_theme'),
            'media_mode' => settings('media.optimization_mode'),
            'counts' => [
                'inquiries_unread' => request()->user()?->can('inquiries.view') ? Inquiry::whereNull('read_at')->count() : 0,
                'bookings_unread' => request()->user()?->can('bookings.view') ? Booking::whereNull('read_at')->count() : 0,
            ],
        ]);
    }
}
