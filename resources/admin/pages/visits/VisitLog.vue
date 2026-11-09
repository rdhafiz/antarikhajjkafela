<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { ChevronRight, Globe, Languages, Monitor, Network, Route, ScrollText, Share2, Smartphone, User, X } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import { useListQuery } from '@admin/composables/useListQuery';
import { formatDate } from '@admin/lib/format';
import { http, type Paginated } from '@admin/lib/http';
import { deviceLabel, DRILL_KEYS, subjectLabel, type DrillKey, type VisitFilterOptions, type VisitFilters, type VisitRow, type VisitTotals } from './types';

const props = defineProps<{ filters: VisitFilters }>();
const emit = defineEmits<{ totals: [VisitTotals]; unavailable: [string]; drill: [Partial<Record<DrillKey, string>>] }>();

type LogResponse = Paginated<VisitRow> & { totals: VisitTotals };

const SHARED = ['date_from', 'date_to', 'include_bots', ...DRILL_KEYS] as const;

const defaults: Record<string, string> = { view: 'log', date_from: props.filters.date_from, date_to: props.filters.date_to, include_bots: '' };
for (const k of DRILL_KEYS) defaults[k] = '';

const list = useListQuery<VisitRow, LogResponse>('/visits', defaults, { perPage: 20, onLoaded: (r) => emit('totals', r.totals) });

// The page owns the dates and the crawler switch, the log owns the drill-downs; each mirrors the other.
watch(
    () => SHARED.map((k) => props.filters[k]),
    () => {
        for (const k of SHARED) if (list.filters[k] !== props.filters[k]) list.filters[k] = props.filters[k];
    },
);
watch(
    () => DRILL_KEYS.map((k) => list.filters[k]),
    () => {
        for (const k of DRILL_KEYS) if (props.filters[k] !== list.filters[k]) props.filters[k] = list.filters[k] as string;
    },
);

const options = ref<VisitFilterOptions | null>(null);
onMounted(async () => {
    for (const k of SHARED) if (list.filters[k] !== props.filters[k]) list.filters[k] = props.filters[k];
    list.load();
    try {
        options.value = (await http.get<{ data: VisitFilterOptions }>('/visits/filters')).data.data;
    } catch {
        // The filter chips just stay empty.
    }
});

const filtersOpen = ref(false);
const failed = computed(() => !list.loading.value && !list.response.value);
watch(failed, (v) => v && emit('unavailable', 'The visit log could not be loaded. Try again in a moment.'));

