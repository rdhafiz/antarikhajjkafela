<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue';
import {
    ArrowDownUp,
    Ban,
    BadgeCheck,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    CircleCheckBig,
    Clock,
    Download,
    ExternalLink,
    Hash,
    LayoutList,
    Mail,
    MessageCircle,
    Package as PackageIcon,
    Phone,
    PiggyBank,
    Users,
    Wallet,
} from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import BookingListItem from './BookingListItem.vue';
import headerPhoto from '@admin/assets/bookings-header.webp';
import { pickText } from '@admin/pages/content/builder/types';
import { useListQuery } from '@admin/composables/useListQuery';
import { http, type Paginated } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import type { Translations } from '@admin/types';
import { money, statusTone, type BookingRow, type BookingStatus } from './types';

type BookingsResponse = Paginated<BookingRow> & { counts: Partial<Record<BookingStatus, number>>; statuses: Record<BookingStatus, string> };

const app = useAppStore();

const list = useListQuery<BookingRow, BookingsResponse>('/bookings', { status: '', package_id: '', payment: '', from: '', to: '', sort: '' });
const packages = ref<{ id: number; name: Translations }[]>([]);

const current = ref<BookingRow | null>(null);
const drawerOpen = ref(false);
const filtersOpen = ref(false);

const statusMeta: Record<BookingStatus, { icon: Component; tone: string }> = {
    pending: { icon: Clock, tone: '#fbbf24' },
    confirmed: { icon: BadgeCheck, tone: '#60a5fa' },
    advance_paid: { icon: PiggyBank, tone: '#fb923c' },
    fully_paid: { icon: Wallet, tone: '#34d399' },
    completed: { icon: CircleCheckBig, tone: '#2dd4bf' },
    cancelled: { icon: Ban, tone: '#fb7185' },
};

const statuses = computed(() => list.response.value?.statuses ?? ({} as Record<BookingStatus, string>));
const counts = computed(() => list.response.value?.counts ?? {});
const statusLabel = (status: BookingStatus) => statuses.value[status] ?? status;
const total = computed(() => list.meta.value?.total ?? 0);

/** Every filter in the drawer ('' = any). Status shows how many bookings each one has. */
const filterGroups = computed<FilterGroup[]>(() => {
    const all = Object.values(counts.value).reduce<number>((sum, n) => sum + (n ?? 0), 0);
    return [
        {
            key: 'status',
            label: 'Status',
            icon: LayoutList,
            tone: '#818cf8',
            options: [
                { value: '', label: 'All', count: list.response.value ? all : null },
                ...(Object.keys(statuses.value) as BookingStatus[]).map((key) => ({ value: key, label: statuses.value[key], count: counts.value[key] ?? 0 })),
            ],
        },
        {
            key: 'package_id',
            label: 'Package',
            icon: PackageIcon,
            tone: '#a78bfa',
            options: [{ value: '', label: 'All packages' }, ...packages.value.map((p) => ({ value: String(p.id), label: pickText(p.name, app.defaultLocale) }))],
        },
        {
            key: 'payment',
            label: 'Payment',
            icon: Wallet,
            tone: '#34d399',
            options: [
                { value: '', label: 'Any' },
                { value: 'unpaid', label: 'Nothing paid' },
                { value: 'partial', label: 'Part paid' },
                { value: 'paid', label: 'Paid in full' },
            ],
        },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Newest first' },
                { value: 'oldest', label: 'Oldest first' },
                { value: 'total_desc', label: 'Highest total' },
                { value: 'balance_desc', label: 'Most due' },
            ],
        },
    ];
});

const activeFilters = computed(
    () => (['status', 'package_id', 'payment'] as const).filter((key) => list.filters[key] !== '').length + (list.filters.from || list.filters.to ? 1 : 0),
);
const filtered = computed(() => Boolean(list.filters.search) || activeFilters.value > 0);

// The date pickers give null when cleared; the list keeps '' for "any".
const fromDate = computed({ get: () => (list.filters.from as string) || null, set: (v: string | null) => (list.filters.from = v ?? '') });
const toDate = computed({ get: () => (list.filters.to as string) || null, set: (v: string | null) => (list.filters.to = v ?? '') });

