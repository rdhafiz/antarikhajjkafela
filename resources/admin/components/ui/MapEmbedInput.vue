<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { MapPinned } from 'lucide-vue-next';
import FormField from './FormField.vue';

/**
 * Accepts the "Embed a map" HTML from Google Maps (or just its URL) and
 * stores only the embed URL, so no raw HTML is ever saved.
 */
const model = defineModel<string | null>();
defineProps<{ label: string; hint?: string; error?: string | null }>();

const id = useId();
const raw = ref(model.value ?? '');
const invalid = ref(false);

watch(model, (value) => {
    if (value && value !== raw.value && !raw.value.includes(value)) raw.value = value;
});

const preview = computed(() => (model.value && /^https:\/\/(www\.)?google\.com\/maps\/embed\?/.test(model.value) ? model.value : null));

function onInput(value: string): void {
    raw.value = value;
    const trimmed = value.trim();

    if (!trimmed) {
        invalid.value = false;
        model.value = null;
        return;
    }

    const src = trimmed.match(/src=["']([^"']+)["']/i)?.[1] ?? trimmed;
    const decoded = src.replace(/&amp;/g, '&');

    invalid.value = !/^https:\/\/(www\.)?google\.com\/maps\/embed\?/.test(decoded);
    model.value = decoded;
}
</script>

<template>
    <FormField :label="label" :for="id" :hint="hint" :error="error ?? (invalid ? 'That doesn\'t look like a Google Maps embed. Use Share → Embed a map.' : null)">
        <textarea
            :id="id"
            :value="raw"
            rows="3"
            spellcheck="false"
            placeholder='<iframe src="https://www.google.com/maps/embed?pb=..."></iframe>'
            class="control resize-y font-mono text-[12px]"
            :class="{ 'control-invalid': error || invalid }"
            @input="onInput(($event.target as HTMLTextAreaElement).value)"
        />
        <div class="mt-3 overflow-hidden rounded-xl ring-1 ring-line">
            <iframe
                v-if="preview"
                :src="preview"
                title="Map preview"
                class="block h-56 w-full"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            />
            <div v-else class="flex h-28 flex-col items-center justify-center gap-1.5 bg-canvas text-[13px] text-ink-muted">
                <MapPinned class="size-5" />
                Map preview appears here
            </div>
        </div>
    </FormField>
</template>
