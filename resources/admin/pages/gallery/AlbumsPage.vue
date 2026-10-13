<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowDownUp, CalendarDays, Eye, EyeOff, ExternalLink, Image as ImageIcon, Images, LayoutGrid, Link2, List, Pencil, Plus, Trash2, Video } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import AlbumListItem, { type AlbumItem } from './AlbumListItem.vue';
import headerPhoto from '@admin/assets/gallery-header.webp';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface AlbumRow {
    id: number;
    title: Translations;
    slug: string;
    url: string;
    description: Translations;
    cover_id: number | null;
    event_date: string | null;
    is_published: boolean;
    cover_thumb: string | null;
    images_count: number;
    videos_count: number;
    updated_at: string | null;
}

type AlbumsResponse = Paginated<AlbumRow> & {
    counts: { published: number; draft: number; photos: number; videos: number; empty: number };
};

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<AlbumRow, AlbumsResponse>('/gallery', { status: '', content: '', sort: '' }, { perPage: 12 });
const createOpen = ref(false);
const filtersOpen = ref(false);
const drawerOpen = ref(false);
const current = ref<AlbumRow | null>(null);
const form = useForm({ title: {} as Translations, event_date: '' as string | null });

const counts = computed(() => list.response.value?.counts);
const total = computed(() => list.meta.value?.total ?? 0);
const activeFilters = computed(() => (['status', 'content'] as const).filter((key) => list.filters[key] !== '').length);
const filtered = computed(() => Boolean(list.filters.search) || activeFilters.value > 0);

// Grid or list, remembered on this device. Albums are pictures, so grid comes first.
const VIEW_KEY = 'ahk.gallery.view';
const view = ref<'list' | 'grid'>('grid');
try {
    if (localStorage.getItem(VIEW_KEY) === 'list') view.value = 'list';
} catch {
    // Storage can be blocked; the grid view is the default.
}
watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Not remembered, which is fine.
    }
});

const filterGroups = computed<FilterGroup[]>(() => {
    const c = counts.value;
    return [
        {
            key: 'status',
            label: 'Website',
            icon: Eye,
            tone: '#34d399',
            options: [
                { value: '', label: 'Shown or hidden', count: c ? c.published + c.draft : null },
                { value: 'published', label: 'Shown', count: c?.published ?? null },
                { value: 'draft', label: 'Hidden', count: c?.draft ?? null },
            ],
        },
        {
            key: 'content',
            label: 'Content',
            icon: Images,
            tone: '#38bdf8',
            options: [
                { value: '', label: 'Any' },
                { value: 'photos', label: 'Has photos', count: c?.photos ?? null },
                { value: 'videos', label: 'Has videos', count: c?.videos ?? null },
                { value: 'empty', label: 'Empty', count: c?.empty ?? null },
            ],
        },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Newest date first' },
                { value: 'oldest', label: 'Oldest date first' },
                { value: 'updated', label: 'Recently updated' },
                { value: 'title', label: 'Title A–Z' },
            ],
        },
    ];
});

const title = (album: AlbumRow) => pickText(album.title, app.defaultLocale);
const item = (album: AlbumRow): AlbumItem => ({
    id: album.id,
    title: title(album),
    description: pickText(album.description, app.defaultLocale),
    slug: album.slug,
    url: album.url,
    event_date: album.event_date,
    is_published: album.is_published,
    cover_thumb: album.cover_thumb,
    images_count: album.images_count,
    videos_count: album.videos_count,
});
const isEmpty = (album: AlbumRow) => album.images_count + album.videos_count === 0;

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

function open(album: AlbumRow): void {
    current.value = album;
    drawerOpen.value = true;
}

function openCreate(): void {
    form.reset({ title: {}, event_date: new Date().toISOString().slice(0, 10) });
    createOpen.value = true;
}

async function create(): Promise<void> {
    const response = await form.submit(() => http.post<{ data: { id: number } }>('/gallery', { ...form.data, event_date: form.data.event_date || null }));
    if (response) {
        createOpen.value = false;
        router.push({ name: 'album-editor', params: { id: response.data.data.id } });
    }
}

