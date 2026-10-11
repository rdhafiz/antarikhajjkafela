<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Serves the admin portal shell; the Vue router takes over from there.
 */
class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.app', [
            'theme' => settings()->group('admin_theme'),
            'appName' => setting_t('branding.site_name', 'en'),
        ]);
    }
}
