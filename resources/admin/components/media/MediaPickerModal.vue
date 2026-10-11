<script setup lang="ts">
import { ref, watch } from 'vue';
import { Search } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import MediaDropzone from './MediaDropzone.vue';
import MediaGrid from './MediaGrid.vue';
import { useMediaList } from '@admin/composables/useMediaList';
import { useAuthStore } from '@admin/stores/auth';
import type { Media } from '@admin/types';

/** Choose an existing image or upload a new one for a specific usage. */
const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ usage: string; selectedId?: number | null }>();
const emit = defineEmits<{ select: [media: Media] }>();

const auth = useAuthStore();
const list = useMediaList(props.usage, 18);
const chosen = ref<Media | null>(null);

watch(open, (value) => {
    if (!value) return;
    chosen.value = null;
    list.filters.usage = props.usage;
    list.load();
});

function uploaded(media: Media): void {
    list.prepend(media);
    chosen.value = media;
}

function confirm(): void {
    if (chosen.value) emit('select', chosen.value);
    open.value = false;
}
</script>

<template>
    <AppModal v-model:open="open" title="Choose an image" size="xl">
        <MediaDropzone v-if="auth.can('media.create')" :usage="usage" :multiple="false" compact class="mb-5" @uploaded="uploaded" />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Search images</span>
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                <input v-model="list.filters.search" type="search" placeholder="Search by file name" class="control pl-9" />
            </label>
            <label class="inline-flex items-center gap-2 text-[13px] font-medium text-ink-soft">
                <input
                    type="checkbox"
                    class="size-4 accent-[var(--admin-primary)]"
                    :checked="list.filters.usage === ''"
                    @change="list.filters.usage = ($event.target as HTMLInputElement).checked ? '' : usage"
                />
                Show all images
            </label>
        </div>

        <div v-if="list.loading.value && !list.items.value.length" class="flex justify-center py-12 text-brand"><AppSpinner class="size-6" /></div>
        <p v-else-if="!list.items.value.length" class="py-12 text-center text-sm text-ink-muted">No images yet. Upload one above.</p>
        <MediaGrid v-else :items="list.items.value" :selected-id="chosen?.id ?? selectedId" @select="chosen = $event" />

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" @change="list.goTo" />

        <template #footer>
            <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
            <AppButton :disabled="!chosen" @click="confirm">Use this image</AppButton>
        </template>
    </AppModal>
</template>
