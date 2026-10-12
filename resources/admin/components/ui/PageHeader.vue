<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useRoute } from 'vue-router';
import { findNavItem } from '@admin/navigation';

/**
 * Page title block. The icon and its colour come from the page's sidebar module
 * (Bookings → green calendar…) unless given.
 */
const props = defineProps<{ title: string; description?: string; icon?: Component; tone?: string }>();

const route = useRoute();
const module = computed(() => findNavItem(route.path)?.item);
const icon = computed(() => props.icon ?? module.value?.icon);
const tone = computed(() => props.tone ?? module.value?.tone ?? '#818cf8');
</script>

<template>
    <div class="ph mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" :style="{ '--tone': tone }">
        <div class="flex min-w-0 items-center gap-4">
            <span v-if="icon" class="ph-tile flex size-12 shrink-0 items-center justify-center rounded-2xl" aria-hidden="true">
                <component :is="icon" class="size-[22px]" />
            </span>
            <div class="min-w-0">
                <h1 class="text-[22px] leading-tight font-bold tracking-[-0.02em] text-ink sm:text-[24px]">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-[14px] text-ink-soft">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
