# Admin UI conventions

How every admin screen (Vue 3 + TypeScript, `resources/admin/`) is built. The reference
implementation is `resources/admin/pages/travel/PackagesPage.vue`; copy its patterns.

## Shared building blocks (`resources/admin/components/ui/`)

| Component | Use |
|---|---|
| `PageHeader` | Title + description + `#actions` slot. Its icon and colour come from the sidebar module automatically. |
| `FilterBar` | `v-model:search`, `placeholder`, `:active` (number of set filters), `:summary` ("24 bookings"), `@clear`. Filter `<select class="control">` go in the default slot; on phones they fold behind a "Filters" button. |
| `AppPagination` | `:meta`, `label`, `@change(page)`, `@per-page(size)`. Numbered pages + rows per page. |
| `AppDrawer` | Right-hand sheet: `v-model:open`, `title`, `description`, `icon`, optional `tone`, `width="md"\|"lg"`, `#footer`. For previewing a record and its actions. |
| `TabSwitcher` | `v-model`, `:options="[{ value, label, count?, icon?, tone?, alert? }]"` (`alert` = red dot, e.g. a tab with errors), `label`, `variant="pills"\|"underline"`. Wraps on wide screens; on phones it is one button that opens a drawer. Use it for every tab strip or filter-chip strip. |
| `AppDatePicker` | flatpickr. `v-model` keeps the API format: `mode="date"` → `YYYY-MM-DD`, `mode="datetime"` → `YYYY-MM-DDTHH:mm`. Props `id`, `placeholder`, `minDate`, `maxDate`, `clearable`, `invalid`. Every date or date-time field uses it (no `<input type="date|datetime-local">`). |
| `PhoneInput` | intl-tel-input. `v-model` (saves `+880 1711-381901`), `id`, `error` (server error), `country` (default `bd`). Every phone field uses it. The server validates with `Propaganistas\LaravelPhone\Rules\Phone`. |
| `ListHero` | Page header with a photo fading in from the right: `title`, `description`, `icon`, `tone`, `photo` (import from `resources/admin/assets`), `#actions`. Used by Packages and Bookings. |
| `ListToolbar` | One row above a list: `v-model:search`, `placeholder`, `:active` (count of set filters) and `@filters` (opens the drawer); extras such as a view switch go in the default slot. |
| `FilterDrawer` | Every filter of a list as chip groups (`groups: [{ key, label, icon, tone, options: [{ value, label, count? }] }]`) bound to `filters[key]`, plus a slot for other controls (e.g. a date range). Footer: Clear all and "Show N …". |
| `EmptyState`, `AppBadge`, `AppButton`, `AppModal`, `FormField`, `AppSpinner` | Unchanged APIs. |

`composables/useListQuery.ts` drives every server-paginated list:

```ts
const list = useListQuery<Row>('/endpoint', { status: '', category_id: '' }, { perPage: 20 });
// list.filters (reactive, incl. search/page/per_page; kept in the address bar), list.rows, list.meta,
// list.response (full JSON, e.g. counts), list.loading, list.active, list.load(), list.goTo(p), list.setPerPage(n), list.clear()
```

Call `list.load()` in `onMounted`. Filter values are strings (`''` = not set); search is debounced.

## Rules

1. **Never scroll sideways.** No `overflow-x-auto` wrappers or `min-w-[…]` on tables. Tables use
   `class="dt"`: the first cell is the row's title, every other cell has `data-label="…"` (shown as
   a label when the table stacks into cards on phones), and the last cell is
   `<td class="dt-trigger"><button class="dt-open" :aria-label="`Open …`" @click.stop="open(row)"><ChevronRight class="size-4" /></button></td>`.
   Rows are `class="dt-row" @click="open(row)"`. Cells that are noise on phones get `class="dt-hide-sm"`.
2. **No rows of icon buttons.** A row's actions (edit, view, duplicate, delete…) move into an
   `AppDrawer` that previews the record: badges, a `<dl class="dl rounded-2xl bg-canvas p-4">`
   of details, then an "Actions" list of
   `<button|a|RouterLink class="dw-action"><span class="dw-action-icon" style="--tone: #hex"><Icon class="size-4" /></span>Label</…>`
   (`dw-action is-danger` for delete). Close the drawer after an action and reload the list.
3. **Filters and pagination.** Server-paginated lists use `useListQuery` + `AppPagination`
   (with `@per-page`), and either `FilterBar` or, on the redesigned lists (Packages, Bookings),
   `ListToolbar` + `FilterDrawer`. Rows there are card/row components laid out with container
   queries (`PackageListItem`, `BookingListItem`) and a ⋮ menu styled with `.lm-item`/`.lm-icon`. Short drag-sorted lists (services, reviews) filter on the
   client instead; disable dragging while a filter or search is active.
4. **Colourful, meaningful icons** (lucide-vue-next). Palette: indigo `#818cf8`, green `#34d399`,
   sky `#38bdf8`, amber `#fbbf24`, violet `#a78bfa`, blue `#60a5fa`, rose `#fb7185`,
   orange `#fb923c`, pink `#f472b6`, teal `#2dd4bf`, lime `#a3e635`, cyan `#22d3ee`.
   On white, tint backgrounds with `color-mix(in oklab, <hex> 16%, white)` and use darker
   text `color-mix(in oklab, <hex> 50%, black)` (or Tailwind `text-emerald-600`, `text-violet-600`…).
5. **Empty and no-result states** use `EmptyState`; when filters are set, say "No … match" and
   offer "Clear filters".
6. **Page entrance** is automatic (each top-level block of the page rises in). Keep the page's
   root a single `<div>` whose direct children are the blocks.
7. Long-content inputs never push the page sideways: fieldsets already get `min-width: 0`; add
   `min-w-0` to flex/grid children that hold long text, and give grids a `grid-cols-[minmax(0,1fr)]` base.
8. **No ad-like class or file names.** Ad blockers hide elements whose classes look like ads
   (EasyList has rules such as `##.ad-body`, `##.ad-overlay`, `##.ad-tile`). Never start a class
   with `ad-`, `ads-` or `advert`, and avoid `banner`/`sponsor` in class and asset names. Drawer
   classes use the `dw-` prefix for this reason.
9. Keep every existing behaviour: permission checks (`auth.can(...)`), API calls, toasts,
   confirmations, keyboard access, `aria-*` labels.
