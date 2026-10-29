<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Support\Locales;
use Tests\Feature\Admin\AdminTestCase;

class LocaleMessagesTest extends AdminTestCase
{
    /**
     * Re-run the boot-time language setup after changing the default language.
     */
    private function useDefaultLanguage(string $code): void
    {
        settings()->set('localization', ['default_locale' => $code]);

        (fn () => $this->applyRuntimeSettings())->call(app()->getProvider(AppServiceProvider::class));
    }

    public function test_admin_api_answers_in_english_when_the_site_default_is_bangla(): void
    {
        $this->useDefaultLanguage('bn');

        $this->postJson('/api/admin/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonPath('errors.password.0', 'The password field is required.');
    }

    public function test_missing_bangla_strings_fall_back_to_english_not_keys(): void
    {
        $this->useDefaultLanguage('bn');

        $this->assertSame('en', app('translator')->getFallback());
        $this->assertSame('bn', config('app.fallback_locale'));
        $this->assertSame('The email address or password is not correct.', __('auth.failed'));
        $this->assertSame('The password field must contain at least one letter.', __('validation.password.letters', ['attribute' => 'password']));
    }

    /**
     * #131, #133: php artisan optimize caches the config as the boot left it, with app.locale
     * already switched to the site's default language. English must stay the base language.
     */
    public function test_a_cached_config_keeps_english_as_the_base_language(): void
    {
        config(['app.locale' => 'bn', 'app.fallback_locale' => 'bn']);
        $this->app->forgetInstance(Locales::class);
        $this->useDefaultLanguage('bn');

        $this->assertSame('en', app(Locales::class)->base());
        $this->postJson('/api/admin/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertJsonPath('errors.email.0', 'The email address or password is not correct.');
        $this->assertSame('The password field must contain at least one letter.', __('validation.password.letters', ['attribute' => 'password'], 'en'));
    }

    public function test_public_forms_show_bangla_messages_to_bangla_visitors(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')
            ->from('/booking/status')
            ->post('/booking/status', [])
            ->assertSessionHasErrors('reference');

        $message = session('errors')->first('reference');

        $this->assertStringContainsString('দিতে হবে', $message);
        $this->assertStringNotContainsString('validation.', $message);
    }
}
