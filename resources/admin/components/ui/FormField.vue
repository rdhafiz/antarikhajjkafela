<script setup lang="ts">
defineProps<{
    label?: string;
    for?: string;
    error?: string | string[] | null;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div>
        <div v-if="label || $slots.aside" class="flex items-baseline justify-between gap-3">
            <label v-if="label" :for="$props.for" class="label">
                {{ label }}<span v-if="required" class="ml-0.5 text-red-500" aria-hidden="true">*</span>
            </label>
            <slot name="aside" />
        </div>
        <slot />
        <p v-if="error" class="mt-1.5 text-[13px] font-medium text-red-600" role="alert">
            {{ Array.isArray(error) ? error[0] : error }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-[13px] text-ink-muted">{{ hint }}</p>
    </div>
</template>
