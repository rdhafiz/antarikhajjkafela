# Campaign List Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A public `/marketing` page that lists every live campaign as cards, styled like the existing listing pages, and linked from the footer.

**Architecture:** One new action on the existing `Site\PageController`, one Blade view modelled on `site/packages/index.blade.php`, and one card component modelled on `x-site.post-card`. Each card's image and summary come from the campaign's SEO fields (`seo.image`, `seo.description`), which every campaign already has in the admin. The footer gets one hard-coded link, next to "All packages".

**Tech Stack:** Laravel, Blade, Tailwind (existing site classes), PHPUnit feature tests.

**Spec:** this conversation. List page follows the existing UI; its route goes in the footer.

## Global Constraints

- URL: `GET /marketing`, route name `campaigns.index`. `Page::CAMPAIGN_PREFIX` already reserves the word `marketing`, so no admin page can take that slug.
- "Listed" means `Page::isVisibleToPublic()`. For campaigns that is `campaignState() === 'live'`. Drafts, scheduled and expired campaigns never appear.
- Every new UI string uses `__()` and gets a Bangla entry in `lang/bn.json`.
- Reuse the existing pieces: `x-site.layout header="overlay"`, `x-site.page-header`, `x-site.section tone="sand"`, the `card-site` empty state, `Dates::format` (through `PostPresenter::date`), and `x-site.icon`.
- No pagination. Campaigns are few. Add `x-site.pagination` once there are more than about 24.

## Review Focus

1. **Route order.** `/marketing` must be registered before `/{slug}`, or the slug route catches it and returns 404. Pinned in Task 1's test.
2. **Scheduled and expired campaigns leak.** Filtering only on `status = published` would show them. Pinned in Task 1's test.
3. **A campaign with no SEO image or description.** The card falls back to the star-pattern block and title only, the same way `post-card` does without a banner. Pinned in Task 1's test.
4. **No live campaigns.** The page shows the empty state, not a blank grid, and still returns 200. Pinned in Task 1's test.
5. **Bangla locale.** The page and the footer link render translated. Covered by the `bn.json` entries in Tasks 1 and 2.

---

### Task 1: `/marketing` list page

**Files:**
- Modify: `routes/web.php:72-75`. Add the route above the `campaign` route.
- Modify: `app/Http/Controllers/Site/PageController.php`. Add the `campaigns()` action.
- Create: `resources/views/site/campaigns/index.blade.php`
- Create: `resources/views/components/site/campaign-card.blade.php`
- Modify: `lang/bn.json`
- Modify: `app/Http/Controllers/Site/SitemapController.php:38`. Add `['loc' => route('campaigns.index'), 'lastmod' => null]` next to the `packages.index` entry.
- Test: `tests/Feature/Site/CampaignListTest.php`

**Interfaces:**
- Produces: route `campaigns.index` → `GET /marketing`. Task 2 links to it.
- View data: `campaigns` (`Collection<Page>`, live only, newest first) and `images` (`Collection<int, Media>` keyed by id, loaded with a single `Media::whereIn` query).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Site/CampaignListTest.php`. Use `RefreshDatabase` and seed `[MenuSeeder::class, PageSeeder::class]`, like `LayoutTest`. The seeded campaign `hajj-pre-registration` is the fixture.

```php
public function test_live_campaigns_are_listed(): void
{
    $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();
    $campaign->update(['status' => 'published', 'starts_at' => null, 'expires_at' => null]);

    $this->get('/marketing')
        ->assertOk()
        ->assertSee('href="'.url('/marketing/hajj-pre-registration').'"', false)
        ->assertSeeText($campaign->getTranslation('title', 'en'))
        ->assertSee('<link rel="canonical" href="'.url('/marketing').'">', false);

    $this->assertStringContainsString(url('/marketing').'<', $this->get('/sitemap.xml')->getContent());
}

public function test_draft_scheduled_and_expired_campaigns_are_hidden(): void
{
    $campaign = Page::where('slug', 'hajj-pre-registration')->firstOrFail();
    $link = 'href="'.url('/marketing/hajj-pre-registration').'"';

    foreach ([
        ['status' => 'draft', 'starts_at' => null, 'expires_at' => null],
        ['status' => 'published', 'starts_at' => now()->addDay(), 'expires_at' => null],
        ['status' => 'published', 'starts_at' => null, 'expires_at' => now()->subMinute()],
    ] as $state) {
        $campaign->update($state);
        $this->get('/marketing')->assertOk()->assertDontSee($link, false)
            ->assertSeeText('No offers running right now');
    }
}

