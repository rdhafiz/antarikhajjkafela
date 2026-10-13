<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowDownUp, CalendarClock, CircleCheck, Eye, ExternalLink, FolderTree, LayoutGrid, Link2, List, Newspaper, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import CategoriesModal from './CategoriesModal.vue';
import PostListItem, { type PostItem } from './PostListItem.vue';
import headerPhoto from '@admin/assets/blog-header.webp';
import type { Paginated } from '@admin/lib/http';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';
import type { PostCategory, PostMeta, PostState } from './types';

interface PostRow {
    id: number;
    title: Translations;
    slug: string;
    url: string;
    category: Translations | null;
    state: PostState;
    is_featured: boolean;
    banner_url: string | null;
    excerpt: Translations;
    author_name: Translations;
    tags: string[];
    published_at: string | null;
    updated_at: string;
}

type PostsResponse = Paginated<PostRow> & {
    counts: { states: Record<PostState, number>; categories: Record<string, number>; featured: number };
};

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<PostRow, PostsResponse>('/posts', { category_id: '', state: '', featured: '', sort: '' });
const categories = ref<PostCategory[]>([]);
const current = ref<PostRow | null>(null);
const drawerOpen = ref(false);
const previewing = ref(false);
const createOpen = ref(false);
const categoriesOpen = ref(false);
const createForm = useForm({ title: {} as Translations, category_id: '' as number | '' });

const stateTone = { draft: 'neutral', scheduled: 'warning', published: 'success' } as const;
const stateLabel = { draft: 'Draft', scheduled: 'Scheduled', published: 'Published' } as const;

const filtersOpen = ref(false);
const counts = computed(() => list.response.value?.counts);
const total = computed(() => list.meta.value?.total ?? 0);
const activeFilters = computed(() => (['category_id', 'state', 'featured'] as const).filter((key) => list.filters[key] !== '').length);
const filtered = computed(() => Boolean(list.filters.search) || activeFilters.value > 0);

// List or grid, remembered on this device.
const VIEW_KEY = 'ahk.posts.view';
const view = ref<'list' | 'grid'>('list');
try {
    if (localStorage.getItem(VIEW_KEY) === 'grid') view.value = 'grid';
} catch {
    // Storage can be blocked; the list view is the default.
}
watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Not remembered, which is fine.
    }
});

const filterGroups = computed<FilterGroup[]>(() => {
    const states = counts.value?.states;
    const byCategory = counts.value?.categories ?? {};
    return [
        {
            key: 'state',
            label: 'Status',
            icon: CircleCheck,
            tone: '#34d399',
            options: [
                { value: '', label: 'Any status', count: states ? states.published + states.scheduled + states.draft : null },
                { value: 'published', label: 'Published', count: states?.published ?? null },
                { value: 'scheduled', label: 'Scheduled', count: states?.scheduled ?? null },
                { value: 'draft', label: 'Draft', count: states?.draft ?? null },
            ],
        },
        {
            key: 'category_id',
            label: 'Category',
            icon: FolderTree,
            tone: '#a78bfa',
            options: [{ value: '', label: 'All categories' }, ...categories.value.map((c) => ({ value: String(c.id), label: pickText(c.name, app.defaultLocale), count: byCategory[c.id] ?? 0 }))],
        },
        {
            key: 'featured',
            label: 'Featured',
            icon: Star,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Featured or not' },
                { value: 'yes', label: 'Featured only', count: counts.value?.featured ?? null },
                { value: 'no', label: 'Not featured' },
            ],
        },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#38bdf8',
            options: [
                { value: '', label: 'Newest first' },
                { value: 'updated', label: 'Recently updated' },
                { value: 'title', label: 'Title A–Z' },
            ],
        },
    ];
});

const item = (row: PostRow): PostItem => ({
    id: row.id,
    title: title(row),
    excerpt: pickText(row.excerpt, app.defaultLocale),
    slug: row.slug,
    url: row.url,
    category: row.category ? pickText(row.category, app.defaultLocale) : null,
    author: pickText(row.author_name, app.defaultLocale),
    tags: row.tags ?? [],
    state: row.state,
    is_featured: row.is_featured,
    banner_url: row.banner_url,
    published_at: row.published_at,
});

const title = (row: PostRow) => pickText(row.title, app.defaultLocale);
const categoryName = (row: PostRow) => (row.category ? pickText(row.category, app.defaultLocale) : 'No category');

async function loadMeta(): Promise<void> {
    categories.value = (await http.get<PostMeta>('/posts/meta')).data.categories;
}

