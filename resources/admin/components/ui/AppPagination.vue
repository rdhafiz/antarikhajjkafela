<script setup lang="ts">
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import type { Paginated } from '@admin/lib/http';

/**
 * Pagination bar: "Showing 21–40 of 130", a rows-per-page choice and numbered pages
 * (first, last and two around the current page, with gaps shown as "…").
 */
const props = withDefaults(
    defineProps<{
        meta: Paginated<unknown>['meta'];
        /** Offer a rows-per-page choice (emits `per-page`). */
        perPageOptions?: number[] | false;
        label?: string;
    }>(),
    { perPageOptions: () => [10, 20, 50, 100], label: 'results' },
);
const emit = defineEmits<{ change: [page: number]; 'per-page': [size: number] }>();

const pages = computed<(number | 'gap')[]>(() => {
    const { current_page: current, last_page: last } = props.meta;
    const wanted = new Set([1, last, current - 1, current, current + 1].filter((p) => p >= 1 && p <= last));
    if (current <= 3) [2, 3, 4].forEach((p) => p <= last && wanted.add(p));
    if (current >= last - 2) [last - 1, last - 2, last - 3].forEach((p) => p >= 1 && wanted.add(p));

    const sorted = [...wanted].sort((a, b) => a - b);
    const result: (number | 'gap')[] = [];
    sorted.forEach((page, i) => {
        if (i > 0 && page - sorted[i - 1] > 1) result.push('gap');
        result.push(page);
    });

    return result;
});

function go(page: number): void {
    if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) emit('change', page);
}
</script>

<template>
    <nav v-if="meta.total > 0" class="pg flex flex-col items-center justify-between gap-3 pt-4 sm:flex-row" aria-label="Pagination">
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[13px] text-ink-soft">
            <p>
                Showing <strong class="font-semibold text-ink">{{ meta.from }}–{{ meta.to }}</strong>
                of <strong class="font-semibold text-ink">{{ meta.total }}</strong> {{ label }}
            </p>
            <label v-if="perPageOptions && meta.total > Math.min(...perPageOptions)" class="flex items-center gap-2">
                <span>Rows</span>
                <select class="control h-8 w-[74px] py-0 text-[13px]" :value="meta.per_page" @change="emit('per-page', Number(($event.target as HTMLSelectElement).value))">
                    <option v-for="size in perPageOptions" :key="size" :value="size">{{ size }}</option>
                </select>
            </label>
        </div>

        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <button type="button" class="pg-btn hidden sm:flex" :disabled="meta.current_page <= 1" aria-label="First page" @click="go(1)"><ChevronsLeft class="size-4" /></button>
            <button type="button" class="pg-btn" :disabled="meta.current_page <= 1" aria-label="Previous page" @click="go(meta.current_page - 1)"><ChevronLeft class="size-4" /></button>
            <template v-for="(page, i) in pages" :key="i">
                <span v-if="page === 'gap'" class="px-1 text-ink-muted" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    class="pg-btn pg-num"
                    :class="{ 'is-current': page === meta.current_page }"
                    :aria-current="page === meta.current_page ? 'page' : undefined"
                    :aria-label="`Page ${page}`"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>
            <button type="button" class="pg-btn" :disabled="meta.current_page >= meta.last_page" aria-label="Next page" @click="go(meta.current_page + 1)"><ChevronRight class="size-4" /></button>
            <button type="button" class="pg-btn hidden sm:flex" :disabled="meta.current_page >= meta.last_page" aria-label="Last page" @click="go(meta.last_page)"><ChevronsRight class="size-4" /></button>
        </div>
    </nav>
</template>
