<?php

namespace Tests\Feature\Admin;

use App\Models\IpLocation;
use App\Models\Package;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshVisitLog;

class VisitReportTest extends AdminTestCase
{
    use RefreshVisitLog;

    private const S1 = '22222222-2222-4222-8222-222222222222';

    private const V1 = '11111111-1111-4111-8111-111111111111';

    private const ROUTES = ['visits', 'visits/sources', 'visits/pages', 'visits/filters', 'visits/export'];

    private function visit(array $row = []): void
    {
        DB::connection('analytics')->table('page_visits')->insert($row + [
            'visitor_id' => self::V1, 'session_id' => self::S1, 'subject_type' => 'home', 'path' => '/',
            'channel' => 'direct', 'is_bot' => false, 'created_at' => now(),
        ]);
    }

    private function viewer()
    {
        return $this->asAdmin($this->adminWith(['visits.view']));
    }

    private function package(): Package
    {
        $service = Service::create(['name' => ['en' => 'Hajj'], 'slug' => 'hajj']);

        return Package::create(['service_id' => $service->id, 'name' => ['en' => 'Umrah Plus'], 'slug' => 'umrah-plus']);
    }

    private function booking(?string $session): void
    {
        DB::table('bookings')->insert([
            'reference' => 'B'.random_int(1000, 999999), 'snapshot' => '{}', 'unit_price' => 1, 'travelers' => 1, 'quantity' => 1,
            'total' => 1, 'customer_name' => 'A', 'customer_phone' => '017', 'session_id' => $session,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_requires_permission(): void
    {
        foreach (self::ROUTES as $route) {
            $this->asAdmin($this->adminWith(['dashboard.view']))->getJson("/api/admin/$route")->assertForbidden();
            $this->viewer()->get("/api/admin/$route")->assertOk();
        }
    }

    public function test_bots_are_excluded_unless_asked(): void
    {
        $this->visit();
        $this->visit();
        $this->visit(['is_bot' => true]);

        $this->viewer()->getJson('/api/admin/visits/sources')->assertJsonPath('totals.views', 2)->assertJsonPath('totals.bot_views', 1);
        $this->viewer()->getJson('/api/admin/visits/sources?include_bots=1')->assertJsonPath('totals.views', 3);
    }

    public function test_date_range_is_inclusive(): void
    {
        $this->visit(['created_at' => '2026-11-01 23:59:59']);
        $this->visit(['created_at' => '2026-11-02 00:00:00']);

        $this->viewer()->getJson('/api/admin/visits?date_from=2026-11-02&date_to=2026-11-02')->assertJsonPath('totals.views', 1);
    }

    public function test_pages_are_named_including_deleted_content(): void
    {
        $package = $this->package();
        $post = Post::create(['title' => ['en' => 'Zamzam guide'], 'slug' => 'zamzam', 'status' => 'published']);
        $page = Page::create(['type' => 'page', 'title' => ['en' => 'About us'], 'slug' => 'about', 'status' => 'published']);
        $post->delete();
        $this->visit(['subject_type' => 'package', 'subject_id' => $package->id, 'path' => 'packages/umrah-plus']);
        $this->visit(['subject_type' => 'post', 'subject_id' => $post->id, 'path' => 'blog/zamzam']);
        $this->visit(['subject_type' => 'page', 'subject_id' => $page->id, 'path' => 'about']);

        $rows = collect($this->viewer()->getJson('/api/admin/visits/pages')->assertOk()->json('data'))->keyBy('subject_type');

        $this->assertSame('Umrah Plus', $rows['package']['title']);
        $this->assertFalse($rows['package']['deleted']);
        $this->assertSame(rtrim(config('app.url'), '/').'/packages/umrah-plus', $rows['package']['url']);
        $this->assertSame('Zamzam guide', $rows['post']['title']);
        $this->assertTrue($rows['post']['deleted']);
        $this->assertSame('About us', $rows['page']['title']);
    }

    public function test_conversions_count_per_landing_channel(): void
    {
        $this->visit(['channel' => 'social', 'source' => 'facebook']);
        $this->visit(['channel' => 'direct', 'subject_type' => 'package', 'subject_id' => 1]);
        $this->booking(self::S1);
        $this->booking(null);

        $channels = collect($this->viewer()->getJson('/api/admin/visits/sources')->assertJsonPath('totals.bookings', 1)->json('data.channels'))->keyBy('channel');

        $this->assertSame(1, $channels['social']['bookings']);
        $this->assertSame(0, $channels['direct']['bookings']);
        $this->assertSame(1, $channels['direct']['views']);
    }

    public function test_log_filters_and_paginates(): void
    {
        $this->visit();
        $this->visit(['channel' => 'social']);
        $this->visit();

        $this->viewer()->getJson('/api/admin/visits?channel=direct&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->viewer()->getJson('/api/admin/visits?per_page=500')->assertUnprocessable();
    }

    public function test_row_carries_converted_counts(): void
    {
        $this->visit();
        $this->booking(self::S1);

        $this->viewer()->getJson('/api/admin/visits')->assertJsonPath('data.0.converted', ['bookings' => 1, 'enquiries' => 0]);
    }

    public function test_private_ip_reads_local_network(): void
    {
        $this->visit(['ip' => '127.0.0.1']);
        $this->visit(['ip' => '8.8.8.8']);
        IpLocation::create(['ip' => '8.8.8.8', 'city' => 'Dhaka', 'country' => 'Bangladesh', 'resolved_at' => now()]);

        $rows = collect($this->viewer()->getJson('/api/admin/visits')->json('data'))->keyBy('ip');

        $this->assertSame('Local network', $rows['127.0.0.1']['location']['label']);
        $this->assertSame('Dhaka, Bangladesh', $rows['8.8.8.8']['location']['label']);
    }

    public function test_unavailable_log_returns_503(): void
    {
        Schema::connection('analytics')->drop('page_visits');

        foreach (self::ROUTES as $route) {
            $this->viewer()->getJson("/api/admin/$route")->assertStatus(503)->assertJsonPath('message', 'The visit log is unavailable right now.');
        }
    }

    public function test_storage_reports_separate_database(): void
    {
        $this->viewer()->getJson('/api/admin/visits')->assertJsonPath('totals.storage.separate', true);
    }

    public function test_export_streams_csv_with_bom(): void
    {
        $this->visit(['referrer' => '=HYPERLINK("x")']);

        $response = $this->viewer()->get('/api/admin/visits/export')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFid,visited_at,subject_type", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('page-visits-'.now()->format('Y-m-d').'.csv', $response->headers->get('content-disposition'));
    }
}
