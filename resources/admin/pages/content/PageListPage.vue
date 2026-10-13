<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowDownUp, CalendarClock, CircleCheck, Copy, ExternalLink, Eye, FileText, Layers, Link2, Lock, Megaphone, Pencil, Plus, Radio, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import CreatePageModal from './CreatePageModal.vue';
import PageListItem from './PageListItem.vue';
import pagesPhoto from '@admin/assets/pages-header.webp';
import campaignsPhoto from '@admin/assets/campaigns-header.webp';
import type { Paginated } from '@admin/lib/http';
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

type PagesResponse = Paginated<PageSummary> & {
    counts: { statuses: Record<string, number>; states: Record<string, number>; types: Record<string, number> };
};

const list = useListQuery<PageSummary, PagesResponse>('/pages', { type: routeType(), status: '', state: '', sort: '' });

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
/** Status (or a campaign's state) is the filter; the type is fixed by the route or the switch. */
const activeFilters = computed(() => (list.filters.status !== '' ? 1 : 0) + (list.filters.state !== '' ? 1 : 0));
const filtered = computed(() => Boolean(list.filters.search || activeFilters.value));
const counts = computed(() => list.response.value?.counts);
const total = computed(() => list.meta.value?.total ?? 0);
const filtersOpen = ref(false);

const typeTabs = computed(() => [
    { value: 'default' as PageType, label: 'Built-in', icon: Lock, count: counts.value?.types.default },
    { value: 'custom' as PageType, label: 'Your pages', icon: FileText, count: counts.value?.types.custom },
]);

const filterGroups = computed<FilterGroup[]>(() => {
    const statuses = counts.value?.statuses ?? {};
    const states = counts.value?.states ?? {};
    const all = Object.values(isCampaigns.value ? states : statuses).reduce<number>((sum, n) => sum + n, 0);
    const known = Boolean(counts.value);
    return [
        isCampaigns.value
            ? {
                  key: 'state',
                  label: 'Runs',
                  icon: Radio,
                  tone: '#34d399',
                  options: [
                      { value: '', label: 'All', count: known ? all : null },
                      { value: 'live', label: 'Live now', count: states.live ?? 0 },
                      { value: 'scheduled', label: 'Scheduled', count: states.scheduled ?? 0 },
                      { value: 'expired', label: 'Expired', count: states.expired ?? 0 },
                      { value: 'draft', label: 'Draft', count: states.draft ?? 0 },
                  ],
              }
            : {
                  key: 'status',
                  label: 'Status',
                  icon: CircleCheck,
                  tone: '#34d399',
                  options: [
                      { value: '', label: 'Any status', count: known ? all : null },
                      { value: 'published', label: 'Published', count: statuses.published ?? 0 },
                      { value: 'draft', label: 'Draft', count: statuses.draft ?? 0 },
                  ],
              },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#fbbf24',
            options: [
                { value: '', label: isBuiltIn.value ? 'Menu order' : 'Recently updated' },
                ...(isBuiltIn.value ? [{ value: 'updated', label: 'Recently updated' }] : []),
                { value: 'title', label: 'Title A–Z' },
            ],
        },
    ];
});

const current = ref<PageSummary | null>(null);
const drawerOpen = ref(false);
const previewing = ref(false);
const createOpen = ref(false);

const stateTone = { draft: 'neutral', published: 'success', live: 'success', scheduled: 'warning', expired: 'danger' } as const;
const stateLabel = { draft: 'Draft', published: 'Published', live: 'Live', scheduled: 'Scheduled', expired: 'Expired' } as const;

const noun = computed(() => (isCampaigns.value ? 'campaign' : 'page'));

const title = (page: PageSummary) => pickText(page.title, app.defaultLocale);
const address = (page: PageSummary) => (page.system_key === 'home' ? '/' : `/${page.slug}`);
const isOnline = (page: PageSummary) => page.state === 'published' || page.state === 'live';

function open(page: PageSummary): void {
    current.value = page;
    drawerOpen.value = true;
}

function clearFilters(): void {
    list.filters.status = '';
    list.filters.state = '';
    list.filters.sort = '';
}

function clearAll(): void {
    clearFilters();
    list.filters.search = '';
}

