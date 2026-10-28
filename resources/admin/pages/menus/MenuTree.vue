<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import {
    ChevronDown,
    ChevronRight,
    Clock,
    Compass,
    CornerDownRight,
    EllipsisVertical,
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Globe,
    GripVertical,
    Package,
    Pencil,
    Plane,
    Trash2,
} from 'lucide-vue-next';
import { subtreeHeight, type LinkType, type MenuItemNode } from './types';

defineOptions({ name: 'MenuTree' });

export type MenuAction = 'edit' | 'add-child' | 'toggle' | 'remove';

const items = defineModel<MenuItemNode[]>({ required: true });
const props = defineProps<{ depth: number; maxDepth: number; linkTypes: LinkType[]; locale: string; readonly?: boolean }>();
const emit = defineEmits<{
    open: [item: MenuItemNode, depth: number, summary: string];
    action: [action: MenuAction, item: MenuItemNode];
    change: [];
}>();

/** Icon and colour for each kind of link. */
const kinds: Record<string, { icon: unknown; tone: string; label: string }> = {
    route: { icon: Compass, tone: '#38bdf8', label: 'Site page' },
    page: { icon: FileText, tone: '#60a5fa', label: 'Page' },
    service: { icon: Plane, tone: '#a78bfa', label: 'Service' },
    package: { icon: Package, tone: '#fbbf24', label: 'Package' },
    url: { icon: Globe, tone: '#2dd4bf', label: 'Web address' },
    none: { icon: ChevronDown, tone: '#94a3b8', label: 'Dropdown' },
};
const kind = (item: MenuItemNode) => kinds[item.link_type] ?? kinds.url;

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

