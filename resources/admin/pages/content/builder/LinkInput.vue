<script setup lang="ts">
import { computed, useId } from 'vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import type { Translations } from '@admin/types';

interface LinkValue {
    label: Translations;
    url: string | null;
}

const model = defineModel<LinkValue | null>({ default: null });
const props = defineProps<{ label: string; hint?: string; errors: Record<string, string[]>; path: string }>();

const id = useId();
const value = computed<LinkValue>(() => model.value ?? { label: {}, url: null });
const urlError = computed(() => props.errors[`${props.path}.url`]?.[0]);

function update(patch: Partial<LinkValue>): void {
    model.value = { ...value.value, ...patch };
}
</script>

<template>
    <fieldset class="rounded-xl border border-line p-3.5">
        <legend class="px-1 text-[13px] font-semibold text-ink">{{ label }}</legend>
        <p v-if="hint" class="-mt-1 mb-3 text-[12px] text-ink-muted">{{ hint }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <TranslatableInput
                :model-value="value.label"
                label="Button text"
                :errors="errors"
                :error-key="`${path}.label`"
                @update:model-value="update({ label: $event })"
            />
            <div>
                <label :for="id" class="label">Link</label>
                <input
                    :id="id"
                    :value="value.url ?? ''"
                    class="control"
                    :class="{ 'control-invalid': urlError }"
                    placeholder="/contact or https://…"
                    @input="update({ url: ($event.target as HTMLInputElement).value || null })"
                />
                <p v-if="urlError" class="mt-1.5 text-[13px] font-medium text-red-600">Use a link starting with /, https://, tel:, mailto: or #.</p>
            </div>
        </div>
    </fieldset>
</template>
