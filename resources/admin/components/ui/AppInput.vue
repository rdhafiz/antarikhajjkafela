<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import FormField from './FormField.vue';

const model = defineModel<string | number | null>();

const props = withDefaults(
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

/** Password fields get a show/hide button so a typed password can be checked. */
const revealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));
</script>

<template>
    <FormField :label="label" :for="id ?? fallbackId" :error="error" :hint="hint" :required="required">
        <template v-if="$slots.aside" #aside><slot name="aside" /></template>
        <div :class="{ relative: isPassword }">
            <input
                :id="id ?? fallbackId"
                v-model="model"
                :type="inputType"
                :placeholder="placeholder"
                :required="required"
                :autocomplete="autocomplete"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                class="control"
                :class="{ 'control-invalid': error, 'pr-11': isPassword }"
            />
            <button
                v-if="isPassword"
                type="button"
                class="absolute top-1/2 right-1.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink disabled:opacity-50"
                :aria-label="revealed ? 'Hide password' : 'Show password'"
                :aria-pressed="revealed"
                :title="revealed ? 'Hide password' : 'Show password'"
                :disabled="disabled"
                @click="revealed = !revealed"
            >
                <component :is="revealed ? EyeOff : Eye" class="size-4" aria-hidden="true" />
            </button>
        </div>
    </FormField>
</template>
