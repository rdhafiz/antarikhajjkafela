<script setup lang="ts">
import { ref } from 'vue';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { X } from 'lucide-vue-next';

/**
 * Centered dialog (a bottom sheet on phones). It closes only from its close or
 * cancel buttons (and Escape, unless `persistent`), never from a click outside;
 * an outside click gives a small nudge instead.
 */
const open = defineModel<boolean>('open', { default: false });

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        /** Also ignore Escape (e.g. while saving). */
        persistent?: boolean;
    }>(),
    { size: 'md' },
);

const panel = ref<{ $el: HTMLElement } | null>(null);

/** Outside clicks never close the modal; a short pulse shows it is waiting for its buttons. */
function stayOpen(event: CustomEvent<{ originalEvent: Event }>): void {
    event.preventDefault();
    // Only for clicks on the backdrop: date pickers and country lists also open outside the panel.
    const target = event.detail?.originalEvent?.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('mdl-overlay')) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    panel.value?.$el.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.018)' }, { transform: 'scale(1)' }], {
        duration: 320,
        easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
    });
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="mdl-overlay fixed inset-0 z-50 bg-[#0b1a2c]/45 backdrop-blur-[2px]" />
            <DialogContent
                ref="panel"
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[92dvh] flex-col rounded-t-2xl bg-white shadow-[var(--shadow-pop)] outline-none sm:inset-auto sm:top-1/2 sm:left-1/2 sm:max-h-[88dvh] sm:w-[calc(100%-2rem)] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl"
                :class="[{ sm: 'sm:max-w-md', md: 'sm:max-w-xl', lg: 'sm:max-w-3xl', xl: 'sm:max-w-5xl' }[size], 'mdl-panel']"
                @interact-outside="stayOpen"
                @escape-key-down="persistent && $event.preventDefault()"
            >
                <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                    <div class="min-w-0 [overflow-wrap:anywhere]">
                        <DialogTitle class="text-base font-bold text-ink">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description" class="mt-0.5 text-[13px] text-ink-muted">{{ description }}</DialogDescription>
                    </div>
                    <DialogClose class="-m-1.5 rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Close">
                        <X class="size-5" />
                    </DialogClose>
                </header>
                <div class="scroll-thin flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                    <slot />
                </div>
                <footer v-if="$slots.footer" class="flex flex-col-reverse gap-2 border-t border-line bg-canvas/60 px-5 py-3.5 sm:flex-row sm:justify-end sm:px-6">
                    <slot name="footer" />
                </footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
