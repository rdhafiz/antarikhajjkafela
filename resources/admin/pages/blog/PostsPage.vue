<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import { ExternalLink, FolderTree, Newspaper, Pencil, Plus, Search, Star, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import CategoriesModal from './CategoriesModal.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
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

const posts = ref<PostRow[]>([]);
const meta = ref<Paginated<PostRow>['meta'] | null>(null);
const categories = ref<PostCategory[]>([]);
const loading = ref(true);
const filters = reactive({ category_id: '' as number | '', state: '', search: '', page: 1 });
const createOpen = ref(false);
const categoriesOpen = ref(false);
const createForm = useForm({ title: {} as Translations, category_id: '' as number | '' });

const stateTone = { draft: 'neutral', scheduled: 'warning', published: 'success' } as const;
const stateLabel = { draft: 'Draft', scheduled: 'Scheduled', published: 'Published' } as const;

async function loadMeta(): Promise<void> {
    categories.value = (await http.get<PostMeta>('/posts/meta')).data.categories;
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
        const { data } = await http.get<Paginated<PostRow>>('/posts', { params });
        posts.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

const searchLater = useDebounceFn(() => {
    filters.page = 1;
    load();
}, 300);

watch(() => filters.search, searchLater);
watch(() => [filters.category_id, filters.state], () => {
    filters.page = 1;
    load();
});

function goTo(page: number): void {
    filters.page = page;
    load();
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

async function remove(row: PostRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(row.title)}"?`, message: 'The post will be removed from the website. This cannot be undone.', confirmLabel: 'Delete post' }))) return;
    try {
        await http.delete(`/posts/${row.id}`);
        toast.success('Post deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function categoriesChanged(): Promise<void> {
    await loadMeta();
    load();
}

onMounted(async () => {
    try {
        await loadMeta();
    } catch (e) {
        toast.error(errorMessage(e));
    }
    load();
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

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <div class="relative sm:w-72">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                <input v-model="filters.search" type="search" class="control pl-9" placeholder="Search posts" aria-label="Search posts" />
            </div>
            <select v-model="filters.category_id" class="control sm:w-52" aria-label="Filter by category">
                <option value="">All categories</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ pickText(category.name, app.defaultLocale) }}</option>
            </select>
            <select v-model="filters.state" class="control sm:w-44" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="scheduled">Scheduled</option>
                <option value="draft">Draft</option>
            </select>
        </div>

        <div v-if="loading && !posts.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!posts.length" class="card">
            <EmptyState :icon="Newspaper" :title="filters.search || filters.category_id || filters.state ? 'No matching posts' : 'No posts yet'" description="Write a guide or share news with your pilgrims.">
                <AppButton v-if="auth.can('blog.create')" :icon="Plus" @click="openCreate">New post</AppButton>
            </EmptyState>
        </div>
        <template v-else>
            <div class="card overflow-x-auto" :class="{ 'opacity-60': loading }">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                            <th class="px-4 py-3">Post</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Publish date</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in posts" :key="row.id" class="hover:bg-canvas/60">
                            <td class="px-4 py-3">
                                <RouterLink :to="{ name: 'post-editor', params: { id: row.id } }" class="flex items-center gap-1.5 font-semibold text-ink hover:text-brand">
                                    <Star v-if="row.is_featured" class="size-3.5 shrink-0 fill-gold text-gold" aria-label="Featured" />
                                    {{ pickText(row.title, app.defaultLocale) }}
                                </RouterLink>
                                <p class="text-[12px] text-ink-muted">{{ row.category ? pickText(row.category, app.defaultLocale) : 'No category' }} · /blog/{{ row.slug }}</p>
                            </td>
                            <td class="px-4 py-3"><AppBadge :tone="stateTone[row.state]">{{ stateLabel[row.state] }}</AppBadge></td>
                            <td class="px-4 py-3 text-[13px] text-ink-soft">{{ row.published_at ? formatDate(row.published_at, row.state === 'scheduled') : '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <a v-if="row.state === 'published'" :href="row.url" target="_blank" rel="noopener" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" aria-label="View on website"><ExternalLink class="size-4" /></a>
                                    <RouterLink :to="{ name: 'post-editor', params: { id: row.id } }" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" aria-label="Edit"><Pencil class="size-4" /></RouterLink>
                                    <button v-if="auth.can('blog.delete')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label="Delete" @click="remove(row)"><Trash2 class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <AppPagination v-if="meta" :meta="meta" @change="goTo" />
        </template>

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
