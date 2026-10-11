<script setup lang="ts">
import { useId } from 'vue';
import { SwitchRoot, SwitchThumb } from 'reka-ui';

const model = defineModel<boolean>({ default: false });

defineProps<{ label: string; description?: string; disabled?: boolean }>();

const id = useId();
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <label :for="id" class="cursor-pointer">
            <span class="block text-sm font-semibold text-ink">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-[13px] text-ink-muted">{{ description }}</span>
        </label>
        <SwitchRoot
            :id="id"
            v-model="model"
            :disabled="disabled"
            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-[#d5dce6] transition-colors duration-200 data-[state=checked]:bg-brand disabled:opacity-50"
        >
            <SwitchThumb
                class="block size-5 translate-x-0.5 rounded-full bg-white shadow transition-transform duration-200 data-[state=checked]:translate-x-[22px]"
            />
        </SwitchRoot>
    </div>
</template>
