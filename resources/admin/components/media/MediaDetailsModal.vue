<script setup lang="ts">
import { computed, watch } from 'vue';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { formatBytes, formatDate } from '@admin/lib/format';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Media } from '@admin/types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ media: Media | null }>();
const emit = defineEmits<{ updated: [media: Media]; deleted: [id: number] }>();

const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();
const form = useForm({ alt: {} as Record<string, string | null> });

watch(open, (value) => value && props.media && form.reset({ alt: { ...props.media.alt } }));

const variants = computed(() =>
    Object.entries(props.media?.variants ?? {})
        .filter(([name]) => name !== 'admin')
        .sort(([, a], [, b]) => b.width - a.width),
);

const saving = computed(() =>
    props.media && props.media.size > 0 ? Math.max(0, Math.round((1 - (variants.value[0]?.[1].size ?? 0) / props.media.size) * 100)) : 0,
);

async function save(): Promise<void> {
    const response = await form.submit(() => http.put<{ data: Media }>(`/media/${props.media!.id}`, form.data));

    if (response) {
        emit('updated', response.data.data);
        toast.success('Image details saved');
        open.value = false;
    }
}

async function remove(): Promise<void> {
    const media = props.media;
    if (!media) return;

    const ok = await confirm({
        title: 'Delete this image?',
        message: 'It will be removed everywhere it is used on the website. It goes to the Trash, where a super admin can restore it.',
        confirmLabel: 'Delete image',
    });
    if (!ok) return;

    try {
        await http.delete(`/media/${media.id}`);
        emit('deleted', media.id);
        toast.success('Image deleted');
        open.value = false;
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <AppModal v-model:open="open" title="Image details" size="lg" :persistent="form.processing">
        <div v-if="media" class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div>
                <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-[repeating-conic-gradient(#eef1f5_0_25%,#fff_0_50%)] bg-[length:20px_20px] ring-1 ring-line">
                    <img :src="media.url ?? ''" :alt="media.alt.en ?? ''" class="max-h-full max-w-full object-contain" />
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 text-[13px]">
                    <dt class="text-ink-muted">File name</dt>
                    <dd class="truncate font-medium text-ink" :title="media.original_name">{{ media.original_name }}</dd>
                    <dt class="text-ink-muted">Original</dt>
                    <dd class="font-medium text-ink">{{ media.width }} × {{ media.height }} · {{ formatBytes(media.size) }}</dd>
                    <dt class="text-ink-muted">Used as</dt>
                    <dd class="font-medium text-ink capitalize">{{ media.usage }}</dd>
                    <dt class="text-ink-muted">Uploaded</dt>
                    <dd class="font-medium text-ink">{{ formatDate(media.created_at, true) }}</dd>
                </dl>
            </div>

            <div class="space-y-5">
                <TranslatableInput
                    v-model="form.data.alt"
                    label="Alternative text"
                    hint="Describe the image for screen readers and search engines."
                    error-key="alt"
                    :errors="form.errors"
                />

                <div>
                    <p class="label">Optimized versions <span class="font-medium text-emerald-600">· {{ saving }}% smaller</span></p>
                    <ul class="divide-y divide-line rounded-lg border border-line text-[13px]">
                        <li v-for="[name, variant] in variants" :key="name" class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="font-semibold text-ink uppercase">{{ name }}</span>
                            <span class="text-ink-soft">{{ variant.width }} × {{ variant.height }}</span>
                            <span class="text-ink-soft">{{ formatBytes(variant.size) }}</span>
                            <a :href="variant.url" target="_blank" rel="noopener" class="text-ink-muted hover:text-brand" :aria-label="`Open ${name}`">
                                <ExternalLink class="size-3.5" />
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton
                v-if="auth.can('media.delete')"
                variant="ghost"
                :icon="Trash2"
                class="text-red-600 hover:bg-red-50 hover:text-red-700 sm:mr-auto"
                @click="remove"
            >
                Delete
            </AppButton>
            <AppButton variant="secondary" @click="open = false">Close</AppButton>
            <AppButton v-if="auth.can('media.update')" :loading="form.processing" @click="save">Save details</AppButton>
        </template>
    </AppModal>
</template>
