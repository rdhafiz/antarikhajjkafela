<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { X } from 'lucide-vue-next';
import FormField from '@admin/components/ui/FormField.vue';

/** Free-form tags as removable chips; Enter or comma adds one. Suggests tags already in use. */
const tags = defineModel<string[]>({ default: () => [] });
const props = defineProps<{ label: string; suggestions?: string[]; error?: string | null; max?: number }>();

const id = useId();
const draft = ref('');
const listId = `${id}-list`;
const available = computed(() => (props.suggestions ?? []).filter((s) => !tags.value.some((t) => t.toLowerCase() === s.toLowerCase())));

function add(): void {
    const value = draft.value.replace(/,/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 40);
    draft.value = '';
    if (!value || tags.value.length >= (props.max ?? 12)) return;
    if (!tags.value.some((t) => t.toLowerCase() === value.toLowerCase())) tags.value = [...tags.value, value];
}

function remove(index: number): void {
    tags.value = tags.value.filter((_, i) => i !== index);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add();
    } else if (event.key === 'Backspace' && !draft.value && tags.value.length) {
        remove(tags.value.length - 1);
    }
}
</script>

<template>
    <FormField :label="label" :for="id" :error="error" hint="Press Enter after each tag.">
        <div class="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-line bg-white px-2 py-1.5 focus-within:border-brand">
            <span v-for="(tag, index) in tags" :key="tag" class="inline-flex items-center gap-1 rounded-md bg-brand-soft py-0.5 pr-1 pl-2 text-[13px] font-medium text-brand">
                {{ tag }}
                <button type="button" class="rounded p-0.5 hover:bg-white" :aria-label="`Remove ${tag}`" @click="remove(index)"><X class="size-3" /></button>
            </span>
            <input :id="id" v-model="draft" :list="listId" class="min-w-24 flex-1 px-1 py-0.5 text-sm outline-none" placeholder="Add tag" @keydown="onKeydown" @blur="add" @change="add" />
            <datalist :id="listId">
                <option v-for="suggestion in available" :key="suggestion" :value="suggestion" />
            </datalist>
        </div>
    </FormField>
</template>
