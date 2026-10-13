<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowDownUp, CalendarCheck, CalendarClock, Check, Copy, ExternalLink, LayoutGrid, Layers, List, Package as PackageIcon, Pencil, Plus, Search, SlidersHorizontal, Star, Trash2, Wallet, X } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import PackageListItem, { type PackageRow as ItemRow } from './PackageListItem.vue';
import headerPhoto from '@admin/assets/packages-header.webp';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface PackageRow {
    id: number;
    name: Translations;
    slug: string;
    url: string;
    service: Translations | null;
    status: 'draft' | 'published';
    is_featured: boolean;
    booking_state: 'open' | 'upcoming' | 'closed';
    booking_ends_on: string | null;
    duration_days: number | null;
    min_price: number | null;
    types_count: number;
    banner_url: string | null;
    prices_count: number;
    hotels_count: number;
    flights_count: number;
    sections_count: number;
    updated_at: string;
}

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<PackageRow>('/packages', { service_id: '', status: '', featured: '', booking: '', sort: '' });
const services = ref<{ id: number; name: Translations }[]>([]);

const current = ref<PackageRow | null>(null);
const drawerOpen = ref(false);
const createOpen = ref(false);
const createForm = useForm({ service_id: '' as number | '', name: {} as Translations });

// List or grid, remembered on this device.
const VIEW_KEY = 'ahk.packages.view';
const view = ref<'list' | 'grid'>('list');
try {
    if (localStorage.getItem(VIEW_KEY) === 'grid') view.value = 'grid';
} catch {
    // Storage can be blocked; the list view is the default.
}
watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Not remembered, which is fine.
    }
});

const money = new Intl.NumberFormat('en-IN');
const bookingTone = { open: 'success', upcoming: 'warning', closed: 'danger' } as const;
const bookingLabel = { open: 'Booking open', upcoming: 'Opens soon', closed: 'Booking closed' } as const;
const price = (value: number | null) => (value !== null ? `৳${money.format(value)}` : '—');
const total = computed(() => list.meta.value?.total ?? 0);
const filtersOpen = ref(false);

/** Every filter in the drawer, as groups of choices ('' = any). Sort is a choice too, but not counted as a filter. */
const filterGroups = computed(() => [
    {
        key: 'service_id' as const,
        label: 'Service',
        icon: LayoutGrid,
        tone: '#38bdf8',
        options: [{ value: '', label: 'All services' }, ...services.value.map((s) => ({ value: String(s.id), label: pickText(s.name, app.defaultLocale) }))],
    },
    {
        key: 'status' as const,
        label: 'Status',
        icon: Layers,
        tone: '#a78bfa',
        options: [
            { value: '', label: 'Any status' },
            { value: 'published', label: 'Published' },
            { value: 'draft', label: 'Draft' },
        ],
    },
    {
        key: 'booking' as const,
        label: 'Booking',
        icon: CalendarCheck,
        tone: '#34d399',
        options: [
            { value: '', label: 'Any' },
            { value: 'open', label: 'Booking open' },
            { value: 'upcoming', label: 'Opens soon' },
            { value: 'closed', label: 'Booking closed' },
        ],
    },
    {
        key: 'featured' as const,
        label: 'Featured',
        icon: Star,
        tone: '#fb7185',
        options: [
            { value: '', label: 'Featured or not' },
            { value: 'yes', label: 'Featured only' },
            { value: 'no', label: 'Not featured' },
        ],
    },
    {
        key: 'sort' as const,
        label: 'Sort by',
        icon: ArrowDownUp,
        tone: '#fbbf24',
        options: [
            { value: '', label: 'Featured first' },
            { value: 'updated', label: 'Recently updated' },
            { value: 'price_asc', label: 'Price: low to high' },
            { value: 'price_desc', label: 'Price: high to low' },
        ],
    },
]);
const activeFilters = computed(() => (['service_id', 'status', 'booking', 'featured'] as const).filter((key) => list.filters[key] !== '').length);
const filtered = computed(() => Boolean(list.filters.search) || activeFilters.value > 0);

/** Price-box colour per service, so packages of one service share a colour. */
const serviceTones = ['#34d399', '#38bdf8', '#fb923c', '#a78bfa', '#f472b6', '#fbbf24'];
function toneFor(row: PackageRow): string {
    const name = pickText(row.service, app.defaultLocale);
    const index = services.value.findIndex((s) => pickText(s.name, app.defaultLocale) === name);
    return serviceTones[(index < 0 ? 0 : index) % serviceTones.length];
}

