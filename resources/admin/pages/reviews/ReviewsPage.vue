<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { EyeOff, GripVertical, MessageSquareQuote, Pencil, Plus, Star, Trash2, Video } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
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

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!reviews.length" class="card">
            <EmptyState :icon="MessageSquareQuote" title="No reviews yet" description="Add what your pilgrims say about their journey: a written review, a YouTube video, or both.">
                <AppButton v-if="auth.can('reviews.create')" :icon="Plus" @click="edit(null)">Add review</AppButton>
            </EmptyState>
        </div>
        <VueDraggable v-else v-model="reviews" handle=".drag" :animation="160" :disabled="!auth.can('reviews.update')" class="space-y-3" @end="saveOrder">
            <div v-for="review in reviews" :key="review.id" class="card flex items-start gap-3 p-3 sm:p-4" :class="{ 'opacity-60': !review.is_published }">
                <button v-if="auth.can('reviews.update')" type="button" class="drag mt-1.5 flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas" aria-label="Drag to reorder"><GripVertical class="size-4" /></button>
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand text-[14px] font-semibold text-gold">{{ initials(pickText(review.name, app.defaultLocale)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                        {{ pickText(review.name, app.defaultLocale) }}
                        <AppBadge v-if="review.is_featured" tone="gold">Featured</AppBadge>
                        <AppBadge v-if="!review.is_published" tone="neutral"><EyeOff class="mr-1 inline size-3" />Hidden</AppBadge>
                    </p>
                    <p class="flex items-center gap-2 text-[12px] text-ink-muted">
                        <span v-if="review.rating" class="inline-flex items-center gap-0.5 text-gold" :aria-label="`${review.rating} stars`">
                            <Star v-for="i in review.rating" :key="i" class="size-3 fill-current" />
                        </span>
                        <span v-if="pickText(review.intro, app.defaultLocale)">{{ pickText(review.intro, app.defaultLocale) }}</span>
                        <span v-if="review.video_url" class="inline-flex items-center gap-1"><Video class="size-3.5" /> Video</span>
                    </p>
                    <p v-if="pickText(review.text, app.defaultLocale)" class="mt-1.5 line-clamp-2 text-[13px] text-ink-soft">{{ pickText(review.text, app.defaultLocale) }}</p>
                </div>
                <button v-if="auth.can('reviews.update')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-brand" :aria-label="`Edit review by ${pickText(review.name)}`" @click="edit(review)"><Pencil class="size-4" /></button>
                <button v-if="auth.can('reviews.delete')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete review by ${pickText(review.name)}`" @click="remove(review)"><Trash2 class="size-4" /></button>
            </div>
        </VueDraggable>

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
