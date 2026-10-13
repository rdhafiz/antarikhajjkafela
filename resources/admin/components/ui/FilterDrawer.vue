<script setup lang="ts">
import type { Component } from 'vue';
import { Check, SlidersHorizontal } from 'lucide-vue-next';
import AppButton from './AppButton.vue';
import AppDrawer from './AppDrawer.vue';

export interface FilterGroup {
    key: string;
    label: string;
    icon: Component;
    tone: string;
    options: { value: string; label: string; count?: number | null }[];
}

/**
 * Every filter of a list in one drawer: each group is a set of choice chips
 * bound to `filters[group.key]` ('' = any). The list updates as you choose;
 * extra controls (e.g. a date range) go in the default slot.
 */
const open = defineModel<boolean>('open', { default: false });

defineProps<{
    filters: Record<string, string | number>;
    groups: FilterGroup[];
    /** Results for the current choice, shown on the close button. */
    total: number;
    /** e.g. 'package' / 'packages'. */
    noun: [string, string];
    loading?: boolean;
    canClear?: boolean;
}>();
const emit = defineEmits<{ clear: [] }>();
</script>

<template>
    <AppDrawer v-model:open="open" :title="`Filter ${noun[1]}`" description="Results update as you choose." :icon="SlidersHorizontal" tone="#38bdf8">
        <div class="space-y-6">
            <fieldset v-for="group in groups" :key="group.key" class="min-w-0">
                <legend class="mb-2.5 flex items-center gap-2 text-[13.5px] font-bold text-ink">
                    <span class="fd-icon" :style="{ '--tone': group.tone }"><component :is="group.icon" class="size-4" aria-hidden="true" /></span>
                    {{ group.label }}
                </legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="option in group.options" :key="option.value" class="fd-chip" :class="{ 'is-on': String(filters[group.key]) === option.value }" :style="{ '--tone': group.tone }">
                        <input v-model="filters[group.key]" type="radio" class="sr-only" :name="`fd-${group.key}`" :value="option.value" />
                        <Check v-if="String(filters[group.key]) === option.value" class="size-3.5" aria-hidden="true" />
                        {{ option.label }}
                        <span v-if="option.count !== undefined && option.count !== null" class="fd-count">{{ option.count }}</span>
                    </label>
                </div>
            </fieldset>
            <slot />
        </div>
        <template #footer>
            <AppButton variant="ghost" :disabled="!canClear" @click="emit('clear')">Clear all</AppButton>
            <AppButton class="ml-auto" :loading="loading" @click="open = false">Show {{ total }} {{ total === 1 ? noun[0] : noun[1] }}</AppButton>
        </template>
    </AppDrawer>
</template>

<style>
/* Shared with filter controls placed in the slot. */
.fd-icon {
    display: flex;
    width: 1.75rem;
    height: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: color-mix(in oklab, var(--tone) 16%, white);
    color: color-mix(in oklab, var(--tone) 55%, black);
}

.fd-chip {
    display: inline-flex;
    min-height: 2.5rem;
    cursor: pointer;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 0.95rem;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--color-ink-soft);
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;

    &:hover {
        border-color: #cbd3df;
        color: var(--color-ink);
    }

    &:has(:focus-visible) {
        outline: 2px solid var(--admin-primary);
        outline-offset: 2px;
    }

    &.is-on {
        border-color: color-mix(in oklab, var(--tone) 65%, black 5%);
        background: color-mix(in oklab, var(--tone) 14%, white);
        color: color-mix(in oklab, var(--tone) 45%, black);
    }
}

.fd-count {
    border-radius: 999px;
    background: var(--color-canvas);
    padding: 0.05rem 0.45rem;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--color-ink-soft);

    .is-on & {
        background: #fff;
    }
}
</style>
