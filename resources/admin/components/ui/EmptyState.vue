<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ icon?: Component; title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <div v-if="icon" class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand">
            <component :is="icon" class="size-6" />
        </div>
        <p class="text-[15px] font-semibold text-ink">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-ink-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
