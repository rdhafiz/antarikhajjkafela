<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
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
        $this->assertSame('These credentials do not match our records.', __('auth.failed'));
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
