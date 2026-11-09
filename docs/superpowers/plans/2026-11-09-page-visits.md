# Page Visits Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record every public page view on the site, tie bookings and enquiries back to the visit they came from, and give staff a Page visits report (Sources / Pages / Visit log) in the admin portal.

**Architecture:** The visit log lives in **its own MySQL database** (connection `analytics`, own migrations folder, `analytics:migrate`), never joined to the main one. A global web middleware writes one `page_visits` row there after any public page whose controller called `Visited::…()`, and sets two cookies (`pv_vid`, `pv_sid`). Bookings and enquiries (main database) store those two ids, so the report can count conversions per traffic source with two separate queries. Visitor locations are looked up from ipwho.is **after the response** with `defer()`, because this host has no cron or queue worker. The report is a read-only admin API (`can:visits.view`) behind one Vue page with three tabs.

**Tech Stack:** Laravel 13, MySQL 8 (SQLite in tests), Vue 3 + TypeScript + Tailwind v4 (admin SPA), lucide-vue-next, `Http` facade for ipwho.is. No new Composer or npm packages.

**Spec:** `docs/superpowers/specs/2026-11-09-page-visits-source-guide.md` ("guide §x" below). The feature is already live on Newsvioso. Port its tested code instead of rewriting it:

| Port from (Newsvioso, on this machine) | To (here) |
|---|---|
| `D:\laragon\www\newsvioso.paper\app\Support\Analytics\{UserAgent,TrafficSource,VisitRecorder,Visited}.php` + `tests\Unit\Analytics\*` | `app/Domain/Visits/*`, `tests/Unit/Visits/*`, `tests/Feature/Visits/*` |
| `D:\laragon\www\newsvioso.paper\app\Http\Middleware\TrackPageVisit.php` | `app/Http/Middleware/TrackPageVisit.php` |
| `D:\laragon\www\newsvioso.app\app\{Models\Analytics,Support\Analytics}\*`, `Console\Commands\ResolveVisitLocations.php`, `Http\Controllers\Api\PageVisitController.php`, `database\migrations\analytics\*` | `app/Models/{PageVisit,IpLocation}.php`, `app/Domain/Visits/*`, `app/Console/Commands/ResolveVisitLocations.php`, `app/Http/Controllers/Admin/VisitController.php`, `database/migrations/*` |
| `D:\laragon\www\newsvioso.app\resources\js\pages\page-visits\*.vue` | **Reference only.** Rebuild in TypeScript with this repo's UI kit (`docs/ADMIN-UI.md`). |

How this site differs from Newsvioso. Where this plan and the guide or the Newsvioso code differ, **this plan wins**:

| Newsvioso | Here |
|---|---|
| Two apps share the `analytics` connection | One app, same idea: **separate `analytics` connection** (every `ANALYTICS_DB_*` falls back to `DB_*`), migrations in `database/migrations/analytics/`, run only by `analytics:migrate`; `deploy/activate.sh` runs it after `migrate`. Tests put it on its own in-memory SQLite. |
| `website_id`, `WebsiteScope`, tenant domain | No tenancy: drop every `website_id` and scope. "Own host" is the request host or `config('app.url')`'s host. |
| `reader_id` (reader guard) | None (no customer accounts). Signed-in **staff are not recorded** at all. |
| Subjects `article, category, page, home` | `home, page, campaign, service, package, booking, post, album, packages, campaigns` |
| Route middleware `track.visit:<type>` | One **global** web middleware (no parameter). Recording happens only when the controller called `Visited::…()`. |
| Scheduled `analytics:locations` every minute | `defer()` after a new IP's page view resolves up to 5 pending IPs. `visits:locations` is a manual command for backfill and retries. |
| Conversions out of scope | **In scope:** bookings and enquiries store `visitor_id` / `session_id`. |
| `role:super_admin` | Permission `visits.view` (new `visits` module in `config/access.php`) |
| `{ success, data, message }` envelope | This repo's shapes: `{ data }`, and `{ data, meta }` for paginated lists |
| Pinia store + 4 Vue files, heroicons, `dark:` | One page + 3 tab components using `TabSwitcher`, `useListQuery`, `AppDatePicker`, `AppDrawer`; lucide icons |
| `locale` not stored | `locale` stored (bilingual site) |

## Global Constraints

