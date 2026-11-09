<?php

namespace Tests\Feature\Visits;

use App\Domain\Access\AccessSynchronizer;
use App\Models\GalleryAlbum;
use App\Models\IpLocation;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageVisit;
use App\Models\Post;
use App\Models\SlugRedirect;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Concerns\RefreshVisitLog;
use Tests\TestCase;

class RecordVisitsTest extends TestCase
{
    use RefreshDatabase;
    use RefreshVisitLog;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        app(AccessSynchronizer::class)->sync();
        $this->seed([MenuSeeder::class, PageSeeder::class, TravelSeeder::class]);
        Http::fake();
    }

    private function package(): Package
    {
        return Package::where('slug', 'umrah-package')->firstOrFail();
    }

    private function cookie($response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    public function test_package_page_is_recorded(): void
    {
        $package = $this->package();

        $response = $this->withHeaders(['User-Agent' => self::IPHONE])->get('/packages/'.$package->slug)->assertOk();

        $visit = PageVisit::sole();
        $this->assertSame('package', $visit->subject_type);
        $this->assertSame($package->id, $visit->subject_id);
        $this->assertSame('packages/'.$package->slug, $visit->path);
        $this->assertSame('phone', $visit->device_type);
        $this->assertSame('en', $visit->locale);
        $this->assertNotNull($this->cookie($response, 'pv_vid'));
        $this->assertNotNull($this->cookie($response, 'pv_sid'));
    }

    /**
     * @return array<string, array{0: callable(): array{0: string, 1: string, 2: ?int}}>
     */
    public static function pageTypes(): array
    {
        return [
            'home' => [fn () => ['/', 'home', null]],
            'page' => [fn () => ['/about', 'page', Page::where('system_key', 'about')->value('id')]],
            'campaign' => [fn () => ['/marketing/hajj-pre-registration', 'campaign', Page::where('slug', 'hajj-pre-registration')->value('id')]],
            'packages' => [fn () => ['/packages', 'packages', null]],
            'campaigns' => [fn () => ['/marketing', 'campaigns', null]],
            'post' => [function () {
                $post = Post::create(['title' => ['en' => 'Zamzam guide'], 'slug' => 'zamzam-guide', 'status' => 'published', 'published_at' => now()->subDay()]);

                return ['/blog/zamzam-guide', 'post', $post->id];
            }],
            'album' => [function () {
                $album = GalleryAlbum::create(['title' => ['en' => 'Arafat day'], 'slug' => 'arafat-day', 'is_published' => true]);
                $album->items()->create(['type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

                return ['/gallery/arafat-day', 'album', $album->id];
            }],
            'booking' => [fn () => ['/packages/umrah-package/book', 'booking', Package::where('slug', 'umrah-package')->value('id')]],
        ];
    }

    #[DataProvider('pageTypes')]
    public function test_each_public_page_type_records_its_subject(callable $case): void
    {
        [$url, $type, $id] = $case();
        Page::where('slug', 'hajj-pre-registration')->update(['status' => 'published', 'starts_at' => null, 'expires_at' => null]);

        $this->get($url)->assertOk();

        $visit = PageVisit::sole();
        $this->assertSame($type, $visit->subject_type);
        $this->assertSame($id, $visit->subject_id);
    }

    public function test_service_page_is_recorded_as_a_service(): void
    {
        $this->get('/hajj')->assertOk();

        $this->assertSame('service', PageVisit::sole()->subject_type);
    }

    public function test_redirect_is_not_recorded(): void
    {
        SlugRedirect::remember('package', 'old-umrah', $this->package()->id);

        $this->get('/home')->assertStatus(301);
        $this->get('/packages/old-umrah')->assertStatus(301);

        $this->assertSame(0, PageVisit::count());
    }

    public function test_not_found_and_previews_are_not_recorded(): void
    {
        $this->get('/no-such-page')->assertNotFound();
        $this->get(URL::temporarySignedRoute('pages.preview', now()->addHour(), ['page' => Page::where('system_key', 'about')->value('id')]))->assertOk();

        $this->assertSame(0, PageVisit::count());
    }

    public function test_signed_in_staff_are_not_recorded(): void
    {
        $this->actingAs(User::factory()->create())->get('/packages/'.$this->package()->slug)->assertOk();

        $this->assertSame(0, PageVisit::count());
    }

    public function test_utm_and_bot_are_classified(): void
    {
        $this->get('/packages?utm_source=facebook&utm_medium=cpc')->assertOk();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/packages')->assertOk();

        $paid = PageVisit::where('utm_source', 'facebook')->sole();
        $this->assertSame('paid_social', $paid->channel);
        $this->assertTrue(PageVisit::where('is_bot', true)->exists());
    }

    public function test_cookies_are_kept_and_invalid_ones_replaced(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();

        $response = $this->withCookie('pv_vid', $vid)->withCookie('pv_sid', $sid)->get('/packages')->assertOk();

        $visit = PageVisit::sole();
        $this->assertSame($vid, $visit->visitor_id);
        $this->assertSame($sid, $visit->session_id);
        $response->assertCookie('pv_vid', $vid)->assertCookie('pv_sid', $sid);

        $vidCookie = $this->cookie($response, 'pv_vid');
        $sidCookie = $this->cookie($response, 'pv_sid');
        $this->assertEqualsWithDelta(time() + 730 * 86400, $vidCookie->getExpiresTime(), 60);
        $this->assertEqualsWithDelta(time() + 30 * 60, $sidCookie->getExpiresTime(), 60);
        $this->assertTrue($vidCookie->isHttpOnly());
        $this->assertSame('lax', $vidCookie->getSameSite());
        $this->assertSame('lax', $sidCookie->getSameSite());

        $this->withCookie('pv_vid', 'abc')->get('/packages')->assertOk();
        $this->assertTrue(Str::isUuid(PageVisit::latest('id')->first()->visitor_id));
    }

    public function test_a_new_campaign_click_or_external_referrer_starts_a_new_session(): void
    {
        $vid = (string) Str::uuid();
        $sid = (string) Str::uuid();
        $withIds = fn () => $this->withCookie('pv_vid', $vid)->withCookie('pv_sid', $sid);

        $response = $withIds()->get('/packages?utm_source=google&utm_medium=cpc')->assertOk();
        $first = PageVisit::latest('id')->first();
        $this->assertNotSame($sid, $first->session_id);
        $this->assertSame($vid, $first->visitor_id);
        $response->assertCookie('pv_sid', $first->session_id);

        $withIds()->get('/packages?gclid=abc')->assertOk();
        $this->assertNotSame($sid, PageVisit::latest('id')->first()->session_id);

        $withIds()->withHeaders(['Referer' => 'https://www.facebook.com/'])->get('/packages')->assertOk();
        $this->assertNotSame($sid, PageVisit::latest('id')->first()->session_id);
    }

    public function test_internal_navigation_and_in_app_browsers_keep_the_session(): void
    {
        $sid = (string) Str::uuid();
        $withSid = fn () => $this->withCookie('pv_sid', $sid);

        $withSid()->withHeaders(['Referer' => config('app.url').'/packages'])->get('/packages')->assertOk();
        $withSid()->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 [FBAN/FBIOS;FBAV/430.0]'])->get('/packages')->assertOk();

        $this->assertSame([$sid, $sid], PageVisit::orderBy('id')->pluck('session_id')->all());
    }

    public function test_public_ip_is_queued_and_private_is_not(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();
        $this->assertSame(1, IpLocation::count());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/packages')->assertOk();
        $this->assertSame(1, IpLocation::count());

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/packages')->assertOk();
        $this->assertSame(1, IpLocation::count());
    }

    public function test_missing_table_does_not_break_the_page(): void
    {
        Schema::connection('analytics')->drop('page_visits');

        $response = $this->get('/packages/'.$this->package()->slug)->assertOk();

        $this->assertNotNull($this->cookie($response, 'pv_vid'));
        $this->assertNotNull($this->cookie($response, 'pv_sid'));
    }

    public function test_disabled_records_nothing(): void
    {
        config(['visits.enabled' => false]);

        $response = $this->get('/packages')->assertOk();

        $this->assertSame(0, PageVisit::count());
        $this->assertNull($this->cookie($response, 'pv_vid'));
        $this->assertNull($this->cookie($response, 'pv_sid'));
    }
}
