<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import { Check, ChevronDown, CornerDownRight, Eye, EyeOff, Layers, Link2, ListTree, PanelBottom, PanelTop, Pencil, Plus, Trash2, Undo2 } from 'lucide-vue-next';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import MenuItemModal from './MenuItemModal.vue';
import MenuTree, { type MenuAction } from './MenuTree.vue';
import headerPhoto from '@admin/assets/menus-header.webp';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http, validationErrors } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import { fromServer, toServer, uid, type LinkType, type MenuItemNode, type MenuLocation } from './types';

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const locations = ref<MenuLocation[]>([]);
const menu = ref<MenuLocation | null>(null);
const items = ref<MenuItemNode[]>([]);
const linkTypes = ref<LinkType[]>([]);
const loading = ref(true);
const saving = ref(false);
const saved = ref('[]');

const editorOpen = ref(false);
const editing = ref<MenuItemNode | null>(null);
const editingIsNew = ref(false);
let insertInto: MenuItemNode[] | null = null;

const drawerOpen = ref(false);
const selected = ref<{ item: MenuItemNode; depth: number; summary: string } | null>(null);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/** Totals for the open menu, updated as it is edited. */
const stats = computed(() => {
    const walk = (list: MenuItemNode[], depth: number): { all: number; hidden: number; dropdowns: number; deepest: number } =>
        list.reduce(
            (acc, item) => {
                const inner = walk(item.children, depth + 1);
                return {
                    all: acc.all + 1 + inner.all,
                    hidden: acc.hidden + (item.is_visible ? 0 : 1) + inner.hidden,
                    dropdowns: acc.dropdowns + (item.children.length ? 1 : 0) + inner.dropdowns,
                    deepest: Math.max(acc.deepest, depth, inner.deepest),
                };
            },
            { all: 0, hidden: 0, dropdowns: 0, deepest: 0 },
        );
    return walk(items.value, 1);
});

/** Visible top-level items, as the website shows them. */
const previewItems = computed(() => items.value.filter((item) => item.is_visible).map((item) => ({ uid: item.uid, label: itemLabel(item), dropdown: item.children.some((c) => c.is_visible) })));

function itemLabel(item: MenuItemNode): string {
    return item.label[app.defaultLocale] || item.label.en || Object.values(item.label).find(Boolean) || 'Untitled';
}

function onAction(action: MenuAction, item: MenuItemNode): void {
    if (action === 'edit') editItem(item);
    else if (action === 'add-child') addItem(item);
    else if (action === 'toggle') item.is_visible = !item.is_visible;
    else removeItem(item);
}

function undo(): void {
    items.value = fromServer(JSON.parse(saved.value));
}

// Ctrl/Cmd + S saves the menu.
function onKey(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && menu.value) {
        event.preventDefault();
        if (!readonly.value && dirty.value && !saving.value) save();
    }
}

function openItem(item: MenuItemNode, depth: number, summary: string): void {
    selected.value = { item, depth, summary };
    drawerOpen.value = true;
}

/** Runs a drawer action after closing the drawer, so modals and dialogs open on top cleanly. */
function act(action: (item: MenuItemNode) => unknown): void {
    const item = selected.value?.item;
    drawerOpen.value = false;
    if (item) action(item);
}

function selectedLabel(): string {
    const label = selected.value?.item.label ?? {};
    return label[app.defaultLocale] || label.en || Object.values(label).find(Boolean) || 'Untitled';
}

const location = computed(() => String(route.params.location || 'primary'));
const readonly = computed(() => !auth.can('menus.update'));
// Nothing loaded yet (saved is empty) is never "unsaved".
const dirty = computed(() => saved.value !== '' && JSON.stringify(toServer(items.value)) !== saved.value);