- Visit log storage: connection name `config('visits.connection')` (`ANALYTICS_DB_CONNECTION`, default `analytics`), defined in `config/database.php` exactly as guide §3.1. Production sets `ANALYTICS_DB_DATABASE`, `ANALYTICS_DB_USERNAME`, `ANALYTICS_DB_PASSWORD`; unset keys fall back to the main `DB_*` values.
- `page_visits` and `ip_locations` exist only on that connection. Models override `getConnectionName()`, migrations override `getConnection()`, raw queries use `DB::connection(config('visits.connection'))`. No foreign keys, no Eloquent relationships and **no SQL join** between the two databases: query one, collect ids, query the other, merge in PHP.
- `php artisan migrate` and `migrate:fresh` never touch the visit log; only `analytics:migrate {--fresh} {--force}` does.
- Tests: `phpunit.xml` adds `ANALYTICS_DB_DRIVER=sqlite`, `ANALYTICS_DB_DATABASE=:memory:`, `ANALYTICS_DB_URL=""`. A `Tests\Concerns\RefreshVisitLog` trait runs `analytics:migrate --force` in `setUp`; every test touching visits uses it next to `RefreshDatabase`.
- Recording must never break a page. Everything after the response is built sits in `try { } catch (\Throwable) { … }`; the cookies are returned even when the insert fails.
- Cookie names and lifetimes: `pv_vid` 730 days; `pv_sid` `config('visits.session_minutes')` (`VISITS_SESSION_MINUTES`, default `30`), re-issued on every view. Both `httpOnly`, `SameSite=Lax`, `secure` on HTTPS. Values must be UUIDs; anything else is replaced. They stay Laravel-encrypted (not added to `encryptCookies(except:)`).
- Countable view: `GET`, not XHR, status 200, `Content-Type` contains `text/html`, `Visited` attribute set, no signed-in staff (`auth()->check()` false). Signed preview routes never call `Visited`.
- Raw user-agent strings are not stored.
- Channel values: `social, paid_social, organic_search, paid_search, referral, email, sms, direct, other`.
- Subject types (`PageVisit::SUBJECTS`): `home, page, campaign, service, package, booking, post, album, packages, campaigns`. Last two are listings, `subject_id` null; `booking` is the booking form, subject = the package.
- Geo budget: ipwho.is allows 1,000 requests/day per server IP. `VISITS_GEO_DAILY_BUDGET=900`, per-visit batch `5`, command batch max `25`, `VISITS_GEO_DRIVER=ipwho|none`.
- Admin API: under `/api/admin/visits`, every route `->middleware('can:visits.view')`. Dates as `toIso8601String()`, like `InquiryController::format()`.
- Admin UI follows `docs/ADMIN-UI.md`: tables use `class="dt"` (no sideways scroll), no rows of icon buttons, `EmptyState`, no class names starting `ad-`.
- CI runs `vendor/bin/pint --test`, `npm run typecheck`, `npm run build` and `php artisan test`. Every task leaves all four green.
- Commit subjects are sentence-case statements (e.g. "Record page visits on the public site"). Rebuilt `public/build/` goes in its own "Build assets" commit.

## Review Focus

1. **Facebook / Instagram in-app browsers send no referrer.** They must read as `social` with `in_app=facebook|instagram`, not `direct`, or the biggest ad source disappears. → ported `TrafficSourceTest::test_facebook_in_app_without_referrer_is_social` (Task 1).
2. **Staff browsing the site while logged in to the admin** would inflate every number. → `RecordVisitsTest::test_signed_in_staff_are_not_recorded` (Task 3).
3. **The visit-log database is down, or `analytics:migrate` was never run** on a deploy: public pages still return 200 and set cookies, and the admin report says the log is unavailable instead of showing an empty report. → `RecordVisitsTest::test_missing_table_does_not_break_the_page` (Task 3) and `VisitReportTest::test_unavailable_log_returns_503` (Task 6).
4. **Service pages share the `/{slug}` route with pages.** `/hajj` must record `service`, not `page`; a 301 from an old slug records nothing. → `RecordVisitsTest::test_service_page_is_recorded_as_a_service` and `test_redirect_is_not_recorded` (Task 3).
5. **ipwho.is is slow, down or over budget.** The page is unaffected (lookup runs after the response), a 429 stops lookups for the day, and the budget is never exceeded. → `ResolveLocationsTest::test_429_spends_the_day` and `test_daily_budget_is_respected` (Task 5).

---

## File structure

| File | Responsibility |
|---|---|
| `config/visits.php` (create), `config/database.php` (modify) | `connection`, `enabled`, `session_minutes`, `geo` settings; the `analytics` connection |
| `database/migrations/analytics/2026_11_09_000001_create_page_visits_table.php` | `page_visits` (visit-log DB) |
| `database/migrations/analytics/2026_11_09_000002_create_ip_locations_table.php` | `ip_locations` (visit-log DB) |
| `app/Console/Commands/AnalyticsMigrate.php` | `analytics:migrate` |
| `tests/Concerns/RefreshVisitLog.php` | migrates the visit-log test DB |
| `database/migrations/2026_11_09_000003_add_visit_ids_to_bookings_and_inquiries.php` | `visitor_id`, `session_id` on both (main DB) |
| `app/Models/PageVisit.php`, `app/Models/IpLocation.php` | models, labels, `pending()` |
| `app/Domain/Visits/UserAgent.php`, `TrafficSource.php` | classifiers (ported) |
| `app/Domain/Visits/Visited.php` | controller marker |
| `app/Domain/Visits/VisitRecorder.php` | one insert + IP queue + cookies + deferred lookup |
| `app/Domain/Visits/VisitCookies.php` | read the two ids from a request (recorder and attribution share it) |
| `app/Domain/Visits/IpWhoIsLocator.php`, `LocationResolver.php` | ipwho.is client with budget; resolve pending rows |
| `app/Domain/Visits/VisitReport.php` | every report query (controller stays thin) |
| `app/Http/Middleware/TrackPageVisit.php` | global web middleware |
| `app/Console/Commands/ResolveVisitLocations.php` | `visits:locations` |
| `app/Http/Controllers/Admin/VisitController.php` | 5 GET endpoints |
| `bootstrap/app.php`, `routes/api.php`, `config/access.php` (modify) | wiring, routes, permission |
| 6 site controllers (modify) | `Visited::…()` calls |
| `app/Http/Controllers/Site/{BookingController,FormController}.php` (modify) | store visit ids |
| `app/Http/Controllers/Admin/{BookingController,InquiryController}.php` (modify) | "came from" on the detail |
| `resources/admin/pages/visits/{VisitsPage,VisitSources,VisitPages,VisitLog}.vue`, `types.ts` | the report |
| `resources/admin/{router,navigation}.ts` (modify) | route + nav item |
| `phpunit.xml` (modify) | visit-log test DB |
| `.env.example`, `deploy/activate.sh`, `docs/DEPLOYMENT.md`, `README.md` (modify) | env keys, `analytics:migrate` on deploy, deploy notes |

