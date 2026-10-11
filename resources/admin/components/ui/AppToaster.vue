<script setup lang="ts">
import { CircleCheck, CircleX, Info, X } from 'lucide-vue-next';
import { useToastStore } from '@admin/stores/toast';

const toast = useToastStore();

const icons = { success: CircleCheck, error: CircleX, info: Info };
const iconColors = { success: 'text-emerald-600', error: 'text-red-600', info: 'text-brand' };
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6" aria-live="polite">
        <TransitionGroup
            enter-from-class="translate-y-2 opacity-0"
            enter-active-class="transition duration-200 ease-out"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
        >
            <div
                v-for="item in toast.toasts"
                :key="item.id"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-white p-4 shadow-[var(--shadow-pop)]"
                :role="item.tone === 'error' ? 'alert' : 'status'"
            >
                <component :is="icons[item.tone]" class="mt-0.5 size-5 shrink-0" :class="iconColors[item.tone]" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink">{{ item.title }}</p>
                    <p v-if="item.description" class="mt-0.5 text-[13px] text-ink-soft">{{ item.description }}</p>
                </div>
                <button type="button" class="-m-1 rounded-md p-1 text-ink-muted hover:text-ink" aria-label="Dismiss" @click="toast.dismiss(item.id)">
                    <X class="size-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