async function load(key: string): Promise<void> {
    loading.value = true;

    try {
        const { data } = await http.get<{ data: MenuLocation & { items: never[] }; link_types: LinkType[] }>(`/menus/${key}`);
        menu.value = data.data;
        linkTypes.value = data.link_types;
        items.value = fromServer(data.data.items);
        saved.value = JSON.stringify(toServer(items.value));
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

function blankItem(): MenuItemNode {
    return { uid: uid(), label: {}, link_type: 'route', link_target: null, url: null, open_in_new_tab: false, is_visible: true, children: [] };
}

function addItem(parent?: MenuItemNode): void {
    insertInto = parent ? parent.children : items.value;
    editing.value = blankItem();
    editingIsNew.value = true;
    editorOpen.value = true;
}

function editItem(item: MenuItemNode): void {
    insertInto = null;
    editing.value = item;
    editingIsNew.value = false;
    editorOpen.value = true;
}

/** Replace a node anywhere in the tree (matched by uid). */
function replaceNode(list: MenuItemNode[], node: MenuItemNode): boolean {
    const index = list.findIndex((i) => i.uid === node.uid);
    if (index >= 0) {
        list[index] = node;
        return true;
    }
    return list.some((i) => replaceNode(i.children, node));
}

function applyItem(item: MenuItemNode): void {
    if (editingIsNew.value && insertInto) {
        insertInto.push(item);
    } else {
        replaceNode(items.value, item);
    }
}

function removeNode(list: MenuItemNode[], uidToRemove: string): boolean {
    const index = list.findIndex((i) => i.uid === uidToRemove);
    if (index >= 0) {
        list.splice(index, 1);
        return true;
    }
    return list.some((i) => removeNode(i.children, uidToRemove));
}

async function removeItem(item: MenuItemNode): Promise<void> {
    if (item.children.length) {
        const ok = await confirm({
            title: 'Delete this item and its sub-items?',
            message: `"${item.label.en ?? 'This item'}" has ${item.children.length} sub-item(s) that will be deleted too.`,
            confirmLabel: 'Delete',
        });
        if (!ok) return;
    }
    removeNode(items.value, item.uid);
}

async function save(): Promise<void> {
    saving.value = true;

    try {
        const { data } = await http.put<{ data: MenuLocation & { items: never[] } }>(`/menus/${location.value}`, { items: toServer(items.value) });
        items.value = fromServer(data.data.items);
        saved.value = JSON.stringify(toServer(items.value));
        http.get<{ data: MenuLocation[] }>('/menus').then(({ data }) => (locations.value = data.data)).catch(() => {});
        toast.success(`${menu.value?.name ?? 'Menu'} saved`);
    } catch (e) {
        const errors = validationErrors(e);
        toast.error('Could not save the menu', errors ? Object.values(errors)[0]?.[0] : errorMessage(e));
    } finally {
        saving.value = false;
    }
}

async function confirmDiscard(): Promise<boolean> {
    return !dirty.value || confirm({ title: 'Discard unsaved changes?', message: 'Your changes to this menu have not been saved.', confirmLabel: 'Discard changes' });
}

onBeforeRouteUpdate(async (to) => {
    if (!(await confirmDiscard())) return false;
    await load(String(to.params.location || 'primary'));
    return true;
});
onBeforeRouteLeave(confirmDiscard);

function beforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) event.preventDefault();
}

onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);
    window.addEventListener('keydown', onKey);
    const { data } = await http.get<{ data: MenuLocation[] }>('/menus');
    locations.value = data.data;
    await load(location.value);
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div>
        <!-- Header with a photo -->
        <ListHero title="Menus" description="Choose which links appear in the website navigation and footer, and in what order." :icon="ListTree" tone="#a3e635" :photo="headerPhoto" photo-position="50% 40%">
            <template #actions>
                <AppButton v-if="!readonly" :icon="Plus" @click="addItem()">Add item</AppButton>
            </template>
        </ListHero>

        <!-- Which menu -->
        <div v-if="locations.length" class="mn-locations mb-5" role="group" aria-label="Menu location">
            <button
                v-for="loc in locations"
                :key="loc.key"
                type="button"
                class="mn-location"
                :class="{ 'is-on': location === loc.key }"
                :aria-pressed="location === loc.key"
                @click="router.push({ name: 'menus', params: { location: loc.key } })"
            >
                <span class="mn-location-icon" aria-hidden="true"><component :is="loc.key === 'footer' ? PanelBottom : PanelTop" class="size-5" /></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block text-[15px] font-bold text-ink">{{ loc.name }}</span>
                    <span class="block text-[12.5px] text-ink-soft">{{ loc.items_count ?? 0 }} {{ loc.items_count === 1 ? 'item' : 'items' }} · {{ loc.max_depth > 1 ? `up to ${loc.max_depth} levels` : 'one level' }}</span>
                </span>
                <Check v-if="location === loc.key" class="size-5 shrink-0 text-brand" aria-hidden="true" />
            </button>
        </div>

        <div v-if="loading" class="grid gap-3" aria-busy="true" aria-label="Loading the menu">
            <div v-for="n in 4" :key="n" class="card h-[66px] animate-pulse" />
        </div>

        <div v-else-if="menu" class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
                <!-- Totals -->
                <p class="mb-3.5 flex flex-wrap gap-2" aria-label="This menu">
                    <span class="mn-stat" style="--tone: #a3e635"><ListTree class="size-3.5" aria-hidden="true" />{{ stats.all }} {{ stats.all === 1 ? 'item' : 'items' }}</span>
                    <span v-if="menu.max_depth > 1 && stats.dropdowns" class="mn-stat" style="--tone: #a78bfa"><ChevronDown class="size-3.5" aria-hidden="true" />{{ stats.dropdowns }} {{ stats.dropdowns === 1 ? 'dropdown' : 'dropdowns' }}</span>
                    <span v-if="menu.max_depth > 1" class="mn-stat" style="--tone: #38bdf8"><Layers class="size-3.5" aria-hidden="true" />{{ stats.deepest }} of {{ menu.max_depth }} levels used</span>
                    <span v-if="stats.hidden" class="mn-stat" style="--tone: #94a3b8"><EyeOff class="size-3.5" aria-hidden="true" />{{ stats.hidden }} hidden</span>
                </p>

                <div v-if="!items.length" class="card">
                    <EmptyState :icon="ListTree" title="This menu is empty" description="Add the first link to show it on the website.">
                        <AppButton v-if="!readonly" :icon="Plus" @click="addItem()">Add item</AppButton>
                    </EmptyState>
                </div>
                <MenuTree
                    v-else
                    v-model="items"
                    :depth="1"
                    :max-depth="menu.max_depth"
                    :link-types="linkTypes"
                    :locale="app.defaultLocale"
                    :readonly="readonly"
                    @open="openItem"
                    @action="onAction"
                />

                <!-- Appears only when something changed -->
                <Transition name="st-bar">
                    <div v-if="!readonly && dirty" class="st-bar sticky bottom-3 z-20 mt-5 sm:bottom-5" role="region" aria-label="Unsaved changes">
                        <div class="flex items-center gap-2 rounded-2xl bg-[#0b1d33] py-2.5 pr-2.5 pl-4 text-white shadow-[0_18px_44px_-14px_rgb(2_12_27/0.55)] sm:gap-3 sm:pl-5">
                            <span class="st-pulse size-2 shrink-0 rounded-full bg-amber-400" aria-hidden="true" />
                            <p class="min-w-0 flex-1 truncate text-[14px] font-semibold">
                                Unsaved changes
                                <span class="ml-2 hidden font-normal text-white/60 md:inline"><kbd class="rounded border border-white/20 px-1.5 py-0.5 font-sans text-[11.5px]">{{ isMac ? '⌘' : 'Ctrl' }} S</kbd> to save</span>
                            </p>
                            <button type="button" class="flex h-10 items-center gap-1.5 rounded-xl px-3 text-[14px] font-semibold text-white/80 hover:bg-white/10 hover:text-white disabled:opacity-50" :disabled="saving" @click="undo">
                                <Undo2 class="size-4" aria-hidden="true" />Undo
                            </button>
                            <button type="button" class="st-save flex h-10 items-center gap-1.5 rounded-xl px-4 text-[14px] font-bold disabled:opacity-70" :disabled="saving" @click="save">
                                <Check class="size-4" aria-hidden="true" />{{ saving ? 'Saving' : 'Save menu' }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24 xl:h-fit">
                <!-- What the website shows -->
                <figure class="card overflow-hidden" aria-label="Preview on the website">
                    <figcaption class="flex items-center justify-between border-b border-line px-4 py-3">
                        <span class="text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Preview</span>
                        <span class="text-[12px] text-ink-soft">Shown items only</span>
                    </figcaption>
                    <div v-if="menu.key !== 'footer'" class="mn-preview-header">
                        <span class="mn-preview-logo" aria-hidden="true" />
                        <ul class="flex min-w-0 flex-wrap gap-x-3 gap-y-1">
                            <li v-for="link in previewItems" :key="link.uid" class="inline-flex items-center gap-0.5">
                                {{ link.label }}<ChevronDown v-if="link.dropdown" class="size-3 opacity-70" aria-hidden="true" />
                            </li>
                            <li v-if="!previewItems.length" class="opacity-60">No shown items</li>
                        </ul>
                    </div>
                    <div v-else class="mn-preview-footer">
                        <p class="mb-2 text-[11px] font-bold tracking-[0.14em] text-[#fdc802] uppercase">Links</p>
                        <ul class="space-y-1.5">
                            <li v-for="link in previewItems" :key="link.uid">{{ link.label }}</li>
                            <li v-if="!previewItems.length" class="opacity-60">No shown items</li>
                        </ul>
                    </div>
                </figure>

                <div class="card p-5 text-[13px] leading-relaxed text-ink-soft">
                    <p class="text-[14px] font-bold text-ink">{{ menu.name }}</p>
                    <p class="mt-1">{{ menu.description }}</p>
                    <ul class="mt-4 space-y-2">
                        <li>Drag the <strong class="text-ink">⋮⋮</strong> handle to reorder items{{ menu.max_depth > 1 ? ' or move them into a dropdown' : '' }}.</li>
                        <li v-if="menu.max_depth > 1">Up to {{ menu.max_depth }} levels: menu → dropdown → sub-dropdown.</li>
                        <li>Use an item's <strong class="text-ink">⋮</strong> menu to edit, hide, delete or add a sub-item.</li>
                        <li>Changes go live when you press <strong class="text-ink">Save menu</strong>.</li>
                    </ul>
                </div>
            </aside>
        </div>

        <AppDrawer v-model:open="drawerOpen" :title="selected ? selectedLabel() : ''" :description="selected?.summary" :icon="ListTree">
            <template v-if="selected">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="selected.item.is_visible ? 'success' : 'neutral'">{{ selected.item.is_visible ? 'Shown' : 'Hidden' }}</AppBadge>
                    <AppBadge v-if="selected.item.children.length" tone="brand">{{ selected.item.children.length }} sub-item{{ selected.item.children.length === 1 ? '' : 's' }}</AppBadge>
                    <AppBadge v-if="selected.item.open_in_new_tab" tone="neutral">Opens in a new tab</AppBadge>
                </div>
                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Link2 class="size-4 text-sky-600" />Links to</dt>
                    <dd class="break-words">{{ selected.summary || '—' }}</dd>
                    <dt>Level</dt>
                    <dd>{{ selected.depth === 1 ? 'Top level' : `Level ${selected.depth}` }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <button type="button" class="dw-action" @click="act(editItem)">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit item
                    </button>
                    <button v-if="menu && selected.depth < menu.max_depth" type="button" class="dw-action" @click="act(addItem)">
                        <span class="dw-action-icon" style="--tone: #a78bfa"><CornerDownRight class="size-4" /></span>Add a sub-item
                    </button>
                    <button type="button" class="dw-action" @click="selected.item.is_visible = !selected.item.is_visible">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><component :is="selected.item.is_visible ? EyeOff : Eye" class="size-4" /></span>
                        {{ selected.item.is_visible ? 'Hide from the website' : 'Show on the website' }}
                    </button>
                    <button type="button" class="dw-action is-danger" @click="act(removeItem)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete item
                    </button>
                </div>
            </template>
        </AppDrawer>

        <MenuItemModal v-model:open="editorOpen" :item="editing" :link-types="linkTypes" :is-new="editingIsNew" @save="applyItem" />
    </div>
</template>

<style scoped>
.mn-locations {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
}

.mn-location {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    border-radius: 1rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0.85rem 1rem;
    box-shadow: var(--shadow-card);
    transition: border-color 200ms ease, box-shadow 200ms ease, background-color 200ms ease;

    &:hover {
        border-color: #cbd3df;
    }

    &.is-on {
        border-color: color-mix(in oklab, var(--admin-primary) 55%, white);
        background: color-mix(in oklab, var(--admin-primary) 5%, white);
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--admin-primary) 55%, white), var(--shadow-card);
    }
}

.mn-location-icon {
    display: flex;
    width: 2.6rem;
    height: 2.6rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background: color-mix(in oklab, #a3e635 20%, white);
    color: color-mix(in oklab, #a3e635 35%, black);

    .is-on & {
        background: var(--admin-primary);
        color: #fff;
    }
}

.mn-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    background: color-mix(in oklab, var(--tone) 15%, white);
    padding: 0.3rem 0.75rem;
    font-size: 12.5px;
    font-weight: 600;
    color: color-mix(in oklab, var(--tone) 35%, black);
}

/* A small mock of the site header / footer in the brand colours. */
.mn-preview-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: #fff;
    padding: 0.9rem 1rem;
    font-size: 12.5px;
    font-weight: 600;
    color: #012a51;
    box-shadow: inset 0 -1px 0 var(--color-line);
}

.mn-preview-logo {
    width: 1.6rem;
    height: 1.6rem;
    flex-shrink: 0;
    border-radius: 0.45rem;
    background: linear-gradient(135deg, #012a51, #01914d);
}

.mn-preview-footer {
    background: #011c36;
    padding: 1rem 1.1rem;
    font-size: 12.5px;
    color: rgb(255 255 255 / 0.82);
}
</style>
