<?php

namespace App\Console\Commands;

use App\Domain\Visits\LocationResolver;
use App\Models\IpLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveVisitLocations extends Command
{
    protected $signature = 'visits:locations {--limit=25} {--backfill}';

    protected $description = 'Look up the country and city of visitor IPs (budgeted)';

    public function handle(LocationResolver $resolver): int
    {
        if (config('visits.geo.driver') === 'none') {
            return self::SUCCESS;
        }

        if ($this->option('backfill')) {
            $this->backfill();
        }

        $resolved = $resolver->resolvePending(min((int) $this->option('limit'), 25));
        $this->info($resolved.' resolved.');

        return self::SUCCESS;
    }

    private function backfill(): void
    {
        $db = DB::connection(config('visits.connection'));
        $now = now();

        $db->table('page_visits')->where('is_bot', false)->whereNotNull('ip')->distinct()->pluck('ip')
            ->filter(fn ($ip) => IpLocation::isPublic($ip))
            ->chunk(500)
            ->each(fn ($chunk) => $db->table('ip_locations')->insertOrIgnore(
                $chunk->map(fn ($ip) => ['ip' => $ip, 'created_at' => $now, 'updated_at' => $now])->all()
            ));
    }
}
