<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import { CornerDownRight, Eye, EyeOff, ExternalLink, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { subtreeHeight, type LinkType, type MenuItemNode } from './types';

defineOptions({ name: 'MenuTree' });

const items = defineModel<MenuItemNode[]>({ required: true });
const props = defineProps<{ depth: number; maxDepth: number; linkTypes: LinkType[]; locale: string; readonly?: boolean }>();
const emit = defineEmits<{ edit: [item: MenuItemNode]; remove: [item: MenuItemNode]; 'add-child': [item: MenuItemNode]; change: [] }>();

/** Block drops that would make the menu deeper than allowed. */
function onMove(event: { to: HTMLElement; dragged: HTMLElement }): boolean {
    const targetDepth = Number(event.to.dataset.depth ?? 1);
    const height = Number(event.dragged.dataset.height ?? 1);

    return targetDepth + height - 1 <= props.maxDepth;
}

function linkSummary(item: MenuItemNode): string {
    if (item.link_type === 'none') return 'Dropdown heading';
    if (item.link_type === 'url') return item.url ?? '';

    const type = props.linkTypes.find((t) => t.type === item.link_type);
    return type?.targets?.find((t) => t.value === item.link_target)?.label ?? item.link_target ?? '';
}

function label(item: MenuItemNode): string {
    return item.label[props.locale] || item.label.en || Object.values(item.label).find(Boolean) || 'Untitled';
}
</script>

<template>
    <VueDraggable
        v-model="items"
        tag="ul"
        :data-depth="depth"
        group="menu-items"
        handle=".drag-handle"
        :animation="160"
        :disabled="readonly"
        :fallback-on-body="true"
        :swap-threshold="0.6"
        ghost-class="menu-ghost"
        :on-move="onMove"
        class="space-y-2"
        :class="depth > 1 ? 'mt-2 ml-6 min-h-2 border-l-2 border-dashed border-line pl-4 sm:ml-8' : 'min-h-10'"
        @end="emit('change')"
    >
        <li v-for="item in items" :key="item.uid" :data-height="subtreeHeight(item)">
            <div
                class="group flex items-center gap-2 rounded-xl border bg-white py-2 pr-2 pl-1.5 shadow-[var(--shadow-card)] transition-colors"
                :class="item.is_visible ? 'border-line' : 'border-dashed border-[#cfd7e2] bg-canvas/60'"
            >
                <button
                    v-if="!readonly"
                    type="button"
                    class="drag-handle flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas hover:text-ink active:cursor-grabbing"
                    :aria-label="`Drag ${label(item)}`"
                >
                    <GripVertical class="size-4" />
                </button>
                <CornerDownRight v-else-if="depth > 1" class="ml-2 size-4 text-ink-muted" />

                <button type="button" class="min-w-0 flex-1 text-left" :disabled="readonly" @click="emit('edit', item)">
                    <span class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold" :class="item.is_visible ? 'text-ink' : 'text-ink-muted line-through decoration-1'">
                            {{ label(item) }}
                        </span>
                        <ExternalLink v-if="item.open_in_new_tab" class="size-3 shrink-0 text-ink-muted" />
                    </span>
                    <span class="block truncate text-[12px] text-ink-muted">{{ linkSummary(item) }}</span>
                </button>

                <div v-if="!readonly" class="flex shrink-0 items-center">
                    <button
                        v-if="depth < maxDepth"
                        type="button"
                        class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-brand"
                        :aria-label="`Add a sub-item under ${label(item)}`"
                        title="Add sub-item"
                        @click="emit('add-child', item)"
                    >
                        <Plus class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-ink"
                        :aria-label="item.is_visible ? `Hide ${label(item)}` : `Show ${label(item)}`"
                        :title="item.is_visible ? 'Hide' : 'Show'"
                        @click="item.is_visible = !item.is_visible; emit('change')"
                    >
                        <Eye v-if="item.is_visible" class="size-4" />
                        <EyeOff v-else class="size-4" />
                    </button>
                    <button type="button" class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-brand" :aria-label="`Edit ${label(item)}`" title="Edit" @click="emit('edit', item)">
                        <Pencil class="size-4" />
                    </button>
                    <button type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${label(item)}`" title="Delete" @click="emit('remove', item)">
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <MenuTree
                v-if="depth < maxDepth"
                v-model="item.children"
                :depth="depth + 1"
                :max-depth="maxDepth"
                :link-types="linkTypes"
                :locale="locale"
                :readonly="readonly"
                @edit="emit('edit', $event)"
                @remove="emit('remove', $event)"
                @add-child="emit('add-child', $event)"
                @change="emit('change')"
            />
        </li>
    </VueDraggable>
</template>

<style>
.menu-ghost > div {
    border-style: dashed;
    border-color: var(--admin-primary);
    background: color-mix(in oklab, var(--admin-primary) 6%, white);
    opacity: 0.7;
}
</style>
