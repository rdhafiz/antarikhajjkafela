<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import type { Paginated } from '@admin/lib/http';

defineProps<{ meta: Paginated<unknown>['meta'] }>();
const emit = defineEmits<{ change: [page: number] }>();
</script>

<template>
    <div v-if="meta.last_page > 1 || meta.total > 0" class="flex items-center justify-between gap-4 px-1 pt-4 text-[13px] text-ink-muted">
        <p>
            <template v-if="meta.total">Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}</template>
        </p>
        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-lg border border-line bg-white text-ink hover:bg-canvas disabled:opacity-40"
                :disabled="meta.current_page <= 1"
                aria-label="Previous page"
                @click="emit('change', meta.current_page - 1)"
            >
                <ChevronLeft class="size-4" />
            </button>
            <span class="px-2 font-medium text-ink">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-lg border border-line bg-white text-ink hover:bg-canvas disabled:opacity-40"
                :disabled="meta.current_page >= meta.last_page"
                aria-label="Next page"
                @click="emit('change', meta.current_page + 1)"
            >
                <ChevronRight class="size-4" />
            </button>
        </div>
    </div>
</template>
