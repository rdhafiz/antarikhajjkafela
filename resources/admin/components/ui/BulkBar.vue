<script setup lang="ts">
import { X } from 'lucide-vue-next';

/**
 * The bar that appears at the bottom of a list once rows are ticked: how many,
 * select all on the page, the actions (slot) and clear.
 */
defineProps<{ count: number; pageCount: number; noun: [string, string] }>();
const emit = defineEmits<{ selectPage: []; clear: [] }>();
</script>

<template>
    <Transition name="bulk">
        <div v-if="count > 0" class="bulk-bar" role="region" :aria-label="`${count} selected`">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <strong class="text-[14px] text-white">{{ count }} {{ count === 1 ? noun[0] : noun[1] }} selected</strong>
                <button v-if="count < pageCount" type="button" class="text-[13px] font-semibold text-gold underline-offset-2 hover:underline" @click="emit('selectPage')">
                    Select all {{ pageCount }} on this page
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot />
                <button type="button" class="bulk-clear" aria-label="Clear the selection" @click="emit('clear')"><X class="size-4" /></button>
            </div>
        </div>
    </Transition>
</template>
