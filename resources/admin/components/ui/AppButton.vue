<script setup lang="ts">
import { computed, type Component } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import AppSpinner from './AppSpinner.vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'gold';
        size?: 'sm' | 'md';
        type?: 'button' | 'submit';
        to?: RouteLocationRaw;
        href?: string;
        loading?: boolean;
        disabled?: boolean;
        icon?: Component;
        block?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button' },
);

const tag = computed(() => (props.to ? RouterLink : props.href ? 'a' : 'button'));

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-semibold whitespace-nowrap select-none',
    'transition-[background-color,border-color,color,box-shadow,transform] duration-150 active:translate-y-px',
    'disabled:pointer-events-none disabled:opacity-55',
    props.size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm',
    props.block && 'w-full',
    {
        primary: 'bg-brand text-white shadow-sm hover:bg-[color-mix(in_oklab,var(--admin-primary)_88%,black)]',
        secondary: 'border border-line bg-white text-ink shadow-[var(--shadow-card)] hover:border-[#cbd3df] hover:bg-canvas',
        ghost: 'text-ink-soft hover:bg-canvas hover:text-ink',
        danger: 'bg-red-600 text-white shadow-sm hover:bg-red-700',
        gold: 'bg-gold text-brand shadow-sm hover:bg-[color-mix(in_oklab,var(--admin-secondary)_90%,black)]',
    }[props.variant],
]);
</script>

<template>
    <component
        :is="tag"
        :to="to"
        :href="href"
        :type="tag === 'button' ? type : undefined"
        :disabled="tag === 'button' ? disabled || loading : undefined"
        :aria-busy="loading || undefined"
        :class="classes"
    >
        <AppSpinner v-if="loading" class="size-4" />
        <component :is="icon" v-else-if="icon" class="size-4 shrink-0" :stroke-width="2.2" />
        <slot />
    </component>
</template>
