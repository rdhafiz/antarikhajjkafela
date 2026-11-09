# Page Visits — source guide (from the PROGGAA shop project)

This is the guide the Newsvioso plan ports; `docs/superpowers/plans/2026-11-09-page-visits.md` ports it again, to this site.
It describes a **shop** (books, landing pages, orders, customers). The plan's adaptation table
says what changes for Newsvioso; where the two differ, **the plan wins**. Sections the plan does
not use (orders attribution, checkout, PrimeVue UI, permission seeders, XLSX) are omitted here.

## 2. Design rules

1. **Separate database, no joins.** Analytics models have no Eloquent relationships and the
   report never joins `page_visits` to content tables. Query the log, collect ids, query the
   content tables separately, merge in PHP.
2. **No foreign keys** in the analytics tables.
3. **Own migration folder** (`database/migrations/analytics/`), run only by
   `php artisan analytics:migrate`, so `migrate:fresh` never wipes logs.
4. **Recording costs one INSERT and never throws.** No network call, no lookup in the request.
   Everything in `try/catch` → `Log::warning`. A page must never 500 because the counter failed.
5. **Classify on write, not on read.** UA → `device_type/platform/browser/is_bot`;
   referrer + UTM + UA → `channel/source/referrer_host/in_app`. Report does `GROUP BY column`.
6. **In-app browsers matter most.** Facebook/Instagram webviews send no referrer; read the UA
   (`FBAN`, `Instagram`, …) or the biggest campaign source reads as "direct".
7. **Bots are recorded, flagged (`is_bot`), not dropped.** API excludes them by default
   (`include_bots=1` to include); the admin screen passes it on by default.
8. **Geolocation is out-of-band and budgeted.** Only public IPs are queued, one row per distinct
   IP; a cron resolves a small batch every minute against a counted daily budget.
9. **Read-only admin API.**

## 3.1 The connection (`config/database.php`)

```php
'analytics' => [
    'driver'      => env('ANALYTICS_DB_DRIVER', env('DB_CONNECTION', 'mysql')),
    'url'         => env('ANALYTICS_DB_URL'),
    'host'        => env('ANALYTICS_DB_HOST', env('DB_HOST', '127.0.0.1')),
    'port'        => env('ANALYTICS_DB_PORT', env('DB_PORT', '3306')),
    'database'    => env('ANALYTICS_DB_DATABASE', env('DB_DATABASE', 'laravel')),
    'username'    => env('ANALYTICS_DB_USERNAME', env('DB_USERNAME', 'root')),
    'password'    => env('ANALYTICS_DB_PASSWORD', env('DB_PASSWORD', '')),
    'unix_socket' => env('ANALYTICS_DB_SOCKET', env('DB_SOCKET', '')),
    'charset'     => env('DB_CHARSET', 'utf8mb4'),
    'collation'   => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
],
```

`config/analytics.php`: `'connection' => env('ANALYTICS_DB_CONNECTION', 'analytics')`.
No `ANALYTICS_DB_*` lines → logs go to the app's own database. Models and migrations resolve the
connection at runtime:

```php
public function getConnectionName(): ?string { return config('analytics.connection'); } // models
public function getConnection(): ?string     { return config('analytics.connection'); } // migrations
```

## 3.2 `page_visits` — one row per page view

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `visitor_id` | uuid, index | cookie `pv_vid` (2 years) |
| `session_id` | uuid, index | cookie `pv_sid`, expires after `session_minutes` (30) idle |
| `subject_type` | varchar | page type |
| `subject_id` | bigint unsigned | id in the main DB (no FK) |
| `path` | varchar(512) | request path only (no host, no query) |
| `referrer` | varchar(2048) null | external referrer only; own-host referrers dropped |
| `channel` | varchar(24) null | `social, paid_social, organic_search, paid_search, referral, email, sms, direct, other` |
| `source` | varchar(64) null | `facebook`, `google`, or a referring host; null for direct |
| `referrer_host` | varchar(255) null | host without `www.` |
| `in_app` | varchar(32) null | `facebook, instagram, messenger, tiktok, …` webview |
| `customer_id` | bigint null, index | (Newsvioso: `reader_id`) |
| `ip` | varchar(45) null, index | |
| `device_type` | varchar(16) null | `phone, tablet, desktop` (null for bots) |
| `platform` | varchar(32) null | `iOS, Android, Windows, macOS, Linux` |
| `browser` | varchar(32) null | `Chrome, Safari, Edge, Firefox, Samsung Internet, …` |
| `is_bot` | bool default false | |
| `utm_source/medium/campaign` | varchar(255) null | raw query tags |
| `created_at` | timestamp default now | no `updated_at` |

