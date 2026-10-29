<?php

namespace Tests\Feature\Admin;

use App\Domain\Menus\MenuLinks;
use App\Domain\Menus\MenuRepository;
use App\Domain\Settings\Settings;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\PageSeeder;
use Database\Seeders\TourSeeder;
use Database\Seeders\TravelSeeder;

class TravelTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class]);
    }

    private function umrah(): Package
    {
        return Package::where('slug', 'umrah-package')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Package $package, array $overrides = []): array
    {
        $data = $this->asAdmin($this->superAdmin())->getJson("/api/admin/packages/{$package->id}")->json('data');

        return array_replace($data, $overrides);
    }

    public function test_service_pages_show_stats_the_verse_and_no_filter_tabs(): void
    {
        $this->get('/hajj')
            ->assertOk()
            ->assertSee('hero-75', false)
            ->assertSeeText('Answer the call of Hajj,')
            ->assertSeeText('with scholars beside you.')
            ->assertSeeText('35–40 days')
            ->assertSeeText('About Hajj')
            ->assertSeeTextInOrder(['with scholars beside you.', 'Choose your Hajj package', 'What your Hajj package includes', 'From Dhaka to the two Holy Mosques', 'Surah Al-Hajj 22:27', 'About Hajj'])
            ->assertSeeText('Mina–Arafah–Muzdalifah')
            ->assertSee('images/pilgrimage/makkah.webp', false)
            ->assertSee('ic-tone ic-1', false)
            ->assertSee('class="decor ', false)
            ->assertSeeText('Surah Al-Hajj 22:27')
            ->assertSee('<blockquote', false)
            ->assertDontSee('aria-label="Filter packages"', false);

        // Each package card lists its options (cheapest price per type, most expensive first) with hotels.
        $umrah = $this->umrah()->load('types.prices');
        $basic = $umrah->types->firstWhere('code', 'UM-C')->prices->sortBy('price')->first();
        $this->get('/umrah')
            ->assertOk()
            ->assertSeeTextInOrder(['Package A · Standard', 'Package B · Regular', 'Package C · Basic'])
            ->assertSeeText('Any five-star hotel')
            ->assertSeeText('Direct / transit flights')
            ->assertSee('/packages/umrah-package/book?option='.$basic->id, false);

        // The hadith keeps its lead-in above the quote.
        $this->get('/umrah')->assertOk()->assertSeeText('The Prophet Muhammad (peace be upon him) said')->assertSeeText('Sahih Bukhari & Muslim');
        $this->get('/umrah')->assertOk()->assertSeeText('What your Umrah package includes')->assertSeeText('More than 15 sites in Makkah')->assertDontSeeText('Mina–Arafah–Muzdalifah');

        // The all-packages page keeps its filter and uses the same wide cards, with the service named.
        $this->get('/packages')->assertOk()->assertSee('aria-label="Filter packages"', false)
            ->assertSee('pkg-feature', false)
            ->assertSeeText('Package C · Basic');
    }

    public function test_package_page_has_a_section_bar_option_tabs_and_anchored_sections(): void
    {
        $package = Package::where('slug', 'hajj-regular-package')->firstOrFail();
        $option = $package->types()->with('prices')->get()->first()->prices->first();

        $this->get('/packages/hajj-regular-package')
            ->assertOk()
            ->assertSee('x-data="sectionNav"', false)
            ->assertSee('href="#prices"', false)
            ->assertSee('href="#flights"', false)
            ->assertSee('id="details-1"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('/packages/hajj-regular-package/book?option='.$option->id, false)
            ->assertSeeText('You may also like')
            ->assertSee('pkg-feature', false);
    }

    public function test_flight_airlines_are_translatable(): void
    {
        $this->get('/packages/hajj-regular-package')->assertOk()->assertSeeText('Saudia / Biman Bangladesh');
        $this->withUnencryptedCookie('locale', 'bn')->get('/packages/hajj-regular-package')
            ->assertOk()
            ->assertSeeText('সৌদিয়া / বিমান বাংলাদেশ')
            ->assertDontSeeText('Saudia / Biman Bangladesh');

        // Saved from the admin with both languages.
        $package = $this->umrah();
        $payload = $this->payload($package);
        $payload['flights'][0]['airline'] = ['en' => 'Saudia', 'bn' => 'সৌদিয়া'];
        $this->asAdmin($this->superAdmin())->putJson("/api/admin/packages/{$package->id}", $payload)->assertOk();
        $this->assertSame(['en' => 'Saudia', 'bn' => 'সৌদিয়া'], $package->fresh()->flights[0]['airline']);

        // The English name is required; a value saved before the change (plain text) still shows.
        $payload['flights'][0]['airline'] = ['bn' => 'সৌদিয়া'];
        $this->asAdmin($this->superAdmin())->putJson("/api/admin/packages/{$package->id}", $payload)->assertJsonValidationErrors('flights.0.airline.en');

        $package->forceFill(['flights' => [['airline' => 'Biman', 'schedule' => null, 'description' => null]]])->save();
        $this->withUnencryptedCookie('locale', 'bn')->get('/packages/umrah-package')->assertOk()->assertSeeText('Biman');
    }

    public function test_service_and_package_pages_render(): void
    {
        $this->get('/hajj')->assertOk()->assertSeeText('Hajj Regular Package')->assertSeeText('Hajj Premium Package')->assertDontSeeText('Umrah Package');
        $this->get('/packages')->assertOk()->assertSeeText('Umrah Package');

        $this->get('/packages/umrah-package')
            ->assertOk()
            ->assertSee('1,35,000', false)
            ->assertSeeText('Any five-star hotel')
            ->assertSeeText('Everything included in the package')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"lowPrice":135000', false);
    }

    public function test_prices_use_bangla_digits_for_bangla_visitors(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')->get('/packages/umrah-package')
            ->assertOk()
            ->assertSee('১,৩৫,০০০', false)
            ->assertSeeText('জনপ্রতি');
    }

    public function test_drafts_and_hidden_services_are_not_public(): void
    {
        $this->umrah()->update(['status' => 'draft']);
        $this->get('/packages/umrah-package')->assertNotFound();

        Service::where('slug', 'hajj')->update(['is_active' => false]);
        $this->get('/hajj')->assertNotFound();
        $this->get('/packages/hajj-regular-package')->assertNotFound();
    }

    public function test_saving_syncs_types_prices_and_the_starting_price(): void
    {
        $package = $this->umrah();
        $payload = $this->payload($package);

        // Drop type C, change the price of type A, add a new price to type B.
        $payload['types'] = array_slice($payload['types'], 0, 2);
        $payload['types'][0]['prices'][0]['price'] = 99000;
        $payload['types'][1]['prices'][] = ['title' => ['en' => 'Double room'], 'price' => 210000, 'unit' => 'room', 'hotels' => []];

        $keptPriceId = $payload['types'][0]['prices'][0]['id'];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/packages/{$package->id}", $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data.types')
            ->assertJsonPath('data.min_price', 99000)
            ->assertJsonPath('data.types.0.prices.0.id', $keptPriceId)
            ->assertJsonPath('data.types.1.prices.1.unit', 'room');

        $this->assertSame(3, $package->prices()->count());
        $this->assertSame(99000.0, (float) $package->fresh()->min_price);
    }

    public function test_slug_follows_the_english_name_and_old_links_redirect(): void
    {
        $package = $this->umrah();

        $this->asAdmin($this->superAdmin())
            ->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['name' => ['en' => 'Umrah 2027 Special', 'bn' => 'উমরাহ ২০২৭']]))
            ->assertOk()
            ->assertJsonPath('data.slug', 'umrah-2027-special');

        $this->get('/packages/umrah-package')->assertRedirect(route('packages.show', 'umrah-2027-special'))->assertStatus(301);
    }

    public function test_package_validation(): void
    {
        $admin = $this->superAdmin();
        $package = $this->umrah();

        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['advance_type' => 'percentage', 'advance_value' => 150]))
            ->assertJsonValidationErrors('advance_value');

        // #50: a fixed advance cannot be more than the cheapest price per person.
        $lowest = collect($this->payload($package)['types'])->flatMap(fn ($t) => $t['prices'])->where('unit', 'person')->min('price');
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['advance_type' => 'fixed', 'advance_value' => 999999]))
            ->assertJsonValidationErrors(['advance_value' => 'cannot be more than the lowest price per person']);
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['advance_type' => 'fixed', 'advance_value' => $lowest]))
            ->assertOk();

        // #98: a price must be at least ৳1. #100: nested fields are named by their label, not their key.
        $types = $this->payload($package)['types'];
        $types[0]['prices'][0]['price'] = 0;
        $types[0]['prices'][0]['hotels'] = [['heading' => ['en' => ''], 'subheading' => ['en' => 'Near the Haram']]];
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['types' => $types]))
            ->assertJsonValidationErrors([
                'types.0.prices.0.price' => 'Enter a price of at least ৳1.',
                'types.0.prices.0.hotels.0.heading.en' => 'The Heading (English) field is required.',
            ]);
        $types[0]['prices'][0]['price'] = null;
        $types[0]['prices'][0]['hotels'] = [];
        $types[0]['prices'][0]['title'] = ['en' => str_repeat('x', 200)];
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['types' => $types]))
            ->assertJsonValidationErrors(['types.0.prices.0.price' => 'Enter the price.', 'types.0.prices.0.title.en' => 'The Title (English) field must not be greater than 120 characters.']);

        // #54: per-language fields have readable names.
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['name' => ['en' => '', 'bn' => '']]))
            ->assertJsonValidationErrors(['name.en' => 'An English name is needed for the web address.']);
        app(Settings::class)->set('localization', ['default_locale' => 'bn']);
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['name' => ['en' => 'Umrah', 'bn' => '']]))
            ->assertJsonValidationErrors(['name.bn' => 'The Name (Bangla) field is required.']);
        app(Settings::class)->set('localization', ['default_locale' => 'en']);

        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['booking_starts_on' => '2027-05-01', 'booking_ends_on' => '2027-04-01']))
            ->assertJsonValidationErrors('booking_ends_on');

        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['types' => []]))
            ->assertJsonValidationErrors('types');

        $sections = [['layout' => 'full', 'blocks' => [['type' => 'video', 'url' => 'https://evil.example.com/v']]]];
        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['sections' => $sections]))
            ->assertJsonValidationErrors('sections.0.blocks.0.url');
    }

    public function test_content_blocks_are_sanitized_and_rendered(): void
    {
        $package = $this->umrah();
        $sections = [[
            'layout' => 'full',
            'heading' => ['en' => 'Itinerary'],
            'blocks' => [
                ['type' => 'text', 'body' => ['en' => '<p>Day one<script>alert(1)</script></p>']],
                ['type' => 'table', 'header' => true, 'rows' => [[['en' => 'Day'], ['en' => 'City']], [['en' => '1'], ['en' => 'Makkah']]]],
                ['type' => 'list', 'style' => 'number', 'items' => [['en' => 'Tawaf'], ['en' => 'Sa\'i']]],
            ],
        ]];

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['sections' => $sections]))->assertOk();

        $this->assertStringNotContainsString('script', json_encode($package->fresh()->sections));
        $this->get('/packages/umrah-package')->assertSeeText('Itinerary')->assertSee('<th scope="col" class="px-4 py-3 font-semibold">City</th>', false)->assertSeeText('Tawaf');
    }

    public function test_booking_window_states(): void
    {
        $package = $this->umrah();

        $package->update(['booking_starts_on' => now()->addWeek(), 'booking_ends_on' => null]);
        $this->assertSame('upcoming', $package->fresh()->bookingState());

        $package->update(['booking_starts_on' => null, 'booking_ends_on' => now()->subDay()]);
        $this->assertSame('closed', $package->fresh()->bookingState());
        $this->get('/packages/umrah-package')->assertSeeText('Booking closed')->assertDontSeeText('Book this option');
    }

    public function test_advance_amounts(): void
    {
        $package = $this->umrah();

        $package->update(['advance_type' => 'fixed', 'advance_value' => 50000]);
        $this->assertSame(150000.0, $package->advanceFor(160000, 3));

        $package->update(['advance_type' => 'percentage', 'advance_value' => 20]);
        $this->assertSame(96000.0, $package->advanceFor(160000, 3));
    }

    public function test_menus_can_link_to_packages_and_follow_slug_changes(): void
    {
        $admin = $this->superAdmin();
        $package = $this->umrah();

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            ['label' => ['en' => 'Umrah deal'], 'link_type' => 'package', 'link_target' => (string) $package->id],
        ]])->assertOk();
        $this->assertSame(url('/packages/umrah-package'), app(MenuRepository::class)->tree('primary')[0]['url']);

        $this->asAdmin($admin)->putJson("/api/admin/packages/{$package->id}", $this->payload($package, ['name' => ['en' => 'Umrah Plus']]))->assertOk();
        $this->assertSame(url('/packages/umrah-plus'), app(MenuRepository::class)->tree('primary')[0]['url']);

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            ['label' => ['en' => 'Missing'], 'link_type' => 'package', 'link_target' => '99999'],
        ]])->assertJsonValidationErrors('items.0.link_target');
    }

    public function test_home_page_shows_featured_packages(): void
    {
        $this->get('/')->assertSee('data-section="package_showcase"', false)->assertSeeText('Hajj Premium Package');
    }

    public function test_one_service_package_sections_show_only_featured_packages(): void
    {
        $umrah = $this->umrah();
        $umrah->update(['is_featured' => false]);
        $section = Page::where('system_key', 'home')->firstOrFail()->sections()->create([
            'component' => 'package_showcase',
            'sort' => 99,
            'content' => ['title' => ['en' => 'Umrah picks'], 'source' => 'service', 'service' => (string) $umrah->service_id, 'limit' => 6],
            'style' => [],
        ]);

        // Without the setting (sections saved before it existed) only featured packages show.
        $this->get('/')->assertDontSeeText('Umrah picks');

        $umrah->update(['is_featured' => true]);
        $this->get('/')->assertSeeText('Umrah picks')->assertSee($umrah->url(), false);

        $umrah->update(['is_featured' => false]);
        $section->update(['content' => [...$section->content, 'featured_only' => false]]);
        $this->get('/')->assertSeeText('Umrah picks')->assertSee($umrah->url(), false);

        // Existing sections are switched to featured only by the migration.
        $section->update(['content' => array_diff_key($section->content, ['featured_only' => 0])]);
        (require database_path('migrations/2026_10_29_130000_featured_packages_on_service_showcases.php'))->up();
        $this->assertTrue($section->fresh()->content['featured_only']);
    }

    public function test_services_admin_and_permissions(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->postJson('/api/admin/services', ['name' => ['en' => 'Ziyarah tours'], 'slug' => 'about'])
            ->assertJsonValidationErrors('slug'); // used by a page

        // #79: the English name is required (the site shows English first).
        $this->asAdmin($admin)->postJson('/api/admin/services', ['name' => ['bn' => 'জিয়ারাহ'], 'slug' => 'ziyarah'])
            ->assertJsonValidationErrors(['name.en' => 'An English name is needed: the website shows English first.']);

        $this->asAdmin($admin)->postJson('/api/admin/services', ['name' => ['en' => 'Ziyarah tours'], 'slug' => 'ziyarah-tours'])->assertCreated();

        // #83: a changed address redirects from the old one.
        $hajj = collect($this->asAdmin($admin)->getJson('/api/admin/services')->json('data'))->firstWhere('slug', 'hajj');
        $this->asAdmin($admin)->putJson("/api/admin/services/{$hajj['id']}", [...$hajj, 'slug' => 'hajj-packages'])->assertOk();
        $this->get('/hajj')->assertRedirect(url('hajj-packages'))->assertStatus(301);
        $this->get('/hajj-packages')->assertOk();
        $this->asAdmin($admin)->putJson("/api/admin/services/{$hajj['id']}", [...$hajj, 'slug' => 'hajj'])->assertOk();
        $this->get('/hajj-packages')->assertRedirect(url('hajj'));
        $this->seed(TourSeeder::class);
        $this->get('/tours')->assertOk()
            ->assertSeeTextInOrder(['What your tour includes', 'Halal food noted', 'From Dhaka and back, planned for you', 'At your destination'])
            ->assertSee('images/tours/flight.webp', false)
            ->assertDontSeeText('Mina–Arafah–Muzdalifah');
        $this->get('/ziyarah-tours')->assertOk()->assertSeeText('New packages are being prepared')
            ->assertDontSeeText('From Dhaka to the two Holy Mosques');

        $ziyarah = Service::where('slug', 'ziyarah-tours')->firstOrFail();
        $this->asAdmin($admin)->putJson("/api/admin/services/{$ziyarah->id}", [
            'name' => ['en' => 'Ziyarah tours'],
            'slug' => 'ziyarah-tours',
            'headline' => ['en' => 'Walk where the Prophet walked,'],
            'highlight' => ['en' => 'in Makkah and Madinah.'],
        ])->assertOk()->assertJsonPath('data.headline.en', 'Walk where the Prophet walked,');
        $this->get('/ziyarah-tours')->assertOk()->assertSeeTextInOrder(['Walk where the Prophet walked,', 'in Makkah and Madinah.']);

        // #78: staff can preview it, but visitors don't see a service with nothing to book, nor its menu link or sitemap entry.
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->get('/ziyarah-tours')->assertNotFound();
        $this->get('/packages')->assertOk()->assertViewHas('services', fn ($services) => ! $services->contains('slug', 'ziyarah-tours'));
        $this->assertStringNotContainsString('/ziyarah-tours', $this->get('/sitemap.xml')->getContent());
        $this->assertNull(app(MenuLinks::class)->resolve('service', (string) $ziyarah->id, null));
        $this->get('/hajj')->assertOk()->assertSee('href="'.route('packages.index').'"', false)->assertSeeText('See all packages');

        $hajj = Service::where('slug', 'hajj')->firstOrFail();
        $this->asAdmin($admin)->deleteJson("/api/admin/services/{$hajj->id}")->assertJsonValidationErrors('service');

        $viewer = $this->adminWith(['packages.view']);
        $this->asAdmin($viewer)->getJson('/api/admin/packages')->assertOk();
        $this->asAdmin($viewer)->putJson("/api/admin/packages/{$this->umrah()->id}", [])->assertForbidden();
    }

    public function test_duplicating_copies_types_and_prices_as_a_draft(): void
    {
        $package = $this->umrah();

        $id = $this->asAdmin($this->superAdmin())->postJson("/api/admin/packages/{$package->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data.id');

        $this->assertSame(3, PackagePrice::whereIn('package_type_id', Package::find($id)->types()->pluck('id'))->count());
    }

    public function test_services_and_packages_take_their_own_schema(): void
    {
        $admin = $this->asAdmin($this->superAdmin());

        $umrah = collect($admin->getJson('/api/admin/services')->json('data'))->firstWhere('slug', 'umrah');
        $admin->putJson('/api/admin/services/'.$umrah['id'], [...$umrah, 'seo' => [...$umrah['seo'], 'schema' => 'not json']])
            ->assertJsonValidationErrors('seo.schema');
        $admin->putJson('/api/admin/services/'.$umrah['id'], [...$umrah, 'seo' => [...$umrah['seo'], 'schema' => '{"@type": "FAQPage", "name": "Umrah questions"}']])
            ->assertOk();
        $this->get('/umrah')->assertOk()->assertSee('{"@type":"FAQPage","name":"Umrah questions"}', false);

        $package = $this->umrah();
        $payload = $this->payload($package);
        $payload['seo']['schema'] = '[{"@type": "Offer", "name": "Early bird"}]';
        $admin->putJson("/api/admin/packages/{$package->id}", $payload)->assertOk()->assertJsonPath('data.seo.schema', '[{"@type": "Offer", "name": "Early bird"}]');
        $this->get($package->fresh()->url())->assertOk()->assertSee('{"@type":"Offer","name":"Early bird"}', false);
    }
}
