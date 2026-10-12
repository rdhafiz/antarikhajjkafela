<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CalendarClock, ChevronRight, Copy, ExternalLink, Eye, FileText, Layers, Link2, Lock, Megaphone, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TabSwitcher from '@admin/components/ui/TabSwitcher.vue';
import CreatePageModal from './CreatePageModal.vue';
import { pickText, type PageSummary } from './builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';

type PageType = PageSummary['type'];

/**
 * Lists pages. On /admin/pages: built-in and custom pages (one tab each); on /admin/campaigns: campaign pages.
 * The page type is a fixed part of the query, not a filter the visitor can clear.
 */
const route = useRoute();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const isCampaigns = computed(() => route.name === 'campaigns');
const scope = computed(() => (isCampaigns.value ? 'campaigns' : 'pages'));

/** The type this route starts on (and the only one allowed on campaigns). */
function routeType(): PageType {
    return isCampaigns.value ? 'campaign' : 'default';
}

const list = useListQuery<PageSummary>('/pages', { type: routeType(), status: '' });

// The address bar may carry a type from the other route (or a bad one): keep it within this route.
function normalizeType(): boolean {
    const allowed: PageType[] = isCampaigns.value ? ['campaign'] : ['default', 'custom'];
    if (allowed.includes(list.filters.type as PageType)) return false;
    list.filters.type = routeType();
    return true;
}
const normalized = normalizeType();

const type = computed(() => list.filters.type as PageType);
const isBuiltIn = computed(() => type.value === 'default');
/** Only the status counts as a filter; the type is fixed by the route or the tab. */
const activeFilters = computed(() => (list.filters.status !== '' ? 1 : 0));
const filtered = computed(() => Boolean(list.filters.search || activeFilters.value));

const typeTabs = [
    { value: 'default' as PageType, label: 'Built-in pages', icon: Lock, tone: '#818cf8' },
    { value: 'custom' as PageType, label: 'Your pages', icon: FileText, tone: '#34d399' },
];
const typeModel = computed<PageType>({
    get: () => type.value,
    set: (value) => {
        list.filters.type = value;
    },
});

const current = ref<PageSummary | null>(null);
const drawerOpen = ref(false);
const previewing = ref(false);
const createOpen = ref(false);

const stateTone = { draft: 'neutral', published: 'success', live: 'success', scheduled: 'warning', expired: 'danger' } as const;
const stateLabel = { draft: 'Draft', published: 'Published', live: 'Live', scheduled: 'Scheduled', expired: 'Expired' } as const;

