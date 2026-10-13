<script setup lang="ts">
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ChevronDown, Copy, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import FieldList from './FieldList.vue';
import { deepClone, newRepeaterItem, pickText, uid, type FieldDef } from './types';
import { useAppStore } from '@admin/stores/app';

/** A sortable list of items, each edited with the repeater's own fields. */
const items = defineModel<Record<string, unknown>[]>({ default: () => [] });
const props = defineProps<{ field: FieldDef; errors: Record<string, string[]>; path: string; icons: { name: string; svg: string }[] }>();

const app = useAppStore();
const open = ref<string | null>(null);

const max = computed(() => props.field.max ?? 12);
const groupError = computed(() => props.errors[props.path]?.[0]);

/** Make sure every item has a stable id for keys and drag-and-drop. */
const list = computed({
    get: () => {
        for (const item of items.value ?? []) {
            if (!item._id) item._id = uid();
        }
        return items.value ?? [];
    },
    set: (value) => (items.value = value),
});

function itemTitle(item: Record<string, unknown>, index: number): string {
    const key = props.field.item_label;
    const child = props.field.fields?.find((f) => f.key === key);
    const raw = key ? item[key] : null;
    const text = child?.type === 'select' ? child.options?.[String(raw)] : pickText(raw, app.defaultLocale);

    // Without a title (e.g. an image-only slide), the item's type names it.
    const kind = props.field.fields?.find((f) => f.type === 'select');
    const kindLabel = kind ? kind.options?.[String(item[kind.key])] : null;

    return text || kindLabel || `Item ${index + 1}`;
}

function itemHasError(index: number): boolean {
    return Object.keys(props.errors).some((k) => k.startsWith(`${props.path}.${index}.`));
}

function add(): void {
    const item = newRepeaterItem(props.field.fields ?? []);
    list.value = [...list.value, item];
    open.value = item._id as string;
}

function duplicate(index: number): void {
    const copy = { ...deepClone(list.value[index]), _id: uid() };
    const next = [...list.value];
    next.splice(index + 1, 0, copy);
    list.value = next;
    open.value = copy._id as string;
}

function remove(index: number): void {
    list.value = list.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div>
        <div class="mb-2 flex items-baseline justify-between gap-3">
            <p class="text-[13px] font-semibold text-ink">
                {{ field.label }} <span class="font-medium text-ink-muted">({{ list.length }}/{{ max }})</span>
            </p>
            <p v-if="field.hint" class="text-[12px] text-ink-muted">{{ field.hint }}</p>
        </div>

        <VueDraggable v-model="list" handle=".item-handle" :animation="160" class="space-y-2">
            <div
                v-for="(item, index) in list"
                :key="item._id as string"
                class="overflow-hidden rounded-xl border bg-white"
                :class="itemHasError(index) ? 'border-red-300' : 'border-line'"
            >
                <div class="flex items-center gap-1 py-1.5 pr-1.5 pl-1">
                    <button type="button" class="item-handle flex size-8 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas active:cursor-grabbing" aria-label="Drag to reorder">
                        <GripVertical class="size-4" />
                    </button>
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2 py-1 text-left" :aria-expanded="open === item._id" @click="open = open === item._id ? null : (item._id as string)">
                        <span class="truncate text-[13px] font-semibold" :class="itemHasError(index) ? 'text-red-600' : 'text-ink'">{{ itemTitle(item, index) }}</span>
                        <ChevronDown class="size-4 shrink-0 text-ink-muted transition-transform" :class="{ 'rotate-180': open === item._id }" />
                    </button>
                    <button v-if="list.length < max" type="button" class="rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-brand" title="Duplicate" aria-label="Duplicate item" @click="duplicate(index)">
                        <Copy class="size-3.5" />
                    </button>
                    <button type="button" class="rounded-lg p-1.5 text-ink-muted hover:bg-red-50 hover:text-red-600" title="Remove" aria-label="Remove item" @click="remove(index)">
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
                <div v-if="open === item._id" class="border-t border-line bg-canvas/40 p-3.5">
                    <FieldList :fields="field.fields ?? []" :model-value="item" :errors="errors" :path="`${path}.${index}`" :icons="icons" @update:model-value="list[index] = $event" />
                </div>
            </div>
        </VueDraggable>

        <button
            v-if="list.length < max"
            type="button"
            class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-[#cfd7e2] py-2.5 text-[13px] font-semibold text-ink-soft transition-colors hover:border-brand/50 hover:text-brand"
            @click="add"
        >
            <Plus class="size-4" /> Add {{ field.label.toLowerCase().replace(/s$/, '') }}
        </button>
        <p v-if="groupError" class="mt-1.5 text-[13px] font-medium text-red-600">{{ groupError }}</p>
    </div>
</template>
