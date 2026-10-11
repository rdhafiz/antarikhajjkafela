<script setup lang="ts">
import { ref, useId, watch } from 'vue';
import FormField from './FormField.vue';

const model = defineModel<string | null>();
defineProps<{ label: string; hint?: string; error?: string | null; disabled?: boolean }>();

const id = useId();
const text = ref(model.value ?? '');

watch(model, (value) => {
    if ((value ?? '') !== text.value) text.value = value ?? '';
});

function onText(value: string): void {
    text.value = value;
    const normalized = value.startsWith('#') ? value : `#${value}`;
    if (/^#[0-9a-fA-F]{6}$/.test(normalized)) model.value = normalized.toLowerCase();
}
</script>

<template>
    <FormField :label="label" :for="id" :hint="hint" :error="error">
        <div class="flex items-center gap-2">
            <label
                class="relative size-10 shrink-0 cursor-pointer overflow-hidden rounded-lg ring-1 ring-line ring-inset"
                :style="{ backgroundColor: model ?? '#ffffff' }"
            >
                <span class="sr-only">Pick {{ label }}</span>
                <input
                    type="color"
                    :value="model ?? '#ffffff'"
                    :disabled="disabled"
                    class="absolute inset-0 size-full cursor-pointer opacity-0"
                    @input="model = ($event.target as HTMLInputElement).value"
                />
            </label>
            <input
                :id="id"
                :value="text"
                :disabled="disabled"
                maxlength="7"
                spellcheck="false"
                class="control font-mono uppercase"
                :class="{ 'control-invalid': error }"
                @input="onText(($event.target as HTMLInputElement).value)"
            />
        </div>
    </FormField>
</template>
