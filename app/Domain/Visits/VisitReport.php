<?php

namespace App\Domain\Visits;

use App\Console\Commands\AnalyticsMigrate;
use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\Inquiry;
use App\Models\IpLocation;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageVisit;
use App\Models\Post;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only queries behind the admin Page visits screens. */
class VisitReport
{
    public const EXACT_FILTERS = [
        'subject_type', 'subject_id', 'channel', 'source', 'in_app', 'device_type',
        'platform', 'browser', 'locale', 'visitor_id', 'session_id', 'ip',
    ];

    private const AGG = 'COUNT(*) as views, COUNT(DISTINCT visitor_id) as visitors';

    /** @param  array<string, mixed>  $filters */
    public function filtered(array $filters, ?bool $includeBots = null): Builder
    {
        $q = PageVisit::query();

        if (! ($includeBots ?? ! empty($filters['include_bots']))) {
            $q->where('is_bot', false);
        }
        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
        foreach (self::EXACT_FILTERS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $q->where($col, $filters[$col]);
            }
        }

        return $q;
    }

    /** @param  array<string, mixed>  $filters */
    public function sources(array $filters): array
    {
        $conv = $this->conversions($filters);
        $label = fn ($c) => PageVisit::CHANNEL_LABELS[$c] ?? ucfirst((string) $c);

        $channels = $this->filtered($filters)->selectRaw('channel, '.self::AGG.', COUNT(DISTINCT session_id) as sessions')
            ->groupBy('channel')->orderByDesc('views')->get()
            ->map(fn ($r) => [
                'channel' => $r->channel, 'label' => $label($r->channel),
                'views' => (int) $r->views, 'visitors' => (int) $r->visitors, 'sessions' => (int) $r->sessions,
                'bookings' => $conv['channels'][$r->channel]['bookings'] ?? 0,
                'enquiries' => $conv['channels'][$r->channel]['enquiries'] ?? 0,
            ])->all();

        $sources = $this->filtered($filters)->whereNotNull('source')->selectRaw('channel, source, '.self::AGG)
            ->groupBy('channel', 'source')->orderByDesc('views')->limit(100)->get()
            ->map(fn ($r) => [
                'channel' => $r->channel, 'label' => $label($r->channel), 'source' => $r->source,
                'views' => (int) $r->views, 'visitors' => (int) $r->visitors,
                'bookings' => $conv['sources'][$r->channel.'|'.$r->source]['bookings'] ?? 0,
                'enquiries' => $conv['sources'][$r->channel.'|'.$r->source]['enquiries'] ?? 0,
            ])->all();

        $apps = $this->filtered($filters)->whereNotNull('in_app')->selectRaw('in_app as app, '.self::AGG)
            ->groupBy('in_app')->orderByDesc('views')->get()
            ->map(fn ($r) => ['app' => $r->app, 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])->all();

        return [
            'data' => [
                'channels' => $channels,
                'sources' => $sources,
                'apps' => $apps,
                'crawlers' => ['views' => $this->botViews($filters)],
            ],
            'totals' => $this->totals($filters, $conv),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function pages(array $filters): array
    {
        $rows = $this->filtered($filters)
            ->selectRaw('subject_type, subject_id, MAX(path) as path, '.self::AGG.', MAX(created_at) as last_seen')
            ->groupBy('subject_type', 'subject_id')->orderByDesc('views')->limit(200)->get();

        $names = $this->namesFor($rows);

        $data = $rows->map(function ($r) use ($names) {
            $n = $names[$r->subject_type.':'.$r->subject_id];

            return [
                'subject_type' => $r->subject_type,
                'subject_id' => $r->subject_id,
                'title' => $n['title'],
                'path' => $r->path,
                'url' => $this->urlFor($r->path),
                'deleted' => $n['deleted'],
                'views' => (int) $r->views,
                'visitors' => (int) $r->visitors,
                'last_seen' => Carbon::parse($r->last_seen)->toIso8601String(),
            ];
        })->values()->all();

        return ['data' => $data, 'totals' => $this->totals($filters)];
    }

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters): array
    {
        $sortable = ['created_at', 'subject_type', 'channel', 'device_type', 'browser'];
        $sortBy = in_array($filters['sort_by'] ?? null, $sortable, true) ? $filters['sort_by'] : 'created_at';
        $order = ($filters['sort_order'] ?? null) === 'asc' ? 'asc' : 'desc';

        $page = $this->filtered($filters)->orderBy($sortBy, $order)->orderBy('id', $order)
            ->paginate((int) ($filters['per_page'] ?? 25));

        return [
            'data' => $this->shape(collect($page->items()))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'totals' => $this->totals($filters),
        ];
    }

    public function filterOptions(): array
    {
        $distinct = fn (string $col) => PageVisit::query()->whereNotNull($col)->distinct()->orderBy($col)->pluck($col)->values()->all();
        $special = ['packages' => 'Packages list', 'campaigns' => 'Campaigns list', 'booking' => 'Booking form'];

        return [
            'devices' => $distinct('device_type'),
            'platforms' => $distinct('platform'),
            'browsers' => $distinct('browser'),
            'channels' => collect(PageVisit::CHANNEL_LABELS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
            'apps' => $distinct('in_app'),
            'subjects' => collect(PageVisit::SUBJECTS)->map(fn ($s) => ['value' => $s, 'label' => $special[$s] ?? ucfirst($s)])->all(),
            'geo_enabled' => config('visits.geo.driver') !== 'none',
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function totals(array $filters, ?array $conv = null): array
    {
        $t = $this->filtered($filters)->selectRaw(self::AGG.', COUNT(DISTINCT session_id) as sessions')->first();
        $conv ??= $this->conversions($filters);

        return [
            'views' => (int) $t->views,
            'visitors' => (int) $t->visitors,
            'sessions' => (int) $t->sessions,
            'bot_views' => $this->botViews($filters),
            'bookings' => $conv['bookings'],
            'enquiries' => $conv['enquiries'],
            'storage' => [
                'database' => (string) config('database.connections.'.config('visits.connection').'.database'),
                'separate' => AnalyticsMigrate::isSeparate(),
            ],
        ];
    }

    /**
     * Bookings and enquiries made in the date range, credited to the channel and source of their
     * session's first visit. Two queries on two databases, merged here (no cross-database join).
     *
     * @param  array<string, mixed>  $filters
     * @return array{bookings: int, enquiries: int, channels: array, sources: array}
     */
    public function conversions(array $filters): array
    {
        $inRange = fn (string $model) => $model::query()->whereNotNull('session_id')
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->pluck('session_id');

        $made = ['bookings' => $inRange(Booking::class), 'enquiries' => $inRange(Inquiry::class)];
        $sessions = $made['bookings']->merge($made['enquiries'])->unique()->values();

        $landing = [];
        foreach ($sessions->chunk(500) as $chunk) {
            $ids = PageVisit::query()->whereIn('session_id', $chunk->all())->selectRaw('MIN(id) as id')->groupBy('session_id')->pluck('id');
            foreach (PageVisit::query()->whereIn('id', $ids->all())->get(['session_id', 'channel', 'source']) as $v) {
                $landing[$v->session_id] = $v;
            }
        }

        $out = ['bookings' => $made['bookings']->count(), 'enquiries' => $made['enquiries']->count(), 'channels' => [], 'sources' => []];
        foreach ($made as $kind => $ids) {
            foreach ($ids as $sid) {
                if (! ($v = $landing[$sid] ?? null)) {
                    continue;
                }
                $out['channels'][$v->channel][$kind] = ($out['channels'][$v->channel][$kind] ?? 0) + 1;
                if ($v->source !== null) {
                    $key = $v->channel.'|'.$v->source;
                    $out['sources'][$key][$kind] = ($out['sources'][$key][$kind] ?? 0) + 1;
                }
            }
        }

        return $out;
    }

    /** Turn a batch of visits into log rows (a fixed number of extra queries). */
    public function shape(Collection $visits): Collection
    {
        $names = $this->namesFor($visits);
        $locations = $this->locationsFor($visits);
        $sessions = $visits->pluck('session_id')->filter()->unique()->all();
        $count = fn (string $model) => $sessions
            ? $model::whereIn('session_id', $sessions)->selectRaw('session_id, COUNT(*) as n')->groupBy('session_id')->pluck('n', 'session_id')
            : collect();
        $bookings = $count(Booking::class);
        $enquiries = $count(Inquiry::class);

        return $visits->map(function ($v) use ($names, $locations, $bookings, $enquiries) {
            $n = $names[$v->subject_type.':'.$v->subject_id];

            return [
                'id' => $v->id,
                'visited_at' => $v->created_at?->toIso8601String(),
                'subject_type' => $v->subject_type,
                'subject_id' => $v->subject_id,
                'title' => $n['title'],
                'deleted' => $n['deleted'],
                'path' => $v->path,
                'url' => $this->urlFor($v->path),
                'referrer' => $v->referrer,
                'visitor_id' => $v->visitor_id,
                'session_id' => $v->session_id,
                'ip' => $v->ip,
                'location' => $v->ip ? $locations[$v->ip] : null,
                'device_type' => $v->device_type,
                'platform' => $v->platform,
                'browser' => $v->browser,
                'is_bot' => (bool) $v->is_bot,
                'locale' => $v->locale,
                'channel' => $v->channel,
                'channel_label' => PageVisit::CHANNEL_LABELS[$v->channel] ?? null,
                'source' => $v->source,
                'in_app' => $v->in_app,
                'utm_source' => $v->utm_source,
                'utm_medium' => $v->utm_medium,
                'utm_campaign' => $v->utm_campaign,
                'converted' => [
                    'bookings' => (int) ($bookings[$v->session_id] ?? 0),
                    'enquiries' => (int) ($enquiries[$v->session_id] ?? 0),
                ],
            ];
        });
    }

    /** @param  array<string, mixed>  $filters */
    private function botViews(array $filters): int
    {
        return $this->filtered($filters, true)->where('is_bot', true)->count();
    }

    private function urlFor(?string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim((string) $path, '/');
    }

    /** @return array<string, array{title: ?string, deleted: bool}> keyed "type:id" */
    private function namesFor(Collection $rows): array
    {
        $models = [
            ['model' => Page::class, 'types' => ['home', 'page', 'campaign'], 'field' => 'title'],
            ['model' => Service::class, 'types' => ['service'], 'field' => 'name'],
            ['model' => Package::class, 'types' => ['package', 'booking'], 'field' => 'name'],
            ['model' => Post::class, 'types' => ['post'], 'field' => 'title'],
            ['model' => GalleryAlbum::class, 'types' => ['album'], 'field' => 'title'],
        ];
        $fixed = ['home' => 'Home page', 'packages' => 'Packages list', 'campaigns' => 'Campaigns list'];
        $out = [];

        foreach ($models as $m) {
            $ids = $rows->whereIn('subject_type', $m['types'])->pluck('subject_id')->filter()->unique()->all();
            if (! $ids) {
                continue;
            }
            foreach ($m['model']::withTrashed()->whereKey($ids)->get() as $row) {
                $info = ['title' => $row->getTranslation($m['field'], 'en'), 'deleted' => $row->trashed()];
                foreach ($m['types'] as $type) {
                    $out[$type.':'.$row->id] = $info;
                }
            }
        }

        foreach ($rows as $r) {
            $key = $r->subject_type.':'.$r->subject_id;
            if (isset($fixed[$r->subject_type])) {
                $out[$key] = ['title' => $fixed[$r->subject_type], 'deleted' => false];
            } elseif (! isset($out[$key])) {
                $out[$key] = ['title' => null, 'deleted' => true];
            }
        }

        return $out;
    }

    private function locationsFor(Collection $rows): array
    {
        $public = $rows->pluck('ip')->filter(fn ($ip) => IpLocation::isPublic($ip))->unique()->all();
        $found = $public ? IpLocation::whereIn('ip', $public)->whereNotNull('resolved_at')->get()->keyBy('ip') : collect();

        $out = [];
        foreach ($rows->pluck('ip')->filter()->unique() as $ip) {
            if (! IpLocation::isPublic($ip)) {
                $out[$ip] = ['label' => 'Local network', 'local' => true];

                continue;
            }
            $l = $found->get($ip);
            $out[$ip] = $l ? [
                'label' => $l->label(), 'country' => $l->country, 'country_code' => $l->country_code,
                'city' => $l->city, 'isp' => $l->isp,
            ] : null;
        }

        return $out;
    }
}
