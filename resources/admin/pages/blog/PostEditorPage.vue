<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Eye, ExternalLink } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { useErrorClearing } from '@admin/composables/useErrorClearing';
import MediaField from '@admin/components/media/MediaField.vue';
import BlocksEditor from '@admin/pages/content/builder/BlocksEditor.vue';
import TagsInput from './TagsInput.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http, validationErrors, type ValidationErrors } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';
import type { PostMeta, PostState } from './types';

interface PostData {
    id: number;
    title: Translations;
    slug: string;
    url: string;
    excerpt: Translations;
    category_id: number | null;
    banner_id: number | null;
    author_name: Translations;
    author_image_id: number | null;
    tags: string[];
    blocks: never[];
    status: 'draft' | 'published';
    state: PostState;
    published_at: string | null;
    is_featured: boolean;
    seo: { title: Translations; description: Translations; image: number | null; noindex: boolean };
}

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const meta = ref<PostMeta | null>(null);
const data = ref<PostData | null>(null);
const errors = ref<ValidationErrors>({});
useErrorClearing(() => data.value, errors);
const saving = ref(false);
const saved = ref('');

const canEdit = computed(() => auth.can('blog.update'));
const dirty = computed(() => saved.value !== '' && JSON.stringify(data.value) !== saved.value);
const stateTone = { draft: 'neutral', scheduled: 'warning', published: 'success' } as const;

const publishHint = computed(() => {
    // Past dates are allowed on purpose: older articles keep the date they first appeared.
    const future = Boolean(data.value?.published_at) && new Date(data.value!.published_at!) > new Date();
    if (!data.value || data.value.status === 'draft') {
        return future
            ? 'Drafts are only visible to admins. Once published, it appears automatically at this time.'
            : 'Drafts are only visible to admins. A future date schedules the post; a past date back-dates an older article.';
    }
    if (!data.value.published_at) return 'Publishes now when you save.';
    return future ? 'Scheduled: appears automatically at this time.' : 'On the website now, shown with this date (a past date back-dates an older article).';
});

function apply(payload: PostData): void {
    data.value = JSON.parse(JSON.stringify(payload));
    saved.value = JSON.stringify(data.value);
}

async function load(): Promise<void> {
    try {
        const [post, info] = await Promise.all([http.get<{ data: PostData }>(`/posts/${route.params.id}`), http.get<PostMeta>('/posts/meta')]);
        meta.value = info.data;
        apply(post.data.data);
    } catch (e) {
        toast.error(errorMessage(e));
        router.replace('/admin/blog');
    }
}

async function save(): Promise<boolean> {
    if (!data.value) return false;
    saving.value = true;
    errors.value = {};

    try {
        const response = await http.put<{ data: PostData }>(`/posts/${data.value.id}`, data.value);
        const slugChanged = response.data.data.slug !== JSON.parse(saved.value).slug;
        apply(response.data.data);
        toast.success('Post saved', slugChanged ? 'The web address changed; the old one redirects automatically.' : undefined);
        return true;
    } catch (e) {
        const validation = validationErrors(e);
        if (validation) {
            errors.value = validation;
            const first = Object.keys(validation)[0] ?? '';
            toast.error('Please fix the highlighted fields', validation[first]?.[0]);
        } else {
            toast.error('Could not save the post', errorMessage(e));
        }
        return false;
    } finally {
        saving.value = false;
    }
}

async function preview(): Promise<void> {
    if (!data.value) return;
    if (dirty.value) {
        const ok = await confirm({ title: 'Save before previewing?', message: 'The preview shows the last saved version.', confirmLabel: 'Save and preview', tone: 'primary' });
        if (!ok || !(await save())) return;
    }
    const tabRef = window.open('', '_blank');
    try {
        const response = await http.get<{ url: string }>(`/posts/${data.value.id}/preview-url`);
        if (tabRef) tabRef.location.href = response.data.url;
    } catch (e) {
        tabRef?.close();
        toast.error(errorMessage(e));
    }
}

function slugify(value: string): string {
    return value.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        if (canEdit.value && !saving.value) save();
    }
}

function beforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) event.preventDefault();
}