const noun = computed(() => (isCampaigns.value ? 'campaign' : 'page'));
const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? noun.value : `${noun.value}s`}` : ''));

const title = (page: PageSummary) => pickText(page.title, app.defaultLocale);
const address = (page: PageSummary) => (page.system_key === 'home' ? '/' : `/${page.slug}`);
const isOnline = (page: PageSummary) => page.state === 'published' || page.state === 'live';

function open(page: PageSummary): void {
    current.value = page;
    drawerOpen.value = true;
}

function clearFilters(): void {
    list.filters.status = '';
}

function clearAll(): void {
    list.filters.status = '';
    list.filters.search = '';
}

async function preview(page: PageSummary): Promise<void> {
    // Open the tab right away so pop-up blockers allow it, then point it at the signed preview.
    const tab = window.open('', '_blank');
    previewing.value = true;
    try {
        const { data } = await http.get<{ url: string }>(`/pages/${page.id}/preview-url`);
        if (tab) tab.location.href = data.url;
        drawerOpen.value = false;
    } catch (e) {
        tab?.close();
        toast.error(errorMessage(e));
    } finally {
        previewing.value = false;
    }
}

async function duplicate(page: PageSummary): Promise<void> {
    try {
        await http.post(`/pages/${page.id}/duplicate`);
        toast.success('Copy created', 'The copy is saved as a draft.');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(page: PageSummary): Promise<void> {
    const ok = await confirm({
        title: `Delete "${title(page)}"?`,
        message: 'The page and all its sections will be removed. Menu links to it will stop working.',
        confirmLabel: 'Delete page',
    });
    if (!ok) return;

    try {
        await http.delete(`/pages/${page.id}`);
        toast.success('Page deleted');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function schedule(page: PageSummary): string {
    if (!page.starts_at && !page.expires_at) return 'No end date';
    return `${page.starts_at ? formatDate(page.starts_at) : 'Now'} – ${page.expires_at ? formatDate(page.expires_at) : 'no end'}`;
}

// Pages and campaigns share this screen: switching routes swaps the type (which reloads the list).
watch(
    () => route.name,
    () => {
        drawerOpen.value = false;
        list.filters.status = '';
        list.filters.search = '';
        list.filters.type = routeType();
    },
);

onMounted(() => {
    // A corrected type already triggers a reload.
    if (!normalized) list.load();
});
</script>

<template>
    <div>
        <PageHeader
            :title="isCampaigns ? 'Campaign pages' : 'Pages'"
            :description="isCampaigns ? 'Landing pages for marketing and ads, live between their start and expiry dates.' : 'Built-in website pages and your own pages, each built from sections.'"
        >
            <template #actions>
                <AppButton v-if="auth.can(`${scope}.create`)" :icon="Plus" @click="createOpen = true">{{ isCampaigns ? 'New campaign' : 'New page' }}</AppButton>
            </template>
        </PageHeader>

        <TabSwitcher v-if="!isCampaigns" v-model="typeModel" :options="typeTabs" label="Show pages" class="mb-4" />

        <FilterBar
            v-model:search="list.filters.search as string"
            :placeholder="isCampaigns ? 'Search campaigns by title or address' : 'Search pages by title or address'"
            :active="activeFilters"
            :summary="summary"
            @clear="clearFilters"
        >
            <select v-model="list.filters.status" class="control" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="isCampaigns ? Megaphone : FileText"
                :title="filtered ? `No ${noun}s match` : isCampaigns ? 'No campaigns yet' : isBuiltIn ? 'No built-in pages' : 'No custom pages yet'"
                :description="
                    filtered
                        ? 'Try another search or clear the filters.'
                        : isCampaigns
                          ? 'Create a landing page for your next registration drive or ad campaign.'
                          : 'Create pages such as a refund policy or a Hajj guide.'
                "
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="!isBuiltIn && auth.can(`${scope}.create`)" :icon="Plus" @click="createOpen = true">{{ isCampaigns ? 'New campaign' : 'New page' }}</AppButton>
            </EmptyState>
            <table v-else class="dt" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <thead>
                    <tr>
                        <th>{{ isCampaigns ? 'Campaign' : 'Page' }}</th>
                        <th>Status</th>
                        <th v-if="isCampaigns">Runs</th>
                        <th class="dt-hide-sm">Sections</th>
                        <th>Updated</th>
                        <th class="dt-trigger"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in list.rows.value" :key="page.id" class="dt-row" @click="open(page)">
                        <td>
                            <p class="flex items-center gap-1.5 font-semibold text-ink">
                                {{ title(page) }}
                                <Lock v-if="page.type === 'default'" class="size-3 shrink-0 text-ink-muted" aria-label="Built-in page" />
                            </p>
                            <p class="mt-0.5 text-[12.5px] break-all text-ink-muted">{{ address(page) }}</p>
                        </td>
                        <td data-label="Status"><AppBadge :tone="stateTone[page.state]">{{ stateLabel[page.state] }}</AppBadge></td>
                        <td v-if="isCampaigns" data-label="Runs" class="text-[13px] text-ink-soft">
                            <span class="inline-flex items-center gap-1.5"><CalendarClock class="size-3.5 shrink-0 text-ink-muted" /> {{ schedule(page) }}</span>
                        </td>
                        <td data-label="Sections" class="dt-hide-sm text-[13px] text-ink-soft">{{ page.sections_count }}</td>
                        <td data-label="Updated" class="text-[13px] whitespace-nowrap text-ink-soft">{{ formatDate(page.updated_at) }}</td>
                        <td class="dt-trigger">
                            <button type="button" class="dt-open" :aria-label="`Open ${title(page)}`" @click.stop="open(page)"><ChevronRight class="size-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" :label="`${noun}s`" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? title(current) : ''" :description="current ? address(current) : ''" :icon="isCampaigns ? Megaphone : FileText">
            <template v-if="current">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="stateTone[current.state]">{{ stateLabel[current.state] }}</AppBadge>
                    <AppBadge v-if="current.type === 'default'" tone="neutral"><Lock class="size-3" />Built-in</AppBadge>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Link2 class="size-4 text-emerald-600" />Address</dt>
                    <dd class="break-all">{{ address(current) }}</dd>
                    <dt class="flex items-center gap-2"><Layers class="size-4 text-violet-600" />Sections</dt>
                    <dd>{{ current.sections_count }}</dd>
                    <template v-if="current.type === 'campaign'">
                        <dt class="flex items-center gap-2"><CalendarClock class="size-4 text-sky-600" />Starts</dt>
                        <dd>{{ current.starts_at ? formatDate(current.starts_at, true) : 'When published' }}</dd>
                        <dt class="flex items-center gap-2"><CalendarClock class="size-4 text-rose-600" />Ends</dt>
                        <dd>{{ current.expires_at ? formatDate(current.expires_at, true) : 'No end date' }}</dd>
                    </template>
                    <dt>Updated</dt>
                    <dd>{{ formatDate(current.updated_at, true) }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'page-editor', params: { id: current.id } }" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit {{ isCampaigns ? 'campaign' : 'page' }}
                    </RouterLink>
                    <a v-if="isOnline(current)" :href="current.url" target="_blank" rel="noopener" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button type="button" class="ad-action" :disabled="previewing" @click="preview(current)">
                        <span class="ad-action-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                    </button>
                    <button v-if="current.type !== 'default' && auth.can(`${scope}.create`)" type="button" class="ad-action" @click="duplicate(current)">
                        <span class="ad-action-icon" style="--tone: #2dd4bf"><Copy class="size-4" /></span>Duplicate as a draft
                    </button>
                    <button v-if="current.type !== 'default' && auth.can(`${scope}.delete`)" type="button" class="ad-action is-danger" @click="remove(current)">
                        <span class="ad-action-icon"><Trash2 class="size-4" /></span>Delete {{ isCampaigns ? 'campaign' : 'page' }}
                    </button>
                </div>
            </template>
        </AppDrawer>

        <CreatePageModal v-model:open="createOpen" :type="isCampaigns ? 'campaign' : 'custom'" />
    </div>
</template>
