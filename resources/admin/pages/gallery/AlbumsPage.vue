<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { EyeOff, Image as ImageIcon, Images, Plus, Video } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useForm } from '@admin/composables/useForm';
import { useListQuery } from '@admin/composables/useListQuery';
import { http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import type { Translations } from '@admin/types';

interface AlbumRow {
    id: number;
    title: Translations;
    slug: string;
    event_date: string | null;
    is_published: boolean;
    cover_thumb: string | null;
    images_count: number;
    videos_count: number;
}

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();

const list = useListQuery<AlbumRow>('/gallery', { status: '' }, { perPage: 12 });
const createOpen = ref(false);
const form = useForm({ title: {} as Translations, event_date: '' as string | null });

const filtered = computed(() => !!list.filters.search || list.active.value > 0);
const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'album' : 'albums'}` : ''));

function clearFilters(): void {
    list.clear();
    list.filters.search = '';
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

onMounted(() => list.load());
</script>

<template>
    <div>
        <PageHeader title="Gallery" description="Albums of photos and YouTube videos from your journeys. The newest albums are shown first.">
            <template #actions>
                <AppButton v-if="auth.can('gallery.create')" :icon="Plus" @click="openCreate">New album</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="list.filters.search as string" placeholder="Search albums by title" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.status" class="control" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="draft">Hidden</option>
            </select>
        </FilterBar>

        <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Images"
                :title="filtered ? 'No albums match' : 'No albums yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Create an album, then upload photos or add YouTube videos to it.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearFilters">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('gallery.create')" :icon="Plus" @click="openCreate">New album</AppButton>
            </EmptyState>
        </div>
        <div v-else class="grid grid-cols-[minmax(0,1fr)] gap-4 transition-opacity sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" :class="{ 'opacity-60': list.loading.value }">
            <RouterLink v-for="album in list.rows.value" :key="album.id" :to="{ name: 'album-editor', params: { id: album.id } }"
                        class="card group min-w-0 overflow-hidden transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none">
                <div class="relative aspect-[4/3] bg-canvas">
                    <img v-if="album.cover_thumb" :src="album.cover_thumb" alt="" class="size-full object-cover" loading="lazy" />
                    <div v-else class="flex size-full items-center justify-center text-ink-muted"><Images class="size-8" /></div>
                    <span v-if="!album.is_published" class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-ink/80 px-2.5 py-1 text-[11px] font-semibold text-white"><EyeOff class="size-3" /> Hidden</span>
                </div>
                <div class="p-4">
                    <p class="font-semibold break-words text-ink group-hover:text-brand">{{ pickText(album.title, app.defaultLocale) }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-3 text-[12px] text-ink-muted">
                        <span v-if="album.event_date">{{ formatDate(album.event_date) }}</span>
                        <span class="inline-flex items-center gap-1"><ImageIcon class="size-3.5 text-sky-600" aria-hidden="true" />{{ album.images_count }}<span class="sr-only"> photos</span></span>
                        <span class="inline-flex items-center gap-1"><Video class="size-3.5 text-rose-500" aria-hidden="true" />{{ album.videos_count }}<span class="sr-only"> videos</span></span>
                    </p>
                </div>
            </RouterLink>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="albums" :per-page-options="[12, 24, 48, 96]" @change="list.goTo" @per-page="list.setPerPage" />

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