The raw user-agent string is deliberately not stored.

## 3.3 `ip_locations` — one row per distinct IP

| Column | Type | Notes |
|---|---|---|
| `ip` | varchar(45) **PK** | |
| `country`, `country_code`(2), `region`, `city` | nullable | |
| `isp` | varchar(128) null | |
| `is_proxy`, `is_hosting` | bool null | null = provider didn't say |
| `resolved_at` | timestamp null | null = not answered yet |
| `failed_at` | timestamp null | last failed attempt |
| `attempts` | smallint default 0 | give up at 3 (`IpLocation::MAX_ATTEMPTS`) |
| `created_at`, `updated_at` | | |

Index `(resolved_at, failed_at)`. Scope `pending()` = `resolved_at IS NULL AND attempts < 3 ORDER BY created_at`.

## 3.5 `analytics:migrate`

Calls `migrate --database=<analytics conn> --path=database/migrations/analytics` (or
`migrate:fresh` with `--fresh`; `--force` for production) and prints a **warning when the log DB
equals the app DB**. Laravel also creates a `migrations` table inside the analytics database —
expected.

## 4. Recording pipeline

```
GET /article/{slug}
  └─ route ->middleware('track.visit:article')          (TrackPageVisit)
       └─ controller renders page and calls Visited::article($article)
            → $request->attributes['visited'] = ['type'=>'article','id'=>42]
  ← response built
  TrackPageVisit (after $next):
     countable?  GET && !ajax && status 200 && Content-Type text/html
     subject id from request attribute (type must match middleware param)
     VisitRecorder::record($request, 'article', 42) → [cookies]
     attach pv_vid / pv_sid cookies to response
```

Why a request attribute instead of re-querying the slug: the controller knows exactly which
record it rendered; calling `Visited::*` on a route without the middleware is a harmless no-op.
Why after the response: redirects / 404s / 304s are not views.

### 4.2 `VisitRecorder::record()`

1. `if (! config('analytics.enabled')) return [];`
2. Read `pv_vid` / `pv_sid` cookies; **accept only valid UUIDs**, else generate new.
3. In `try { … } catch (Throwable) { Log::warning(...) }`:
   - `UserAgent::parse($ua)` → `is_bot, device_type, platform, browser`
   - `TrafficSource::for($request)` → `channel, source, referrer_host, in_app`
   - one INSERT, no model events.
   - Queue IP: if geo driver ≠ `none`, not a bot, and IP is public
     (`filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)`)
     → `insertOrIgnore(['ip'=>…])` into `ip_locations`.
4. Return the two cookies **even if the insert failed**:
   - `pv_vid`: 730 days. `pv_sid`: `session_minutes` (sliding — re-issued every view).
   - `httpOnly`, `SameSite=Lax` (not Strict — Strict drops the cookie on the cross-site ad
     click), `secure` when HTTPS.

### 4.3 `UserAgent::parse()` — tiny regex parser, no package

- Empty UA → bot.
- One regex of bot markers (`bot|crawl|spider|slurp|facebookexternalhit|facebot|embedly|preview|curl|wget|python-requests|python-urllib|go-http-client|okhttp|java/|libwww|httpclient|headlesschrome|phantomjs|lighthouse|pingdom|uptime|monitor|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|gptbot|claudebot|ccbot`) → bot, with device/platform/browser **null**.
- Device: tablet first (`ipad|tablet|kindle|silk|playbook|android(?!.*mobile)`), then phone (`mobile|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone`), else desktop.
- Platform, first match: iOS (`iphone|ipad|ipod`) → Android → Windows → macOS (`macintosh|mac os x`) → Linux. Order matters: iOS UA says "like Mac OS X".
- Browser, first match: Edge (`edg(e|a|ios)?/`) → Opera (`opr/|opera`) → Samsung Internet (`samsungbrowser`) → UC (`ucbrowser`) → Firefox (`firefox|fxios`) → Chrome (`chrome|crios`) → Safari (`safari`) → IE (`msie|trident`). Order matters: Edge contains "Chrome" and "Safari".

