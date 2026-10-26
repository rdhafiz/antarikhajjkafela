<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Database\Seeders\PageSeeder;

class SettingsTest extends AdminTestCase
{
    public function test_defaults_are_returned_until_values_are_saved(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/settings/theme')
            ->assertOk()
            ->assertJsonPath('data.values.primary_color', '#012a51')
            ->assertJsonPath('data.values.secondary_color', '#fdc802')
            ->assertJsonPath('data.fields.0.label', 'Primary color');
    }

    public function test_values_are_validated_and_saved(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->putJson('/api/admin/settings/theme', ['primary_color' => 'red'])
            ->assertJsonValidationErrors('primary_color');

        $this->asAdmin($admin)->putJson('/api/admin/settings/theme', ['primary_color' => '#112233'])
            ->assertOk()
            ->assertJsonPath('data.values.primary_color', '#112233');

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
            ->assertJsonPath('data.values.sslcommerz_store_password', true);

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

    public function test_navigation_lists_groups_in_sections(): void
    {
        $this->asAdmin($this->superAdmin())->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Website')
            ->assertJsonPath('data.0.groups.0.key', 'branding')
            ->assertJsonPath('data.1.groups.2.key', 'payment');
    }

    public function test_navigation_indexes_fields_and_counts_filled_ones(): void
    {
        $groups = collect($this->asAdmin($this->superAdmin())->getJson('/api/admin/settings')->json('data'))
            ->flatMap(fn ($section) => $section['groups'])
            ->keyBy('key');

        $contact = $groups['contact'];
        $this->assertSame(count(config('settings.groups.contact.fields')), $contact['total']);
        $this->assertContains('Primary WhatsApp', array_column($contact['fields'], 'label'));
        $this->assertGreaterThan(0, $contact['filled']);
        $this->assertLessThanOrEqual($contact['total'], $contact['filled']);

        settings()->set('social', ['instagram' => 'https://instagram.com/antarik']);
        $before = $groups['social']['filled'];

        $after = collect($this->asAdmin($this->superAdmin())->getJson('/api/admin/settings')->json('data'))
            ->flatMap(fn ($section) => $section['groups'])->keyBy('key')['social']['filled'];
        $this->assertSame($before + 1, $after);
    }

    public function test_only_selects_without_a_default_can_be_switched_off(): void
    {
        $fields = collect($this->asAdmin($this->superAdmin())->getJson('/api/admin/settings/payment')->json('data.fields'))->keyBy('key');

        $this->assertTrue($fields['active_gateway']['clearable']);
        $this->assertArrayNotHasKey('clearable', $fields['sslcommerz_mode']);
        $this->assertArrayNotHasKey('clearable', $fields['sslcommerz_store_id']);
    }

    public function test_dynamic_options_are_resolved(): void
    {
        $admin = $this->superAdmin();

        $fields = collect($this->asAdmin($admin)->getJson('/api/admin/settings/localization')->json('data.fields'))->keyBy('key');

        $this->assertSame(['en', 'bn'], array_column($fields['default_locale']['options'], 'value'));
        $this->assertContains('Asia/Dhaka', array_column($fields['timezone']['options'], 'value'));
        $this->assertTrue($fields['currency']['readonly']);
    }

    public function test_only_google_maps_embed_urls_are_accepted(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->putJson('/api/admin/settings/contact', ['google_map' => 'https://evil.example.com/embed'])
            ->assertJsonValidationErrors('google_map');

        $this->asAdmin($admin)->putJson('/api/admin/settings/contact', ['google_map' => 'https://www.google.com/maps/embed?pb=!1m18!1m12'])
            ->assertOk();
    }

    public function test_structured_data_must_be_valid_json(): void
    {
        $this->asAdmin($this->superAdmin())->putJson('/api/admin/settings/seo', ['schema' => '{not json'])
            ->assertJsonValidationErrors('schema');
    }

    public function test_a_gateway_needs_credentials_before_it_can_be_activated(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->putJson('/api/admin/settings/payment', ['active_gateway' => 'sslcommerz'])
            ->assertJsonValidationErrors('active_gateway');

        $this->asAdmin($admin)->putJson('/api/admin/settings/payment', [
            'active_gateway' => 'sslcommerz',
            'sslcommerz_store_id' => 'teststore',
            'sslcommerz_store_password' => 'secret',
        ])->assertOk()->assertJsonPath('data.values.active_gateway', 'sslcommerz');
    }

    public function test_the_site_head_uses_the_saved_brand_colors_and_manifest(): void
    {
        $this->seed(PageSeeder::class);
        settings()->set('theme', ['primary_color' => '#123456']);

        $this->get('/')->assertOk()->assertSee('--brand-primary: #123456', false)->assertSee('site.webmanifest', false);

        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('name', 'Antarik Travel International');
    }
}
