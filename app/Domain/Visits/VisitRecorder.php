<?php

namespace App\Domain\Visits;

use App\Models\IpLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class VisitRecorder
{
    /**
     * Write one page_visits row. Never throws; always returns the two cookies.
     *
     * @return array<int, Cookie>
     */
    public function record(Request $request, string $type, ?int $id): array
    {
        if (! config('visits.enabled')) {
            return [];
        }

        $ids = VisitCookies::ids($request);
        $visitorId = $ids['visitor_id'] ?? (string) Str::uuid();
        $sessionId = $ids['session_id'] ?? (string) Str::uuid();

        try {
            $this->insert($request, $type, $id, $visitorId, $sessionId);
        } catch (Throwable $e) {
            // The cache or the log can fail too; neither may take the page down.
            try {
                if (Cache::add('visits:record-failed', 1, 600)) {
                    Log::warning('Page visit not recorded', ['exception' => get_class($e), 'code' => $e->getCode()]);
                }
            } catch (Throwable) {
            }
        }

        return [
            $this->cookie($request, VisitCookies::VISITOR, $visitorId, 730 * 24 * 60),
            $this->cookie($request, VisitCookies::SESSION, $sessionId, (int) config('visits.session_minutes')),
        ];
    }

    private function insert(Request $request, string $type, ?int $id, string $visitorId, string $sessionId): void
    {
        $ua = UserAgent::parse($request->userAgent());
        $source = TrafficSource::for($request);
        $path = ltrim($request->path(), '/');
        $ip = $request->ip();
        $db = DB::connection(config('visits.connection'));

        $db->table('page_visits')->insert([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'subject_type' => $type,
            'subject_id' => $id,
            'path' => $this->cut($path === '' ? '/' : $path, 512),
            'locale' => app()->getLocale(),
            'referrer' => $this->cut($source['referrer'], 2048),
            'channel' => $this->cut($source['channel'], 24),
            'source' => $this->cut($source['source'], 64),
            'referrer_host' => $this->cut($source['referrer_host'], 255),
            'in_app' => $this->cut($source['in_app'], 32),
            'ip' => $ip,
            'device_type' => $this->cut($ua['device_type'], 16),
            'platform' => $this->cut($ua['platform'], 32),
            'browser' => $this->cut($ua['browser'], 32),
            'is_bot' => $ua['is_bot'],
            'utm_source' => $this->cut($request->query('utm_source'), 255),
            'utm_medium' => $this->cut($request->query('utm_medium'), 255),
            'utm_campaign' => $this->cut($request->query('utm_campaign'), 255),
            'created_at' => now(),
        ]);

        if (config('visits.geo.driver') !== 'none'
            && ! $ua['is_bot']
            && IpLocation::isPublic($ip)) {
            $now = now();
            $isNew = $db->table('ip_locations')->insertOrIgnore(['ip' => $ip, 'created_at' => $now, 'updated_at' => $now]) === 1;

            // No cron on the host: look up after the page is sent. The batch also retries older pending IPs.
            if ($isNew) {
                defer(function () {
                    try {
                        if (Cache::add('visits:geo:running', 1, 60)) {
                            app(LocationResolver::class)->resolvePending((int) config('visits.geo.batch'));
                        }
                    } catch (Throwable) {
                    }
                });
            }
        }
    }

    /** Truncate to the column length; non-strings (e.g. utm_source[]=x) become null. */
    private function cut(mixed $value, int $max): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $max) : null;
    }

    private function cookie(Request $request, string $name, string $value, int $minutes): Cookie
    {
        return cookie($name, $value, $minutes, '/', null, $request->isSecure(), true, false, 'lax');
    }
}
