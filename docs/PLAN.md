# Antarik Hajj Kafela: Development Plan

Status: **draft for review**. No code is written until this plan is approved.

## 1. Goals

- A fully dynamic Hajj & Umrah agency website (server-rendered, fast, SEO-first, bilingual EN/BN).
- A modern admin portal (Vue SPA) that manages every piece of content, the bookings and the settings.
- Online booking, with or without an advance payment, through DGePay or SSLCommerz (one active at a time).
- Runs on **Hostinger shared hosting + MySQL** and deploys through Git CI/CD.

## 2. Tech stack

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel 13, PHP 8.3+ | Already scaffolded |
| Database | MySQL 8 (also used locally through Laragon, replacing SQLite) | Same engine as production, with JSON columns |
| Public site | Blade SSR + Tailwind v4 + Alpine.js | Best for SEO and first load, with minimal JS |
| Public interactivity | CSS transitions, Alpine, Embla (carousels), PhotoSwipe (gallery lightbox) | Light. No GSAP or 3D unless a section truly needs it |
| Admin portal | Vue 3 + TypeScript + Vue Router + Pinia + Tailwind v4 + Reka UI (headless) | Light, fully custom design, no template look |
| Admin editors | Tiptap (rich text), SortableJS / vue-draggable-plus (drag and drop), Cropper.js (manual image mode) | |
| Admin auth | Laravel Sanctum **SPA cookie auth** (same domain, CSRF, httpOnly session) | No tokens stored in the browser |
| Permissions | spatie/laravel-permission | Roles plus fine-grained permissions |
| Translatable content | spatie/laravel-translatable (JSON columns `{en, bn, ...}`) | A new language needs no schema change |
| UI translations | `lang/{locale}.json` files | Adding a language = adding a file |
| Images | Intervention Image v3 (GD/Imagick) → WebP variants | Works on shared hosting |
| Queue | `sync` (no worker or cron for now) | Hostinger constraint, as you agreed |

Admin SPA access: `/admin/*` serves the SPA shell, and the SPA talks to `/api/admin/*` (JSON, stateful Sanctum). The public site and the admin are built as separate Vite entry points, so neither loads the other's JS.

## 3. Architecture overview

```
app/
  Domain/                       # grouped by module
    Settings/  Media/  Pages/  Travel/  Booking/  Payments/  Blog/  Reviews/  Gallery/  Inquiries/  Menus/  Users/
  Http/Controllers/Site/        # Blade controllers (public)
  Http/Controllers/Admin/       # JSON API controllers (admin SPA)
  Http/Requests/Admin/...       # validation for every write
  Http/Resources/Admin/...      # API output shaping
  Policies/                     # permission checks per model
  View/Components/Sections/     # page-builder component renderers
resources/
  views/site/...                # public Blade
  views/sections/...            # one Blade view per page-builder component
  admin/                        # Vue SPA source (main.ts, router, stores, pages, components)
lang/en.json, lang/bn.json      # UI strings
```

Cross-cutting rules:
- Every admin write goes through a Form Request, a Policy and a Service/Action class. Controllers stay thin.
- Every translatable field is stored as JSON `{en, bn}`. The admin forms show one tab per language, generated from the configured locales.
- Settings, menus and the component registry are cached, and the cache is flushed when they're saved.

## 4. Modules

### 4.1 Settings
- **Website:**
  - Branding: name, short description, logo, app icon, favicon. Name and description are translatable.
  - Theme: primary #012a51, secondary #fdc802, accent #01914d. These are injected as CSS variables, so a color change needs no rebuild.
  - Default language: EN by default. Locales are read from the `lang/` files.
  - Timezone: applied at runtime.
  - Currency: BDT, read-only for now.
  - Contact & social.
  - Default SEO: OG image, title, description, schema JSON.
  - Tracking: GTM, GA4, Search Console verification, Facebook Pixel, reCAPTCHA v3 keys.
  - Campaign **expiry redirect page**.
- **Admin:**
  - Admin theme colors: primary, secondary, sidebar (background, text, accent, logo background), header (background, text).
  - Image optimization mode: automatic or manual.
  - Payment gateways: DGePay and SSLCommerz. Credentials are stored **encrypted**, there's a sandbox/live toggle, and only one gateway can be active.
- Storage: a `settings` table (group, key, JSON value), with typed accessors and a cache layer.

### 4.2 Primary navigation (menu builder)
- Menu items nest up to 3 levels and are drag-sorted. Each item links to a page, service, package, blog category or custom URL, with a translatable label.

### 4.3 Media library and image optimization
- Every upload goes through a central `media` table (disk, path, mime, size, dimensions, alt text (translatable), variants JSON).
- **Size presets per usage** (hero, banner, card, thumb, gallery, og, avatar). Variants are WebP with the original kept as a fallback. Images get `srcset`, lazy loading and explicit width/height to avoid layout shift.
- **Automatic mode:** variants are generated on upload from the presets for the field's usage.
- **Manual mode:** after picking a file, a modal opens where the admin sets the sizes, crop and quality for each variant (Cropper.js). The server applies the chosen values, and only then does the upload complete.
- Booking documents (passport, NID) go on a **private disk** and are served only to authorized admins through controller-checked URLs.

