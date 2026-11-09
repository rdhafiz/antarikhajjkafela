<?php

namespace Tests\Feature\Visits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshVisitLog;
use Tests\TestCase;

class AnalyticsMigrateTest extends TestCase
{
    use RefreshDatabase;
    use RefreshVisitLog;

    public function test_creates_both_tables_on_the_visit_log_connection(): void
    {
        $schema = Schema::connection('analytics');

        $this->assertTrue($schema->hasTable('page_visits'));
        $this->assertTrue($schema->hasTable('ip_locations'));
        $this->assertTrue($schema->hasColumns('page_visits', ['locale', 'in_app', 'utm_campaign']));
    }

    public function test_main_database_does_not_get_the_tables(): void
    {
        $this->assertFalse(Schema::hasTable('page_visits'));
        $this->assertFalse(Schema::hasTable('ip_locations'));
    }

    public function test_main_migrate_fresh_keeps_the_log(): void
    {
        DB::connection('analytics')->table('page_visits')->insert([
            'visitor_id' => '11111111-1111-4111-8111-111111111111',
            'session_id' => '22222222-2222-4222-8222-222222222222',
            'subject_type' => 'home',
            'path' => '/',
        ]);

        // SQLite cannot VACUUM (part of migrate:fresh) inside RefreshDatabase's transaction.
        DB::rollBack();

        $this->artisan('migrate:fresh');

        $this->assertSame(1, DB::connection('analytics')->table('page_visits')->count());
    }
}
