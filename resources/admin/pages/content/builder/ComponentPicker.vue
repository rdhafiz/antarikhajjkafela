<script setup lang="ts">
import { computed, ref } from 'vue';
import { Search } from 'lucide-vue-next';
import AppModal from '@admin/components/ui/AppModal.vue';
import { categoryLabels, componentIcon } from './componentIcons';
import type { ComponentDef } from './types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ components: ComponentDef[]; used: string[] }>();
const emit = defineEmits<{ pick: [component: ComponentDef] }>();

const search = ref('');

const groups = computed(() => {
    const term = search.value.trim().toLowerCase();
    const matching = props.components.filter((c) => !term || `${c.name} ${c.description}`.toLowerCase().includes(term));
    const order = ['hero', 'content', 'media', 'conversion', 'dynamic'];

    return order
        .map((category) => ({ category, label: categoryLabels[category] ?? category, items: matching.filter((c) => c.category === category) }))
        .filter((group) => group.items.length);
});

function disabled(component: ComponentDef): boolean {
    return !component.repeatable && props.used.includes(component.key);
}

function pick(component: ComponentDef): void {
    if (disabled(component)) return;
    emit('pick', component);
    open.value = false;
    search.value = '';
}
</script>

<template>
    <AppModal v-model:open="open" title="Add a section" description="Choose a design. You can change its text, images and colours afterwards." size="xl">
        <label class="relative mb-5 block">
            <span class="sr-only">Search sections</span>
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
            <input v-model="search" type="search" placeholder="Search, e.g. FAQ, form, video" class="control pl-9" />
        </label>

        <div v-for="group in groups" :key="group.category" class="mb-6 last:mb-0">
            <p class="mb-2.5 text-[11px] font-bold tracking-[0.08em] text-ink-muted uppercase">{{ group.label }}</p>
            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="component in group.items"
                    :key="component.key"
                    type="button"
                    :disabled="disabled(component)"
                    class="group flex items-start gap-3 rounded-xl border border-line p-3.5 text-left transition-[border-color,background-color,box-shadow] hover:border-brand/40 hover:bg-brand-soft/50 hover:shadow-[var(--shadow-card)] disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-line disabled:hover:bg-transparent"
                    @click="pick(component)"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand group-hover:bg-brand group-hover:text-white">
                        <component :is="componentIcon(component.icon)" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-ink">{{ component.name }}</span>
                        <span class="mt-0.5 block text-[12px] leading-snug text-ink-muted">
                            {{ disabled(component) ? 'Already on this page (one allowed).' : component.description }}
                        </span>
                    </span>
                </button>
            </div>
        </div>
        <p v-if="!groups.length" class="py-10 text-center text-sm text-ink-muted">No sections match "{{ search }}".</p>
    </AppModal>
</template>
