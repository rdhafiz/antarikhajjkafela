<script setup lang="ts">
import { computed, onMounted, ref, type Component } from 'vue';
import { useRouter } from 'vue-router';
import {
    BriefcaseBusiness,
    CalendarCheck,
    FileText,
    GalleryHorizontalEnd,
    Image as ImageIcon,
    Images,
    Inbox,
    Layers,
    MessageSquareQuote,
    Newspaper,
    Package,
    RotateCcw,
    ShieldCheck,
    Tags,
    Trash2,
    Users,
    Wallet,
} from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import { useAuthStore } from '@admin/stores/auth';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import headerPhoto from '@admin/assets/trash-header.webp';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useToastStore } from '@admin/stores/toast';

interface TrashRow {
    type: string;
    type_label: string;
    id: number;
    title: string;
    detail: string | null;
    deleted_at: string;
    deleted_by: string | null;
    blocker: string | null;
}

type TrashResponse = Paginated<TrashRow> & { types: { key: string; label: string; count: number }[] };

const router = useRouter();
const toast = useToastStore();

const list = useListQuery<TrashRow, TrashResponse>('/trash', { type: '' });
const filtersOpen = ref(false);
const restoring = ref<string | null>(null);
const auth = useAuthStore();

// Delete for good: the name of the item is not enough of a check, so the word DELETE is typed.
const purging = ref<TrashRow | null>(null);
const purgeOpen = ref(false);
const purgeWord = ref('');
const purgeBusy = ref(false);

function askPurge(row: TrashRow): void {
    purging.value = row;
    purgeWord.value = '';
    purgeOpen.value = true;
}

async function purge(): Promise<void> {
    const row = purging.value;
    if (!row || purgeWord.value.trim().toUpperCase() !== 'DELETE') return;
    purgeBusy.value = true;
    try {
        await http.delete(`/trash/${row.type}/${row.id}`);
        toast.success(`${row.type_label} deleted for good`, row.title);
        purgeOpen.value = false;
        await list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        purgeBusy.value = false;
    }
}

/** Each kind in its sidebar module's colour. */
const looks: Record<string, { icon: Component; tone: string }> = {
    bookings: { icon: CalendarCheck, tone: '#34d399' },
    payments: { icon: Wallet, tone: '#34d399' },
    inquiries: { icon: Inbox, tone: '#38bdf8' },
    packages: { icon: Package, tone: '#fbbf24' },
    services: { icon: BriefcaseBusiness, tone: '#a78bfa' },
    pages: { icon: FileText, tone: '#60a5fa' },
    posts: { icon: Newspaper, tone: '#fb923c' },
    post_categories: { icon: Tags, tone: '#fb923c' },
    reviews: { icon: MessageSquareQuote, tone: '#f472b6' },
    gallery_albums: { icon: GalleryHorizontalEnd, tone: '#2dd4bf' },
    gallery_items: { icon: ImageIcon, tone: '#2dd4bf' },
    media: { icon: Images, tone: '#22d3ee' },
    users: { icon: Users, tone: '#93c5fd' },
    roles: { icon: ShieldCheck, tone: '#4ade80' },
};
const look = (type: string) => looks[type] ?? { icon: Trash2, tone: '#cbd5e1' };

const types = computed(() => list.response.value?.types ?? []);
const inTrash = computed(() => types.value.reduce((sum, t) => sum + t.count, 0));
const filterGroups = computed<FilterGroup[]>(() => [
    {
        key: 'type',
        label: 'What',
        icon: Layers,
        tone: '#cbd5e1',
        options: [{ value: '', label: 'Everything', count: inTrash.value }, ...types.value.filter((t) => t.count).map((t) => ({ value: t.key, label: t.label, count: t.count }))],
    },
]);
const filtered = computed(() => list.active.value > 0 || list.filters.search !== '');

