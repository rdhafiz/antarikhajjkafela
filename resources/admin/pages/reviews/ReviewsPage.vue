<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ChevronRight, Eye, EyeOff, GripVertical, MessageSquareQuote, Pencil, Plus, Star, Trash2, Video } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { initials } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface ReviewRow {
    id: number;
    name: Translations;
    intro: Translations;
    text: Translations;
    video_url: string | null;
    photo_id: number | null;
    rating: number | null;
    is_featured: boolean;
    is_published: boolean;
}

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const reviews = ref<ReviewRow[]>([]);
const loading = ref(true);
const open = ref(false);
const editing = ref<ReviewRow | null>(null);
const current = ref<ReviewRow | null>(null);
const drawerOpen = ref(false);

const search = ref('');
const filters = reactive({ type: '', featured: '', published: '' });
const term = computed(() => search.value.trim().toLowerCase());
const active = computed(() => Object.values(filters).filter((value) => value !== '').length);
const filtering = computed(() => !!term.value || active.value > 0);
const canSort = computed(() => auth.can('reviews.update') && !filtering.value);

function hasText(review: ReviewRow): boolean {
    return Object.values(review.text ?? {}).some((value) => !!value?.trim());
}

function matches(review: ReviewRow): boolean {
    if (filters.type === 'text' && !hasText(review)) return false;
    if (filters.type === 'video' && !review.video_url) return false;
    if (filters.featured && review.is_featured !== (filters.featured === 'yes')) return false;
    if (filters.published && review.is_published !== (filters.published === 'yes')) return false;
    if (!term.value) return true;

    return [review.name, review.intro, review.text].some((field) => Object.values(field ?? {}).some((value) => (value ?? '').toLowerCase().includes(term.value)));
}

const visibleCount = computed(() => reviews.value.filter(matches).length);
const summary = computed(() => {
    const total = `${reviews.value.length} ${reviews.value.length === 1 ? 'review' : 'reviews'}`;
    return filtering.value ? `${visibleCount.value} of ${total}` : total;
});

function clearFilters(): void {
    Object.assign(filters, { type: '', featured: '', published: '' });
}

function clearAll(): void {
    clearFilters();
    search.value = '';
}

function preview(review: ReviewRow): void {
    current.value = review;
    drawerOpen.value = true;
}

function youtubeId(url: string | null): string | null {
    if (!url) return null;
    const match = url.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})/);
    return match?.[1] ?? null;
}

const blank = () => ({ name: {} as Translations, intro: {} as Translations, text: {} as Translations, video_url: '' as string | null, photo_id: null as number | null, rating: 5 as number | null, is_featured: false, is_published: true });
const form = useForm(blank());

