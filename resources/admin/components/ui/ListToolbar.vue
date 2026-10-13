<script setup lang="ts">
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';

/**
 * One row above a list: search, a Filters button (with the number of active
 * filters) that opens the page's FilterDrawer, then optional extras (view switch, export…).
 */
const search = defineModel<string>('search', { default: '' });

withDefaults(defineProps<{ placeholder?: string; active?: number }>(), { placeholder: 'Search', active: 0 });
const emit = defineEmits<{ filters: [] }>();
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center gap-2 sm:gap-3">
        <!-- The search keeps at least 12rem; extras wrap onto their own row on narrow screens. -->
        <label class="relative min-w-0 flex-1 basis-48">
            <span class="sr-only">{{ placeholder }}</span>
            <Search class="pointer-events-none absolute top-1/2 left-4 size-[18px] -translate-y-1/2 text-ink-muted" aria-hidden="true" />
            <input v-model="search" type="search" :placeholder="placeholder" class="control lt-search pr-10 pl-11" />
            <button v-if="search" type="button" class="absolute top-1/2 right-2.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Clear search" @click="search = ''">
                <X class="size-4" />
            </button>
        </label>
        <button type="button" class="lt-btn" :class="{ 'is-active': active }" aria-haspopup="dialog" @click="emit('filters')">
            <SlidersHorizontal class="size-[18px]" aria-hidden="true" />
            <span class="max-sm:sr-only">Filters</span>
            <span v-if="active" class="lt-count"><span class="sr-only">, </span>{{ active }}<span class="sr-only"> active</span></span>
        </button>
        <slot />
    </div>
</template>

<style scoped>
.lt-search {
    height: 3rem;
    border-radius: 0.9rem;
    box-shadow: var(--shadow-card);
}

.lt-btn {
    position: relative;
    display: inline-flex;
    height: 3rem;
    min-width: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 1.1rem;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-ink);
    box-shadow: var(--shadow-card);
    transition: border-color 180ms ease, background-color 180ms ease;

    &:hover {
        border-color: #cbd3df;
    }

    &.is-active {
        border-color: color-mix(in oklab, var(--admin-primary) 45%, white);
        background: color-mix(in oklab, var(--admin-primary) 6%, white);
    }
}

.lt-count {
    display: flex;
    min-width: 1.25rem;
    height: 1.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--admin-primary);
    padding: 0 0.3rem;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}
</style>