public function test_a_campaign_without_seo_image_or_description_still_renders(): void
{
    Page::where('slug', 'hajj-pre-registration')->firstOrFail()
        ->update(['status' => 'published', 'starts_at' => null, 'expires_at' => null, 'seo' => []]);

    $this->get('/marketing')->assertOk()->assertSee('href="'.url('/marketing/hajj-pre-registration').'"', false);
}
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test --filter=CampaignListTest`
Expected: FAIL. `/marketing` returns 404.

- [ ] **Step 3: Add the route**

In `routes/web.php`, directly above the `/marketing/{slug}` route:
`Route::get('/'.Page::CAMPAIGN_PREFIX, [PageController::class, 'campaigns'])->name('campaigns.index');`

- [ ] **Step 4: Add `PageController::campaigns(): Response`**

- Query: `Page::where('type', Page::TYPE_CAMPAIGN)->published()->get()->filter->isVisibleToPublic()->sortByDesc(fn ($p) => $p->starts_at ?? $p->created_at)->values()`.
- Images: `Media::whereIn('id', $campaigns->pluck('seo.image')->filter())->get()->keyBy('id')`.
- SEO, in the same style as `PackageController::index`:
  - title `__('Special offers')`
  - description `__('Limited-time Hajj, Umrah and travel offers from Antarik.')`
  - canonical `route('campaigns.index')`
  - breadcrumbs: Home, then `__('Special offers')`
  - `pageType('CollectionPage')`
- Return `response()->view('site.campaigns.index', compact('campaigns', 'images'))`.

- [ ] **Step 5: Create `x-site.campaign-card`**

Props: `['page', 'image' => null]`. Copy the `post-card` non-lead layout (rounded card, 16/10 image block, hover lift, stretched link) with these changes:
- Image: `$image` (Media or null), using its `url('sm')` and `srcset()`. With no image, use the star pattern plus the `megaphone` icon. If that icon doesn't exist in `x-site.icon`, use `calendar`.
- Meta line: when `expires_at` is set, show `__('Ends :date', ['date' => PostPresenter::date($page->expires_at)])` in a `<time>`. Otherwise show nothing.
- Heading: `h2` with the page title, linked to `$page->url()`.
- Excerpt: `SeoText::single($page->seo['description'] ?? null, $locale)`, `line-clamp-3`. Leave it out when empty.
- CTA text: `__('View offer')` plus an arrow-right icon.

- [ ] **Step 6: Create `site/campaigns/index.blade.php`**

Follow the structure of `site/packages/index.blade.php`:
- `x-site.page-header` with eyebrow `__('Campaigns')`, title `__('Special offers')`, and the intro set to the SEO description.
- `x-site.section tone="sand" spacing="md"`.
- With no campaigns, the `card-site` empty state with the `calendar` icon, heading `__('No offers running right now')` and text `__('Call or message us and we will share the latest offers with you.')`.
- Otherwise, `<div class="mx-auto grid max-w-6xl gap-8 sm:grid-cols-2 lg:grid-cols-3">` with one `x-site.campaign-card :page :image="$images[$page->seo['image'] ?? 0] ?? null"` per campaign.

- [ ] **Step 7: Add the Bangla strings to `lang/bn.json`**

```json
"Campaigns": "ক্যাম্পেইন",
"Special offers": "বিশেষ অফার",
"Limited-time Hajj, Umrah and travel offers from Antarik.": "অন্তরিকের সীমিত সময়ের হজ্জ, উমরাহ ও ভ্রমণ অফার।",
"Ends :date": "শেষ হবে :date",
"View offer": "অফার দেখুন",
"No offers running right now": "এই মুহূর্তে কোনো অফার চলছে না",
"Call or message us and we will share the latest offers with you.": "কল বা মেসেজ করুন, আমরা আপনাকে সর্বশেষ অফারগুলো জানাব।"
```

- [ ] **Step 8: Add `/marketing` to the sitemap**

See Files above.

- [ ] **Step 9: Run the tests and confirm they pass**

Run: `php artisan test --filter="CampaignListTest|PageBuilderTest|SeoTest"`
Expected: all PASS. PageBuilderTest and SeoTest confirm `/marketing/{slug}` and the sitemap still work.

- [ ] **Step 10: Commit**

```bash
git add routes/web.php app/Http/Controllers/Site/PageController.php app/Http/Controllers/Site/SitemapController.php resources/views/site/campaigns resources/views/components/site/campaign-card.blade.php lang/bn.json tests/Feature/Site/CampaignListTest.php
git commit -m "Add /marketing campaign list page"
```

### Task 2: Footer link

**Files:**
- Modify: `resources/views/components/site/footer.blade.php:66`
- Modify: `tests/Feature/Site/CampaignListTest.php`

**Interfaces:**
- Consumes: route `campaigns.index` from Task 1.

- [ ] **Step 1: Write the failing test**

```php
public function test_the_footer_links_to_the_campaign_list(): void
{
    $this->get('/')->assertOk()->assertSee('href="'.route('campaigns.index').'"', false)->assertSeeText('Special offers');
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=test_the_footer_links_to_the_campaign_list`
Expected: FAIL. The link is missing.

- [ ] **Step 3: Add the link**

Directly after the "All packages" `<li>` in the Explore column, add an `<li>` with the same classes: `<a href="{{ route('campaigns.index') }}" class="link-underline transition-colors hover:text-white">{{ __('Special offers') }}</a>`. The link always shows. When nothing is live, the page shows its empty state.

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `php artisan test --filter="CampaignListTest|LayoutTest"`
Expected: all PASS.

- [ ] **Step 5: Check it in the browser**

Open `https://antarikhajjkafela.test/marketing` (EN and BN) at phone and desktop widths, and check the footer link on the home page.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/site/footer.blade.php tests/Feature/Site/CampaignListTest.php
git commit -m "Link the campaign list from the footer"
```