---

### Task 1: User-agent and traffic-source classifiers

**Files:**
- Create: `app/Domain/Visits/UserAgent.php`, `app/Domain/Visits/TrafficSource.php`
- Test: `tests/Unit/Visits/UserAgentTest.php`, `tests/Unit/Visits/TrafficSourceTest.php`

**Interfaces:**
- Produces:
  - `UserAgent::parse(?string $ua): array{is_bot: bool, device_type: ?string, platform: ?string, browser: ?string}`
  - `TrafficSource::for(Request $request): array{channel: string, source: ?string, referrer_host: ?string, referrer: ?string, in_app: ?string}` (`referrer` is the full external referrer, null for own host or none)
  - `TrafficSource::inApp(?string $ua): ?string`

- [ ] **Step 1: Port the tests.** Copy `newsvioso.paper/tests/Unit/Analytics/{UserAgentTest,TrafficSourceTest}.php` into `tests/Unit/Visits/`, namespace `Tests\Unit\Visits`, classes from `App\Domain\Visits`. Replace the tenant setup in `TrafficSourceTest` with `config(['app.url' => 'https://antarik.test'])`. Keep every case (guide §4.3 and §4.4). Rewrite the own-host case and add one:
  - `test_own_host_referrer_is_direct`: host `antarik.test`, referrer `https://www.antarik.test/packages` → `channel=direct`, `referrer=null`.
  - `test_app_url_host_counts_as_own_host`: request host `www.antarik.test`, `app.url` `https://antarik.test`, referrer `https://antarik.test/hajj` → `direct`.

- [ ] **Step 2: Run** `php artisan test tests/Unit/Visits`. Expected: FAIL (classes not found).

- [ ] **Step 3: Port the two classes** into `App\Domain\Visits`, unchanged except `TrafficSource::isOwnHost()`. A host is own when its `www.`-stripped, lower-cased form equals that of the request host or of `parse_url(config('app.url'), PHP_URL_HOST)`. Remove the `currentWebsite()` call.

- [ ] **Step 4: Run** the same command. Expected: PASS. Run `vendor/bin/pint` on the new files.

- [ ] **Step 5: Commit** "Classify visitor devices and traffic sources".

---

### Task 2: Visit-log database and `analytics:migrate`

**Files:**
- Create: `config/visits.php`, `database/migrations/analytics/2026_11_09_000001_create_page_visits_table.php`, `database/migrations/analytics/2026_11_09_000002_create_ip_locations_table.php`, `app/Console/Commands/AnalyticsMigrate.php`, `app/Models/PageVisit.php`, `app/Models/IpLocation.php`, `tests/Concerns/RefreshVisitLog.php`
- Modify: `config/database.php` (`connections`), `phpunit.xml`, `.env.example`
- Test: `tests/Feature/Visits/AnalyticsMigrateTest.php`

