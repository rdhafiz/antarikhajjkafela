<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import { errorMessage, http } from '@admin/lib/http';
import type { AppRow, ChannelRow, DrillKey, SourceRow, VisitFilters, VisitTotals } from './types';

const props = defineProps<{ filters: VisitFilters }>();
const emit = defineEmits<{ totals: [VisitTotals]; unavailable: [string]; drill: [Partial<Record<DrillKey, string>>] }>();

const channels = ref<ChannelRow[]>([]);
const sources = ref<SourceRow[]>([]);
const apps = ref<AppRow[]>([]);
const crawlers = ref(0);
const loading = ref(true);

async function load(): Promise<void> {
    loading.value = true;
    const { date_from, date_to, include_bots } = props.filters;
    try {
        const { data } = await http.get('/visits/sources', { params: { date_from, date_to, include_bots: include_bots || undefined } });
        channels.value = data.data.channels;
        sources.value = data.data.sources;
        apps.value = data.data.apps;
        crawlers.value = data.data.crawlers.views;
        emit('totals', data.totals);
    } catch (e) {
        emit('unavailable', errorMessage(e));
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => [props.filters.date_from, props.filters.date_to, props.filters.include_bots], load);
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <EmptyState v-else-if="!channels.length" title="No visits in this period" description="Try a wider date range." />
        <template v-else>
            <section class="card overflow-hidden" aria-labelledby="vs-channels">
                <h2 id="vs-channels" class="px-4 pt-4 text-[12px] font-semibold tracking-wide text-ink-muted uppercase">Channels</h2>
                <table class="dt mt-2">
                    <thead>
                        <tr><th>Channel</th><th>Views</th><th>Visitors</th><th>Bookings</th><th>Enquiries</th><th /></tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in channels" :key="c.channel" class="dt-row" @click="emit('drill', { channel: c.channel })">
                            <td class="font-semibold text-ink">{{ c.label }}</td>
                            <td data-label="Views">{{ c.views }}</td>
                            <td data-label="Visitors">{{ c.visitors }}</td>
                            <td data-label="Bookings">{{ c.bookings }}</td>
                            <td data-label="Enquiries">{{ c.enquiries }}</td>
                            <td class="dt-trigger"><button class="dt-open" :aria-label="`Open ${c.label} visits`" @click.stop="emit('drill', { channel: c.channel })"><ChevronRight class="size-4" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="sources.length" class="card overflow-hidden" aria-labelledby="vs-sources">
                <h2 id="vs-sources" class="px-4 pt-4 text-[12px] font-semibold tracking-wide text-ink-muted uppercase">Sources</h2>
                <table class="dt mt-2">
                    <thead>
                        <tr><th>Source</th><th>Channel</th><th>Views</th><th>Visitors</th><th>Bookings</th><th>Enquiries</th><th /></tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sources" :key="`${s.channel}|${s.source}`" class="dt-row" @click="emit('drill', { channel: s.channel, source: s.source })">
                            <td class="font-semibold text-ink">{{ s.source }}</td>
                            <td data-label="Channel">{{ s.label }}</td>
                            <td data-label="Views">{{ s.views }}</td>
                            <td data-label="Visitors">{{ s.visitors }}</td>
                            <td data-label="Bookings">{{ s.bookings }}</td>
                            <td data-label="Enquiries">{{ s.enquiries }}</td>
                            <td class="dt-trigger"><button class="dt-open" :aria-label="`Open ${s.source} visits`" @click.stop="emit('drill', { channel: s.channel, source: s.source })"><ChevronRight class="size-4" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="apps.length" class="card overflow-hidden" aria-labelledby="vs-apps">
                <h2 id="vs-apps" class="px-4 pt-4 text-[12px] font-semibold tracking-wide text-ink-muted uppercase">In-app browsers</h2>
                <table class="dt mt-2">
                    <thead>
                        <tr><th>App</th><th>Views</th><th>Visitors</th><th /></tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in apps" :key="a.app" class="dt-row" @click="emit('drill', { in_app: a.app })">
                            <td class="font-semibold text-ink">{{ a.app }}</td>
                            <td data-label="Views">{{ a.views }}</td>
                            <td data-label="Visitors">{{ a.visitors }}</td>
                            <td class="dt-trigger"><button class="dt-open" :aria-label="`Open ${a.app} visits`" @click.stop="emit('drill', { in_app: a.app })"><ChevronRight class="size-4" /></button></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>

        <p v-if="!loading" class="text-[13px] text-ink-muted">{{ crawlers }} crawler {{ crawlers === 1 ? 'view' : 'views' }} in this period{{ filters.include_bots ? '' : ' (not counted above)' }}.</p>
    </div>
</template>
