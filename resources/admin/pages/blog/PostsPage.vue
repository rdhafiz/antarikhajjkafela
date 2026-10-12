<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { CalendarClock, ChevronRight, Eye, ExternalLink, FolderTree, Link2, Newspaper, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import CategoriesModal from './CategoriesModal.vue';
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
    published_at: string | null;
    updated_at: string;
}

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<PostRow>('/posts', { category_id: '', state: '' });
const categories = ref<PostCategory[]>([]);
const current = ref<PostRow | null>(null);
const drawerOpen = ref(false);
const previewing = ref(false);
const createOpen = ref(false);
const categoriesOpen = ref(false);
const createForm = useForm({ title: {} as Translations, category_id: '' as number | '' });

const stateTone = { draft: 'neutral', scheduled: 'warning', published: 'success' } as const;
const stateLabel = { draft: 'Draft', scheduled: 'Scheduled', published: 'Published' } as const;

const filtered = computed(() => Boolean(list.filters.search || list.active.value));
const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'post' : 'posts'}` : ''));

const title = (row: PostRow) => pickText(row.title, app.defaultLocale);
const categoryName = (row: PostRow) => (row.category ? pickText(row.category, app.defaultLocale) : 'No category');
const publishDate = (row: PostRow) => (row.published_at ? formatDate(row.published_at, row.state === 'scheduled') : '—');

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
        <PageHeader title="Blog & news" description="Guides, news and announcements. Scheduled posts appear automatically at their publish time.">
            <template #actions>
                <AppButton v-if="auth.can('blog.update')" variant="secondary" :icon="FolderTree" @click="categoriesOpen = true">Categories</AppButton>
                <AppButton v-if="auth.can('blog.create')" :icon="Plus" @click="openCreate">New post</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="list.filters.search as string" placeholder="Search posts by title" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.category_id" class="control" aria-label="Filter by category">
                <option value="">All categories</option>
                <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ pickText(category.name, app.defaultLocale) }}</option>
            </select>
            <select v-model="list.filters.state" class="control" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="scheduled">Scheduled</option>
                <option value="draft">Draft</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="Newspaper"
                :title="filtered ? 'No posts match' : 'No posts yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Write a guide or share news with your pilgrims.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('blog.create')" :icon="Plus" @click="openCreate">New post</AppButton>
            </EmptyState>
            <table v-else class="dt" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Publish date</th>
                        <th class="dt-trigger"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in list.rows.value" :key="row.id" class="dt-row" @click="open(row)">
                        <td>
                            <p class="flex items-center gap-1.5 font-semibold text-ink">
                                <Star v-if="row.is_featured" class="size-3.5 shrink-0 fill-gold text-gold" aria-label="Featured" />
                                {{ title(row) }}
                            </p>
                            <p class="mt-0.5 text-[12.5px] break-all text-ink-muted">/blog/{{ row.slug }}</p>
                        </td>
                        <td data-label="Category" class="text-[13px] text-ink-soft">{{ categoryName(row) }}</td>
                        <td data-label="Status"><AppBadge :tone="stateTone[row.state]">{{ stateLabel[row.state] }}</AppBadge></td>
                        <td data-label="Publish date" class="text-[13px] whitespace-nowrap text-ink-soft">{{ publishDate(row) }}</td>
                        <td class="dt-trigger">
                            <button type="button" class="dt-open" :aria-label="`Open ${title(row)}`" @click.stop="open(row)"><ChevronRight class="size-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="posts" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? title(current) : ''" :description="current ? categoryName(current) : ''" :icon="Newspaper">
            <template v-if="current">
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
                    <RouterLink :to="{ name: 'post-editor', params: { id: current.id } }" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit post
                    </RouterLink>
                    <a v-if="current.state === 'published'" :href="current.url" target="_blank" rel="noopener" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-else type="button" class="ad-action" :disabled="previewing" @click="preview(current)">
                        <span class="ad-action-icon" style="--tone: #a78bfa"><Eye class="size-4" /></span>Preview the saved version
                    </button>
                    <button v-if="auth.can('blog.delete')" type="button" class="ad-action is-danger" @click="remove(current)">
                        <span class="ad-action-icon"><Trash2 class="size-4" /></span>Delete post
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
