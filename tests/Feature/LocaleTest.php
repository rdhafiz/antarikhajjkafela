<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_languages_are_discovered_from_lang_files(): void
    {
        $this->assertSame(['en', 'bn'], app(Locales::class)->codes());
    }

    public function test_switching_language_sets_a_cookie_and_returns_to_the_same_page(): void
    {
        $this->from('/')
            ->get('/language/bn')
            ->assertRedirect('/')
            ->assertPlainCookie('locale', 'bn');
    }

    public function test_unknown_languages_are_rejected(): void
    {
        $this->get('/language/xx')->assertNotFound();
    }

    public function test_the_language_cookie_is_applied_to_requests(): void
    {
        $this->seed(PageSeeder::class);

        $this->withUnencryptedCookie('locale', 'bn')->get('/')->assertOk();

        $this->assertSame('bn', app()->getLocale());
    }

    public function test_external_redirects_are_not_followed(): void
    {
        $this->from('https://evil.example.com/')->get('/language/bn')->assertRedirect(url('/'));
    }
}
