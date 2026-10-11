<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { CalendarCheck, Download, Search } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useToastStore } from '@admin/stores/toast';
import { money, statusTone, type BookingRow, type BookingStatus } from './types';

const toast = useToastStore();

const rows = ref<BookingRow[]>([]);
const meta = ref<Paginated<BookingRow>['meta'] | null>(null);
const counts = ref<Partial<Record<BookingStatus, number>>>({});
const statuses = ref<Record<BookingStatus, string>>({} as Record<BookingStatus, string>);
const loading = ref(true);
const filters = reactive({ status: '' as BookingStatus | '', search: '', page: 1 });

async function load(): Promise<void> {
    loading.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
        const { data } = await http.get<Paginated<BookingRow> & { counts: typeof counts.value; statuses: typeof statuses.value }>('/bookings', { params });
        rows.value = data.data;
        meta.value = data.meta;
        counts.value = data.counts;
        statuses.value = data.statuses;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

const reload = useDebounceFn(() => {
    filters.page = 1;
    load();
}, 300);

watch(() => filters.search, reload);
watch(() => filters.status, () => {
    filters.page = 1;
    load();
});

function goTo(page: number): void {
    filters.page = page;
    load();
}

function exportCsv(): void {
    const params = new URLSearchParams(Object.entries(filters).filter(([k, v]) => v !== '' && k !== 'page') as [string, string][]);
    window.location.href = `/api/admin/bookings/export?${params}`;
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Bookings" description="Bookings from the website, their payments and documents.">
            <template #actions>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Filter by status">
            <button type="button" role="tab" :aria-selected="filters.status === ''" class="shrink-0 rounded-full px-3.5 py-1.5 text-[13px] font-semibold" :class="filters.status === '' ? 'bg-brand text-white' : 'bg-white text-ink-soft ring-1 ring-line hover:text-ink'" @click="filters.status = ''">All</button>
            <button v-for="(label, key) in statuses" :key="key" type="button" role="tab" :aria-selected="filters.status === key"
                    class="shrink-0 rounded-full px-3.5 py-1.5 text-[13px] font-semibold" :class="filters.status === key ? 'bg-brand text-white' : 'bg-white text-ink-soft ring-1 ring-line hover:text-ink'" @click="filters.status = key">
                {{ label }} <span class="ml-1 opacity-70">{{ counts[key] ?? 0 }}</span>
            </button>
        </div>
        <div class="relative mb-4 sm:w-80">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
            <input v-model="filters.search" type="search" class="control pl-9" placeholder="Reference, name, phone or email" aria-label="Search bookings" />
        </div>

        <div v-if="loading && !rows.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!rows.length" class="card">
            <EmptyState :icon="CalendarCheck" :title="filters.search || filters.status ? 'No matching bookings' : 'No bookings yet'" description="Bookings made on the package pages appear here." />
        </div>
        <template v-else>
            <div class="card overflow-x-auto" :class="{ 'opacity-60': loading }">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                            <th class="px-4 py-3">Booking</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-canvas/60">
                            <td class="px-4 py-3">
                                <RouterLink :to="{ name: 'booking', params: { id: row.id } }" class="flex items-center gap-2 font-semibold text-ink hover:text-brand">
                                    <span v-if="row.is_unread" class="size-2 rounded-full bg-brand" aria-label="New" />
                                    {{ row.reference }}
                                </RouterLink>
                                <p class="text-[12px] text-ink-muted">{{ formatDate(row.created_at, true) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-ink">{{ row.customer_name }}</p>
                                <p class="text-[12px] text-ink-muted">{{ row.customer_phone }}</p>
                            </td>
                            <td class="max-w-64 px-4 py-3">
                                <p class="truncate text-ink">{{ row.package }}</p>
                                <p class="truncate text-[12px] text-ink-muted">{{ row.option }} · {{ row.travelers }} traveler{{ row.travelers > 1 ? 's' : '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap text-ink">{{ money(row.total) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" :class="row.balance > 0 ? 'text-ink' : 'text-emerald-700'">{{ row.balance > 0 ? money(row.balance) : 'Paid' }}</td>
                            <td class="px-4 py-3"><AppBadge :tone="statusTone[row.status]">{{ statuses[row.status] ?? row.status }}</AppBadge></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-if="meta" :meta="meta" @change="goTo" />
        </template>
    </div>
</template>
