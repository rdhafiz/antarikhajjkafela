<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { formatBytes } from '@admin/lib/format';
import type { Media } from '@admin/types';

defineProps<{ items: Media[]; selectedId?: number | null }>();
const emit = defineEmits<{ select: [media: Media] }>();
</script>

<template>
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
        <li v-for="item in items" :key="item.id">
            <button
                type="button"
                class="group relative block w-full overflow-hidden rounded-xl bg-canvas text-left ring-1 transition-shadow"
                :class="selectedId === item.id ? 'ring-2 ring-brand' : 'ring-line hover:ring-[#b8c3d2]'"
                :aria-pressed="selectedId === item.id"
                @click="emit('select', item)"
            >
                <span class="block aspect-square bg-white">
                    <img
                        :src="item.thumb_url ?? ''"
                        :alt="item.alt.en ?? item.original_name"
                        loading="lazy"
                        class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                    />
                </span>
                <span class="block border-t border-line bg-white px-2.5 py-2">
                    <span class="block truncate text-[12px] font-semibold text-ink">{{ item.original_name }}</span>
                    <span class="block text-[11px] text-ink-muted">{{ item.width }}×{{ item.height }} · {{ formatBytes(item.optimized_size) }}</span>
                </span>
                <span
                    v-if="selectedId === item.id"
                    class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-brand text-white shadow"
                >
                    <Check class="size-3.5" :stroke-width="3" />
                </span>
            </button>
        </li>
    </ul>
</template>
