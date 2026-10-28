<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
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

// Keep the last question on screen while the dialog animates closed.
const shown = ref(pending.value);
watch(pending, (value) => value && (shown.value = value));

// Closing the dialog any other way — Cancel, Escape, the overlay — is a "no".
// Confirming is the one path that must not come through here, so the confirm
// button calls settle(true) itself and the close follows from pending emptying.
const open = computed({
    get: () => pending.value !== null,
    set: (value) => !value && settle(false),
});
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="mdl-overlay fixed inset-0 z-[60] bg-[#0b1a2c]/45 backdrop-blur-[2px]" />
            <AlertDialogContent
                v-if="shown"
                class="mdl-panel fixed top-1/2 left-1/2 z-[60] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 shadow-[var(--shadow-pop)] outline-none"
            >
                <div class="flex gap-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full"
                        :class="shown.tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-brand-soft text-brand'"
                    >
                        <TriangleAlert class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <AlertDialogTitle class="text-base font-bold text-ink">{{ shown.title }}</AlertDialogTitle>
                        <AlertDialogDescription v-if="shown.message" class="mt-1.5 text-sm leading-relaxed text-ink-soft">
                            {{ shown.message }}
                        </AlertDialogDescription>
                        <ul v-if="shown.items?.length" class="mt-3 max-h-48 space-y-1 overflow-y-auto rounded-lg bg-canvas px-3 py-2 text-[13px] text-ink">
                            <li v-for="item in shown.items" :key="item" class="flex gap-2"><span class="text-ink-muted" aria-hidden="true">•</span>{{ item }}</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <AlertDialogCancel
                        class="h-10 rounded-lg border border-line bg-white px-4 text-sm font-semibold text-ink hover:bg-canvas"
                        @click="settle(false)"
                    >
                        {{ shown.notice ? 'OK' : 'Cancel' }}
                    </AlertDialogCancel>
                    <!--
                        A plain button, not AlertDialogAction: that component closes
                        the dialog as part of its own click handling, which resolved
                        the promise false before this handler could answer true, and
                        every confirmed action quietly did nothing.
                    -->
                    <button
                        v-if="!shown.notice"
                        type="button"
                        class="h-10 rounded-lg px-4 text-sm font-semibold text-white"
                        :class="shown.tone === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-brand hover:opacity-90'"
                        @click="settle(true)"
                    >
                        {{ shown.confirmLabel }}
                    </button>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
