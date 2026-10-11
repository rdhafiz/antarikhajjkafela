<?php

namespace App\Providers;

use App\Domain\Menus\MenuRepository;
use App\Domain\Settings\Settings;
use App\Models\Package;
use App\Models\Page;
use App\Models\Service;
use App\Support\Branding;
use App\Support\Locales;
use App\Support\Seo\Seo;
use App\Support\SiteContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Locales::class);
        $this->app->scoped(Branding::class);
        $this->app->scoped(SiteContext::class);
        $this->app->scoped(Seo::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyRuntimeSettings();

        // Super admins pass every permission check.
        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        // Menus store resolved links; refresh them when a linked record changes.
        foreach ([Page::class, Service::class, Package::class] as $model) {
            $model::saved(fn () => app(MenuRepository::class)->flushAll());
            $model::deleted(fn () => app(MenuRepository::class)->flushAll());
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/admin/reset-password').'?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }

    /**
     * Apply the timezone and default language chosen in the admin settings.
     */
    private function applyRuntimeSettings(): void
    {
        $settings = $this->app->make(Settings::class);

        $timezone = $settings->get('localization.timezone');

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        $default = $this->app->make(Locales::class)->default();

        config(['app.fallback_locale' => $default]);
        $this->app->setLocale($default);

        Translatable::fallback(fallbackLocale: $default, fallbackAny: true);
    }
}
