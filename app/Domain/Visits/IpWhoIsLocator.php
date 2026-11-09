<?php

namespace App\Domain\Visits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class IpWhoIsLocator
{
    /** IPs that got a definite answer (found or not found) in the last locate() call. */
    public array $answered = [];

    /**
     * Never throws. Returns only the IPs that were found.
     *
     * @return array<string, array{country, country_code, region, city, isp, is_proxy, is_hosting}>
     */
    public function locate(array $ips): array
    {
        $this->answered = [];
        $found = [];
        $budget = (int) config('visits.geo.daily_budget');
        $key = 'visits:geo:ipwho:'.now()->toDateString();

        foreach ($ips as $ip) {
            Cache::add($key, 0, now()->addDays(2));
            if (Cache::increment($key) > $budget) {
                break;
            }

            try {
                $response = Http::timeout((int) config('visits.geo.timeout'))
                    ->get(rtrim(config('visits.geo.endpoint'), '/').'/'.$ip, [
                        'fields' => 'success,country,country_code,region,city,connection,security',
                    ]);
            } catch (Throwable) {
                break;
            }

            if ($response->status() === 429) {
                Cache::put($key, $budget, now()->addDay());
                break;
            }
            if ($response->serverError()) {
                break;
            }

            $this->answered[] = $ip;
            $data = $response->json();

            if ($response->successful() && is_array($data) && ($data['success'] ?? false) === true) {
                $found[$ip] = [
                    'country' => $data['country'] ?? null,
                    'country_code' => isset($data['country_code']) ? substr($data['country_code'], 0, 2) : null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                    'isp' => isset($data['connection']['isp']) ? mb_substr($data['connection']['isp'], 0, 128) : null,
                    'is_proxy' => $data['security']['proxy'] ?? null,
                    'is_hosting' => $data['security']['hosting'] ?? null,
                ];
            }
        }

        return $found;
    }
}
