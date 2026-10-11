<script setup lang="ts">
import { computed } from 'vue';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui';
import { ChevronDown, X } from 'lucide-vue-next';
import FormField from '@admin/components/ui/FormField.vue';

/** Picks one of the website's content icons (the same SVGs the site renders). */
const model = defineModel<string | null>({ default: null });
const props = defineProps<{ label: string; icons: { name: string; svg: string }[] }>();

const current = computed(() => props.icons.find((i) => i.name === model.value) ?? null);
</script>

<template>
    <FormField :label="label">
        <PopoverRoot>
            <PopoverTrigger class="control flex items-center gap-2.5 text-left">
                <span v-if="current" class="flex size-6 items-center justify-center text-brand [&_svg]:size-5" v-html="current.svg" />
                <span class="flex-1 truncate" :class="current ? 'text-ink' : 'text-ink-muted'">{{ current ? current.name.replace(/-/g, ' ') : 'No icon' }}</span>
                <ChevronDown class="size-4 text-ink-muted" />
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent :side-offset="6" align="start" class="z-[80] w-72 rounded-xl border border-line bg-white p-2 shadow-[var(--shadow-pop)]">
                    <div class="grid grid-cols-7 gap-1">
                        <button
                            v-for="icon in icons"
                            :key="icon.name"
                            type="button"
                            class="flex aspect-square items-center justify-center rounded-lg transition-colors [&_svg]:size-5"
                            :class="model === icon.name ? 'bg-brand text-white' : 'text-ink-soft hover:bg-canvas hover:text-brand'"
                            :title="icon.name.replace(/-/g, ' ')"
                            :aria-label="icon.name.replace(/-/g, ' ')"
                            @click="model = icon.name"
                            v-html="icon.svg"
                        />
                    </div>
                    <button v-if="model" type="button" class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg py-1.5 text-[12px] font-semibold text-ink-muted hover:bg-canvas hover:text-red-600" @click="model = null">
                        <X class="size-3.5" /> Remove icon
                    </button>
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </FormField>
</template>
