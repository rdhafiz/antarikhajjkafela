<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import { formatDate } from '@admin/lib/format';
import { errorMessage, http } from '@admin/lib/http';
import { subjectLabel, type DrillKey, type PageRow, type VisitFilters, type VisitTotals } from './types';

const props = defineProps<{ filters: VisitFilters }>();
const emit = defineEmits<{ totals: [VisitTotals]; unavailable: [string]; drill: [Partial<Record<DrillKey, string>>] }>();

const rows = ref<PageRow[]>([]);
const loading = ref(true);

async function load(): Promise<void> {
    loading.value = true;
    const { date_from, date_to, include_bots } = props.filters;
    try {
        const { data } = await http.get('/visits/pages', { params: { date_from, date_to, include_bots: include_bots || undefined } });
        rows.value = data.data;
        emit('totals', data.totals);
    } catch (e) {
        emit('unavailable', errorMessage(e));
    } finally {
        loading.value = false;
    }
}

function open(row: PageRow): void {
    emit('drill', { subject_type: row.subject_type, subject_id: row.subject_id === null ? '' : String(row.subject_id) });
}

onMounted(load);
watch(() => [props.filters.date_from, props.filters.date_to, props.filters.include_bots], load);
</script>

<template>
    <div>
        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <EmptyState v-else-if="!rows.length" title="No visits in this period" description="Try a wider date range." />
        <section v-else class="card overflow-hidden" aria-label="Most visited pages">
            <table class="dt">
                <thead>
                    <tr><th>Page</th><th>Views</th><th>Visitors</th><th>Last seen</th><th /></tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="`${r.subject_type}:${r.subject_id}`" class="dt-row" @click="open(r)">
                        <td class="min-w-0">
                            <span class="font-semibold [overflow-wrap:anywhere] text-ink">{{ r.title ?? r.path }}</span>
                            <span class="ml-2 inline-flex gap-1 align-middle">
                                <AppBadge>{{ subjectLabel(r.subject_type) }}</AppBadge>
                                <AppBadge v-if="r.deleted" tone="danger">Deleted</AppBadge>
                            </span>
                        </td>
                        <td data-label="Views">{{ r.views }}</td>
                        <td data-label="Visitors">{{ r.visitors }}</td>
                        <td data-label="Last seen">{{ formatDate(r.last_seen, true) }}</td>
                        <td class="dt-trigger"><button class="dt-open" :aria-label="`Open visits to ${r.title ?? r.path}`" @click.stop="open(r)"><ChevronRight class="size-4" /></button></td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
