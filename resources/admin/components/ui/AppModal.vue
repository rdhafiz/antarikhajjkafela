<script setup lang="ts">
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { X } from 'lucide-vue-next';

const open = defineModel<boolean>('open', { default: false });

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        /** Prevent closing by clicking outside (e.g. while saving). */
        persistent?: boolean;
    }>(),
    { size: 'md' },
);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-50 bg-[#0b1a2c]/45 backdrop-blur-[2px] data-[state=open]:animate-[fade-in_160ms_ease-out]" />
            <DialogContent
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[92dvh] flex-col rounded-t-2xl bg-white shadow-[var(--shadow-pop)] outline-none sm:inset-auto sm:top-1/2 sm:left-1/2 sm:max-h-[88dvh] sm:w-[calc(100%-2rem)] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl data-[state=open]:animate-[modal-in_200ms_cubic-bezier(.2,.8,.2,1)]"
                :class="{ sm: 'sm:max-w-md', md: 'sm:max-w-xl', lg: 'sm:max-w-3xl', xl: 'sm:max-w-5xl' }[size]"
                @interact-outside="persistent && $event.preventDefault()"
                @escape-key-down="persistent && $event.preventDefault()"
            >
                <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                    <div>
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

<style>
@keyframes fade-in {
    from { opacity: 0; }
}
@keyframes modal-in {
    from { opacity: 0; transform: translate(-50%, calc(-50% + 8px)) scale(0.98); }
}
@media (max-width: 639px) {
    @keyframes modal-in {
        from { transform: translateY(24px); opacity: 0; }
    }
}
</style>