onBeforeRouteLeave(async () => !dirty.value || confirm({ title: 'Leave without saving?', message: 'Your changes to this post have not been saved.', confirmLabel: 'Leave' }));

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', beforeUnload);
    load();
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<template>
    <div v-if="!data || !meta" class="flex justify-center py-24 text-brand"><AppSpinner class="size-6" /></div>

    <div v-else>
        <div class="sticky top-16 z-20 -mx-4 mb-6 border-b border-line bg-canvas/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center gap-3">
                <RouterLink to="/admin/blog" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white hover:text-ink" aria-label="Back to posts"><ArrowLeft class="size-5" /></RouterLink>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="min-w-0 truncate text-[17px] font-bold text-ink">{{ pickText(data.title, app.defaultLocale) || 'Untitled post' }}</h1>
                        <AppBadge :tone="stateTone[data.state]">{{ data.state }}</AppBadge>
                    </div>
                    <a v-if="data.state === 'published'" :href="data.url" target="_blank" rel="noopener" class="inline-flex max-w-full items-center gap-1 text-[12px] text-ink-muted hover:text-brand"><span class="truncate">/blog/{{ data.slug }}</span> <ExternalLink class="size-3 shrink-0" /></a>
                    <p v-else class="truncate text-[12px] text-ink-muted">/blog/{{ data.slug }}</p>
                </div>
                <span v-if="dirty" class="hidden text-[13px] font-semibold text-amber-700 sm:inline">Unsaved changes</span>
                <AppButton variant="secondary" :icon="Eye" @click="preview">Preview</AppButton>
                <AppButton v-if="canEdit" :loading="saving" :disabled="!dirty" @click="save">Save</AppButton>
            </div>
        </div>

        <fieldset :disabled="!canEdit" class="m-0 grid min-w-0 grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-6">
                <div class="card space-y-5 p-6">
                    <TranslatableInput v-model="data.title" label="Title" required :errors="errors" error-key="title" />
                    <TranslatableInput v-model="data.excerpt" label="Summary" multiline :rows="2" :errors="errors" error-key="excerpt" hint="One or two sentences shown on post cards and under the title." />
                </div>

                <div class="card p-6">
                    <BlocksEditor v-model="data.blocks" label="Content" :errors="errors" path="blocks" :max="60" />
                </div>

                <div class="card space-y-4 p-6">
                    <h2 class="text-[15px] font-semibold text-ink">Search & sharing</h2>
                    <TranslatableInput v-model="data.seo.title" label="SEO title" placeholder="Defaults to the post title" :errors="errors" error-key="seo.title" />
                    <TranslatableInput v-model="data.seo.description" label="SEO description" multiline :rows="2" placeholder="Defaults to the summary" :errors="errors" error-key="seo.description" />
                    <MediaField v-model="data.seo.image" label="Social share image" usage="og" aspect="1200 / 630" hint="Defaults to the banner." />
                    <div class="rounded-lg border border-line p-3.5">
                        <AppSwitch v-model="data.seo.noindex" label="Hide from search engines" />
                    </div>
                </div>
            </div>

            <aside class="min-w-0 space-y-6">
                <div class="card space-y-4 p-5">
                    <FormField label="Status" for="post-status">
                        <div class="grid grid-cols-2 gap-2">
                            <label v-for="option in [{ v: 'draft', l: 'Draft', t: 'Hidden' }, { v: 'published', l: 'Publish', t: 'On the website' }]" :key="option.v"
                                   class="cursor-pointer rounded-lg border p-3" :class="data.status === option.v ? 'border-brand bg-brand-soft' : 'border-line'">
                                <input v-model="data.status" type="radio" :value="option.v" class="sr-only" />
                                <span class="block text-sm font-semibold" :class="data.status === option.v ? 'text-brand' : 'text-ink'">{{ option.l }}</span>
                                <span class="block text-[12px] text-ink-soft">{{ option.t }}</span>
                            </label>
                        </div>
                    </FormField>
                    <FormField label="Publish date" for="post-date" :error="errors.published_at?.[0]" :hint="publishHint">
                        <AppDatePicker id="post-date" v-model="data.published_at" mode="datetime" placeholder="Publish now" :disabled="!canEdit" :invalid="Boolean(errors.published_at)" />
                    </FormField>
                    <div class="rounded-lg border border-line p-3.5">
                        <AppSwitch v-model="data.is_featured" label="Featured" description="Leads the blog page when it is the newest featured post." />
                    </div>
                </div>

                <div class="card space-y-4 p-5">
                    <FormField label="Category" for="post-category" :error="errors.category_id?.[0]">
                        <select id="post-category" v-model="data.category_id" class="control">
                            <option :value="null">No category</option>
                            <option v-for="category in meta.categories" :key="category.id" :value="category.id">{{ pickText(category.name, app.defaultLocale) }}</option>
                        </select>
                    </FormField>
                    <TagsInput v-model="data.tags" label="Tags" :suggestions="meta.tags" :error="errors.tags?.[0]" />
                    <FormField label="Web address" for="post-slug" :error="errors.slug?.[0]" required>
                        <div class="flex items-stretch overflow-hidden rounded-lg border border-line bg-white focus-within:border-brand">
                            <span class="flex items-center border-r border-line bg-canvas px-2.5 text-[13px] text-ink-muted">/blog/</span>
                            <input id="post-slug" v-model="data.slug" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none" @blur="data.slug = slugify(data.slug)" />
                        </div>
                    </FormField>
                </div>

                <div class="card space-y-4 p-5">
                    <MediaField v-model="data.banner_id" label="Banner image" usage="general" aspect="16 / 9" :error="errors.banner_id?.[0]" hint="Top of the post and on post cards." />
                    <TranslatableInput v-model="data.author_name" label="Author name" :errors="errors" error-key="author_name" placeholder="Defaults to the agency name" />
                    <MediaField v-model="data.author_image_id" label="Author photo" usage="avatar" aspect="1 / 1" />
                </div>
            </aside>
        </fieldset>
    </div>
</template>
