<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue';
import {
    Ban,
    BadgeCheck,
    CalendarCheck,
    CalendarDays,
    ChevronRight,
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
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TabSwitcher, { type TabOption } from '@admin/components/ui/TabSwitcher.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useListQuery } from '@admin/composables/useListQuery';
import { http, type Paginated } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import type { Translations } from '@admin/types';
import { money, statusTone, type BookingRow, type BookingStatus } from './types';

/** The list rows don't carry an email today; shown only if the API adds it. */
type Row = BookingRow & { customer_email?: string | null };
type BookingsResponse = Paginated<Row> & { counts: Partial<Record<BookingStatus, number>>; statuses: Record<BookingStatus, string> };

const app = useAppStore();

const list = useListQuery<Row, BookingsResponse>('/bookings', { status: '', package_id: '' });
const packages = ref<{ id: number; name: Translations }[]>([]);

const current = ref<Row | null>(null);
const drawerOpen = ref(false);

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

const statusOptions = computed<TabOption<string>[]>(() => {
    const all = Object.values(counts.value).reduce<number>((sum, n) => sum + (n ?? 0), 0);
    return [
        { value: '', label: 'All', count: list.response.value ? all : null, icon: LayoutList, tone: '#818cf8' },
        ...(Object.keys(statuses.value) as BookingStatus[]).map((key) => ({
            value: key,
            label: statuses.value[key],
            count: counts.value[key] ?? 0,
            icon: statusMeta[key]?.icon,
            tone: statusMeta[key]?.tone,
        })),
    ];
});

const statusModel = computed({
    get: () => String(list.filters.status),
    set: (value: string) => (list.filters.status = value),
});

const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'booking' : 'bookings'}` : ''));
const hasFilters = computed(() => Boolean(list.filters.search) || list.active.value > 0);

const whatsapp = computed(() => {
    const digits = (current.value?.customer_phone ?? '').replace(/\D/g, '');
    return digits ? `https://wa.me/${digits.startsWith('0') ? `88${digits}` : digits}` : null;
});

function open(row: Row): void {
    current.value = row;
    drawerOpen.value = true;
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

function exportCsv(): void {
    const params = new URLSearchParams(
        Object.entries({ status: list.filters.status, package_id: list.filters.package_id, search: list.filters.search })
            .filter(([, v]) => v !== '')
            .map(([k, v]) => [k, String(v)]),
    );
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
        <PageHeader title="Bookings" description="Bookings from the website, their payments and documents.">
            <template #actions>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </PageHeader>

        <TabSwitcher v-model="statusModel" :options="statusOptions" label="Show bookings" class="mb-4" />

        <FilterBar v-model:search="list.filters.search as string" placeholder="Reference, name, phone or email" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.package_id" class="control md:w-56" aria-label="Filter by package">
                <option value="">All packages</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="String(pkg.id)">{{ pickText(pkg.name, app.defaultLocale) }}</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="CalendarCheck"
                :title="hasFilters ? 'No bookings match' : 'No bookings yet'"
                :description="hasFilters ? 'Try another search or clear the filters.' : 'Bookings made on the package pages appear here.'"
            >
                <AppButton v-if="hasFilters" variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
            <table v-else class="dt" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Total</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="dt-trigger"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in list.rows.value" :key="row.id" class="dt-row" @click="open(row)">
                        <td>
                            <p class="flex items-center gap-2 font-semibold text-ink">
                                <span v-if="row.is_unread" class="size-2 shrink-0 rounded-full bg-brand" aria-label="New" />
                                {{ row.reference }}
                            </p>
                            <p class="mt-0.5 text-[12.5px] text-ink-muted">{{ formatDate(row.created_at, true) }}</p>
                        </td>
                        <td data-label="Customer">
                            <p class="font-medium text-ink">{{ row.customer_name }}</p>
                            <p class="text-[12px] text-ink-muted">{{ row.customer_phone }}</p>
                        </td>
                        <td data-label="Package" class="dt-hide-sm">
                            <p class="text-ink">{{ row.package ?? '—' }}</p>
                            <p class="text-[12px] text-ink-muted">{{ row.option }} · {{ row.travelers }} traveler{{ row.travelers > 1 ? 's' : '' }}</p>
                        </td>
                        <td data-label="Total" class="font-semibold whitespace-nowrap text-ink">{{ money(row.total) }}</td>
                        <td data-label="Balance" class="whitespace-nowrap" :class="row.balance > 0 ? 'text-ink' : 'text-emerald-700'">{{ row.balance > 0 ? money(row.balance) : 'Paid' }}</td>
                        <td data-label="Status"><AppBadge :tone="statusTone[row.status]">{{ statusLabel(row.status) }}</AppBadge></td>
                        <td class="dt-trigger">
                            <button type="button" class="dt-open" :aria-label="`Open booking ${row.reference}`" @click.stop="open(row)"><ChevronRight class="size-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="bookings" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- Preview and actions -->
        <AppDrawer
            v-model:open="drawerOpen"
            :title="current ? current.reference : ''"
            :description="current ? `${current.customer_name} · ${formatDate(current.created_at, true)}` : ''"
            :icon="CalendarCheck"
        >
            <template v-if="current">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="statusTone[current.status]">{{ statusLabel(current.status) }}</AppBadge>
                    <AppBadge v-if="current.is_unread" tone="brand">New</AppBadge>
                    <AppBadge v-if="current.balance <= 0" tone="success">Paid in full</AppBadge>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Hash class="size-4 text-indigo-500" />Reference</dt>
                    <dd>{{ current.reference }}</dd>
                    <dt class="flex items-center gap-2"><Users class="size-4 text-sky-600" />Customer</dt>
                    <dd>{{ current.customer_name }}<br /><span class="text-ink-muted">{{ current.customer_phone }}</span></dd>
                    <dt class="flex items-center gap-2"><PackageIcon class="size-4 text-violet-600" />Package</dt>
                    <dd>{{ current.package ?? '—' }}<template v-if="current.option"><br /><span class="text-ink-muted">{{ current.option }}</span></template></dd>
                    <dt class="flex items-center gap-2"><Users class="size-4 text-teal-600" />Travellers</dt>
                    <dd>{{ current.travelers }}</dd>
                    <dt class="flex items-center gap-2"><Wallet class="size-4 text-emerald-600" />Total</dt>
                    <dd>{{ money(current.total) }}</dd>
                    <dt>Paid</dt>
                    <dd class="text-emerald-700">{{ money(current.paid_amount) }}</dd>
                    <dt class="flex items-center gap-2"><PiggyBank class="size-4 text-orange-500" />Balance</dt>
                    <dd :class="current.balance > 0 ? 'font-semibold' : 'text-emerald-700'">{{ current.balance > 0 ? money(current.balance) : 'Paid' }}</dd>
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