// A page (type + id) is one chip: removing half of it would leave an id matching any subject.
const chips = computed(() => {
    const f = list.filters;
    const page = f.subject_type !== '' && f.subject_id !== '';
    const out: { key: string; text: string; clear: DrillKey[] }[] = [];
    if (page) {
        const row = list.rows.value.find((r) => r.subject_type === f.subject_type && String(r.subject_id) === f.subject_id);
        const type = subjectLabel(String(f.subject_type));
        out.push({ key: 'page', text: `Page: ${row?.title ?? `Page #${f.subject_id}`} (${type})`, clear: ['subject_type', 'subject_id'] });
    }
    for (const k of DRILL_KEYS) {
        if (f[k] === '' || (page && (k === 'subject_type' || k === 'subject_id'))) continue;
        out.push({ key: k, text: chipText(k, String(f[k])), clear: [k] });
    }
    return out;
});
const labels: Record<DrillKey, string> = { channel: 'Channel', source: 'Source', in_app: 'App', subject_type: 'Page type', subject_id: 'Page', device_type: 'Device', browser: 'Browser', locale: 'Language', visitor_id: 'Visitor', session_id: 'Session', ip: 'IP' };
function chipText(key: DrillKey, value: string): string {
    if (key === 'subject_type') return `${labels[key]}: ${subjectLabel(value)}`;
    if (key === 'device_type') return `${labels[key]}: ${deviceLabel(value)}`;
    return `${labels[key]}: ${value.length > 12 ? `${value.slice(0, 8)}…` : value}`;
}
const filterCount = computed(() => chips.value.length);

const ipText = ref((list.filters.ip as string) ?? '');
const applyIp = useDebounceFn((v: string) => (list.filters.ip = v.trim()), 400);
watch(() => list.filters.ip, (v) => { if (v !== ipText.value.trim()) ipText.value = (v as string) ?? ''; });
const ipSearch = computed({ get: () => ipText.value, set: (v: string) => { ipText.value = v; applyIp(v); } });

const groups = computed<FilterGroup[]>(() => {
    const o = options.value;
    const any = { value: '', label: 'Any' };
    const plain = (values: string[] = [], label: (v: string) => string = (v) => v) => values.map((v) => ({ value: v, label: label(v) }));
    return [
        { key: 'device_type', label: 'Device', icon: Smartphone, tone: '#38bdf8', options: [any, ...plain(o?.devices, deviceLabel)] },
        { key: 'browser', label: 'Browser', icon: Globe, tone: '#a78bfa', options: [any, ...plain(o?.browsers)] },
        { key: 'channel', label: 'Channel', icon: Share2, tone: '#e879f9', options: [any, ...(o?.channels ?? [])] },
        { key: 'subject_type', label: 'Page type', icon: ScrollText, tone: '#fbbf24', options: [any, ...(o?.subjects ?? [])] },
        { key: 'locale', label: 'Language', icon: Languages, tone: '#34d399', options: [any, { value: 'en', label: 'English' }, { value: 'bn', label: 'Bangla' }] },
    ];
});

function clearChip(keys: DrillKey[]): void {
    for (const k of keys) list.filters[k] = '';
}
function clearFilters(): void {
    for (const k of DRILL_KEYS) list.filters[k] = '';
}

// Detail drawer
const current = ref<VisitRow | null>(null);
const detailOpen = ref(false);
function open(row: VisitRow): void {
    current.value = row;
    detailOpen.value = true;
}
function narrow(key: 'visitor_id' | 'session_id' | 'ip', value: string | null): void {
    if (!value) return;
    // These replace every other drill-down; dates and the crawler switch stay.
    for (const k of DRILL_KEYS) list.filters[k] = '';
    list.filters[key] = value;
    detailOpen.value = false;
}

const details = computed<[string, string][]>(() => {
    const v = current.value;
    if (!v) return [];
    const loc = v.location;
    const rows: [string, string | null | undefined][] = [
        ['Time', formatDate(v.visited_at, true)],
        ['Page', v.title ?? v.path],
        ['Page type', subjectLabel(v.subject_type)],
        ['Address', v.url],
        ['Came from', [v.channel_label ?? v.channel, v.source].filter(Boolean).join(' · ')],
        ['In-app browser', v.in_app],
        ['Referrer', v.referrer],
        ['Campaign', [v.utm_source, v.utm_medium, v.utm_campaign].filter(Boolean).join(' / ')],
        ['Location', loc ? [loc.label, loc.isp].filter(Boolean).join(' · ') : null],
        ['IP address', v.ip],
        ['Device', v.is_bot ? 'Crawler' : [deviceLabel(v.device_type), v.platform, v.browser].filter(Boolean).join(' · ')],
        ['Language', v.locale?.toUpperCase()],
        ['Visitor', v.visitor_id],
        ['Session', v.session_id],
        ['Bookings in this session', v.session_converted.bookings ? String(v.session_converted.bookings) : null],
        ['Enquiries in this session', v.session_converted.enquiries ? String(v.session_converted.enquiries) : null],
    ];
    return rows.filter((r): r is [string, string] => Boolean(r[1]));
});
</script>

<template>
    <div>
        <ListToolbar v-model:search="ipSearch" placeholder="Filter by IP address" :active="filterCount" @filters="filtersOpen = true" />

        <ul v-if="chips.length" class="mb-4 flex flex-wrap gap-2" aria-label="Active filters">
            <li v-for="c in chips" :key="c.key">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full bg-brand-soft px-3 py-1 text-[13px] font-semibold text-brand" :aria-label="`Remove filter ${c.text}`" @click="clearChip(c.clear)">
                    {{ c.text }}<X class="size-3.5" />
                </button>
            </li>
        </ul>

        <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16"><AppSpinner /></div>
        <EmptyState v-else-if="failed" :icon="ScrollText" title="The visit log could not be loaded" />
        <EmptyState v-else-if="!list.rows.value.length" :icon="ScrollText" title="No visits match" :description="filterCount ? 'Try removing a filter or widening the dates.' : 'Nothing was recorded in this period.'">
            <AppButton v-if="filterCount" variant="secondary" @click="clearFilters">Clear filters</AppButton>
        </EmptyState>
        <template v-else>
            <section class="card overflow-hidden" aria-label="Visits">
                <table class="dt">
                    <thead>
                        <tr><th>Page</th><th>Location</th><th>Device</th><th>Came from</th><th>Result</th><th /></tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in list.rows.value" :key="r.id" class="dt-row" @click="open(r)">
                            <td class="min-w-0">
                                <span class="font-semibold [overflow-wrap:anywhere] text-ink">{{ r.title ?? r.path }}</span>
                                <span class="block text-[12px] text-ink-muted">{{ formatDate(r.visited_at, true) }}</span>
                            </td>
                            <td data-label="Location" class="dt-hide-sm">{{ r.location?.label ?? '–' }}</td>
                            <td data-label="Device">
                                <AppBadge v-if="r.is_bot">Crawler</AppBadge>
                                <template v-else>{{ [deviceLabel(r.device_type), r.browser].filter(Boolean).join(' · ') || '–' }}</template>
                            </td>
                            <td data-label="Came from">
                                {{ r.channel_label ?? r.channel }}<template v-if="r.source"> · {{ r.source }}</template>
                                <AppBadge v-if="r.in_app" class="ml-1">{{ r.in_app }}</AppBadge>
                            </td>
                            <td data-label="Result">
                                <AppBadge v-if="r.converted.bookings" tone="success">Booked</AppBadge>
                                <AppBadge v-if="r.converted.enquiries" tone="warning">Enquired</AppBadge>
                                <template v-if="!r.converted.bookings && !r.converted.enquiries">–</template>
                            </td>
                            <td class="dt-trigger"><button class="dt-open" :aria-label="`Open visit to ${r.title ?? r.path}`" @click.stop="open(r)"><ChevronRight class="size-4" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="visits" @change="list.goTo" @per-page="list.setPerPage" />
        </template>

        <FilterDrawer v-model:open="filtersOpen" :filters="list.filters" :groups="groups" :total="list.meta.value?.total ?? 0" :noun="['visit', 'visits']" :loading="list.loading.value" :can-clear="filterCount > 0" @clear="clearFilters" />

        <AppDrawer v-model:open="detailOpen" title="Visit" :description="current ? formatDate(current.visited_at, true) : ''" :icon="Route" width="lg">
            <div v-if="current" class="space-y-5">
                <div class="flex flex-wrap gap-2">
                    <AppBadge v-if="current.converted.bookings" tone="success">Booked</AppBadge>
                    <AppBadge v-if="current.converted.enquiries" tone="warning">Enquired</AppBadge>
                    <AppBadge v-if="current.is_bot">Crawler</AppBadge>
                    <AppBadge v-if="current.deleted" tone="danger">Deleted page</AppBadge>
                </div>
                <dl class="dl rounded-2xl bg-canvas p-4">
                    <template v-for="[label, value] in details" :key="label">
                        <dt>{{ label }}</dt>
                        <dd class="min-w-0 [overflow-wrap:anywhere]">{{ value }}</dd>
                    </template>
                </dl>
                <div>
                    <p class="mb-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase">Actions</p>
                    <div class="space-y-2">
                        <button type="button" class="dw-action" @click="narrow('visitor_id', current.visitor_id)">
                            <span class="dw-action-icon" style="--tone: #818cf8"><User class="size-4" /></span>All visits by this visitor
                        </button>
                        <button v-if="current.session_id" type="button" class="dw-action" @click="narrow('session_id', current.session_id)">
                            <span class="dw-action-icon" style="--tone: #34d399"><Monitor class="size-4" /></span>This session
                        </button>
                        <button v-if="current.ip" type="button" class="dw-action" @click="narrow('ip', current.ip)">
                            <span class="dw-action-icon" style="--tone: #38bdf8"><Network class="size-4" /></span>This IP
                        </button>
                    </div>
                </div>
            </div>
        </AppDrawer>
    </div>
</template>