const item = (row: PackageRow): ItemRow => ({
    ...row,
    name: pickText(row.name, app.defaultLocale),
    service: row.service ? pickText(row.service, app.defaultLocale) : null,
});

function open(row: PackageRow): void {
    current.value = row;
    drawerOpen.value = true;
}

function openCreate(): void {
    createForm.reset({ service_id: services.value[0]?.id ?? '', name: {} });
    createOpen.value = true;
}

async function create(): Promise<void> {
    const response = await createForm.submit(() => http.post<{ data: { id: number } }>('/packages', createForm.data));
    if (response) {
        createOpen.value = false;
        router.push({ name: 'package-editor', params: { id: response.data.data.id } });
    }
}

async function duplicate(row: PackageRow): Promise<void> {
    try {
        await http.post(`/packages/${row.id}/duplicate`);
        toast.success('Copy created as a draft');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(row: PackageRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(row.name)}"?`, message: 'Its prices and page content will be deleted. This cannot be undone.', confirmLabel: 'Delete package' }))) return;
    try {
        await http.delete(`/packages/${row.id}`);
        toast.success('Package deleted');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

onMounted(async () => {
    list.load();
    try {
        services.value = (await http.get<{ services: { id: number; name: Translations }[] }>('/packages/schema')).data.services;
    } catch {
        // The service filter is optional.
    }
});
</script>

<template>
    <div>
        <!-- Header with a Makkah photo -->
        <header class="pk-hero relative mb-5 overflow-hidden rounded-[var(--radius-card)] border border-line">
            <img :src="headerPhoto" alt="" class="pk-hero-img" aria-hidden="true" />
            <div class="relative flex flex-wrap items-center gap-x-5 gap-y-4 px-5 py-6 sm:px-7 sm:py-7">
                <span class="ph-tile flex size-14 shrink-0 items-center justify-center rounded-2xl sm:size-16" style="--tone: #fbbf24" aria-hidden="true">
                    <PackageIcon class="size-7" />
                </span>
                <div class="min-w-0 flex-1 basis-64">
                    <h1 class="text-[26px] leading-tight font-bold tracking-[-0.01em] text-ink sm:text-[30px]">Packages</h1>
                    <p class="mt-1 max-w-md text-[14.5px] text-ink-soft">Hajj and Umrah packages with their types, prices, hotels, flights and page content.</p>
                </div>
                <AppButton v-if="auth.can('packages.create')" :icon="Plus" class="pk-new" @click="openCreate">New package</AppButton>
            </div>
        </header>

        <!-- Search, filters and view switch in one row -->
        <div class="mb-4 flex items-center gap-2 sm:gap-3">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Search packages</span>
                <Search class="pointer-events-none absolute top-1/2 left-4 size-[18px] -translate-y-1/2 text-ink-muted" aria-hidden="true" />
                <input v-model="list.filters.search" type="search" placeholder="Search packages" class="control pk-search pr-10 pl-11" />
                <button v-if="list.filters.search" type="button" class="absolute top-1/2 right-2.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Clear search" @click="list.filters.search = ''">
                    <X class="size-4" />
                </button>
            </label>
            <button type="button" class="pk-filter-btn" :class="{ 'is-active': activeFilters }" aria-haspopup="dialog" @click="filtersOpen = true">
                <SlidersHorizontal class="size-[18px]" aria-hidden="true" />
                <span class="max-sm:sr-only">Filters</span>
                <span v-if="activeFilters" class="pk-filter-count"><span class="sr-only">, </span>{{ activeFilters }}<span class="sr-only"> active</span></span>
            </button>
            <div class="hidden rounded-xl border border-line bg-white p-1 sm:flex" role="group" aria-label="Show packages as">
                <button type="button" class="pk-view" :class="{ 'is-on': view === 'grid' }" :aria-pressed="view === 'grid'" aria-label="Grid" title="Grid" @click="view = 'grid'"><LayoutGrid class="size-[18px]" /></button>
                <button type="button" class="pk-view" :class="{ 'is-on': view === 'list' }" :aria-pressed="view === 'list'" aria-label="List" title="List" @click="view = 'list'"><List class="size-[18px]" /></button>
            </div>
        </div>

        <!-- Packages -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4" :class="{ 'sm:grid-cols-2 xl:grid-cols-3': view === 'grid' }" aria-busy="true" aria-label="Loading packages">
            <div v-for="n in 3" :key="n" class="card animate-pulse" :class="view === 'list' ? 'h-[300px] lg:h-[134px]' : 'h-[360px]'" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="PackageIcon"
                :title="filtered ? 'No packages match' : 'No packages yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Create your first Hajj or Umrah package.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('packages.create')" :icon="Plus" @click="openCreate">New package</AppButton>
            </EmptyState>
        </div>
        <div v-else :key="view" class="pk-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="pk-grid" :class="`is-${view}`">
                <PackageListItem
                    v-for="(row, i) in list.rows.value"
                    :key="row.id"
                    :row="item(row)"
                    :layout="view"
                    :tone="toneFor(row)"
                    :index="i"
                    :can-create="auth.can('packages.create')"
                    :can-delete="auth.can('packages.delete')"
                    @open="open(row)"
                    @duplicate="duplicate(row)"
                    @remove="remove(row)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="packages" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <AppDrawer v-model:open="filtersOpen" title="Filter packages" description="Results update as you choose." :icon="SlidersHorizontal" tone="#38bdf8">
            <div class="space-y-6">
                <fieldset v-for="group in filterGroups" :key="group.key" class="min-w-0">
                    <legend class="mb-2.5 flex items-center gap-2 text-[13.5px] font-bold text-ink">
                        <span class="pk-group-icon" :style="{ '--tone': group.tone }"><component :is="group.icon" class="size-4" aria-hidden="true" /></span>
                        {{ group.label }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="option in group.options" :key="option.value" class="pk-chip-choice" :class="{ 'is-on': list.filters[group.key] === option.value }" :style="{ '--tone': group.tone }">
                            <input v-model="list.filters[group.key]" type="radio" class="sr-only" :name="`pk-${group.key}`" :value="option.value" />
                            <Check v-if="list.filters[group.key] === option.value" class="size-3.5" aria-hidden="true" />
                            {{ option.label }}
                        </label>
                    </div>
                </fieldset>
            </div>
            <template #footer>
                <AppButton variant="ghost" :disabled="!activeFilters && !list.filters.sort" @click="list.clear()">Clear all</AppButton>
                <AppButton class="ml-auto" :loading="list.loading.value" @click="filtersOpen = false">Show {{ total }} {{ total === 1 ? 'package' : 'packages' }}</AppButton>
            </template>
        </AppDrawer>

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? pickText(current.name, app.defaultLocale) : ''" :description="current ? pickText(current.service, app.defaultLocale) ?? '' : ''" :icon="PackageIcon">
            <template v-if="current">
                <img v-if="current.banner_url" :src="current.banner_url" alt="" class="mb-5 aspect-[16/9] w-full rounded-2xl object-cover" />
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="current.status === 'published' ? 'success' : 'neutral'">{{ current.status === 'published' ? 'Published' : 'Draft' }}</AppBadge>
                    <AppBadge :tone="bookingTone[current.booking_state]">{{ bookingLabel[current.booking_state] }}</AppBadge>
                    <AppBadge v-if="current.is_featured" tone="gold"><Star class="size-3 fill-current" />Featured</AppBadge>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Wallet class="size-4 text-emerald-600" />From</dt>
                    <dd>{{ price(current.min_price) }}</dd>
                    <dt class="flex items-center gap-2"><Layers class="size-4 text-violet-600" />Types</dt>
                    <dd>{{ current.types_count }} types · {{ current.prices_count }} room options</dd>
                    <dt class="flex items-center gap-2"><CalendarClock class="size-4 text-sky-600" />Duration</dt>
                    <dd>{{ current.duration_days ? `${current.duration_days} days` : '—' }}</dd>
                    <dt>Includes</dt>
                    <dd>{{ current.hotels_count }} hotels · {{ current.flights_count }} flights · {{ current.sections_count }} page sections</dd>
                    <template v-if="current.booking_ends_on">
                        <dt>Booking until</dt>
                        <dd>{{ formatDate(current.booking_ends_on) }}</dd>
                    </template>
                    <dt>Updated</dt>
                    <dd>{{ formatDate(current.updated_at, true) }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'package-editor', params: { id: current.id } }" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit package
                    </RouterLink>
                    <a :href="current.url" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-if="auth.can('packages.create')" type="button" class="dw-action" @click="duplicate(current)">
                        <span class="dw-action-icon" style="--tone: #a78bfa"><Copy class="size-4" /></span>Duplicate as a draft
                    </button>
                    <button v-if="auth.can('packages.delete')" type="button" class="dw-action is-danger" @click="remove(current)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete package
                    </button>
                </div>
            </template>
        </AppDrawer>

        <AppModal v-model:open="createOpen" title="New package" description="Saved as a draft. You'll add types, prices, flights and content next." :persistent="createForm.processing">
            <form id="new-package" class="space-y-4" novalidate @submit.prevent="create">
                <FormField label="Service" for="new-package-service" :error="createForm.error('service_id')" required>
                    <select id="new-package-service" v-model="createForm.data.service_id" class="control">
                        <option v-for="service in services" :key="service.id" :value="service.id">{{ pickText(service.name, app.defaultLocale) }}</option>
                    </select>
                </FormField>
                <TranslatableInput v-model="createForm.data.name" label="Package name" required :errors="createForm.errors" error-key="name" hint="The web address is created from the English name." placeholder="Hajj Premium Package 2027" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="new-package" :loading="createForm.processing">Create and edit</AppButton>
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
.pk-hero {
    background: linear-gradient(100deg, #fbfcfe 0%, #f3f6fb 45%, #eef3fa 100%);
}

.pk-hero-img {
    position: absolute;
    inset: 0 0 0 auto;
    width: 46%;
    height: 100%;
    object-fit: cover;
    object-position: 50% 58%;
    mask-image: linear-gradient(to right, transparent 0%, rgb(0 0 0 / 0.6) 30%, #000 60%);
}

/* Keep the title readable where the photo starts. */
.pk-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #fbfcfe 0%, #fbfcfe 40%, rgb(251 252 254 / 0.75) 52%, rgb(251 252 254 / 0) 72%);
    pointer-events: none;
}

.pk-hero > div {
    z-index: 1;
}

.pk-new {
    box-shadow: 0 10px 24px -12px rgb(2 12 27 / 0.6);
}

@media (min-width: 1280px) {
    .pk-hero-img {
        width: 58%;
    }
}

@media (max-width: 767px) {
    .pk-hero-img {
        width: 100%;
        opacity: 0.35;
        mask-image: linear-gradient(to bottom, #000, transparent);
    }

    .pk-hero::after {
        background: linear-gradient(180deg, rgb(251 252 254 / 0.55), #fbfcfe 80%);
    }
}

.pk-search {
    height: 3rem;
    border-radius: 0.9rem;
    box-shadow: var(--shadow-card);
}

.pk-filter-btn {
    position: relative;
    display: inline-flex;
    height: 3rem;
    min-width: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 1.1rem;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-ink);
    box-shadow: var(--shadow-card);
    transition: border-color 180ms ease, background-color 180ms ease;

    &:hover {
        border-color: #cbd3df;
    }

    &.is-active {
        border-color: color-mix(in oklab, var(--admin-primary) 45%, white);
        background: color-mix(in oklab, var(--admin-primary) 6%, white);
    }
}

.pk-filter-count {
    display: flex;
    min-width: 1.25rem;
    height: 1.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--admin-primary);
    padding: 0 0.3rem;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}

.pk-group-icon {
    display: flex;
    width: 1.75rem;
    height: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: color-mix(in oklab, var(--tone) 16%, white);
    color: color-mix(in oklab, var(--tone) 55%, black);
}

.pk-chip-choice {
    display: inline-flex;
    min-height: 2.5rem;
    cursor: pointer;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 0.95rem;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--color-ink-soft);
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;

    &:hover {
        border-color: #cbd3df;
        color: var(--color-ink);
    }

    &:has(:focus-visible) {
        outline: 2px solid var(--admin-primary);
        outline-offset: 2px;
    }

    &.is-on {
        border-color: color-mix(in oklab, var(--tone) 65%, black 5%);
        background: color-mix(in oklab, var(--tone) 14%, white);
        color: color-mix(in oklab, var(--tone) 45%, black);
    }
}

/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.pk-list {
    container: pk-list / inline-size;
}

.pk-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}

@container pk-list (min-width: 860px) {
    .pk-grid.is-list {
        grid-template-columns: minmax(0, 1fr);
    }
}

.pk-view {
    display: flex;
    width: 2.5rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    color: var(--color-ink-soft);
    transition: background-color 180ms ease, color 180ms ease;

    &:hover {
        color: var(--color-ink);
    }

    &.is-on {
        background: var(--admin-primary);
        color: #fff;
    }
}
</style>
