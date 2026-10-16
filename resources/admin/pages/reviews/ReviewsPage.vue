<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { Eye, EyeOff, MessageSquareQuote, Pencil, PenLine, Plus, Star, Trash2, Video } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import ReviewListItem, { type ReviewItem } from './ReviewListItem.vue';
import headerPhoto from '@admin/assets/reviews-header.webp';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
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
    photo_url?: string | null;
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
const filters = reactive({ type: '', featured: '', published: '', rating: '' });
const filtersOpen = ref(false);
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
    if (filters.rating === '5' && review.rating !== 5) return false;
    if (filters.rating === '4' && (review.rating ?? 0) < 4) return false;
    if (filters.rating === 'none' && review.rating) return false;
    if (!term.value) return true;

    return [review.name, review.intro, review.text].some((field) => Object.values(field ?? {}).some((value) => (value ?? '').toLowerCase().includes(term.value)));
}

const visibleCount = computed(() => reviews.value.filter(matches).length);

/** Filters run on the page: the list is short and keeps its drag-and-drop order. */
const filterGroups = computed<FilterGroup[]>(() => {
    const all = reviews.value;
    const count = (test: (r: ReviewRow) => boolean) => all.filter(test).length;
    return [
        {
            key: 'published',
            label: 'Website',
            icon: Eye,
            tone: '#34d399',
            options: [
                { value: '', label: 'Shown or hidden', count: all.length },
                { value: 'yes', label: 'Shown', count: count((r) => r.is_published) },
                { value: 'no', label: 'Hidden', count: count((r) => !r.is_published) },
            ],
        },
        {
            key: 'type',
            label: 'Type',
            icon: PenLine,
            tone: '#38bdf8',
            options: [
                { value: '', label: 'Any' },
                { value: 'text', label: 'Written', count: count(hasText) },
                { value: 'video', label: 'Video', count: count((r) => !!r.video_url) },
            ],
        },
        {
            key: 'featured',
            label: 'Featured',
            icon: Star,
            tone: '#f472b6',
            options: [
                { value: '', label: 'Featured or not' },
                { value: 'yes', label: 'Featured only', count: count((r) => r.is_featured) },
                { value: 'no', label: 'Not featured', count: count((r) => !r.is_featured) },
            ],
        },
        {
            key: 'rating',
            label: 'Rating',
            icon: Star,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Any rating' },
                { value: '5', label: '5 stars', count: count((r) => r.rating === 5) },
                { value: '4', label: '4 stars and up', count: count((r) => (r.rating ?? 0) >= 4) },
                { value: 'none', label: 'No rating', count: count((r) => !r.rating) },
            ],
        },
    ];
});

const item = (review: ReviewRow): ReviewItem => ({
    id: review.id,
    name: pickText(review.name, app.defaultLocale),
    intro: pickText(review.intro, app.defaultLocale),
    text: pickText(review.text, app.defaultLocale),
    photo_url: review.photo_url ?? null,
    video_id: youtubeId(review.video_url),
    has_video: !!review.video_url,
    rating: review.rating,
    is_featured: review.is_featured,
    is_published: review.is_published,
});

function clearFilters(): void {
    Object.assign(filters, { type: '', featured: '', published: '', rating: '' });
}

/** Quick show/hide and featured changes from the list, through the normal update. */
async function quickUpdate(review: ReviewRow, change: Partial<ReviewRow>, message: string): Promise<void> {
    try {
        await http.put(`/reviews/${review.id}`, { ...JSON.parse(JSON.stringify(review)), ...change });
        toast.success(message);
        drawerOpen.value = false;
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

const toggle = (review: ReviewRow) =>
    quickUpdate(review, { is_published: !review.is_published }, review.is_published ? 'Review hidden from the website' : 'Review shown on the website');
const feature = (review: ReviewRow) => quickUpdate(review, { is_featured: !review.is_featured }, review.is_featured ? 'Removed from featured' : 'Marked as featured');

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
    if (!(await confirm({ title: `Delete the review by ${pickText(review.name)}?`, message: 'It goes to the Trash, where a super admin can restore it.', confirmLabel: 'Delete' }))) return;
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
        <!-- Header with a photo -->
        <ListHero title="Reviews" description="Pilgrim testimonials shown on the Reviews page and in testimonial sections. Featured reviews come first; drag to set the order." :icon="MessageSquareQuote" tone="#f472b6" :photo="headerPhoto" photo-position="40% 50%">
            <template #actions>
                <AppButton v-if="auth.can('reviews.create')" :icon="Plus" @click="edit(null)">Add review</AppButton>
            </template>
        </ListHero>

        <!-- Search and filters in one row -->
        <ListToolbar v-model:search="search" placeholder="Search reviews by name or text" :active="active" @filters="filtersOpen = true" />

        <!-- Reviews (drag the grip to reorder) -->
        <div v-if="loading" class="grid gap-3" aria-busy="true" aria-label="Loading reviews">
            <div v-for="n in 3" :key="n" class="card h-[230px] animate-pulse sm:h-[112px]" />
        </div>
        <div v-else-if="!reviews.length || !visibleCount" class="card">
            <EmptyState v-if="reviews.length" :icon="MessageSquareQuote" title="No reviews match" description="Try another search or clear the filters.">
                <AppButton variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
            <EmptyState v-else :icon="MessageSquareQuote" title="No reviews yet" description="Add what your pilgrims say about their journey: a written review, a YouTube video, or both.">
                <AppButton v-if="auth.can('reviews.create')" :icon="Plus" @click="edit(null)">Add review</AppButton>
            </EmptyState>
        </div>
        <div v-else class="rv-list">
            <p v-if="filtering && auth.can('reviews.update')" class="mb-3 px-0.5 text-[13px] text-ink-muted">Clear the search and filters to drag reviews into a new order.</p>
            <VueDraggable v-model="reviews" handle=".drag" :animation="200" :disabled="!canSort" class="grid gap-3" ghost-class="rv-ghost" @end="saveOrder">
                <ReviewListItem
                    v-for="(review, i) in reviews"
                    v-show="matches(review)"
                    :key="review.id"
                    :review="item(review)"
                    :index="i"
                    :can-sort="canSort"
                    :can-update="auth.can('reviews.update')"
                    :can-delete="auth.can('reviews.delete')"
                    @open="preview(review)"
                    @edit="edit(review)"
                    @toggle="toggle(review)"
                    @feature="feature(review)"
                    @remove="remove(review)"
                />
            </VueDraggable>
        </div>

        <!-- All filters -->
        <FilterDrawer v-model:open="filtersOpen" :filters="filters" :groups="filterGroups" :total="visibleCount" :noun="['review', 'reviews']" :can-clear="active > 0" @clear="clearFilters" />

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
                        <button v-if="auth.can('reviews.update')" type="button" class="dw-action" @click="feature(current)">
                            <span class="dw-action-icon" style="--tone: #f472b6"><Star class="size-4" /></span>{{ current.is_featured ? 'Remove from featured' : 'Mark as featured' }}
                        </button>
                        <button v-if="auth.can('reviews.update')" type="button" class="dw-action" @click="toggle(current)">
                            <span class="dw-action-icon" style="--tone: #34d399"><component :is="current.is_published ? EyeOff : Eye" class="size-4" /></span>{{ current.is_published ? 'Hide from the website' : 'Show on the website' }}
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

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.rv-list {
    container: rv-list / inline-size;
}

/* The spot a dragged review will drop into. */
.rv-list :deep(.rv-ghost) {
    opacity: 0.45;
    outline: 2px dashed color-mix(in oklab, #f472b6 60%, white);
    outline-offset: -2px;
}
</style>
