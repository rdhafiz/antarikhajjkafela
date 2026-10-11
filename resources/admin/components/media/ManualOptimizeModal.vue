<script setup lang="ts">
import 'cropperjs/dist/cropper.css';
import Cropper from 'cropperjs';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Crop, Image as ImageIcon } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import type { VariantOverride } from '@admin/lib/media';
import type { MediaUsage } from '@admin/types';

/**
 * Manual optimization step: before an upload completes, the admin sets the
 * size, fit, quality and (optionally) the crop area of every variant.
 */
const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ file: File | null; usage: MediaUsage | null; busy?: boolean; progress?: number }>();
const emit = defineEmits<{ confirm: [variants: Record<string, VariantOverride>]; cancel: [] }>();

const imageEl = ref<HTMLImageElement | null>(null);
const objectUrl = ref<string | null>(null);
const natural = ref({ width: 0, height: 0 });
const variants = ref<Record<string, VariantOverride>>({});
const cropEnabled = ref<Record<string, boolean>>({});
const active = ref<string | null>(null);
let cropper: Cropper | null = null;

const activeVariant = computed(() => (active.value ? variants.value[active.value] : null));

watch(open, async (value) => {
    if (!value || !props.file || !props.usage) {
        destroy();
        return;
    }

    variants.value = Object.fromEntries(
        props.usage.variants.map((v) => [v.name, { width: v.width, height: v.height, fit: v.fit, quality: v.quality, crop: null }]),
    );
    cropEnabled.value = {};
    active.value = props.usage.variants[0]?.name ?? null;
    objectUrl.value = URL.createObjectURL(props.file);

    await nextTick();
});

function onImageLoad(): void {
    if (!imageEl.value) return;

    natural.value = { width: imageEl.value.naturalWidth, height: imageEl.value.naturalHeight };
    cropper?.destroy();
    cropper = new Cropper(imageEl.value, {
        viewMode: 1,
        autoCrop: false,
        background: false,
        responsive: true,
        zoomable: false,
        movable: false,
        crop: () => storeCrop(),
    });
    syncCropper();
}

function aspectFor(name: string): number {
    const v = variants.value[name];
    return v && v.fit === 'cover' && v.width && v.height ? v.width / v.height : NaN;
}

/** Show the active variant's crop box (or none) in the cropper. */
function syncCropper(): void {
    if (!cropper || !active.value) return;

    const name = active.value;
    cropper.setAspectRatio(aspectFor(name));

    if (cropEnabled.value[name]) {
        cropper.crop();
        const saved = variants.value[name]?.crop;
        if (saved) cropper.setData({ ...saved, rotate: 0, scaleX: 1, scaleY: 1 });
    } else {
        cropper.clear();
    }
}

function storeCrop(): void {
    if (!cropper || !active.value || !cropEnabled.value[active.value]) return;

    const data = cropper.getData(true);
    const variant = variants.value[active.value];
    if (variant) variant.crop = { x: data.x, y: data.y, width: data.width, height: data.height };
}

function toggleCrop(name: string): void {
    cropEnabled.value[name] = !cropEnabled.value[name];
    if (!cropEnabled.value[name]) {
        const variant = variants.value[name];
        if (variant) variant.crop = null;
    }
    selectVariant(name);
}

function selectVariant(name: string): void {
    active.value = name;
    syncCropper();
}

function setFit(name: string, fit: 'scale' | 'cover'): void {
    const variant = variants.value[name];
    if (variant) variant.fit = fit;
    if (active.value === name) syncCropper();
}

function confirm(): void {
    const payload: Record<string, VariantOverride> = {};

    for (const [name, v] of Object.entries(variants.value)) {
        payload[name] = { ...v, crop: cropEnabled.value[name] ? v.crop : null };
    }

    emit('confirm', payload);
}

function destroy(): void {
    cropper?.destroy();
    cropper = null;
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
    objectUrl.value = null;
}

watch(() => activeVariant.value?.width, () => syncCropper());
watch(() => activeVariant.value?.height, () => syncCropper());

onBeforeUnmount(destroy);
</script>

<template>
    <AppModal
        v-model:open="open"
        title="Optimize image"
        description="Set the size of each version before uploading. Crop a version to focus on part of the image."
        size="xl"
        persistent
        @update:open="(v) => !v && emit('cancel')"
    >
        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div>
                <div class="flex min-h-[260px] items-center justify-center overflow-hidden rounded-xl bg-[#0f1b2a]">
                    <img v-if="objectUrl" ref="imageEl" :src="objectUrl" alt="" class="block max-h-[56dvh] max-w-full" @load="onImageLoad" />
                </div>
                <p class="mt-2 text-[12px] text-ink-muted">
                    Original: {{ natural.width }} × {{ natural.height }} px
                    <template v-if="active && cropEnabled[active] && activeVariant?.crop">
                        · Crop for <strong class="text-ink">{{ active }}</strong>: {{ activeVariant.crop.width }} × {{ activeVariant.crop.height }} px
                    </template>
                </p>
            </div>

            <div class="space-y-2.5">
                <div
                    v-for="(variant, name) in variants"
                    :key="name"
                    class="rounded-xl border p-3.5 transition-colors"
                    :class="active === name ? 'border-brand bg-brand-soft/60' : 'border-line hover:border-[#cbd3df]'"
                    @click="selectVariant(String(name))"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="flex items-center gap-2 text-sm font-bold text-ink uppercase">
                            <ImageIcon class="size-4 text-ink-muted" /> {{ name }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[12px] font-semibold"
                            :class="cropEnabled[name] ? 'bg-brand text-white' : 'bg-white text-ink-soft ring-1 ring-line hover:text-ink'"
                            @click.stop="toggleCrop(String(name))"
                        >
                            <Crop class="size-3.5" /> {{ cropEnabled[name] ? 'Cropping' : 'Crop' }}
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <label class="text-[11px] font-semibold text-ink-muted uppercase">
                            Width
                            <input v-model.number="variant.width" type="number" min="16" max="6000" class="control mt-1 px-2 py-1.5" @click.stop />
                        </label>
                        <label class="text-[11px] font-semibold text-ink-muted uppercase">
                            Height
                            <input
                                :value="variant.height ?? ''"
                                type="number"
                                min="16"
                                max="6000"
                                placeholder="auto"
                                class="control mt-1 px-2 py-1.5"
                                @click.stop
                                @input="variant.height = ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null"
                            />
                        </label>
                        <label class="text-[11px] font-semibold text-ink-muted uppercase">
                            Quality
                            <input v-model.number="variant.quality" type="number" min="30" max="100" class="control mt-1 px-2 py-1.5" @click.stop />
                        </label>
                    </div>

                    <div class="mt-2.5 flex rounded-lg bg-white p-0.5 ring-1 ring-line" role="radiogroup" :aria-label="`${name} fit`">
                        <button
                            v-for="fit in (['scale', 'cover'] as const)"
                            :key="fit"
                            type="button"
                            role="radio"
                            :aria-checked="variant.fit === fit"
                            class="flex-1 rounded-md py-1 text-[12px] font-semibold"
                            :class="variant.fit === fit ? 'bg-brand text-white' : 'text-ink-soft hover:text-ink'"
                            @click.stop="setFit(String(name), fit)"
                        >
                            {{ fit === 'scale' ? 'Fit inside' : 'Fill & crop' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" :disabled="busy" @click="emit('cancel')">Cancel</AppButton>
            <AppButton :loading="busy" @click="confirm">
                {{ busy && progress !== undefined ? `Uploading ${progress}%` : 'Optimize & upload' }}
            </AppButton>
        </template>
    </AppModal>
</template>
