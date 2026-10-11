<script setup lang="ts">
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';
import { Check, Minus } from 'lucide-vue-next';

const model = defineModel<boolean | 'indeterminate'>({ default: false });

defineProps<{ label?: string; disabled?: boolean }>();
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-ink select-none" :class="{ 'cursor-not-allowed opacity-50': disabled }">
        <CheckboxRoot
            v-model="model"
            :disabled="disabled"
            class="flex size-[18px] shrink-0 items-center justify-center rounded-[5px] border border-[#c3ccd8] bg-white transition-colors data-[state=checked]:border-brand data-[state=checked]:bg-brand data-[state=indeterminate]:border-brand data-[state=indeterminate]:bg-brand"
        >
            <CheckboxIndicator class="text-white">
                <Minus v-if="model === 'indeterminate'" class="size-3.5" :stroke-width="3" />
                <Check v-else class="size-3.5" :stroke-width="3" />
            </CheckboxIndicator>
        </CheckboxRoot>
        <span v-if="label || $slots.default"><slot>{{ label }}</slot></span>
    </label>
</template>
