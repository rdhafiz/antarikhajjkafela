<?php

namespace App\Domain\Visits;

use App\Models\IpLocation;

class LocationResolver
{
    public function __construct(private IpWhoIsLocator $locator) {}

    /** Look up the oldest pending IPs; returns how many were resolved. */
    public function resolvePending(int $limit): int
    {
        if (config('visits.geo.driver') === 'none') {
            return 0;
        }

        $rows = IpLocation::pending()->limit(max(1, $limit))->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $found = $this->locator->locate($rows->pluck('ip')->all());

        // IPs the locator never reached (timeout, 429, 5xx, budget) stay pending untouched.
        foreach ($rows->whereIn('ip', $this->locator->answered) as $row) {
            if (isset($found[$row->ip])) {
                $row->fill($found[$row->ip])->forceFill(['resolved_at' => now()])->save();
            } else {
                $row->forceFill(['failed_at' => now(), 'attempts' => $row->attempts + 1])->save();
            }
        }

        return count($found);
    }
}
