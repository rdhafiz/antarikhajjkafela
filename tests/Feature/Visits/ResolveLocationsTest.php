<?php

namespace Tests\Feature\Visits;

use App\Domain\Access\AccessSynchronizer;
use App\Models\IpLocation;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshVisitLog;
use Tests\TestCase;

class ResolveLocationsTest extends TestCase
{
    use RefreshDatabase;
    use RefreshVisitLog;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['visits.geo.driver' => 'ipwho', 'visits.geo.daily_budget' => 900, 'visits.geo.batch' => 10]);
    }

    private function pending(string ...$ips): void
    {
        foreach ($ips as $ip) {
            IpLocation::create(['ip' => $ip]);
        }
    }

    private function found(): array
    {
        return [
            'success' => true, 'country' => 'Bangladesh', 'country_code' => 'BD', 'region' => 'Dhaka', 'city' => 'Dhaka',
            'connection' => ['isp' => 'Acme'], 'security' => ['proxy' => false, 'hosting' => true],
        ];
    }

    public function test_resolves_pending_ips(): void
    {
        $this->pending('8.8.8.8', '1.1.1.1');
        Http::fake(['ipwho.is/*' => Http::response($this->found())]);

        $this->artisan('visits:locations')->assertExitCode(0);

        foreach (IpLocation::all() as $row) {
            $this->assertSame('Bangladesh', $row->country);
            $this->assertSame('Dhaka', $row->city);
            $this->assertNotNull($row->resolved_at);
            $this->assertTrue($row->is_hosting);
            $this->assertSame('Dhaka, Bangladesh', $row->label());
        }
    }

    public function test_not_found_increments_attempts_and_gives_up_after_three(): void
    {
        $this->pending('8.8.8.8');
        Http::fake(['ipwho.is/*' => Http::response(['success' => false])]);

        $this->artisan('visits:locations');
        $row = IpLocation::find('8.8.8.8');
        $this->assertSame(1, (int) $row->attempts);
        $this->assertNotNull($row->failed_at);

        $this->artisan('visits:locations');
        $this->artisan('visits:locations');
        $this->assertSame(0, IpLocation::pending()->count());
        $this->assertSame(3, (int) IpLocation::find('8.8.8.8')->attempts);
    }

    public function test_429_spends_the_day(): void
    {
        $this->pending('8.8.8.8', '1.1.1.1');
        Http::fake(['ipwho.is/*' => Http::response('', 429)]);

        $this->artisan('visits:locations');
        $this->artisan('visits:locations');

        Http::assertSentCount(1);
        $this->assertSame(0, (int) IpLocation::find('8.8.8.8')->attempts);
        $this->assertSame(2, IpLocation::pending()->count());
    }

    public function test_daily_budget_is_respected(): void
    {
        config(['visits.geo.daily_budget' => 1]);
        $this->pending('8.8.8.8', '1.1.1.1', '9.9.9.9');
        Http::fake(['ipwho.is/*' => Http::response(['success' => true, 'country' => 'X'])]);

        $this->artisan('visits:locations');

        Http::assertSentCount(1);
    }

    public function test_driver_none_does_nothing(): void
    {
        config(['visits.geo.driver' => 'none']);
        $this->pending('8.8.8.8');
        Http::fake();

        $this->artisan('visits:locations')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_backfill_queues_distinct_non_bot_ips(): void
    {
        Http::fake(['ipwho.is/*' => Http::response(['success' => false])]);
        $base = ['visitor_id' => '00000000-0000-4000-8000-000000000001',
            'session_id' => '00000000-0000-4000-8000-000000000002', 'subject_type' => 'home', 'path' => '/'];
        DB::connection(config('visits.connection'))->table('page_visits')->insert([
            $base + ['ip' => '8.8.8.8', 'is_bot' => 0],
            $base + ['ip' => '8.8.8.8', 'is_bot' => 0],
            $base + ['ip' => '1.1.1.1', 'is_bot' => 1],
            $base + ['ip' => '192.168.0.1', 'is_bot' => 0],
        ]);

        $this->artisan('visits:locations', ['--backfill' => true]);

        $this->assertSame(['8.8.8.8'], IpLocation::pluck('ip')->all());
    }

    public function test_new_ip_is_resolved_after_the_page(): void
    {
        $this->withoutDefer();
        app(AccessSynchronizer::class)->sync();
        $this->seed([MenuSeeder::class, PageSeeder::class, TravelSeeder::class]);
        Http::fake(['ipwho.is/*' => Http::response($this->found())]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();

        $row = IpLocation::find('8.8.8.8');
        $this->assertSame('Dhaka', $row->city);
        $this->assertNotNull($row->resolved_at);
        Http::assertSentCount(1);
    }

    public function test_known_ip_triggers_no_lookup(): void
    {
        $this->withoutDefer();
        app(AccessSynchronizer::class)->sync();
        $this->seed([MenuSeeder::class, PageSeeder::class, TravelSeeder::class]);
        Http::fake(['ipwho.is/*' => Http::response($this->found())]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_failing_lookup_does_not_break_the_page(): void
    {
        $this->withoutDefer();
        app(AccessSynchronizer::class)->sync();
        $this->seed([MenuSeeder::class, PageSeeder::class, TravelSeeder::class]);
        Http::fake(['ipwho.is/*' => fn () => throw new ConnectionException('down')]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();

        $this->assertNull(IpLocation::find('8.8.8.8')->resolved_at);
    }

    public function test_a_held_lock_blocks_the_lookup(): void
    {
        $this->withoutDefer();
        app(AccessSynchronizer::class)->sync();
        $this->seed([MenuSeeder::class, PageSeeder::class, TravelSeeder::class]);
        Http::fake(['ipwho.is/*' => Http::response($this->found())]);
        Cache::put('visits:geo:running', 1, 60);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/packages')->assertOk();

        Http::assertNothingSent();
        $this->assertNull(IpLocation::find('8.8.8.8')->resolved_at);
    }
}