/** Show or hide an album straight from the list. */
async function toggle(album: AlbumRow): Promise<void> {
    try {
        await http.put(`/gallery/${album.id}`, {
            title: album.title,
            slug: album.slug,
            description: album.description,
            cover_id: album.cover_id,
            event_date: album.event_date,
            is_published: !album.is_published,
        });
        toast.success(album.is_published ? 'Album hidden from the website' : 'Album shown on the website');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(album: AlbumRow): Promise<void> {
    const ok = await confirm({ title: `Delete "${title(album)}"?`, message: 'The album and its photos and videos will be removed. This cannot be undone.', confirmLabel: 'Delete album' });
    if (!ok) return;
    try {
        await http.delete(`/gallery/${album.id}`);
        toast.success('Album deleted');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(() => list.load());
</script>

<template>
    <div>
        <!-- Header with a Makkah photo -->
        <ListHero title="Gallery" description="Albums of photos and YouTube videos from your journeys. The newest albums are shown first." :icon="Images" tone="#2dd4bf" :photo="headerPhoto" photo-position="50% 50%">
            <template #actions>
                <AppButton v-if="auth.can('gallery.create')" :icon="Plus" @click="openCreate">New album</AppButton>
            </template>
        </ListHero>

        <!-- Search, filters and view switch in one row -->
        <ListToolbar v-model:search="list.filters.search as string" placeholder="Search albums by title" :active="activeFilters" @filters="filtersOpen = true">
            <div class="hidden rounded-xl border border-line bg-white p-1 sm:flex" role="group" aria-label="Show albums as">
                <button type="button" class="al-view" :class="{ 'is-on': view === 'grid' }" :aria-pressed="view === 'grid'" aria-label="Grid" title="Grid" @click="view = 'grid'"><LayoutGrid class="size-[18px]" /></button>
                <button type="button" class="al-view" :class="{ 'is-on': view === 'list' }" :aria-pressed="view === 'list'" aria-label="List" title="List" @click="view = 'list'"><List class="size-[18px]" /></button>
            </div>
        </ListToolbar>

        <!-- Albums -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true" aria-label="Loading albums">
            <div v-for="n in 3" :key="n" class="card h-[360px] animate-pulse" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Images"
                :title="filtered ? 'No albums match' : 'No albums yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Create an album, then upload photos or add YouTube videos to it.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('gallery.create')" :icon="Plus" @click="openCreate">New album</AppButton>
            </EmptyState>
        </div>
        <div v-else :key="view" class="al-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="al-grid" :class="`is-${view}`">
                <AlbumListItem
                    v-for="(album, i) in list.rows.value"
                    :key="album.id"
                    :album="item(album)"
                    :layout="view"
                    :index="i"
                    :can-update="auth.can('gallery.update')"
                    :can-delete="auth.can('gallery.delete')"
                    @open="open(album)"
                    @toggle="toggle(album)"
                    @remove="remove(album)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="albums" :per-page-options="[12, 24, 48, 96]" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['album', 'albums']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="list.clear()"
        />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? title(current) : ''" :description="current ? `/gallery/${current.slug}` : ''" :icon="Images">
            <template v-if="current">
                <img v-if="current.cover_thumb" :src="current.cover_thumb" alt="" class="mb-5 aspect-[4/3] w-full rounded-2xl object-cover" />
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="current.is_published ? 'success' : 'neutral'">
                        <component :is="current.is_published ? Eye : EyeOff" class="size-3" />{{ current.is_published ? 'Shown on the website' : 'Hidden' }}
                    </AppBadge>
                    <AppBadge v-if="isEmpty(current)" tone="warning">Empty: not shown until it has photos or videos</AppBadge>
                </div>

                <p v-if="pickText(current.description, app.defaultLocale)" class="mt-4 text-[14px] text-ink-soft">{{ pickText(current.description, app.defaultLocale) }}</p>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><CalendarDays class="size-4 text-teal-600" />Date</dt>
                    <dd>{{ current.event_date ? formatDate(current.event_date) : '—' }}</dd>
                    <dt class="flex items-center gap-2"><ImageIcon class="size-4 text-sky-600" />Photos</dt>
                    <dd>{{ current.images_count }}</dd>
                    <dt class="flex items-center gap-2"><Video class="size-4 text-rose-500" />Videos</dt>
                    <dd>{{ current.videos_count }}</dd>
                    <dt class="flex items-center gap-2"><Link2 class="size-4 text-emerald-600" />Address</dt>
                    <dd class="break-all">/gallery/{{ current.slug }}</dd>
                    <template v-if="current.updated_at">
                        <dt>Updated</dt>
                        <dd>{{ formatDate(current.updated_at, true) }}</dd>
                    </template>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'album-editor', params: { id: current.id } }" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit album and photos
                    </RouterLink>
                    <a v-if="current.is_published && !isEmpty(current)" :href="current.url" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-if="auth.can('gallery.update')" type="button" class="dw-action" @click="toggle(current)">
                        <span class="dw-action-icon" style="--tone: #34d399"><component :is="current.is_published ? EyeOff : Eye" class="size-4" /></span>{{ current.is_published ? 'Hide from the website' : 'Show on the website' }}
                    </button>
                    <button v-if="auth.can('gallery.delete')" type="button" class="dw-action is-danger" @click="remove(current)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete album
                    </button>
                </div>
            </template>
        </AppDrawer>

        <AppModal v-model:open="createOpen" title="New album" description="You'll add photos and videos next." :persistent="form.processing">
            <form id="new-album" class="space-y-4" novalidate @submit.prevent="create">
                <TranslatableInput v-model="form.data.title" label="Title" required :errors="form.errors" error-key="title" placeholder="Hajj 2025: Arafat day" />
                <FormField label="Date" for="new-album-date" :error="form.error('event_date')" hint="When the photos were taken. Albums are sorted by this date.">
                    <AppDatePicker id="new-album-date" v-model="form.data.event_date" mode="date" :invalid="!!form.error('event_date')" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="new-album" :loading="form.processing">Create and add photos</AppButton>
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
/* The list measures its own width, so cards and rows adapt to the space next to the sidebar. */
.al-list {
    container: al-list / inline-size;
}

.al-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
}

@container al-list (min-width: 760px) {
    .al-grid.is-list {
        grid-template-columns: minmax(0, 1fr);
    }
}

.al-view {
    display: flex;
    width: 2.5rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
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
</style>
