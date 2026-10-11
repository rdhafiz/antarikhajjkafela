<script setup lang="ts">
import { computed, ref } from 'vue';
import { CloudUpload } from 'lucide-vue-next';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import ManualOptimizeModal from './ManualOptimizeModal.vue';
import { checkFile, fetchUsages, uploadMedia, type UsagesResponse, type VariantOverride } from '@admin/lib/media';
import { errorMessage } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';
import type { Media } from '@admin/types';

/**
 * Drag-and-drop uploader. In automatic mode files upload straight away; in
 * manual mode each file first goes through the optimization modal.
 */
const props = withDefaults(defineProps<{ usage: string; multiple?: boolean; compact?: boolean }>(), { multiple: true });
const emit = defineEmits<{ uploaded: [media: Media] }>();

const toast = useToastStore();
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const uploading = ref(0);
const progress = ref(0);

const config = ref<UsagesResponse | null>(null);
const queue = ref<File[]>([]);
const manualFile = ref<File | null>(null);
const manualOpen = ref(false);
const manualBusy = ref(false);

const usageDefinition = computed(() => config.value?.data.find((u) => u.key === props.usage) ?? null);
const accept = computed(() => config.value?.mimes.join(',') ?? 'image/*');

fetchUsages().then((data) => (config.value = data)).catch(() => {});

async function handle(files: FileList | File[] | null): Promise<void> {
    if (!files?.length) return;

    config.value = await fetchUsages(true);
    const list = Array.from(files).slice(0, props.multiple ? undefined : 1);
    const valid: File[] = [];

    for (const file of list) {
        const problem = checkFile(file, config.value);
        problem ? toast.error('Upload skipped', problem) : valid.push(file);
    }

    if (config.value.mode === 'manual') {
        queue.value.push(...valid);
        nextManual();
    } else {
        await Promise.all(valid.map((file) => send(file)));
    }

    if (input.value) input.value.value = '';
}

async function send(file: File, variants?: Record<string, VariantOverride>): Promise<boolean> {
    uploading.value++;
    progress.value = 0;

    try {
        const media = await uploadMedia(file, props.usage, { variants, onProgress: (p) => (progress.value = p) });
        emit('uploaded', media);
        return true;
    } catch (e) {
        toast.error(`Could not upload ${file.name}`, errorMessage(e));
        return false;
    } finally {
        uploading.value--;
    }
}

function nextManual(): void {
    if (manualOpen.value || !queue.value.length) return;

    manualFile.value = queue.value.shift() ?? null;
    manualOpen.value = manualFile.value !== null;
}

async function confirmManual(variants: Record<string, VariantOverride>): Promise<void> {
    if (!manualFile.value) return;

    manualBusy.value = true;
    const ok = await send(manualFile.value, variants);
    manualBusy.value = false;

    if (ok) closeManual();
}

function closeManual(): void {
    manualOpen.value = false;
    manualFile.value = null;
    window.setTimeout(nextManual, 250);
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    handle(event.dataTransfer?.files ?? null);
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed text-center transition-colors"
            :class="[
                dragging ? 'border-brand bg-brand-soft' : 'border-[#cfd7e2] bg-canvas/50 hover:border-brand/50 hover:bg-brand-soft/40',
                compact ? 'gap-1.5 px-4 py-5' : 'gap-2.5 px-6 py-9',
            ]"
            @click="input?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span class="flex size-11 items-center justify-center rounded-full bg-white text-brand shadow-sm ring-1 ring-line">
                <AppSpinner v-if="uploading > 0" class="size-5" />
                <CloudUpload v-else class="size-5" />
            </span>
            <span class="text-sm font-semibold text-ink">
                <template v-if="uploading > 0">Uploading… {{ progress }}%</template>
                <template v-else>Drop images here or <span class="text-brand underline-offset-2 hover:underline">browse</span></template>
            </span>
            <span v-if="!compact" class="text-[12px] text-ink-muted">
                JPG, PNG, WebP, GIF or AVIF · up to {{ Math.round((config?.max_upload_kb ?? 10240) / 1024) }} MB
                <template v-if="config"> · {{ config.mode === 'manual' ? 'manual optimization' : 'optimized automatically' }}</template>
            </span>
        </button>
        <input ref="input" type="file" class="hidden" :accept="accept" :multiple="multiple" @change="handle(($event.target as HTMLInputElement).files)" />

        <ManualOptimizeModal
            v-model:open="manualOpen"
            :file="manualFile"
            :usage="usageDefinition"
            :busy="manualBusy"
            :progress="progress"
            @confirm="confirmManual"
            @cancel="closeManual"
        />
    </div>
</template>
