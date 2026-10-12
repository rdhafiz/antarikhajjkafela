<script setup lang="ts" generic="T extends string | number">
import { computed, ref, type Component } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';
import AppDrawer from './AppDrawer.vue';

/**
 * A set of tabs or filter choices. Wide screens: tabs that wrap (never scroll sideways).
 * Phones: one button showing the current choice, which opens a drawer with all of them.
 */
export interface TabOption<V> {
    value: V;
    label: string;
    count?: number | null;
    icon?: Component;
    tone?: string;
    /** Shows a red dot, e.g. when the tab has validation errors. */
    alert?: boolean;
}

const model = defineModel<T>({ required: true });
const props = withDefaults(
    defineProps<{
        options: TabOption<T>[];
        /** Heading of the phone drawer, e.g. "Show bookings". */
        label: string;
        variant?: 'pills' | 'underline';
    }>(),
    { variant: 'pills' },
);

const open = ref(false);
const current = computed(() => props.options.find((o) => o.value === model.value) ?? props.options[0]);

function pick(value: T): void {
    model.value = value;
    open.value = false;
}
</script>

<template>
    <div class="ts">
        <!-- Wide screens -->
        <div class="hidden md:flex md:flex-wrap" :class="variant === 'pills' ? 'gap-2' : 'gap-1 border-b border-line'" role="tablist" :aria-label="label">
            <button
                v-for="option in options"
                :key="String(option.value)"
                type="button"
                role="tab"
                :aria-selected="option.value === model"
                :class="[variant === 'pills' ? 'ts-pill' : 'ts-line', { 'is-active': option.value === model }]"
                :style="option.tone ? { '--tone': option.tone } : undefined"
                @click="pick(option.value)"
            >
                <component :is="option.icon" v-if="option.icon" class="ts-icon size-4" aria-hidden="true" />
                {{ option.label }}
                <span v-if="option.count !== undefined && option.count !== null" class="ts-count">{{ option.count }}</span>
                <span v-if="option.alert" class="size-2 rounded-full bg-red-500" aria-label="Has errors" />
            </button>
        </div>

        <!-- Phones -->
        <button type="button" class="ts-trigger flex h-11 w-full items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 text-left md:hidden" :aria-label="`${label}: ${current?.label}`" @click="open = true">
            <component :is="current.icon" v-if="current?.icon" class="ts-icon size-4" :style="current.tone ? { '--tone': current.tone } : undefined" aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate text-[14px] font-semibold text-ink">{{ current?.label }}</span>
            <span v-if="current?.count !== undefined && current?.count !== null" class="ts-count">{{ current.count }}</span>
            <span v-if="options.some((o) => o.alert)" class="size-2 rounded-full bg-red-500" aria-label="Some tabs have errors" />
            <ChevronDown class="size-4 text-ink-muted" aria-hidden="true" />
        </button>

        <AppDrawer v-model:open="open" :title="label">
            <ul class="-mx-2 space-y-1">
                <li v-for="option in options" :key="String(option.value)">
                    <button
                        type="button"
                        class="ts-choice flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left"
                        :class="{ 'is-active': option.value === model }"
                        :style="option.tone ? { '--tone': option.tone } : undefined"
                        @click="pick(option.value)"
                    >
                        <span v-if="option.icon" class="ts-choice-icon flex size-9 shrink-0 items-center justify-center rounded-lg" aria-hidden="true">
                            <component :is="option.icon" class="size-[18px]" />
                        </span>
                        <span class="flex-1 text-[14.5px] font-semibold text-ink">{{ option.label }}</span>
                        <span v-if="option.count !== undefined && option.count !== null" class="ts-count">{{ option.count }}</span>
                        <span v-if="option.alert" class="size-2 rounded-full bg-red-500" aria-label="Has errors" />
                        <Check v-if="option.value === model" class="size-4 text-brand" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </AppDrawer>
    </div>
</template>