### 4.4 Content management: page builder
- **Page types:** `default` (Home, Services, About, Contact, Gallery, Reviews, Blog are system pages that can't be deleted and have fixed slugs), `custom` (policy pages and others) and `campaign`.
- **Component library (registry):** each component is a PHP class with its key, name, icon, **field schema**, defaults and a Blade view. The admin SPA builds each component's edit form from its schema, so adding a component requires no admin UI code.
- **Page sections:** `page_id`, `component_key`, `sort`, `is_visible` (show/hide toggle), `content` (translatable JSON), `style` (text color, background color, background image, overlay, spacing).
- The admin picks components from the library, drag-sorts them, and edits text, images and colors for that page only.
- **Dynamic pages** (Blog, Gallery, Reviews, package listings) render their fixed listing in a designated slot, with builder sections above and below it.
- **Campaign pages** have their own admin menu and list, plus:
  - Publish and expiry dates. After expiry, visitors are redirected to the page set in Settings.
  - A hide-header/footer toggle.
  - Per-page SEO and extra tracking scripts.
  - A **lead-form component** whose visible fields the admin chooses. Submissions go to the inquiry inbox, tagged with the campaign.
- **Initial component set (about 20):**
  - Layout and media: Hero (static or slider), Image + Text split, Rich Text, Video embed, Team.
  - Content: About, Stats counter ("tracking numbers"), Services grid, Why choose us / Features, Process steps, FAQ accordion, Pricing highlight, Airlines/partners strip.
  - Dynamic (pull live data): Package showcase, Testimonials, Gallery preview, Latest blog posts.
  - Conversion: CTA banner, Contact info + map, Contact form, Lead form (campaign), Countdown (campaign).
  - More can be added later.

### 4.5 Travel management
- **Services:** CRUD (Hajj and Umrah, and more can be added), with translatable name and description, an image and SEO.
- **Packages**, exactly as you specified:
  - Service, name, slug (unique, regenerated from the English name on create and update), banner.
  - Booking window (start and end dates), duration in days, card summary, status (draft/published), featured flag, SEO.
  - **Advance setting:** fixed amount (per traveler) or percentage (of the total).
  - **Flights[]:** airline, schedule (free text), description.
  - **Package Types[]:** code, name, short description.
    - **Pricing variants[]:** title, short description, price, unit (per person, per room, per group).
      - **Hotel details[]:** heading, subheading, description.
- After the booking window ends, the package still shows, but as **"Booking closed"**.
- **Package detail page:** a fixed, designed layout for the structured data (overview, flights, package types → pricing → hotels, book button), followed by the **custom section builder**:
  - Each section has a layout (full width or two column), badge, heading, subheading, CTA, image (a background for full width; a side image with left/right choice for two column), text color, and a background color that becomes an overlay when there's a background image.
  - Each section holds **content blocks** (drag-sorted): text, table, list, image, embedded video.
- The content-block system is shared with blog posts. It is **separate** from the page component library, as you specified.

### 4.6 Booking and payments
- **Flow:** package → package type → pricing variant → number of travelers and contact details (+ optional passport/NID uploads) → choose:
  - **Pay advance now** → redirected to the active gateway → callback/IPN verified on the server → booking marked **Advance paid**.
  - **Book without payment** → a real booking with status **Pending**.
- **Amounts:** total = price × travelers. The advance is either fixed × travelers or a percentage of the total. All amounts are calculated on the server and snapshotted on the booking (prices can change later without affecting existing bookings).
- **Booking reference** (e.g. `AHK-2026-000123`). Confirmation emails go to the customer and the admin.
- **Admin booking management:**
  - Statuses: Pending → Confirmed → Advance paid → Fully paid → Completed, or Cancelled.
  - Payment ledger: gateway payments are recorded automatically, and the admin records **offline payments** (bank, mobile banking, cash). The paid amount and balance are calculated automatically.
  - Internal notes and document review.
- **Gateway layer:** a `PaymentGateway` interface with **DGePay** and **SSLCommerz** drivers, so new gateways plug in later. Every callback is validated with the gateway's verification API (never trusted from the browser redirect alone) and processed idempotently, and every request and response is logged.
- No customer accounts. Customers get their reference by email, plus a simple "check my booking" page (reference + phone/email).

### 4.7 Blog / News
- Posts: title, slug, banner, author name and image, categories, tags, content blocks, SEO, draft/published, and `published_at` for scheduling (future posts stay hidden until their date, with no cron needed), plus related posts.

### 4.8 Reviews
- Name, short intro, photo, star rating, text and/or YouTube embed, featured flag, sort order. Entered by admins only.

### 4.9 Gallery
- Albums (title, cover, description) → items (image or YouTube video). The newest items are shown first, with a lightbox on the site.

### 4.10 Inquiries
- An inbox for contact-form and campaign lead-form submissions: source tag, read/unread, status, notes, CSV export. Admin email notification.

### 4.11 Users, roles and permissions
- Multiple admins. Default roles: **Super Admin**, **Admin**, **Content Editor**, **Booking Manager**. Each module has fine-grained permissions (view, create, edit, delete), and the Super Admin can edit roles.
- Login is rate-limited, with password reset by email and an **activity log** of admin actions.

## 5. Public site and design

- **Visual direction:**
  - A premium, calm Islamic-modern design: navy (#012a51) as the anchor, gold (#fdc802) used sparingly for CTAs and highlights (never as text on white), and green (#01914d) as the accent.
  - Subtle geometric patterns derived from the logo's star knot.
- **Typography** (self-hosted via Bunny Fonts, subset):
  - Headings: **Fraunces** (EN) + **Tiro Bangla** (BN).
  - Body: **Plus Jakarta Sans** (EN) + **Hind Siliguri** (BN).
  - The font stack switches automatically with the active language.
- **Logo:** the full logo on light surfaces; the transparent icon plus the name in text on dark surfaces.
- **Motion:** purposeful only (section reveals, hover states, counter animation), all respecting `prefers-reduced-motion`.
- **Responsiveness:** mobile-first. Tested on mobile, tablet and desktop.
- **Language switcher:** stored in a session/cookie. The URL stays the same, and content falls back to the default language when a translation is empty.

## 6. SEO and performance

- Per-page SEO (title, description, OG image, canonical, schema JSON) with fallback to the defaults in Settings.
- Structured data:
  - TravelAgency / LocalBusiness on every page.
  - Product + Offer for packages.
  - BlogPosting, FAQPage, BreadcrumbList.
- An auto-generated `sitemap.xml` (cached and rebuilt on save) and `robots.txt`.
- Tracking scripts (GTM, GA4, Pixel) are injected from Settings and load deferred.
- Performance:
  - Critical CSS inlined, JS deferred, fonts preloaded.
  - WebP images with `srcset`, lazy loading and explicit sizes.
  - Eager-loaded queries and cached settings, menus and page trees.
  - Target: Lighthouse ≥ 90 on mobile.
- Note: because there is one URL per page, search engines will index the **default language** only. This was agreed.

## 7. Security

- Sanctum cookie sessions, CSRF on every form, and policies on every admin endpoint.
- reCAPTCHA v3 plus rate limits on the public forms (booking, contact, lead).
- Strict upload validation (MIME sniffing, size limits, extension allow-list), and private storage for personal documents.
- Gateway credentials are encrypted at rest. Callbacks are verified server-side, and amounts are recalculated on the server.
- HTML from the rich-text editor is sanitized (HTMLPurifier) before rendering, and only YouTube/Vimeo embeds are allowed.
- Security headers (CSP, frame-ancestors, referrer policy, and so on).

## 8. Seeding

The initial data comes from the old site ([docs/content/old-site-content.md](content/old-site-content.md)):
- Settings: branding, colors and contacts (as confirmed).
- Services: Hajj and Umrah.
- Packages: the 6 Hajj and 3 Umrah packages, mapped into our structure.
- Pages: About text, vision and mission; the service pages' text as page sections; Terms and Privacy as custom pages.
- Blog and news: 2 posts and 2 news items.
- FAQ: 5 items. Team: 5 members. Stats.
- Images: downloaded into the project and used for the gallery albums.

The Bangla content will be machine-translated drafts, marked for the client to review.

## 9. Delivery phases

| # | Phase | What's done at the end |
|---|---|---|
| 0 | **Foundation** | MySQL, packages installed, folder structure, auth plus roles/permissions, admin SPA shell (custom layout, sidebar, theming), settings infrastructure, translation infrastructure, media library + image optimization |
| 1 | **Settings + Menus** | All website/admin settings screens, the menu builder, theme variables live on the site |
| 2 | **Public design system** | Layout, header/footer, typography, color tokens, base UI components, language switcher, SEO head |
| 3 | **Page builder** | Component registry, about 20 components (admin forms + Blade views), default/custom/campaign pages, campaign expiry and lead forms |
| 4 | **Travel management** | Services and packages with all nested CRUD, the package section builder with content blocks, listing and detail pages |
| 5 | **Blog, Reviews, Gallery, Inquiries** | Admin CRUD plus public pages |
| 6 | **Booking + Payments** | Booking flow, emails, admin booking management, offline payments, SSLCommerz and DGePay sandbox integrations |
| 7 | **Seeding** | Old-site content and images imported, EN/BN |
| 8 | **QA and hardening** | Feature tests (booking amounts, payment callbacks, permissions, campaign expiry), cross-device browser testing, Lighthouse/accessibility pass, security review |
| 9 | **Deployment** | CI/CD to Hostinger (discussed at that point) |

Each phase ends with a demo/review checkpoint before the next one starts.

## 10. Still pending from you (these don't block the start)

- DGePay and SSLCommerz sandbox credentials (needed in phase 6).
- CI/CD details (phase 9).
- Any newer or modified content from the client (applied on top of the seeds).