function setType(value: PageType): void {
    list.filters.status = '';
    list.filters.sort = '';
    list.filters.type = value;
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

// Pages and campaigns share this screen: switching routes swaps the type (which reloads the list).
watch(
    () => route.name,
    () => {
        drawerOpen.value = false;
        clearFilters();
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
        <!-- Header with a photo -->
        <ListHero
            :title="isCampaigns ? 'Campaign pages' : 'Pages'"
            :description="isCampaigns ? 'Landing pages for marketing and ads, live between their start and expiry dates.' : 'Built-in website pages and your own pages, each built from sections.'"
            :icon="isCampaigns ? Megaphone : FileText"
            :tone="isCampaigns ? '#fb7185' : '#60a5fa'"
            :photo="isCampaigns ? campaignsPhoto : pagesPhoto"
            :photo-position="isCampaigns ? '50% 55%' : '50% 50%'"
        >
            <template #actions>
                <AppButton v-if="auth.can(`${scope}.create`)" :icon="Plus" @click="createOpen = true">{{ isCampaigns ? 'New campaign' : 'New page' }}</AppButton>
            </template>
        </ListHero>

        <!-- Search, filters and (on pages) the built-in / your pages switch in one row -->
        <ListToolbar
            v-model:search="list.filters.search as string"
            :placeholder="isCampaigns ? 'Search campaigns by title or address' : 'Search pages by title or address'"
            :active="activeFilters"
            @filters="filtersOpen = true"
        >
            <div v-if="!isCampaigns" class="pg-types" role="group" aria-label="Show pages">
                <button
                    v-for="tab in typeTabs"
                    :key="tab.value"
                    type="button"
                    class="pg-type"
                    :class="{ 'is-on': type === tab.value }"
                    :aria-pressed="type === tab.value"
                    :aria-label="tab.label"
                    @click="setType(tab.value)"
                >
                    <component :is="tab.icon" class="size-4 shrink-0" aria-hidden="true" />
                    <span class="max-lg:sm:hidden">{{ tab.label }}</span>
                    <span v-if="tab.count !== undefined" class="pg-type-count">{{ tab.count }}</span>
                </button>
            </div>
        </ListToolbar>

        <!-- Pages -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4" aria-busy="true" aria-label="Loading pages">
            <div v-for="n in 3" :key="n" class="card h-[300px] animate-pulse sm:h-[118px]" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
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
        </div>
        <div v-else class="pg-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="grid gap-4">
                <PageListItem
                    v-for="(page, i) in list.rows.value"
                    :key="page.id"
                    :page="page"
                    :title="title(page)"
                    :index="i"
                    :can-create="auth.can(`${scope}.create`)"
                    :can-delete="auth.can(`${scope}.delete`)"
                    @open="open(page)"
                    @preview="preview(page)"
                    @duplicate="duplicate(page)"
                    @remove="remove(page)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" :label="`${noun}s`" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="[noun, `${noun}s`]"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="clearFilters"
        />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? title(current) : ''" :description="current ? address(current) : ''" :icon="isCampaigns ? Megaphone : FileText">
            <template v-if="current">
                <img v-if="current.cover_url" :src="current.cover_url" alt="" class="mb-5 aspect-[16/7] w-full rounded-2xl object-cover" />
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
                    <RouterLink :to="{ name: 'page-editor', params: { id: current.id } }" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit {{ isCampaigns ? 'campaign' : 'page' }}
                    </RouterLink>
                    <a v-if="isOnline(current)" :href="current.url" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button type="button" class="dw-action" :disabled="previewing" @click="preview(current)">
                        <span class="dw-action-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                    </button>
                    <button v-if="current.type !== 'default' && auth.can(`${scope}.create`)" type="button" class="dw-action" @click="duplicate(current)">
                        <span class="dw-action-icon" style="--tone: #2dd4bf"><Copy class="size-4" /></span>Duplicate as a draft
                    </button>
                    <button v-if="current.type !== 'default' && auth.can(`${scope}.delete`)" type="button" class="dw-action is-danger" @click="remove(current)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete {{ isCampaigns ? 'campaign' : 'page' }}
                    </button>
                </div>
            </template>
        </AppDrawer>

        <CreatePageModal v-model:open="createOpen" :type="isCampaigns ? 'campaign' : 'custom'" />
    </div>
</template>

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.pg-list {
    container: pg-list / inline-size;
}

.pg-types {
    display: flex;
    flex-shrink: 0;
    gap: 0.25rem;
    border-radius: 0.9rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0.25rem;
    box-shadow: var(--shadow-card);
}

@media (max-width: 639px) {
    .pg-types {
        width: 100%;
    }

    .pg-type {
        flex: 1;
        justify-content: center;
    }
}

.pg-type {
    display: inline-flex;
    height: 2.4rem;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.65rem;
    padding: 0 0.85rem;
    font-size: 13.5px;
    font-weight: 600;
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

.pg-type-count {
    border-radius: 999px;
    background: var(--color-canvas);
    padding: 0.05rem 0.45rem;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--color-ink-soft);

    .is-on & {
        background: rgb(255 255 255 / 0.2);
        color: #fff;
    }
}
</style>
