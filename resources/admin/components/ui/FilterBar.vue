<script setup lang="ts">
import { ref } from 'vue';
import { ListFilter, Search, X } from 'lucide-vue-next';

/**
 * List filters: a search box, the page's filters (slot) and a "Clear filters" link.
 * On phones the filters fold behind a "Filters" button so the list starts high up.
 */
const search = defineModel<string>('search', { default: '' });

withDefaults(
    defineProps<{
        placeholder?: string;
        /** How many filters (besides search) are set; shows "Clear filters" and a count. */
        active?: number;
        /** e.g. "24 bookings", shown at the end of the bar. */
        summary?: string;
        searchable?: boolean;
    }>(),
    { placeholder: 'Search…', active: 0, searchable: true },
);
const emit = defineEmits<{ clear: [] }>();

const open = ref(false);
</script>

<template>
    <div class="fb mb-4">
        <div class="flex items-center gap-2">
            <label v-if="searchable" class="relative min-w-0 flex-1">
                <span class="sr-only">{{ placeholder }}</span>
                <Search class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
                <input v-model="search" type="search" :placeholder="placeholder" class="control h-11 rounded-xl pr-9 pl-10" />
                <button v-if="search" type="button" class="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Clear search" @click="search = ''">
                    <X class="size-4" />
                </button>
            </label>

            <button
                v-if="$slots.default"
                type="button"
                class="fb-toggle relative flex h-11 shrink-0 items-center gap-2 rounded-xl border border-line bg-white px-3.5 text-[14px] font-semibold text-ink md:hidden"
                :aria-expanded="open"
                @click="open = !open"
            >
                <ListFilter class="size-4" aria-hidden="true" />
                Filters
                <span v-if="active" class="flex size-5 items-center justify-center rounded-full bg-brand text-[11px] font-bold text-white">{{ active }}</span>
            </button>

            <div v-if="$slots.default" class="hidden items-center gap-2 md:flex">
                <slot />
            </div>

            <slot name="actions" />
        </div>

        <div v-if="$slots.default" class="fb-panel grid md:hidden" :class="{ 'is-open': open }">
            <div class="min-h-0 overflow-hidden">
                <div class="grid gap-2 pt-2 sm:grid-cols-2">
                    <slot />
                </div>
            </div>
        </div>

        <div v-if="active || summary" class="mt-2.5 flex flex-wrap items-center justify-between gap-2 px-0.5 text-[13px]">
            <p v-if="summary" class="text-ink-soft">{{ summary }}</p>
            <button v-if="active" type="button" class="inline-flex items-center gap-1 font-semibold text-brand hover:underline" @click="emit('clear')">
                <X class="size-3.5" aria-hidden="true" />Clear filters
            </button>
        </div>
    </div>
</template>
