<script setup lang="ts">
import type { Component } from 'vue';

/** Empty list or no results. The icon takes the current module's colour (--tone). */
defineProps<{ icon?: Component; title: string; description?: string }>();
</script>

<template>
    <div class="es flex flex-col items-center px-6 py-16 text-center">
        <div v-if="icon" class="es-tile relative mb-5 flex size-16 items-center justify-center rounded-[1.25rem]">
            <span class="es-ring absolute inset-0 rounded-[1.25rem]" aria-hidden="true" />
            <component :is="icon" class="relative size-7" aria-hidden="true" />
        </div>
        <p class="text-[16px] font-bold text-ink">{{ title }}</p>
        <p v-if="description" class="mt-1.5 max-w-sm text-[14px] text-ink-soft">{{ description }}</p>
        <div v-if="$slots.default" class="mt-6"><slot /></div>
    </div>
</template>
