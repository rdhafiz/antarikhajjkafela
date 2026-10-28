<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ExternalLink, ImagePlus, RefreshCw, Trash2 } from 'lucide-vue-next';
import FormField from '@admin/components/ui/FormField.vue';
import MediaPickerModal from './MediaPickerModal.vue';
import { http } from '@admin/lib/http';
import { formatBytes } from '@admin/lib/format';
import type { Media } from '@admin/types';

/**
 * Form field holding a media id: a framed preview with the file's details and
 * labelled actions (replace, open, remove), or an empty drop area that opens the picker.
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
const loading = ref(false);

watch(
    model,
    async (id) => {
        if (!id) {
            media.value = null;
            return;
        }
        if (media.value?.id === id) return;

        loading.value = true;
        try {
            media.value = (await http.get<{ data: Media }>(`/media/${id}`)).data.data;
        } catch {
            media.value = null;
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

const ratio = computed(() => props.aspect ?? '16 / 9');
// Square slots (icons) stay small; wide ones use the full card width.
const square = computed(() => ratio.value.replace(/\s/g, '') === '1/1');
const format = computed(() => media.value?.mime_type.split('/')[1]?.replace('svg+xml', 'svg').toUpperCase() ?? '');
const details = computed(() => {
    const m = media.value;
    if (!m) return '';
    return [m.width && m.height ? `${m.width} × ${m.height}` : null, format.value || null, formatBytes(m.optimized_size || m.size)].filter(Boolean).join(' · ');
});

// Sharp preview: the small admin thumbnail, or the largest variant (the image the
// website actually uses for this slot) when the preview is shown bigger.
const srcset = computed(() => {
    const variants = Object.values(media.value?.variants ?? {}).filter((v) => v.url && v.width);
    if (variants.length < 2) return undefined;
    const largest = variants.reduce((a, b) => (b.width > a.width ? b : a));
    const thumb = variants.find((v) => v.url === media.value?.thumb_url);
    return [thumb && thumb !== largest ? `${thumb.url} ${thumb.width}w` : null, `${largest.url} ${largest.width}w`].filter(Boolean).join(', ');
});

function select(selected: Media): void {
    media.value = selected;
    model.value = selected.id;
}
</script>

<template>
    <FormField :label="label" :error="error" :hint="hint">
        <div v-if="media || loading" class="mf @container max-w-xl overflow-hidden rounded-2xl border border-line bg-white" :class="{ 'border-red-400': error }">
            <!-- Preview: click to replace -->
            <button
                type="button"
                class="mf-stage group relative flex w-full items-center justify-center overflow-hidden"
                :class="square ? 'p-5' : 'p-3'"
                :aria-label="`Replace ${label.toLowerCase()}`"
                @click="pickerOpen = true"
            >
                <span class="relative flex w-full items-center justify-center" :class="square ? 'max-w-[148px]' : ''" :style="{ aspectRatio: ratio }">
                    <Transition name="mf-fade" mode="out-in">
                        <span v-if="!media" key="loading" class="absolute inset-0 animate-pulse rounded-lg bg-canvas" aria-hidden="true" />
                        <img
                            v-else
                            :key="media.id"
                            :src="media.thumb_url ?? media.url ?? ''"
                            :srcset="srcset"
                            :sizes="square ? '148px' : '(max-width: 640px) 90vw, 560px'"
                            :alt="media.alt.en ?? ''"
                            :width="media.width ?? undefined"
                            :height="media.height ?? undefined"
                            class="max-h-full max-w-full rounded-lg object-contain shadow-[0_6px_18px_-6px_rgb(15_23_42/0.25)]"
                        />
                    </Transition>
                </span>
                <span class="mf-hover pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="flex items-center gap-2 rounded-full bg-white px-3.5 py-2 text-[13px] font-semibold text-ink shadow-lg"><RefreshCw class="size-4" />Replace</span>
                </span>
            </button>

            <!-- Details + actions -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5 border-t border-line px-3.5 py-3">
                <div class="min-w-0 flex-1 basis-40">
                    <p class="truncate text-[13.5px] font-semibold text-ink" :title="media?.original_name">{{ media?.original_name ?? 'Loading…' }}</p>
                    <p class="mt-0.5 text-[12.5px] text-ink-soft tabular-nums">{{ details || ' ' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <button type="button" class="mf-btn" :disabled="!media" @click="pickerOpen = true"><RefreshCw class="size-4" aria-hidden="true" />Replace</button>
                    <a
                        v-if="media?.url"
                        :href="media.url"
                        target="_blank"
                        rel="noopener"
                        class="mf-btn"
                        :aria-label="`Open ${label.toLowerCase()} full size in a new tab`"
                        title="Open full size"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" /><span class="max-sm:sr-only @max-[22rem]:sr-only">Open</span>
                    </a>
                    <span class="mx-1 h-6 w-px bg-line" aria-hidden="true" />
                    <button type="button" class="mf-btn is-danger" :aria-label="`Remove ${label.toLowerCase()}`" title="Remove" :disabled="!media" @click="model = null">
                        <Trash2 class="size-4" aria-hidden="true" /><span class="max-sm:sr-only @max-[22rem]:sr-only">Remove</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <button v-else type="button" class="mf-empty group flex w-full max-w-xl items-center gap-4 rounded-2xl border-2 border-dashed px-4 py-4 text-left" :class="error ? 'border-red-300' : 'border-[#cfd7e2]'" @click="pickerOpen = true">
            <span class="mf-empty-tile flex size-12 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><ImagePlus class="size-[22px]" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-[14.5px] font-semibold text-ink">Choose {{ label.toLowerCase() }}</span>
                <span class="block text-[13px] text-ink-soft">Upload a new image or pick one from the media library</span>
            </span>
            <span class="hidden rounded-lg border border-line bg-white px-3 py-1.5 text-[13px] font-semibold text-ink sm:block">Browse</span>
        </button>

        <MediaPickerModal v-model:open="pickerOpen" :usage="usage" :selected-id="model" @select="select" />
    </FormField>
</template>

<style scoped>
.mf-stage {
    background-color: #fff;
    cursor: pointer;
}

.mf-hover {
    background: rgb(11 29 51 / 0.32);
    opacity: 0;
    transition: opacity 200ms ease;

    > span {
        transform: translateY(6px) scale(0.96);
        transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
    }
}

.mf-stage:hover .mf-hover,
.mf-stage:focus-visible .mf-hover {
    opacity: 1;

    > span {
        transform: none;
    }
}

.mf-stage:focus-visible {
    outline: 2px solid var(--admin-primary);
    outline-offset: -2px;
}

.mf-btn {
    display: inline-flex;
    min-height: 2.5rem;
    min-width: 2.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 0.65rem;
    padding: 0 0.7rem;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--color-ink-soft);
    transition: background-color 160ms ease, color 160ms ease;

    &:hover:not(:disabled) {
        background: var(--color-canvas);
        color: var(--color-ink);
    }

    &.is-danger {
        color: #b91c1c;

        &:hover:not(:disabled) {
            background: #fef2f2;
            color: #991b1b;
        }
    }

    &:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}

.mf-empty {
    background: color-mix(in oklab, var(--color-canvas) 60%, white);
    transition: border-color 200ms ease, background-color 200ms ease;

    &:hover,
    &:focus-visible {
        border-color: color-mix(in oklab, var(--admin-primary) 45%, white);
        background: color-mix(in oklab, var(--admin-primary) 4%, white);
    }
}

.mf-empty-tile {
    background: color-mix(in oklab, var(--tone, var(--admin-primary)) 14%, white);
    color: color-mix(in oklab, var(--tone, var(--admin-primary)) 55%, black);
    transition: transform 260ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.mf-empty:hover .mf-empty-tile {
    transform: scale(1.06);
}

.mf-fade-enter-active {
    transition: opacity 260ms ease;
}

.mf-fade-leave-active {
    transition: opacity 120ms ease;
}

.mf-fade-enter-from,
.mf-fade-leave-to {
    opacity: 0;
}

@media (hover: none) {
    .mf-hover {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .mf-hover > span,
    .mf-empty-tile {
        transition: none;
    }
}
</style>
