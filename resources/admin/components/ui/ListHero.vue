<script setup lang="ts">
import type { Component } from 'vue';

/**
 * Page header with a photo fading in from the right (packages, bookings…).
 * Photos live in resources/admin/assets; keep "banner"/"ad" out of their names (ad blockers).
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon: Component;
        /** Colour of the icon tile. */
        tone: string;
        photo: string;
        /** CSS object-position for the photo. */
        photoPosition?: string;
    }>(),
    { photoPosition: '50% 58%' },
);
</script>

<template>
    <header class="lh relative mb-5 overflow-hidden rounded-[var(--radius-card)] border border-line">
        <img :src="photo" alt="" class="lh-photo" :style="{ objectPosition: photoPosition }" aria-hidden="true" />
        <div class="relative z-[1] flex flex-wrap items-center gap-x-5 gap-y-4 px-5 py-6 sm:px-7 sm:py-7">
            <span class="ph-tile flex size-14 shrink-0 items-center justify-center rounded-2xl sm:size-16" :style="{ '--tone': tone }" aria-hidden="true">
                <component :is="icon" class="size-7" />
            </span>
            <div class="min-w-0 flex-1 basis-64">
                <h1 class="text-[26px] leading-tight font-bold tracking-[-0.01em] text-ink sm:text-[30px]">{{ title }}</h1>
                <p v-if="description" class="mt-1 max-w-md text-[14.5px] text-ink-soft">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="lh-actions flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>

<style scoped>
.lh {
    background: linear-gradient(100deg, #fbfcfe 0%, #f3f6fb 45%, #eef3fa 100%);
}

.lh-photo {
    position: absolute;
    inset: 0 0 0 auto;
    width: 46%;
    height: 100%;
    object-fit: cover;
    mask-image: linear-gradient(to right, transparent 0%, rgb(0 0 0 / 0.6) 30%, #000 60%);
}

/* Keep the title readable where the photo starts. */
.lh::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, #fbfcfe 0%, #fbfcfe 40%, rgb(251 252 254 / 0.75) 52%, rgb(251 252 254 / 0) 72%);
    pointer-events: none;
}

.lh-actions :deep(button),
.lh-actions :deep(a) {
    box-shadow: 0 10px 24px -12px rgb(2 12 27 / 0.6);
}

@media (min-width: 1280px) {
    .lh-photo {
        width: 58%;
    }
}

@media (max-width: 767px) {
    .lh-photo {
        width: 100%;
        opacity: 0.35;
        mask-image: linear-gradient(to bottom, #000, transparent);
    }

    .lh::after {
        background: linear-gradient(180deg, rgb(251 252 254 / 0.55), #fbfcfe 80%);
    }
}
</style>