const paidPercent = computed(() => (current.value && current.value.total > 0 ? Math.min(100, Math.round((current.value.paid_amount / current.value.total) * 100)) : 0));
const whatsapp = computed(() => {
    const digits = (current.value?.customer_phone ?? '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits.startsWith('0') ? `88${digits}` : digits}` : null;
});

function open(row: BookingRow): void {
    current.value = row;
    drawerOpen.value = true;
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

function exportCsv(): void {
    const keys = ['status', 'package_id', 'payment', 'from', 'to', 'search'] as const;
    const params = new URLSearchParams(keys.filter((k) => list.filters[k] !== '').map((k) => [k, String(list.filters[k])]));
    window.location.href = `/api/admin/bookings/export?${params}`;
}

onMounted(async () => {
    list.load();
    try {
        packages.value = (await http.get<Paginated<{ id: number; name: Translations }>>('/packages', { params: { per_page: 100 } })).data.data;
    } catch {
        // The package filter is optional.
    }
});
</script>

<template>
    <div>
        <!-- Header with a Madinah photo -->
        <ListHero title="Bookings" description="Bookings from the website, their payments and documents." :icon="CalendarCheck" tone="#34d399" :photo="headerPhoto" photo-position="50% 45%">
            <template #actions>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </ListHero>

        <!-- Search and filters in one row -->
        <ListToolbar v-model:search="list.filters.search as string" placeholder="Reference, name, phone or email" :active="activeFilters" @filters="filtersOpen = true" />

        <!-- Bookings -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4" aria-busy="true" aria-label="Loading bookings">
            <div v-for="n in 3" :key="n" class="card h-[260px] animate-pulse sm:h-[124px]" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="CalendarCheck"
                :title="filtered ? 'No bookings match' : 'No bookings yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Bookings made on the package pages appear here.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
        </div>
        <div v-else class="bk-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="grid gap-4">
                <BookingListItem
                    v-for="(row, i) in list.rows.value"
                    :key="row.id"
                    :row="row"
                    :status-label="statusLabel(row.status)"
                    :status="statusMeta[row.status]"
                    :index="i"
                    @open="open(row)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="bookings" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['booking', 'bookings']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="list.clear()"
        >
            <fieldset class="min-w-0">
                <legend class="mb-2.5 flex items-center gap-2 text-[13.5px] font-bold text-ink">
                    <span class="fd-icon" style="--tone: #38bdf8"><CalendarRange class="size-4" aria-hidden="true" /></span>
                    Booked between
                </legend>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="bk-from" class="mb-1 block text-[12.5px] font-semibold text-ink-soft">From</label>
                        <AppDatePicker id="bk-from" v-model="fromDate" placeholder="Any day" :max-date="toDate" />
                    </div>
                    <div>
                        <label for="bk-to" class="mb-1 block text-[12.5px] font-semibold text-ink-soft">To</label>
                        <AppDatePicker id="bk-to" v-model="toDate" placeholder="Any day" :min-date="fromDate" />
                    </div>
                </div>
            </fieldset>
        </FilterDrawer>

        <!-- Preview and actions -->
        <AppDrawer
            v-model:open="drawerOpen"
            :title="current ? current.reference : ''"
            :description="current ? `${current.customer_name} · ${formatDate(current.created_at, true)}` : ''"
            :icon="CalendarCheck"
        >
            <template v-if="current">
                <img v-if="current.package_photo" :src="current.package_photo" alt="" class="mb-5 aspect-[16/9] w-full rounded-2xl object-cover" />
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="statusTone[current.status]">{{ statusLabel(current.status) }}</AppBadge>
                    <AppBadge v-if="current.is_unread" tone="brand">New</AppBadge>
                    <AppBadge v-if="current.overpaid > 0" tone="warning">{{ money(current.overpaid) }} overpaid</AppBadge>
                    <AppBadge v-else-if="current.total > 0 && current.balance <= 0" tone="success">Paid in full</AppBadge>
                </div>

                <div class="mt-5 rounded-2xl border border-line p-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-[12px] font-semibold tracking-[0.08em] text-ink-soft uppercase">Paid</span>
                        <span class="text-[13px] font-semibold text-ink tabular-nums">{{ money(current.paid_amount) }} of {{ money(current.total) }}</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-emerald-100" role="progressbar" :aria-valuenow="paidPercent" aria-valuemin="0" aria-valuemax="100" aria-label="Paid">
                        <span class="block h-full rounded-full bg-emerald-600" :style="{ width: `${paidPercent}%` }" />
                    </div>
                    <p class="mt-2 text-[13px] text-ink-soft">{{ current.balance > 0 ? `${money(current.balance)} still due` : 'Nothing left to pay' }}</p>
                </div>

                <dl class="dl mt-4 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Hash class="size-4 text-indigo-500" />Reference</dt>
                    <dd>{{ current.reference }}</dd>
                    <dt class="flex items-center gap-2"><Users class="size-4 text-sky-600" />Customer</dt>
                    <dd>{{ current.customer_name }}<br /><span class="text-ink-muted">{{ current.customer_phone }}</span></dd>
                    <dt class="flex items-center gap-2"><PackageIcon class="size-4 text-violet-600" />Package</dt>
                    <dd>{{ current.package ?? '—' }}<template v-if="current.option"><br /><span class="text-ink-muted">{{ current.option }}</span></template></dd>
                    <dt class="flex items-center gap-2"><Users class="size-4 text-teal-600" />Travellers</dt>
                    <dd>{{ current.travelers }}<template v-if="current.travel_date"><br /><span class="text-ink-muted">Wants to travel: {{ current.travel_date }}</span></template></dd>
                    <dt class="flex items-center gap-2"><CalendarDays class="size-4 text-amber-600" />Booked</dt>
                    <dd>{{ formatDate(current.created_at, true) }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'booking', params: { id: current.id } }" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #818cf8"><ExternalLink class="size-4" /></span>Open booking
                    </RouterLink>
                    <a v-if="current.customer_phone" :href="`tel:${current.customer_phone}`" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><Phone class="size-4" /></span>Call {{ current.customer_phone }}
                    </a>
                    <a v-if="whatsapp" :href="whatsapp" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #34d399"><MessageCircle class="size-4" /></span>WhatsApp the customer
                    </a>
                    <a v-if="current.customer_email" :href="`mailto:${current.customer_email}`" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #f472b6"><Mail class="size-4" /></span>Email {{ current.customer_email }}
                    </a>
                </div>
            </template>
        </AppDrawer>
    </div>
</template>

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.bk-list {
    container: bk-list / inline-size;
}
</style>
