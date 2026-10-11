<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Images, Search, Upload } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import MediaDetailsModal from '@admin/components/media/MediaDetailsModal.vue';
import MediaDropzone from '@admin/components/media/MediaDropzone.vue';
import MediaGrid from '@admin/components/media/MediaGrid.vue';
import { useMediaList } from '@admin/composables/useMediaList';
import { fetchUsages } from '@admin/lib/media';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Media, MediaUsage } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();
const list = useMediaList();

const usages = ref<MediaUsage[]>([]);
const mode = ref<'automatic' | 'manual'>('automatic');
const uploadUsage = ref('general');
const showUploader = ref(false);
const detailsOpen = ref(false);
const current = ref<Media | null>(null);

function open(media: Media): void {
    current.value = media;
    detailsOpen.value = true;
}

function uploaded(media: Media): void {
    list.prepend(media);
    toast.success('Image uploaded', media.original_name);
}

onMounted(async () => {
    list.load();
    const config = await fetchUsages(true);
    usages.value = config.data;
    mode.value = config.mode;
});
</script>

<template>
    <div>
        <PageHeader title="Media library" description="Every image is converted to WebP and resized for where it's used.">
            <template #actions>
                <AppBadge :tone="mode === 'manual' ? 'gold' : 'success'">
                    {{ mode === 'manual' ? 'Manual optimization' : 'Automatic optimization' }}
                </AppBadge>
                <AppButton v-if="auth.can('media.create')" :icon="Upload" @click="showUploader = !showUploader">Upload</AppButton>
            </template>
        </PageHeader>

        <Transition
            enter-from-class="opacity-0 -translate-y-1"
            enter-active-class="transition duration-150"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-100"
        >
            <div v-if="showUploader" class="card mb-6 p-5">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[15px] font-bold text-ink">Upload images</p>
                        <p class="text-[13px] text-ink-muted">Choose where the images will be used so they get the right sizes.</p>
                    </div>
                    <label class="sm:w-64">
                        <span class="label">Use as</span>
                        <select v-model="uploadUsage" class="control">
                            <option v-for="usage in usages" :key="usage.key" :value="usage.key">{{ usage.label }}</option>
                        </select>
                    </label>
                </div>
                <MediaDropzone :usage="uploadUsage" @uploaded="uploaded" />
            </div>
        </Transition>

        <div class="card p-4">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search images</span>
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                    <input v-model="list.filters.search" type="search" placeholder="Search by file name" class="control pl-9" />
                </label>
                <label class="sm:w-56">
                    <span class="sr-only">Filter by usage</span>
                    <select v-model="list.filters.usage" class="control">
                        <option value="">All usages</option>
                        <option v-for="usage in usages" :key="usage.key" :value="usage.key">{{ usage.label }}</option>
                    </select>
                </label>
            </div>

            <div v-if="list.loading.value && !list.items.value.length" class="flex justify-center py-16 text-brand">
                <AppSpinner class="size-6" />
            </div>
            <EmptyState
                v-else-if="!list.items.value.length"
                :icon="Images"
                title="No images yet"
                :description="list.filters.search || list.filters.usage ? 'Nothing matches your search.' : 'Upload images to use them across the website.'"
            />
            <MediaGrid v-else :items="list.items.value" :class="{ 'opacity-60': list.loading.value }" @select="open" />
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" @change="list.goTo" />

        <MediaDetailsModal v-model:open="detailsOpen" :media="current" @updated="list.replace" @deleted="list.remove" />
    </div>
</template>
