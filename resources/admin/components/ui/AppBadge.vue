<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'gold' }>(), { tone: 'neutral' });
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[12px] leading-5 font-semibold whitespace-nowrap"
        :class="{
            neutral: 'bg-[#eef1f5] text-ink-soft',
            brand: 'bg-brand-soft text-brand',
            success: 'bg-emerald-50 text-emerald-700',
            warning: 'bg-amber-50 text-amber-700',
            danger: 'bg-red-50 text-red-700',
            gold: 'bg-[color-mix(in_oklab,var(--admin-secondary)_22%,white)] text-[#6b5400]',
        }[tone]"
    >
        <slot />
    </span>
</template>
