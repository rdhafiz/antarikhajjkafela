<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import { CornerDownRight, Eye, EyeOff, Link2, ListTree, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import TabSwitcher from '@admin/components/ui/TabSwitcher.vue';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import MenuItemModal from './MenuItemModal.vue';
import MenuTree from './MenuTree.vue';
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
const locationOptions = computed(() => locations.value.map((loc) => ({ value: loc.key, label: loc.name })));

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
    const { data } = await http.get<{ data: MenuLocation[] }>('/menus');
    locations.value = data.data;
    await load(location.value);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <div>
        <PageHeader title="Menus" description="Choose which links appear in the website navigation and footer, and in what order.">
            <template #actions>
                <AppButton v-if="!readonly" variant="secondary" :icon="Plus" @click="addItem()">Add item</AppButton>
                <AppButton v-if="!readonly" :loading="saving" :disabled="!dirty" @click="save">Save menu</AppButton>
            </template>
        </PageHeader>

        <TabSwitcher
            v-if="locationOptions.length"
            :model-value="location"
            :options="locationOptions"
            label="Menu location"
            variant="underline"
            class="mb-5"
            @update:model-value="(key) => router.push({ name: 'menus', params: { location: key } })"
        />

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>

        <div v-else-if="menu" class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
            <div>
                <div v-if="dirty" class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800">
                    <span class="font-semibold">You have unsaved changes to this menu.</span>
                    <AppButton size="sm" :loading="saving" @click="save">Save menu</AppButton>
                </div>

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
                />
            </div>

            <aside class="card h-fit p-5 text-[13px] leading-relaxed text-ink-soft">
                <p class="text-[14px] font-bold text-ink">{{ menu.name }}</p>
                <p class="mt-1">{{ menu.description }}</p>
                <ul class="mt-4 space-y-2">
                    <li>Drag the <strong class="text-ink">⋮⋮</strong> handle to reorder items{{ menu.max_depth > 1 ? ' or move them into a dropdown' : '' }}.</li>
                    <li v-if="menu.max_depth > 1">Up to {{ menu.max_depth }} levels: menu → dropdown → sub-dropdown.</li>
                    <li>Open an item to edit, hide, delete or add a sub-item to it.</li>
                    <li>Changes go live when you press <strong class="text-ink">Save menu</strong>.</li>
                </ul>
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
                    <button type="button" class="ad-action" @click="act(editItem)">
                        <span class="ad-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit item
                    </button>
                    <button v-if="menu && selected.depth < menu.max_depth" type="button" class="ad-action" @click="act(addItem)">
                        <span class="ad-action-icon" style="--tone: #a78bfa"><CornerDownRight class="size-4" /></span>Add a sub-item
                    </button>
                    <button type="button" class="ad-action" @click="selected.item.is_visible = !selected.item.is_visible">
                        <span class="ad-action-icon" style="--tone: #38bdf8"><component :is="selected.item.is_visible ? EyeOff : Eye" class="size-4" /></span>
                        {{ selected.item.is_visible ? 'Hide from the website' : 'Show on the website' }}
                    </button>
                    <button type="button" class="ad-action is-danger" @click="act(removeItem)">
                        <span class="ad-action-icon"><Trash2 class="size-4" /></span>Delete item
                    </button>
                </div>
            </template>
        </AppDrawer>

        <MenuItemModal v-model:open="editorOpen" :item="editing" :link-types="linkTypes" :is-new="editingIsNew" @save="applyItem" />
    </div>
</template>
