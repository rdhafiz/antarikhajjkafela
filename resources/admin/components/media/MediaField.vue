<script setup lang="ts">
import { ref, watch } from 'vue';
import { ImagePlus, RefreshCw, X } from 'lucide-vue-next';
import FormField from '@admin/components/ui/FormField.vue';
import MediaPickerModal from './MediaPickerModal.vue';
import { http } from '@admin/lib/http';
import type { Media } from '@admin/types';

/**
 * Form field holding a media id, with a preview and the picker modal.
 * Pass `preview` when the parent already has the media object.
 */
const model = defineModel<number | null>({ default: null });
const props = defineProps<{
    label: string;
    usage: string;
    preview?: Media | null;
    error?: string | null;
    hint?: string;
    aspect?: string;
}>();

const pickerOpen = ref(false);
const media = ref<Media | null>(props.preview ?? null);

watch(
    model,
    async (id) => {
        if (!id) {
            media.value = null;
            return;
        }
        if (media.value?.id === id) return;

        try {
            media.value = (await http.get<{ data: Media }>(`/media/${id}`)).data.data;
        } catch {
            media.value = null;
        }
    },
    { immediate: true },
);

function select(selected: Media): void {
    media.value = selected;
    model.value = selected.id;
}
</script>

<template>
    <FormField :label="label" :error="error" :hint="hint">
        <div v-if="media" class="group relative max-w-md overflow-hidden rounded-xl ring-1 ring-line">
            <div class="flex items-center justify-center bg-[repeating-conic-gradient(#eef1f5_0_25%,#fff_0_50%)] bg-[length:16px_16px]" :style="{ aspectRatio: aspect ?? '16 / 9' }">
                <img :src="media.thumb_url ?? ''" :alt="media.alt.en ?? ''" class="max-h-full max-w-full object-contain" />
            </div>
            <div class="absolute top-2 right-2 flex gap-1.5">
                <button type="button" class="flex size-8 items-center justify-center rounded-lg bg-white/95 text-ink shadow hover:text-brand" aria-label="Change image" @click="pickerOpen = true">
                    <RefreshCw class="size-4" />
                </button>
                <button type="button" class="flex size-8 items-center justify-center rounded-lg bg-white/95 text-ink shadow hover:text-red-600" aria-label="Remove image" @click="model = null">
                    <X class="size-4" />
                </button>
            </div>
        </div>
        <button
            v-else
            type="button"
            class="flex max-h-56 w-full max-w-md flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#cfd7e2] bg-canvas/50 text-sm font-semibold text-ink-soft transition-colors hover:border-brand/50 hover:text-brand"
            :style="{ aspectRatio: aspect ?? '16 / 9' }"
            @click="pickerOpen = true"
        >
            <ImagePlus class="size-6" />
            Choose image
        </button>

        <MediaPickerModal v-model:open="pickerOpen" :usage="usage" :selected-id="model" @select="select" />
    </FormField>
</template>
