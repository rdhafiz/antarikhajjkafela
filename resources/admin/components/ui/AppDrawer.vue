<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useRoute } from 'vue-router';
import { findNavItem } from '@admin/navigation';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { X } from 'lucide-vue-next';

/**
 * Side sheet for previewing a record and acting on it (replaces rows of icon buttons).
 * Slides in from the right; on phones it fills the screen width.
 */
const open = defineModel<boolean>('open', { default: false });

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        /** Colour of the icon tile (a hex value; defaults to the current module's colour). */
        tone?: string;
        width?: 'md' | 'lg';
    }>(),
    { width: 'md' },
);

// Drawers render outside <main>, so they take the module colour themselves.
const route = useRoute();
const toneValue = computed(() => props.tone ?? findNavItem(route.path)?.item.tone ?? '#818cf8');
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="ad-overlay fixed inset-0 z-50 bg-[#06182e]/45 backdrop-blur-[2px]" />
            <DialogContent
                class="ad-sheet fixed inset-y-0 right-0 z-50 flex w-full flex-col bg-white shadow-[-24px_0_60px_-30px_rgb(2_12_27/0.45)] outline-none"
                :class="width === 'lg' ? 'sm:max-w-[640px]' : 'sm:max-w-[460px]'"
                :style="{ '--tone': toneValue }"
            >
                <header class="flex items-start gap-3.5 border-b border-line px-5 py-4 sm:px-6">
                    <span v-if="icon" class="ad-tile flex size-11 shrink-0 items-center justify-center rounded-xl" aria-hidden="true">
                        <component :is="icon" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <DialogTitle class="text-[16px] leading-snug font-bold text-ink">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-0.5 text-[13px] text-ink-muted">{{ description }}</DialogDescription>
                        <slot name="meta" />
                    </div>
                    <DialogClose class="-m-1.5 rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Close">
                        <X class="size-5" />
                    </DialogClose>
                </header>
                <div class="ad-body scroll-thin flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                    <slot />
                </div>
                <footer v-if="$slots.footer" class="flex flex-wrap items-center gap-2 border-t border-line bg-canvas/60 px-5 py-3.5 sm:px-6">
                    <slot name="footer" />
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