function open(row: PostRow): void {
    current.value = row;
    drawerOpen.value = true;
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

function openCreate(): void {
    createForm.reset({ title: {}, category_id: categories.value[0]?.id ?? '' });
    createOpen.value = true;
}

async function create(): Promise<void> {
    const payload = { ...createForm.data, category_id: createForm.data.category_id || null };
    const response = await createForm.submit(() => http.post<{ data: { id: number } }>('/posts', payload));
    if (response) {
        createOpen.value = false;
        router.push({ name: 'post-editor', params: { id: response.data.data.id } });
    }
}

async function preview(row: PostRow): Promise<void> {
    // Open the tab right away so pop-up blockers allow it, then point it at the signed preview.
    const tab = window.open('', '_blank');
    previewing.value = true;
    try {
        const { data } = await http.get<{ url: string }>(`/posts/${row.id}/preview-url`);
        if (tab) tab.location.href = data.url;
        else window.open(data.url, '_blank', 'noopener');
        drawerOpen.value = false;
    } catch (e) {
        tab?.close();
        toast.error(errorMessage(e));
    } finally {
        previewing.value = false;
    }
}

async function remove(row: PostRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(row.title)}"?`, message: 'The post will be removed from the website. This cannot be undone.', confirmLabel: 'Delete post' }))) return;
    try {
        await http.delete(`/posts/${row.id}`);
        toast.success('Post deleted');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function categoriesChanged(): Promise<void> {
    await loadMeta();
    list.load();
}

onMounted(async () => {
    list.load();
    try {
        await loadMeta();
    } catch (e) {
        toast.error(errorMessage(e));
    }
});
</script>

<template>
    <div>
        <!-- Header with a Madinah photo -->
        <ListHero title="Blog & news" description="Guides, news and announcements. Scheduled posts appear automatically at their publish time." :icon="Newspaper" tone="#fb923c" :photo="headerPhoto" photo-position="70% 45%">
            <template #actions>
                <AppButton v-if="auth.can('blog.update')" variant="secondary" :icon="FolderTree" @click="categoriesOpen = true">Categories</AppButton>
                <AppButton v-if="auth.can('blog.create')" :icon="Plus" @click="openCreate">New post</AppButton>
            </template>
        </ListHero>

        <!-- Search, filters and view switch in one row -->
        <ListToolbar v-model:search="list.filters.search as string" placeholder="Search posts by title" :active="activeFilters" @filters="filtersOpen = true">
            <div class="hidden rounded-xl border border-line bg-white p-1 sm:flex" role="group" aria-label="Show posts as">
                <button type="button" class="po-view" :class="{ 'is-on': view === 'grid' }" :aria-pressed="view === 'grid'" aria-label="Grid" title="Grid" @click="view = 'grid'"><LayoutGrid class="size-[18px]" /></button>
                <button type="button" class="po-view" :class="{ 'is-on': view === 'list' }" :aria-pressed="view === 'list'" aria-label="List" title="List" @click="view = 'list'"><List class="size-[18px]" /></button>
            </div>
        </ListToolbar>

        <!-- Posts -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4" :class="{ 'sm:grid-cols-2 xl:grid-cols-3': view === 'grid' }" aria-busy="true" aria-label="Loading posts">
            <div v-for="n in 3" :key="n" class="card animate-pulse" :class="view === 'list' ? 'h-[340px] lg:h-[150px]' : 'h-[380px]'" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Newspaper"
                :title="filtered ? 'No posts match' : 'No posts yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Write a guide or share news with your pilgrims.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('blog.create')" :icon="Plus" @click="openCreate">New post</AppButton>
            </EmptyState>
        </div>
        <div v-else :key="view" class="po-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="po-grid" :class="`is-${view}`">
                <PostListItem
                    v-for="(row, i) in list.rows.value"
                    :key="row.id"
                    :post="item(row)"
                    :layout="view"
                    :index="i"
                    :can-delete="auth.can('blog.delete')"
                    @open="open(row)"
                    @preview="preview(row)"
                    @remove="remove(row)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="posts" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['post', 'posts']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="list.clear()"
        />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? title(current) : ''" :description="current ? categoryName(current) : ''" :icon="Newspaper">
            <template v-if="current">
                <img v-if="current.banner_url" :src="current.banner_url" alt="" class="mb-5 aspect-[16/9] w-full rounded-2xl object-cover" />
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="stateTone[current.state]">{{ stateLabel[current.state] }}</AppBadge>
                    <AppBadge v-if="current.is_featured" tone="gold"><Star class="size-3 fill-current" />Featured</AppBadge>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><FolderTree class="size-4 text-violet-600" />Category</dt>
                    <dd>{{ categoryName(current) }}</dd>
                    <dt class="flex items-center gap-2"><CalendarClock class="size-4 text-sky-600" />{{ current.state === 'scheduled' ? 'Goes live' : 'Published' }}</dt>
                    <dd>{{ current.published_at ? formatDate(current.published_at, true) : 'Not yet' }}</dd>
                    <dt class="flex items-center gap-2"><Link2 class="size-4 text-emerald-600" />Address</dt>
                    <dd class="break-all">/blog/{{ current.slug }}</dd>
                    <dt>Updated</dt>
                    <dd>{{ formatDate(current.updated_at, true) }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'post-editor', params: { id: current.id } }" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit post
                    </RouterLink>
                    <a v-if="current.state === 'published'" :href="current.url" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-else type="button" class="dw-action" :disabled="previewing" @click="preview(current)">
                        <span class="dw-action-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                    </button>
                    <button v-if="auth.can('blog.delete')" type="button" class="dw-action is-danger" @click="remove(current)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete post
                    </button>
                </div>
            </template>
        </AppDrawer>

        <AppModal v-model:open="createOpen" title="New post" description="Saved as a draft. You'll write the content next." :persistent="createForm.processing">
            <form id="new-post" class="space-y-4" novalidate @submit.prevent="create">
                <TranslatableInput v-model="createForm.data.title" label="Title" required :errors="createForm.errors" error-key="title" hint="The web address is created from the English title." />
                <FormField label="Category" for="new-post-category" :error="createForm.error('category_id')">
                    <select id="new-post-category" v-model="createForm.data.category_id" class="control">
                        <option value="">No category</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ pickText(category.name, app.defaultLocale) }}</option>
                    </select>
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="new-post" :loading="createForm.processing">Create and edit</AppButton>
            </template>
        </AppModal>

        <CategoriesModal v-model:open="categoriesOpen" :categories="categories" @changed="categoriesChanged" />
    </div>
</template>

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.po-list {
    container: po-list / inline-size;
}

.po-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}

@container po-list (min-width: 860px) {
    .po-grid.is-list {
        grid-template-columns: minmax(0, 1fr);
    }
}

.po-view {
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