async function load(): Promise<void> {
    try {
        reviews.value = (await http.get<{ data: ReviewRow[] }>('/reviews')).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

function edit(review: ReviewRow | null): void {
    drawerOpen.value = false;
    editing.value = review;
    form.reset(review ? JSON.parse(JSON.stringify(review)) : blank());
    open.value = true;
}

async function save(): Promise<void> {
    const target = editing.value;
    const response = await form.submit(() => (target ? http.put(`/reviews/${target.id}`, form.data) : http.post('/reviews', form.data)));
    if (response) {
        open.value = false;
        toast.success(target ? 'Review updated' : 'Review added');
        load();
    }
}

async function remove(review: ReviewRow): Promise<void> {
    if (!(await confirm({ title: `Delete the review by ${pickText(review.name)}?`, message: 'This cannot be undone.', confirmLabel: 'Delete' }))) return;
    try {
        await http.delete(`/reviews/${review.id}`);
        toast.success('Review deleted');
        drawerOpen.value = false;
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function saveOrder(): Promise<void> {
    try {
        await http.put('/reviews/reorder', { ids: reviews.value.map((r) => r.id) });
        toast.success('Order saved');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Reviews" description="Pilgrim testimonials shown on the Reviews page and in testimonial sections. Featured reviews come first; drag to set the order.">
            <template #actions>
                <AppButton v-if="auth.can('reviews.create')" :icon="Plus" @click="edit(null)">Add review</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="search" placeholder="Search reviews by name or text" :active="active" :summary="reviews.length ? summary : ''" @clear="clearFilters">
            <select v-model="filters.type" class="control" aria-label="Filter by type">
                <option value="">Any type</option>
                <option value="text">Written</option>
                <option value="video">Video</option>
            </select>
            <select v-model="filters.featured" class="control" aria-label="Filter featured reviews">
                <option value="">Featured or not</option>
                <option value="yes">Featured only</option>
                <option value="no">Not featured</option>
            </select>
            <select v-model="filters.published" class="control" aria-label="Filter by visibility">
                <option value="">Shown or hidden</option>
                <option value="yes">Shown on the website</option>
                <option value="no">Hidden</option>
            </select>
        </FilterBar>

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!reviews.length || !visibleCount" class="card">
            <EmptyState v-if="reviews.length" :icon="MessageSquareQuote" title="No reviews match" description="Try another search or clear the filters.">
                <AppButton variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
            <EmptyState v-else :icon="MessageSquareQuote" title="No reviews yet" description="Add what your pilgrims say about their journey: a written review, a YouTube video, or both.">
                <AppButton v-if="auth.can('reviews.create')" :icon="Plus" @click="edit(null)">Add review</AppButton>
            </EmptyState>
        </div>
        <template v-else>
            <p v-if="filtering && auth.can('reviews.update')" class="mb-3 px-0.5 text-[13px] text-ink-muted">Clear the search and filters to drag reviews into a new order.</p>
            <VueDraggable v-model="reviews" handle=".drag" :animation="160" :disabled="!canSort" class="space-y-3" @end="saveOrder">
                <div
                    v-for="review in reviews"
                    v-show="matches(review)"
                    :key="review.id"
                    class="card flex cursor-pointer items-start gap-3 p-3 transition-shadow hover:shadow-md sm:p-4"
                    :class="{ 'opacity-60': !review.is_published }"
                    @click="preview(review)"
                >
                    <button v-if="canSort" type="button" class="drag mt-1.5 flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas" aria-label="Drag to reorder" @click.stop><GripVertical class="size-4" /></button>
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand text-[14px] font-semibold text-gold" aria-hidden="true">{{ initials(pickText(review.name, app.defaultLocale)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                            <span class="min-w-0 break-words">{{ pickText(review.name, app.defaultLocale) }}</span>
                            <AppBadge v-if="review.is_featured" tone="gold">Featured</AppBadge>
                            <AppBadge v-if="!review.is_published" tone="neutral"><EyeOff class="size-3" />Hidden</AppBadge>
                        </p>
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[12px] text-ink-muted">
                            <span v-if="review.rating" class="inline-flex items-center gap-0.5 text-gold" :aria-label="`${review.rating} stars`">
                                <Star v-for="i in review.rating" :key="i" class="size-3 fill-current" />
                            </span>
                            <span v-if="pickText(review.intro, app.defaultLocale)" class="min-w-0 break-words">{{ pickText(review.intro, app.defaultLocale) }}</span>
                            <span v-if="review.video_url" class="inline-flex items-center gap-1"><Video class="size-3.5" /> Video</span>
                        </p>
                        <p v-if="pickText(review.text, app.defaultLocale)" class="mt-1.5 line-clamp-2 text-[13px] break-words text-ink-soft">{{ pickText(review.text, app.defaultLocale) }}</p>
                    </div>
                    <button type="button" class="dt-open mt-1.5 shrink-0" :aria-label="`Open review by ${pickText(review.name, app.defaultLocale)}`" @click.stop="preview(review)"><ChevronRight class="size-4" /></button>
                </div>
            </VueDraggable>
        </template>

        <!-- Preview and actions -->
        <AppDrawer
            v-model:open="drawerOpen"
            :title="current ? pickText(current.name, app.defaultLocale) : ''"
            :description="current ? pickText(current.intro, app.defaultLocale) || undefined : undefined"
            :icon="MessageSquareQuote"
            width="lg"
        >
            <template v-if="current">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="current.is_published ? 'success' : 'neutral'">
                        <component :is="current.is_published ? Eye : EyeOff" class="size-3" />{{ current.is_published ? 'Shown on the website' : 'Hidden' }}
                    </AppBadge>
                    <AppBadge v-if="current.is_featured" tone="gold"><Star class="size-3 fill-current" />Featured</AppBadge>
                    <AppBadge v-if="current.video_url" tone="brand"><Video class="size-3" />Video</AppBadge>
                </div>

                <div v-if="current.rating" class="mt-4 flex items-center gap-0.5 text-gold" :aria-label="`${current.rating} stars`">
                    <Star v-for="i in current.rating" :key="i" class="size-4 fill-current" />
                </div>

                <blockquote v-if="pickText(current.text, app.defaultLocale)" class="mt-4 rounded-2xl bg-canvas p-4 text-[14px] leading-relaxed break-words whitespace-pre-line text-ink">
                    {{ pickText(current.text, app.defaultLocale) }}
                </blockquote>

                <div v-if="youtubeId(current.video_url)" class="mt-4 aspect-video overflow-hidden rounded-2xl bg-ink">
                    <iframe
                        :src="`https://www.youtube-nocookie.com/embed/${youtubeId(current.video_url)}`"
                        :title="`Video review by ${pickText(current.name, app.defaultLocale)}`"
                        class="size-full"
                        loading="lazy"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
                <a v-else-if="current.video_url" :href="current.video_url" target="_blank" rel="noopener" class="mt-4 block text-[13px] break-all text-brand hover:underline">{{ current.video_url }}</a>

                <template v-if="auth.can('reviews.update') || auth.can('reviews.delete')">
                    <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                    <div class="-mx-1 space-y-1">
                        <button v-if="auth.can('reviews.update')" type="button" class="dw-action" @click="edit(current)">
                            <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit review
                        </button>
                        <button v-if="auth.can('reviews.delete')" type="button" class="dw-action is-danger" @click="remove(current)">
                            <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete review
                        </button>
                    </div>
                </template>
            </template>
        </AppDrawer>

        <AppModal v-model:open="open" :title="editing ? 'Edit review' : 'Add review'" size="lg" :persistent="form.processing">
            <form id="review-form" class="space-y-4" novalidate @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <TranslatableInput v-model="form.data.name" label="Name" required :errors="form.errors" error-key="name" />
                    <TranslatableInput v-model="form.data.intro" label="Short intro" :errors="form.errors" error-key="intro" placeholder="Hajj 2025 pilgrim, Dhaka" />
                </div>
                <TranslatableInput v-model="form.data.text" label="Review" multiline :rows="5" :errors="form.errors" error-key="text" hint="Add the written review, a video, or both." />
                <FormField label="YouTube video" for="review-video" :error="form.error('video_url')" hint="Optional. Paste the link of a video review.">
                    <input id="review-video" v-model="form.data.video_url" type="url" class="control" placeholder="https://www.youtube.com/watch?v=…" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <MediaField v-model="form.data.photo_id" label="Photo" usage="avatar" aspect="1 / 1" hint="Optional. Initials are shown without a photo." />
                    <div class="space-y-4">
                        <FormField label="Rating" for="review-rating" :error="form.error('rating')">
                            <div id="review-rating" class="flex items-center gap-1" role="radiogroup" aria-label="Rating">
                                <button v-for="i in 5" :key="i" type="button" role="radio" :aria-checked="form.data.rating === i" :aria-label="`${i} star${i > 1 ? 's' : ''}`"
                                        class="rounded p-0.5 text-gold transition-transform hover:scale-110" @click="form.data.rating = i">
                                    <Star class="size-6" :class="(form.data.rating ?? 0) >= i ? 'fill-current' : 'text-line'" />
                                </button>
                                <button v-if="form.data.rating" type="button" class="ml-2 text-[12px] font-medium text-ink-muted hover:text-ink" @click="form.data.rating = null">No rating</button>
                            </div>
                        </FormField>
                        <div class="space-y-3 rounded-lg border border-line p-3.5">
                            <AppSwitch v-model="form.data.is_featured" label="Featured" description="Shown first and in 'featured reviews' sections." />
                            <AppSwitch v-model="form.data.is_published" label="Show on the website" />
                        </div>
                    </div>
                </div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
                <AppButton type="submit" form="review-form" :loading="form.processing">Save</AppButton>
            </template>
        </AppModal>
    </div>
</template>