/** Why the linked page, service or package isn't on the website yet (the item stays out of the live menu). */
function offline(item: MenuItemNode): string | null {
    const type = props.linkTypes.find((t) => t.type === item.link_type);
    return type?.targets?.find((t) => t.value === item.link_target)?.offline ?? null;
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
        :animation="200"
        :disabled="readonly"
        :fallback-on-body="true"
        :swap-threshold="0.6"
        ghost-class="menu-ghost"
        :on-move="onMove"
        class="space-y-2.5"
        :class="depth > 1 ? ['ml-6 min-h-2 pl-4 sm:ml-9', items.length ? 'mt-2.5 border-l-2 border-dashed border-line' : 'mt-0'] : 'min-h-10'"
        @end="emit('change')"
    >
        <li v-for="item in items" :key="item.uid" :data-height="subtreeHeight(item)">
            <div class="mt-row group" :class="{ 'is-hidden': !item.is_visible }" :style="{ '--kind': kind(item).tone }">
                <button
                    v-if="!readonly"
                    type="button"
                    class="drag-handle flex size-9 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas hover:text-ink active:cursor-grabbing"
                    :aria-label="`Drag ${label(item)}`"
                >
                    <GripVertical class="size-4" />
                </button>
                <CornerDownRight v-else-if="depth > 1" class="ml-2 size-4 text-ink-muted" aria-hidden="true" />

                <span class="mt-kind" :title="kind(item).label" aria-hidden="true"><component :is="kind(item).icon" class="size-[18px]" /></span>

                <button type="button" class="min-w-0 flex-1 py-1 text-left" :disabled="readonly" @click="emit('open', item, depth, linkSummary(item))">
                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="mt-label">{{ label(item) }}</span>
                        <span v-if="item.children.length" class="mt-badge"><ChevronDown class="size-3" aria-hidden="true" />{{ item.children.length }}</span>
                        <span v-if="item.open_in_new_tab" class="mt-badge" title="Opens in a new tab"><ExternalLink class="size-3" aria-hidden="true" /><span class="max-sm:sr-only">New tab</span></span>
                        <span v-if="!item.is_visible" class="mt-badge is-hidden"><EyeOff class="size-3" aria-hidden="true" />Hidden</span>
                        <span
                            v-else-if="offline(item)"
                            class="mt-badge is-offline"
                            :title="`Not in the website's menu until the ${kind(item).label.toLowerCase()} is live`"
                        >
                            <Clock class="size-3" aria-hidden="true" />Not shown: {{ offline(item)!.toLowerCase() }}
                        </span>
                    </span>
                    <span class="mt-summary"><span class="mt-kind-name">{{ kind(item).label }}</span> · {{ linkSummary(item) || '—' }}</span>
                </button>

                <template v-if="!readonly">
                    <DropdownMenuRoot :modal="false">
                        <DropdownMenuTrigger class="mt-icon-btn" :aria-label="`More actions for ${label(item)}`">
                            <EllipsisVertical class="size-5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuContent align="end" :side-offset="6" class="z-50 min-w-56 rounded-2xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                                <DropdownMenuItem class="lm-item" @select="emit('action', 'edit', item)">
                                    <span class="lm-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit item
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="depth < maxDepth" class="lm-item" @select="emit('action', 'add-child', item)">
                                    <span class="lm-icon" style="--tone: #a78bfa"><CornerDownRight class="size-4" /></span>Add a sub-item
                                </DropdownMenuItem>
                                <DropdownMenuItem class="lm-item" @select="emit('action', 'toggle', item)">
                                    <span class="lm-icon" style="--tone: #34d399"><component :is="item.is_visible ? EyeOff : Eye" class="size-4" /></span>{{ item.is_visible ? 'Hide from the website' : 'Show on the website' }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator class="my-1 h-px bg-line" />
                                <DropdownMenuItem class="lm-item is-danger" @select="emit('action', 'remove', item)">
                                    <span class="lm-icon"><Trash2 class="size-4" /></span>Delete item
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenuPortal>
                    </DropdownMenuRoot>
                    <button type="button" class="mt-icon-btn mt-open" :aria-label="`Open ${label(item)}`" @click="emit('open', item, depth, linkSummary(item))">
                        <ChevronRight class="size-5" />
                    </button>
                </template>
            </div>

            <MenuTree
                v-if="depth < maxDepth"
                v-model="item.children"
                :depth="depth + 1"
                :max-depth="maxDepth"
                :link-types="linkTypes"
                :locale="locale"
                :readonly="readonly"
                @open="(child, d, summary) => emit('open', child, d, summary)"
                @action="(action, child) => emit('action', action, child)"
                @change="emit('change')"
            />
        </li>
    </VueDraggable>
</template>

<style scoped>
.mt-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 1rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0.55rem 0.6rem 0.55rem 0.45rem;
    box-shadow: var(--shadow-card);
    transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;

    &:hover {
        border-color: #d5dde8;
        box-shadow: 0 12px 28px -18px rgb(15 23 42 / 0.35), var(--shadow-card);
    }

    &.is-hidden {
        border-style: dashed;
        border-color: #cbd5e1;
        background: #fbfcfd;
    }

    &.is-hidden .mt-kind {
        filter: grayscale(1);
    }
}

.mt-kind {
    display: flex;
    width: 2.4rem;
    height: 2.4rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    background: color-mix(in oklab, var(--kind) 16%, white);
    color: color-mix(in oklab, var(--kind) 50%, black);
}

.mt-label {
    min-width: 0;
    overflow-wrap: anywhere;
    font-size: 14.5px;
    font-weight: 700;
    color: var(--color-ink);
}

.mt-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    background: var(--color-canvas);
    padding: 0.1rem 0.5rem;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--color-ink-soft);

    &.is-hidden {
        background: #eef1f5;
        color: #475569;
    }

    &.is-offline {
        background: #fef3c7;
        color: #92400e;
    }
}

.mt-summary {
    display: block;
    margin-top: 0.15rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12.5px;
    color: var(--color-ink-soft);
}

.mt-kind-name {
    font-weight: 600;
    color: color-mix(in oklab, var(--kind) 45%, black);
}

.mt-icon-btn {
    display: flex;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: var(--color-ink-soft);
    transition: background-color 160ms ease, color 160ms ease, transform 200ms ease;

    &:hover,
    &[data-state='open'] {
        background: var(--color-canvas);
        color: var(--color-ink);
    }
}

.mt-open {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-line), 0 4px 12px -6px rgb(15 23 42 / 0.25);

    .mt-row:hover & {
        transform: translateX(2px);
        color: var(--color-ink);
    }
}

@media (prefers-reduced-motion: reduce) {
    .mt-row,
    .mt-open {
        transition: none;
    }
}
</style>

<style>
.menu-ghost > div {
    border-style: dashed;
    border-color: var(--admin-primary);
    background: color-mix(in oklab, var(--admin-primary) 6%, white);
    opacity: 0.7;
}
</style>