async function restore(row: TrashRow): Promise<void> {
    const key = `${row.type}:${row.id}`;
    restoring.value = key;
    try {
        const { data } = await http.post<{ data: { link: string | null } }>(`/trash/${row.type}/${row.id}/restore`);
        toast.success(`${row.type_label} restored`, row.title);
        await list.load();
        if (data.data.link && !list.rows.value.length && !filtered.value) router.push(data.data.link);
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        restoring.value = null;
    }
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

onMounted(list.load);
</script>

<template>
    <div>
        <ListHero
            title="Trash"
            description="Everything deleted in the admin stays here and can be restored. A super admin can delete an item for good, with its files."
            :icon="Trash2"
            tone="#f87171"
            :photo="headerPhoto"
            photo-position="40% 50%"
        >
            <template #actions>
                <span class="tr-count"><Trash2 class="size-4" aria-hidden="true" />{{ inTrash }} {{ inTrash === 1 ? 'item' : 'items' }}</span>
            </template>
        </ListHero>

        <ListToolbar v-model:search="list.filters.search as string" placeholder="Search the Trash" :active="list.active.value" @filters="filtersOpen = true" />

        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-3" role="status" aria-busy="true" aria-label="Loading the Trash">
            <div v-for="n in 4" :key="n" class="card h-[84px] animate-pulse" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Trash2"
                :title="filtered ? 'Nothing here matches' : 'The Trash is empty'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Anything deleted in the admin will appear here, ready to restore.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
        </div>
        <ul v-else class="grid gap-3" :class="{ 'opacity-60 transition-opacity': list.loading.value }" aria-label="Deleted items">
            <li v-for="(row, i) in list.rows.value" :key="`${row.type}:${row.id}`" class="tr-row card" :style="{ '--tone': look(row.type).tone, '--i': i }">
                <span class="tr-icon" aria-hidden="true"><component :is="look(row.type).icon" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="tr-title">{{ row.title }}</p>
                    <p class="tr-meta">
                        <span class="tr-kind">{{ row.type_label }}</span>
                        <span v-if="row.detail">{{ row.detail }}</span>
                    </p>
                    <p class="tr-when">Deleted {{ formatDate(row.deleted_at, true) }}<template v-if="row.deleted_by"> by {{ row.deleted_by }}</template></p>
                    <p v-if="row.blocker" class="tr-blocker">{{ row.blocker }}</p>
                </div>
                <AppButton
                    variant="secondary"
                    size="sm"
                    :icon="RotateCcw"
                    :loading="restoring === `${row.type}:${row.id}`"
                    :disabled="!!row.blocker || (restoring !== null && restoring !== `${row.type}:${row.id}`)"
                    :aria-label="`Restore ${row.type_label.toLowerCase()} ${row.title}`"
                    @click="restore(row)"
                >
                    Restore
                </AppButton>
                <AppButton
                    v-if="auth.can('trash.delete')"
                    variant="ghost"
                    size="sm"
                    :icon="Trash2"
                    class="text-red-600 hover:bg-red-50"
                    :disabled="restoring !== null"
                    :aria-label="`Delete ${row.type_label.toLowerCase()} ${row.title} for good`"
                    @click="askPurge(row)"
                >
                    Delete forever
                </AppButton>
            </li>
        </ul>

        <AppModal v-model:open="purgeOpen" title="Delete for good?" :description="purging ? `${purging.type_label}: ${purging.title}` : ''" size="sm" :persistent="purgeBusy">
            <form id="trash-purge" class="space-y-3" @submit.prevent="purge">
                <p class="text-sm leading-relaxed text-ink-soft">
                    This removes it permanently, with its files (for a booking: passport and NID copies, payments, updates and its activity log). It cannot be restored.
                </p>
                <label for="purge-word" class="block text-[13px] font-semibold text-ink">Type <span class="font-mono">DELETE</span> to confirm</label>
                <input id="purge-word" v-model="purgeWord" class="control" autocomplete="off" autocapitalize="characters" spellcheck="false">
            </form>
            <template #footer>
                <AppButton variant="secondary" :disabled="purgeBusy" @click="purgeOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="trash-purge" variant="danger" :icon="Trash2" :loading="purgeBusy" :disabled="purgeWord.trim().toUpperCase() !== 'DELETE'">Delete forever</AppButton>
            </template>
        </AppModal>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="items" @change="list.goTo" @per-page="list.setPerPage" />

        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="list.meta.value?.total ?? 0"
            :noun="['item', 'items']"
            :loading="list.loading.value"
            :can-clear="list.active.value > 0"
            @clear="list.clear()"
        />
    </div>
</template>

<style scoped>
.tr-count {
    display: inline-flex;
    min-height: 2.25rem;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    background: #fff;
    padding: 0 0.85rem;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-ink);
    box-shadow: inset 0 0 0 1px var(--color-line);
}

.tr-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.9rem 1rem;
    padding: 1rem 1.15rem;
    animation: tr-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(min(var(--i), 10) * 35ms);
}

.tr-icon {
    display: flex;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    background: color-mix(in oklab, var(--tone) 18%, white);
    color: color-mix(in oklab, var(--tone) 40%, black);
}

.tr-title {
    overflow-wrap: anywhere;
    font-size: 15px;
    font-weight: 700;
    color: var(--color-ink);
}

.tr-meta {
    margin-top: 0.15rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0.6rem;
    font-size: 13px;
    color: var(--color-ink-soft);
}

.tr-kind {
    font-weight: 600;
    color: color-mix(in oklab, var(--tone) 45%, black);
}

.tr-when {
    margin-top: 0.15rem;
    font-size: 12.5px;
    color: var(--color-ink-soft);
}

.tr-blocker {
    margin-top: 0.35rem;
    font-size: 12.5px;
    font-weight: 600;
    color: #b45309;
}

@keyframes tr-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .tr-row {
        animation: none;
    }
}
</style>
