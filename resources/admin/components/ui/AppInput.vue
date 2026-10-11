<script setup lang="ts">
import { useId } from 'vue';
import FormField from './FormField.vue';

const model = defineModel<string | number | null>();

withDefaults(
    defineProps<{
        label?: string;
        type?: string;
        error?: string | string[] | null;
        hint?: string;
        placeholder?: string;
        required?: boolean;
        autocomplete?: string;
        disabled?: boolean;
        id?: string;
    }>(),
    { type: 'text' },
);

const fallbackId = useId();
</script>

<template>
    <FormField :label="label" :for="id ?? fallbackId" :error="error" :hint="hint" :required="required">
        <template v-if="$slots.aside" #aside><slot name="aside" /></template>
        <input
            :id="id ?? fallbackId"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :autocomplete="autocomplete"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
            class="control"
            :class="{ 'control-invalid': error }"
        />
    </FormField>
</template>