**Interfaces:**
- Produces:
  - Connection `analytics` in `config/database.php`, guide §3.1 verbatim.
  - `config('visits.connection')` (`ANALYTICS_DB_CONNECTION`, `analytics`), `config('visits.enabled')` (`VISITS_ENABLED`, true), `config('visits.session_minutes')` (`VISITS_SESSION_MINUTES`, 30), `config('visits.geo.{driver,endpoint,timeout,daily_budget,batch}')` (`ipwho`, `https://ipwho.is`, `5`, `900`, `5`).
  - Table `page_visits` (visit-log DB): guide §3.2 columns minus `customer_id`, plus `locale` varchar(5) null. `subject_type` varchar(16); `subject_id` unsignedBigInteger **nullable**; `created_at` `useCurrent()`, no `updated_at`. Indexes: `created_at`, `(subject_type, subject_id, created_at)`, `(is_bot, created_at)`, `(channel, created_at)`, `(source, created_at)`, `session_id`, `visitor_id`, `ip`.
  - Table `ip_locations` (visit-log DB): exactly guide §3.3.
  - `PageVisit` model: `getConnectionName()` → `config('visits.connection')`, `const UPDATED_AT = null`, casts `is_bot` bool, `const SUBJECTS` (Global Constraints list), `const CHANNEL_LABELS = ['social' => 'Social', 'paid_social' => 'Paid social', 'organic_search' => 'Search', 'paid_search' => 'Paid search', 'referral' => 'Referral', 'email' => 'Email', 'sms' => 'SMS', 'direct' => 'Direct', 'other' => 'Other']`.
  - `IpLocation` model: same connection, primary key `ip` (string, non-incrementing), `const MAX_ATTEMPTS = 3`, `scopePending()` (guide §3.3), `label(): string` → `"City, Country"`, or just `"Country"`.
  - Command `analytics:migrate {--fresh} {--force}`; static `AnalyticsMigrate::isSeparate(): bool` (visit-log database or host differs from the main connection's).
  - Trait `Tests\Concerns\RefreshVisitLog` with `setUpRefreshVisitLog()` (Laravel calls `setUp{Trait}` methods automatically) running `analytics:migrate --force`.

- [ ] **Step 1: Add the connection and config.** Copy the `analytics` block from `newsvioso.app/config/database.php` and the three `ANALYTICS_DB_*` test lines from `newsvioso.app/phpunit.xml`. Add the commented `ANALYTICS_DB_DATABASE`, `ANALYTICS_DB_USERNAME`, `ANALYTICS_DB_PASSWORD` and `VISITS_*` keys to `.env.example`.

- [ ] **Step 2: Write the failing test** `AnalyticsMigrateTest` (with `RefreshDatabase` and `RefreshVisitLog`):
  - `test_creates_both_tables_on_the_visit_log_connection`: `Schema::connection('analytics')->hasTable('page_visits')` and `hasTable('ip_locations')`, `hasColumns('page_visits', ['locale', 'in_app', 'utm_campaign'])`.
  - `test_main_database_does_not_get_the_tables`: `Schema::hasTable('page_visits')` is false on the default connection, so the two really are apart.
  - `test_main_migrate_fresh_keeps_the_log`: insert a row, `$this->artisan('migrate:fresh')`, the row is still there.

- [ ] **Step 3: Run** `php artisan test tests/Feature/Visits/AnalyticsMigrateTest.php`. Expected: FAIL (command not defined).

- [ ] **Step 4: Port the migrations, models and command** from `newsvioso.app` (`database/migrations/analytics/*`, `app/Models/Analytics/*`, `app/Console/Commands/AnalyticsMigrate.php`): drop `website_id` from the columns and indexes, add `locale`, read the connection from `config('visits.connection')`, and move the models to `App\Models`. The command keeps its two messages: "Visit logs share the application database [x]." as a warning, "Visit logs are kept separately, in [x]." as info.

- [ ] **Step 5: Run** the test. Expected: PASS. Then for real, against the local database `ana-antarikhajjkafela` (already created in Laragon, empty): set `ANALYTICS_DB_DATABASE=ana-antarikhajjkafela` in `.env` (same root user as the main database, so no username or password lines), and run `php artisan analytics:migrate`. Expected: "Visit logs are kept separately, in [ana-antarikhajjkafela]." and the two tables plus a `migrations` table in that database.

- [ ] **Step 6: Commit** "Add a separate visit-log database and its migrations".

---

### Task 3: Recorder and wiring

**Files:**
- Create: `app/Domain/Visits/{Visited,VisitCookies,VisitRecorder}.php`, `app/Http/Middleware/TrackPageVisit.php`
- Modify: `bootstrap/app.php`, and the `Visited` calls in `app/Http/Controllers/Site/{PageController,PackageController,BlogController,GalleryController,BookingController}.php`
- Test: `tests/Feature/Visits/RecordVisitsTest.php`

**Interfaces:**
- Consumes: Task 1 classes; Task 2 tables, models, config and `RefreshVisitLog`.
- Produces:
  - `Visited::home()`, `::page(Page)` (a campaign `Page` records as `campaign`), `::service(Service)`, `::package(Package)`, `::booking(Package)`, `::post(Post)`, `::album(GalleryAlbum)`, `::listing('packages'|'campaigns')`. Each sets `request()->attributes->set('visited', ['type' => …, 'id' => ?int])`.
  - `VisitCookies::ids(Request $request): array{visitor_id: ?string, session_id: ?string}`: the cookie values when they are UUIDs, else null.
  - `VisitRecorder::record(Request $request, string $type, ?int $id): array<Cookie>` (two cookies; `[]` when disabled).

- [ ] **Step 1: Write the failing tests** in `tests/Feature/Visits/RecordVisitsTest.php` (`RefreshDatabase` + `RefreshVisitLog`; seed `[MenuSeeder::class, PageSeeder::class]` and the services/packages fixtures the way `tests/Feature/Site/LayoutTest.php` and `BookingTest.php` do). Send real requests, e.g. `$this->withHeaders(['User-Agent' => self::IPHONE])->get('/packages/'.$package->slug)`:
  - `test_package_page_is_recorded`: one row with `subject_type=package`, `subject_id=$package->id`, `path='packages/<slug>'`, `device_type=phone`, `locale='en'`; response has `pv_vid` and `pv_sid` cookies.
  - `test_each_public_page_type_records_its_subject`: data provider over `/` → `home`, an about page → `page`, a campaign `/marketing/<slug>` → `campaign`, `/packages` → `packages`, `/marketing` → `campaigns`, a blog post → `post`, an album → `album`, `/packages/<slug>/book` → `booking` with the package id.
  - `test_service_page_is_recorded_as_a_service`: `/hajj` → `subject_type=service`.
  - `test_redirect_is_not_recorded`: `/home` (301) and an old package slug from `SlugRedirect` → zero rows.
  - `test_not_found_and_previews_are_not_recorded`: an unknown slug (404) and a signed `pages.preview` URL → zero rows.
  - `test_signed_in_staff_are_not_recorded`: `actingAs(User::factory()->create())`, then a package page → zero rows, page 200.
  - `test_utm_and_bot_are_classified`: `?utm_source=facebook&utm_medium=cpc` → `channel=paid_social`, `utm_source=facebook`; Googlebot UA → `is_bot=true`.
  - `test_cookies_are_kept_and_invalid_ones_replaced`: valid UUID cookies are stored and returned unchanged (`pv_sid` lifetime 30 minutes, `pv_vid` 730 days, httpOnly, `SameSite=lax`); `pv_vid=abc` is replaced with a UUID.
  - `test_public_ip_is_queued_and_private_is_not`: `REMOTE_ADDR` `8.8.8.8` → one `ip_locations` row; `127.0.0.1` → none; a bot on `8.8.4.4` → none. Set `config(['visits.geo.driver' => 'none'])` in this test, so Task 5's deferred lookup never runs here.
  - `test_missing_table_does_not_break_the_page`: `Schema::connection('analytics')->drop('page_visits')`, then a package page → 200 with both cookies.
  - `test_disabled_records_nothing`: `config(['visits.enabled' => false])` → zero rows, no `pv_*` cookies.

- [ ] **Step 2: Run** `php artisan test tests/Feature/Visits/RecordVisitsTest.php`. Expected: FAIL.

- [ ] **Step 3: Port `Visited`, `VisitRecorder` and `TrackPageVisit`.** Changes from the Newsvioso files:
  - `VisitRecorder::insert()`: no `website_id` or `reader_id`; add `'locale' => app()->getLocale()`. Write with `DB::connection(config('visits.connection'))->table('page_visits')`, as Newsvioso does. Cookie reading moves to `VisitCookies::ids()` (null → `Str::uuid()`).
  - `TrackPageVisit::handle(Request $request, Closure $next)` has no `$type` parameter. Countable as in Global Constraints. Type and id come from the `visited` attribute.
  - Register it in `bootstrap/app.php` with `$middleware->web(append: [SetLocale::class, TrackPageVisit::class])`. It runs after `SetLocale`, so `locale` is the visitor's language. Admin SPA shell and API requests never call `Visited`, so they are never counted.

- [ ] **Step 4: Wire the controllers.** Call `Visited::…()` only on the path that returns the rendered page, after every redirect or 404 branch:
  - `PageController`: `home()` → `Visited::home()`; `slug()` → `Visited::page($page)` just before `return $this->show($page)`; `campaign()` → `Visited::page($page)` before `show()`; `campaigns()` → `Visited::listing('campaigns')`. Never inside `show()`, which `preview()` also uses.
  - `PackageController`: `index()` → `listing('packages')`; `service()` → `service($service)`; `show()` → `package($package)` after the redirect branch.
  - `BlogController::show()` → `post($post)`; `GalleryController::show()` → `album($album)`; `BookingController::create()` → `booking($package)`.

- [ ] **Step 5: Run** the test file again. Expected: PASS. Then the full `php artisan test`: the existing site tests must still pass, since every response now carries two more cookies.

- [ ] **Step 6: Check in the browser.** With Task 2 Step 5 done, `composer dev`, open `http://antarikhajjkafela.test/`, a package and `/hajj` in a private window. Expect 3 rows (`php artisan tinker` → `PageVisit::latest('id')->take(3)->get()`) and `pv_vid` / `pv_sid` in DevTools. A reload within 30 minutes keeps the same `session_id`. Signed in to the admin in the same browser: no new rows. Stop the visit-log MySQL database (or point `ANALYTICS_DB_DATABASE` at a missing one): pages still load, `storage/logs/laravel.log` has one "Page visit not recorded" warning.

- [ ] **Step 7: Commit** "Record page visits on the public site".

---

### Task 4: Tie bookings and enquiries to their visit

**Files:**
- Create: migration `…000003_add_visit_ids_to_bookings_and_inquiries.php`
- Modify: `app/Http/Controllers/Site/BookingController.php` (`store`), `app/Http/Controllers/Site/FormController.php` (`submit`)
- Test: `tests/Feature/Visits/VisitAttributionTest.php`

**Interfaces:**
- Consumes: `VisitCookies::ids()` (Task 3).
- Produces: nullable `uuid` columns `visitor_id` and `session_id` (both indexed) on `bookings` and `inquiries`, in the **main** database (a normal migration). Task 6 counts conversions with them; Task 7 shows them on the detail.

- [ ] **Step 1: Write the failing tests** (`RefreshDatabase` + `RefreshVisitLog`). Reuse the booking setup from `tests/Feature/BookingTest.php` (fake reCAPTCHA as it does), with `->withUnencryptedCookies(['pv_vid' => $vid, 'pv_sid' => $sid])`:
  - `test_booking_stores_the_visit_ids`: post a booking → the new `Booking` has `visitor_id=$vid`, `session_id=$sid`.
  - `test_enquiry_stores_the_visit_ids`: post the contact form section → the `Inquiry` has both ids.
  - `test_missing_or_invalid_cookies_store_null`: no cookies, then `pv_sid=abc` → both null, and the booking or enquiry is still saved.
  - `test_admin_created_booking_has_no_visit`: a booking created through the admin API keeps both null.

- [ ] **Step 2: Run** `php artisan test tests/Feature/Visits/VisitAttributionTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement.** Add the migration. In `FormController::submit()`, spread `VisitCookies::ids($request)` into the `Inquiry::create([...])` array, and add `'visitor_id', 'session_id'` to the model's `#[Fillable([...])]` attribute. In `BookingController::store()`, after `$this->bookings->create(...)`: `$booking->forceFill(VisitCookies::ids($request))->saveQuietly();`. `saveQuietly` keeps it out of the activity log and booking events. `BookingService` stays unchanged, so admin-created bookings stay null.

- [ ] **Step 4: Run.** Expected: PASS, and `tests/Feature/BookingTest.php` still passes.

- [ ] **Step 5: Commit** "Remember which visit a booking or enquiry came from".

---

### Task 5: Visitor locations without cron

**Files:**
- Create: `app/Domain/Visits/{IpWhoIsLocator,LocationResolver}.php`, `app/Console/Commands/ResolveVisitLocations.php`
- Modify: `app/Domain/Visits/VisitRecorder.php`
- Test: `tests/Feature/Visits/ResolveLocationsTest.php`

**Interfaces:**
- Consumes: `ip_locations`, `IpLocation::pending()` (Task 2); the recorder (Task 3).
- Produces:
  - `IpWhoIsLocator::locate(array $ips): array<string, array{country, country_code, region, city, isp, is_proxy, is_hosting}>`. Never throws; returns only the IPs it found.
  - `LocationResolver::resolvePending(int $limit): int`. Takes `pending()->limit($limit)` and writes every row back (found → fields + `resolved_at`; else `failed_at`, `attempts++`). Returns how many were resolved.
  - Command `visits:locations {--limit=25} {--backfill}`.

- [ ] **Step 1: Port and extend the tests** from `newsvioso.app/tests/Feature/Analytics/ResolveVisitLocationsTest.php` (`Http::fake`, `RefreshDatabase` + `RefreshVisitLog`; `CACHE_STORE` is already `array` in `phpunit.xml`). Drop the `website_id` columns. Keep: `test_resolves_pending_ips`, `test_not_found_increments_attempts_and_gives_up_after_three`, `test_429_spends_the_day` (`Http::assertSentCount(1)` across two runs), `test_daily_budget_is_respected`, `test_driver_none_does_nothing`, `test_backfill_queues_distinct_non_bot_ips`. Add:
  - `test_new_ip_is_resolved_after_the_page`: `$this->withoutDefer()`, `Http::fake(['ipwho.is/*' => <success json>])`, a package page from `8.8.8.8` → the `ip_locations` row has `city` and `resolved_at`, and exactly one request was sent.
  - `test_known_ip_triggers_no_lookup`: a second page view from the same IP → no further HTTP request.

- [ ] **Step 2: Run** `php artisan test tests/Feature/Visits/ResolveLocationsTest.php`. Expected: FAIL.

- [ ] **Step 3: Port `IpWhoIsLocator`.** Config keys come from `config('visits.geo.*')`; the budget cache key is `visits:geo:ipwho:{Y-m-d}`. The rest is unchanged: one GET per IP with `fields=success,country,country_code,region,city,connection,security`; on 429 set the day's counter to the budget; stop the batch on timeout, 429 or 5xx.

- [ ] **Step 4: Implement `LocationResolver` and the command.** The command calls `resolvePending(min($limit, 25))`; with `--backfill` it first queues `DB::connection(config('visits.connection'))->table('page_visits')->where('is_bot', false)->distinct()->pluck('ip')`, filtered by the public-IP check, with `insertOrIgnore`.

- [ ] **Step 5: Hook the recorder.** In `VisitRecorder::insert()`, when `insertOrIgnore` into `ip_locations` returns `1` (a new IP), call `defer(fn () => app(LocationResolver::class)->resolvePending(config('visits.geo.batch')))`. Run it only when `Cache::add('visits:geo:running', 1, 60)` succeeds, so concurrent visitors don't double-spend. The batch also retries older pending IPs, so failed lookups heal without cron.

- [ ] **Step 6: Run.** Expected: PASS, and `RecordVisitsTest` still passes.

- [ ] **Step 7: Commit** "Look up visitor locations after the page is sent".

---

### Task 6: Report API

**Files:**
- Create: `app/Domain/Visits/VisitReport.php`, `app/Http/Controllers/Admin/VisitController.php`
- Modify: `routes/api.php`, `config/access.php`
- Test: `tests/Feature/Admin/VisitReportTest.php` (extends `AdminTestCase`)

**Interfaces:**
- Consumes: Task 2 tables and models, Task 4 visit ids, Task 5 locations.
- Produces: permission `visits.view` (module `'visits' => ['label' => 'Page visits', 'actions' => ['view']]`, granted `'*'` to the `admin` role) and these routes, all `GET`, each `->middleware('can:visits.view')`, inside the existing `auth:sanctum, active` group, named `admin.visits.*`:

| Path | Action | Response |
|---|---|---|
| `/api/admin/visits/sources` | `sources` | `{ data: { channels: [{channel, label, views, visitors, sessions, bookings, enquiries}], sources: [{channel, label, source, views, visitors, bookings, enquiries}], apps: [{app, views, visitors}], crawlers: {views} }, totals }` |
| `/api/admin/visits/pages` | `pages` | `{ data: [{subject_type, subject_id, title, path, url, deleted, views, visitors, last_seen}], totals }` (top 200) |
| `/api/admin/visits` | `index` | `{ data: [row…], meta: {current_page, last_page, per_page, total, from, to}, totals }` |
| `/api/admin/visits/filters` | `filters` | `{ data: { devices, platforms, browsers, channels: [{value, label}], apps, subjects: [{value, label}], geo_enabled } }` |
| `/api/admin/visits/export` | `export` | CSV stream `page-visits-{Y-m-d}.csv` |

`totals` = `{ views, visitors, sessions, bot_views, bookings, enquiries, storage: { database, separate } }` (`separate` from `AnalyticsMigrate::isSeparate()`).

When the visit-log database cannot be queried (`QueryException` / `PDOException` from the analytics connection), every endpoint returns `503 { message: 'The visit log is unavailable right now.' }` and logs one warning, instead of an empty report.

Visit log row: `id, visited_at, subject_type, subject_id, title, deleted, path, url, referrer, visitor_id, session_id, ip, location: {label, country, country_code, city, isp}|{label: 'Local network', local: true}|null, device_type, platform, browser, is_bot, locale, channel, channel_label, source, in_app, utm_source, utm_medium, utm_campaign, converted: {bookings: int, enquiries: int}` (`converted` counts the bookings and enquiries with that row's `session_id`).

- [ ] **Step 1: Write the failing tests** (`AdminTestCase` + `RefreshVisitLog`). Insert rows with `DB::connection('analytics')->table('page_visits')->insert([...])` and create real content (a package, a page, a trashed post):
  - `test_requires_permission`: a user without `visits.view` → 403 on all 5 routes; `adminWith(['visits.view'])` → 200.
  - `test_bots_are_excluded_unless_asked`: 2 human rows and 1 bot row → `totals.views = 2`; with `include_bots=1` → 3.
  - `test_date_range_is_inclusive`: rows on 1 Nov 23:59 and 2 Nov 00:00, `date_from=2026-11-02&date_to=2026-11-02` → 1.
  - `test_pages_are_named_including_deleted_content`: package and trashed post rows → titles in English, `deleted=true` on the post; `url` built from `config('app.url')`.
  - `test_conversions_count_per_landing_channel`: session S first lands from `social/facebook`, then views a package as `direct`; one booking with `session_id=S` → the `social` channel row has `bookings=1`, `direct` has 0.
  - `test_log_filters_and_paginates`: `channel=direct`, `per_page=1` → one row, `meta.total` right; `per_page=500` → 422.
  - `test_private_ip_reads_local_network`: a row from `127.0.0.1` → `location.label = 'Local network'`.
  - `test_unavailable_log_returns_503`: `Schema::connection('analytics')->drop('page_visits')` → each route 503 with the message.
  - `test_storage_reports_separate_database`: `totals.storage.separate` is true (the test DBs are two in-memory SQLite connections).
  - `test_export_streams_csv_with_bom`: the content starts with `"\xEF\xBB\xBF"` and the header row; a referrer starting with `=` is escaped (through `App\Support\Csv::row`).

- [ ] **Step 2: Run** `php artisan test tests/Feature/Admin/VisitReportTest.php`. Expected: FAIL.

- [ ] **Step 3: Implement `VisitReport`.** Port the query helpers from `newsvioso.app/app/Http/Controllers/Api/PageVisitController.php`, dropping tenancy and readers:
  - `filtered(array $filters): Builder` starts from `PageVisit::query()`. It excludes bots unless `include_bots`, applies `date_from` / `date_to` (`startOfDay` / `endOfDay`, app timezone), then exact matches on `subject_type, subject_id, channel, source, in_app, device_type, platform, browser, locale, visitor_id, session_id, ip`.
  - `namesFor(Collection $rows)`: one batched query per type. `Page` covers `home` / `page` / `campaign`, then `Service`, `Package`, `Post`, `GalleryAlbum`, all `withTrashed()` (they soft-delete through `Concerns\Trashable`; a row still missing reads as `deleted=true`). Titles are the English translation. Listings get fixed titles (`'Packages list'`, `'Campaigns list'`), `home` gets `'Home page'`. `url` is `rtrim(config('app.url'), '/').'/'.ltrim($path, '/')`.
  - `locationsFor(Collection $rows)`: as Newsvioso.
  - `conversions(array $filters)`: two separate queries, no join. First, on the main DB, `Booking` and `Inquiry` rows whose own `created_at` is in the date range and `session_id` is set → `pluck('session_id')`. Then, on the visit log, each of those sessions' landing row (lowest `id` per `session_id`, `whereIn` chunked by 500) → its channel and source. Merge in PHP into per-channel and per-source counts.
  - Parse `MAX(created_at)` with `Carbon::parse()` before `toIso8601String()`.

- [ ] **Step 4: Implement `VisitController`.** Thin: validate the query with `$request->validate([...])` (dates `date_format:Y-m-d`, `per_page` integer 1–100, default 25, `subject_type` `in:` `PageVisit::SUBJECTS`, `include_bots` boolean), call `VisitReport`, return JSON. `export` follows `InquiryController::export()` (BOM, `Csv::row`, `chunkById(500)`).

- [ ] **Step 5: Run.** Expected: PASS. Then `php artisan access:sync` locally.

- [ ] **Step 6: Commit** "Add the page visits report API".

---

### Task 7: Admin screens

**Files:**
- Create: `resources/admin/pages/visits/{VisitsPage,VisitSources,VisitPages,VisitLog}.vue`, `resources/admin/pages/visits/types.ts`
- Modify: `resources/admin/router.ts`, `resources/admin/navigation.ts`; the booking and inquiry detail drawers or pages (`resources/admin/pages/bookings/…`, `resources/admin/pages/inquiries/…`); `app/Http/Controllers/Admin/{BookingController,InquiryController}.php` (detail payload)
- Test: `tests/Feature/Admin/VisitReportTest.php` (one more test)

**Interfaces:**
- Consumes: Task 6 endpoints and shapes.
- Produces: SPA route `{ path: 'visits', name: 'visits', component: () => import('@admin/pages/visits/VisitsPage.vue'), meta: { title: 'Page visits', permission: 'visits.view' } }`. Nav item in the first (unlabelled) section after Inquiries: `{ label: 'Page visits', to: '/admin/visits', icon: ChartNoAxesColumn, tone: '#e879f9', permission: 'visits.view' }`.

- [ ] **Step 1: "Came from" on bookings and enquiries (test first).** Add `test_booking_and_inquiry_detail_show_where_they_came_from`: a booking whose `session_id` matches a `social/facebook` landing row → `GET /api/admin/bookings/{id}` has `data.visit = {channel: 'social', channel_label: 'Social', source: 'facebook', landing: {title, url}, visited_at}`; no `session_id` → `data.visit = null`. The same for `GET /api/admin/inquiries/{id}`. Implement with a `VisitReport::landingFor(?string $sessionId): ?array` call in each `show`. In the two admin detail views, add one `dl` row "Came from" (`Social · facebook`, the landing page linked).

- [ ] **Step 2: `VisitsPage.vue`.**
  - `PageHeader` with an Export CSV `AppButton` (`window.location.href = '/api/admin/visits/export?' + params`, as Inquiries does).
  - A `TabSwitcher` (`sources`, `pages`, `log`, kept in `?view=`).
  - One shared filter row: two `AppDatePicker` (`mode="date"`, default the last 7 days) and an "Include crawlers" `AppCheckbox`, all kept in the address bar.
  - A totals strip: views, visitors, sessions, bookings, enquiries. When `totals.storage.separate` is false, a quiet notice: "Visit logs are stored in the main database." A 503 shows an `EmptyState` with the message instead of the tabs.
  - The active tab component, given the filters as a prop.

- [ ] **Step 3: `VisitSources.vue` and `VisitPages.vue`.** `dt` tables. Sources: channels, then sources, then in-app apps, with views, visitors, bookings and enquiries; the crawler count goes under the tables. Pages: title with a subject-type `AppBadge` and a "Deleted" badge, views, visitors, last seen. Clicking a row switches to `log` filtered by `channel` / `source` / `in_app`, or by `subject_type` + `subject_id`.

- [ ] **Step 4: `VisitLog.vue`.** `useListQuery<VisitRow>('/visits', { …filters })` + `AppPagination`, `ListToolbar` + `FilterDrawer` for device, browser, channel, page type and language (options from `/visits/filters`). Each row: time, page, location, device, channel · source (in-app badge), and a "Booked" or "Enquired" badge when `converted` > 0. Clicking a row opens an `AppDrawer` with every field and the actions "All visits by this visitor", "This session" and "This IP" (each sets that filter). Active drill-downs show as removable chips. Empty states use `EmptyState` ("No visits match", with "Clear filters").

- [ ] **Step 5: Check in the browser** (`composer dev`, signed in as super admin at `/ahk/admin/visits`):
  - The rows from Task 3 Step 6 appear. Changing dates updates the URL; reload and Back keep the view.
  - Clicking "Direct" opens the log filtered to `channel=direct`.
  - The phone layout has no sideways scroll.
  - Export opens in Excel with Bangla titles intact.
  - A user without `visits.view` sees no nav item, and the route is blocked.

- [ ] **Step 6: Run** `npm run typecheck`, `vendor/bin/pint --test` and `php artisan test`. Expected: all green.

- [ ] **Step 7: Commit** "Add the Page visits report to the admin", then `npm run build` and commit "Build assets".

---

### Task 8: Deploy notes

**Files:**
- Modify: `deploy/activate.sh`, `docs/DEPLOYMENT.md`, `README.md`

- [ ] **Step 1: Run it on deploy.** In `deploy/activate.sh`, after `"$PHP" artisan migrate --force` (line 41), add `"$PHP" artisan analytics:migrate --force`. If the visit-log database is unreachable the deploy stops there, before the release goes live, which is what we want.

- [ ] **Step 2: Write the docs.**
  - `DEPLOYMENT.md` § 1 "One-time server setup" step 2: also create a **second** database and user for the visit log in hPanel → Databases → MySQL.
  - The production `.env` block gets three lines:
    ```dotenv
    ANALYTICS_DB_DATABASE=<visit-log database>
    ANALYTICS_DB_USERNAME=<visit-log user>
    ANALYTICS_DB_PASSWORD=<visit-log password>
    ```
    Host and port fall back to the main `DB_*` values.
  - `DEPLOYMENT.md` → "After the first deploy" gets a "Page visits" bullet:
    - each deploy runs `analytics:migrate`; the deploy log must say "kept separately", not "share the application database";
    - no cron is needed: locations fill in a moment after a new visitor's first page view;
    - `php artisan visits:locations --backfill` once if visits were recorded with `VISITS_GEO_DRIVER=none`;
    - grant "Page visits" to roles other than Admin in Roles & permissions.
  - Also add the "Cache check" below.
  - `README.md` gets one line under the feature list.
- [ ] **Step 3: Commit** "Run the visit-log migrations on deploy and document page visits".

**Cache check (production):** cached HTML never reaches Laravel, so it is never counted. After deploy, a package page response must not carry `x-litespeed-cache: hit` or `cf-cache-status: HIT`. If it does, exclude HTML from that cache.

---

## Out of scope (add when needed)

- Data retention or pruning, and a privacy-policy line about storing IPs and a 2-year cookie. This is a decision for the owner; without cron, pruning would also run through `defer()`.
- A cookie-consent banner. The site has none today, and these are first-party measurement cookies.
- Dashboard widget (views today, top source). One `VisitReport` call when wanted.
- Offline geolocation (DB-IP Lite) if 900 lookups/day runs out regularly.
