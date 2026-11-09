<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChartNoAxesColumn, Download } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TabSwitcher from '@admin/components/ui/TabSwitcher.vue';
import VisitLog from './VisitLog.vue';
import VisitPages from './VisitPages.vue';
import VisitSources from './VisitSources.vue';
import { DRILL_KEYS, type DrillKey, type VisitFilters, type VisitTotals } from './types';

const route = useRoute();
const router = useRouter();

const day = (offset: number): string => {
    const d = new Date();
    d.setDate(d.getDate() + offset);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const fromUrl = (key: string, fallback: string): string => (typeof route.query[key] === 'string' && route.query[key] !== '' ? (route.query[key] as string) : fallback);

// One state object, kept in the address bar; the Log tab shares it and adds paging.
const filters = reactive(
    Object.fromEntries([
        ['view', fromUrl('view', 'sources')],
        ['date_from', fromUrl('date_from', day(-6))],
        ['date_to', fromUrl('date_to', day(0))],
        ['include_bots', fromUrl('include_bots', '')],
        // Drill-downs belong to the log tab; on the others they are ignored, even when typed into the address.
        ...DRILL_KEYS.map((k) => [k, route.query.view === 'log' ? fromUrl(k, '') : '']),
    ]),
) as VisitFilters;

const views = [
    { value: 'sources', label: 'Sources' },
    { value: 'pages', label: 'Pages' },
    { value: 'log', label: 'Visit log' },
] as const;

/** What the address bar, the export and the API see: drill-downs only for the log tab. */
function query(view: VisitFilters['view'] = filters.view): Record<string, string> {
    return Object.fromEntries(Object.entries({ ...filters, view }).filter(([k, v]) => v !== '' && (view === 'log' || !(DRILL_KEYS as readonly string[]).includes(k))));
}

/** The Log tab writes its own address (it adds paging), so only the other tabs do it here. */
function syncUrl(): void {
    if (filters.view !== 'log') router.replace({ query: query() });
}

const view = computed({
    get: () => filters.view,
    set: async (value: VisitFilters['view']) => {
        await router.replace({ query: query(value) });
        filters.view = value;
        if (value !== 'log') {
            // Only once the log has unmounted: clearing while it is mounted makes it reload and rewrite the address.
            await nextTick();
            for (const k of DRILL_KEYS) filters[k] = '';
        }
    },
});
const dateFrom = computed({
    get: () => filters.date_from,
    set: (v: string | null) => {
        filters.date_from = v ?? day(-6);
        syncUrl();
    },
});
const dateTo = computed({
    get: () => filters.date_to,
    set: (v: string | null) => {
        filters.date_to = v ?? day(0);
        syncUrl();
    },
});
const crawlers = computed({
    get: () => filters.include_bots === '1',
    set: (v: boolean | 'indeterminate') => {
        filters.include_bots = v === true ? '1' : '';
        syncUrl();
    },
});

/** A row of Sources or Pages was clicked: open the log filtered to it. */
function drill(only: Partial<Record<DrillKey, string>>): void {
    for (const k of DRILL_KEYS) filters[k] = only[k] ?? '';
    view.value = 'log';
}

syncUrl(); // drop stray drill-downs from a hand-edited address

const totals = ref<VisitTotals | null>(null);
const unavailable = ref<string | null>(null);
const retry = ref(0);

function exportCsv(): void {
    const { view: _view, ...rest } = query();
    window.location.href = `/api/admin/visits/export?${new URLSearchParams(rest)}`;
}

function tryAgain(): void {
    unavailable.value = null;
    retry.value++;
}

const strip = computed(
    () =>
        [
            ['Views', totals.value?.views],
            ['Visitors', totals.value?.visitors],
            ['Sessions', totals.value?.sessions],
            ['Bookings', totals.value?.bookings],
            ['Enquiries', totals.value?.enquiries],
        ] as const,
);
const tab = computed(() => ({ sources: VisitSources, pages: VisitPages, log: VisitLog })[filters.view]);
</script>

<template>
    <div>
        <PageHeader title="Page visits" description="Who is visiting the public site, where they came from and what they did.">
            <template #actions>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 basis-36">
                <label for="v-from" class="label">From</label>
                <AppDatePicker id="v-from" v-model="dateFrom" mode="date" :clearable="false" :max-date="filters.date_to" />
            </div>
            <div class="min-w-0 flex-1 basis-36">
                <label for="v-to" class="label">To</label>
                <AppDatePicker id="v-to" v-model="dateTo" mode="date" :clearable="false" :min-date="filters.date_from" :max-date="day(0)" />
            </div>
            <div class="pb-2.5"><AppCheckbox v-model="crawlers" label="Include crawlers" /></div>
        </div>

        <EmptyState v-if="unavailable" :icon="ChartNoAxesColumn" title="Visits are unavailable" :description="unavailable">
            <AppButton variant="secondary" @click="tryAgain">Try again</AppButton>
        </EmptyState>

        <template v-else>
            <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="[label, value] in strip" :key="label" class="card px-4 py-3">
                    <dt class="text-[12px] font-semibold text-ink-muted">{{ label }}</dt>
                    <dd class="text-[20px] font-bold text-ink">{{ value ?? '–' }}</dd>
                </div>
            </dl>
            <p v-if="totals && !totals.storage.separate" class="mb-4 text-[13px] text-ink-muted">Visit logs are stored in the main database.</p>

            <div class="mb-4"><TabSwitcher v-model="view" :options="[...views]" label="Show" /></div>

            <component :is="tab" class="overflow-x-clip" :key="`${filters.view}-${retry}`" :filters="filters" @totals="totals = $event" @unavailable="unavailable = $event" @drill="drill" />
        </template>
    </div>
</template>
