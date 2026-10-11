<script setup lang="ts">
import { computed } from 'vue';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { TriangleAlert } from 'lucide-vue-next';
import { useConfirm } from '@admin/composables/useConfirm';

const { pending, settle } = useConfirm();

const open = computed({
    get: () => pending.value !== null,
    set: (value) => !value && settle(false),
});
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="fixed inset-0 z-[60] bg-[#0b1a2c]/45 backdrop-blur-[2px]" />
            <AlertDialogContent
                v-if="pending"
                class="fixed top-1/2 left-1/2 z-[60] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 shadow-[var(--shadow-pop)] outline-none"
            >
                <div class="flex gap-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="pending.tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-brand-soft text-brand'"
                    >
                        <TriangleAlert class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <AlertDialogTitle class="text-base font-bold text-ink">{{ pending.title }}</AlertDialogTitle>
                        <AlertDialogDescription v-if="pending.message" class="mt-1.5 text-sm leading-relaxed text-ink-soft">
                            {{ pending.message }}
                        </AlertDialogDescription>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AlertDialogCancel
                        class="h-10 rounded-lg border border-line bg-white px-4 text-sm font-semibold text-ink hover:bg-canvas"
                        @click="settle(false)"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="h-10 rounded-lg px-4 text-sm font-semibold text-white"
                        :class="pending.tone === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-brand hover:opacity-90'"
                        @click="settle(true)"
                    >
                        {{ pending.confirmLabel }}
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