### 4.4 `TrafficSource::for()` — decision order

1. **UTM tags present** (`utm_medium` or `utm_source`) → believed first.
   - medium `email|e-mail|newsletter` → `email`; `sms|text` → `sms`
   - paid medium (`cpc, ppc, paid, paidsocial, paid_social, paid-social, cpm, cpv, display, banner, retargeting, remarketing, ads, ad`) + search source → `paid_search`, else paid → `paid_social`
   - social source/host/in-app → `social`; search source/host → `organic_search`; has referrer → `referral`; else `other`
   - `source` = utm_source (lower-cased) ?? named host ?? in_app
2. **`gclid` present** → `paid_search` / `google`. (`fbclid` is **not** treated as paid — Facebook adds it to organic shares too.)
3. **In-app browser detected from UA** → `social` (source = app). Checked before referrer because the app usually sends none. Pattern order: messenger (`messenger|fb_iab/messenger|fban/messenger`) → instagram → facebook (`FBAN|FBAV|FB_IAB|FBIOS`) → tiktok (`musical_ly|bytedancewebview|tiktok`) → twitter → linkedin (`linkedinapp`) → pinterest → snapchat → wechat (`micromessenger`) → line (`line/`).
4. **No referrer** (or referrer is our own host) → `direct`.
5. Referrer host in social list → `social`, source = named network. List: facebook.com, m.facebook.com, l.facebook.com, lm.facebook.com, fb.com, fb.me, instagram.com, l.instagram.com, messenger.com, t.co, twitter.com, x.com, linkedin.com, lnkd.in, pinterest.com, pin.it, reddit.com, tiktok.com, youtube.com, youtu.be, whatsapp.com, wa.me, t.me, telegram.org, snapchat.com, threads.net.
6. Referrer host in search list (`google.*`, bing.com, yahoo.com, duckduckgo.com, yandex.*, baidu.com, ecosia.org, search.brave.com, startpage.com) → `organic_search`, source = engine name.
7. Anything else → `referral`, source = the host.

Host matching is **domain-boundary anchored**, not substring (`notgoogle.example` must not match `google.`).

## 5. Geolocation (scheduled, out-of-band)

`analytics:locations [--batch=N] [--backfill]`:
1. Take `IpLocation::pending()->limit(batchSize)`.
2. `locate($ips)` → array keyed by IP of what was found.
3. Write back **every** row: found → fields + `resolved_at`; not found → `failed_at`, `attempts++` (gives up after 3).
4. `--backfill`: one-off `SELECT DISTINCT ip FROM page_visits WHERE is_bot=0` → `insertOrIgnore` into `ip_locations`.

ipwho.is: free, no key, HTTPS, commercial use allowed. One request per IP, **1,000/day per server
IP**; over = 24h lockout. Budget counted in cache key `analytics:geo:ipwho:{date}` (default
900/day); a 429 marks the day spent. Batch default 10, max 25. Locator rules: never throw; return
only what was found; stop the batch on timeout / 429 / 5xx.

## 7. Admin API (shop version, for shape reference)

Shared filters: base `PageVisit::query()`, plus `where is_bot = 0` unless `include_bots=1`;
date range on `created_at` start/end of day; equality filters for subject, channel, source,
in_app, device_type, browser, visitor_id, session_id, ip, customer.

Pages: `SELECT subject_type, subject_id, MAX(path), COUNT(*) views, COUNT(DISTINCT visitor_id) visitors, MAX(created_at) last_seen GROUP BY subject_type, subject_id ORDER BY views DESC LIMIT 200`.

Names onto rows — one batched query each on the main DB: titles (`withTrashed()`), people, and
locations (`IpLocation::whereIn('ip', publicIps)`; private IPs → "Local network").
Storefront URLs are rebuilt on the public site's domain, not the request host, because the
controller runs on the admin domain.

## 11. Gotchas seen in practice

- A missing `ANALYTICS_DB_DATABASE` silently shares the main DB — check `analytics:migrate` output or the report's storage notice.
- Forgot `analytics:migrate` on deploy: public site still works (insert caught; warning logged), admin report errors.
- Local dev: every IP is `127.0.0.1` → "Local network", nothing sent to ipwho.
- Cookies are normal Laravel-encrypted cookies.
- Timestamps from `MAX()` come back as zone-less strings; parse before returning.
- Deleted content keeps its rows; `withTrashed()` is needed to name it.
