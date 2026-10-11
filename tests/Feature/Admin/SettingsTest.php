<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;

class SettingsTest extends AdminTestCase
{
    public function test_defaults_are_returned_until_values_are_saved(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/settings/theme')
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#012a51')
            ->assertJsonPath('data.secondary_color', '#fdc802');
    }

    public function test_values_are_validated_and_saved(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->putJson('/api/admin/settings/theme', ['primary_color' => 'red'])
            ->assertJsonValidationErrors('primary_color');

        $this->asAdmin($admin)->putJson('/api/admin/settings/theme', ['primary_color' => '#112233'])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#112233');

        $this->assertSame('#112233', settings('theme.primary_color'));
    }

    public function test_translatable_values_are_stored_per_language(): void
    {
        $this->asAdmin($this->superAdmin())->putJson('/api/admin/settings/branding', [
            'site_name' => ['en' => 'Antarik', 'bn' => 'আন্তরিক'],
        ])->assertOk();

        $this->assertSame('আন্তরিক', setting_t('branding.site_name', 'bn'));
        $this->assertSame('Antarik', setting_t('branding.site_name', 'en'));
    }

    public function test_secrets_are_encrypted_and_never_returned(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->putJson('/api/admin/settings/payment', ['sslcommerz_store_password' => 'top-secret'])
            ->assertOk()
            ->assertJsonPath('data.sslcommerz_store_password', true);

        $stored = Setting::where('key', 'sslcommerz_store_password')->value('value');
        $this->assertNotSame('top-secret', $stored);
        $this->assertSame('top-secret', settings('payment.sslcommerz_store_password'));

        // Saving the group again without the secret keeps it.
        $this->asAdmin($admin)->putJson('/api/admin/settings/payment', ['sslcommerz_mode' => 'live'])->assertOk();
        $this->assertSame('top-secret', settings('payment.sslcommerz_store_password'));
    }

    public function test_readonly_settings_cannot_be_changed(): void
    {
        $this->asAdmin($this->superAdmin())->putJson('/api/admin/settings/localization', ['currency' => 'USD'])->assertOk();

        $this->assertSame('BDT', settings('localization.currency'));
    }

    public function test_unknown_groups_return_404_and_permissions_apply(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/settings/nope')->assertNotFound();
        $this->asAdmin($this->adminWith(['settings.view']))->putJson('/api/admin/settings/theme', [])->assertForbidden();
    }

    public function test_bootstrap_exposes_locales_and_admin_theme(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/bootstrap')
            ->assertOk()
            ->assertJsonPath('locales.0.code', 'en')
            ->assertJsonPath('locales.1.native', 'বাংলা')
            ->assertJsonPath('admin_theme.sidebar_bg', '#012a51')
            ->assertJsonPath('media_mode', 'automatic');
    }
}
