<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import { ListTree, Plus } from 'lucide-vue-next';
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

        <div class="mb-5 flex gap-1 border-b border-line" role="tablist">
            <button
                v-for="loc in locations"
                :key="loc.key"
                type="button"
                role="tab"
                :aria-selected="location === loc.key"
                class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
                :class="location === loc.key ? 'border-brand text-brand' : 'border-transparent text-ink-muted hover:text-ink'"
                @click="router.push({ name: 'menus', params: { location: loc.key } })"
            >
                {{ loc.name }}
            </button>
        </div>

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
                    @edit="editItem"
                    @remove="removeItem"
                    @add-child="addItem"
                />
            </div>

            <aside class="card h-fit p-5 text-[13px] leading-relaxed text-ink-soft">
                <p class="text-[14px] font-bold text-ink">{{ menu.name }}</p>
                <p class="mt-1">{{ menu.description }}</p>
                <ul class="mt-4 space-y-2">
                    <li>Drag the <strong class="text-ink">⋮⋮</strong> handle to reorder items{{ menu.max_depth > 1 ? ' or move them into a dropdown' : '' }}.</li>
                    <li v-if="menu.max_depth > 1">Up to {{ menu.max_depth }} levels: menu → dropdown → sub-dropdown.</li>
                    <li>Hide an item with the eye icon to keep it for later.</li>
                    <li>Changes go live when you press <strong class="text-ink">Save menu</strong>.</li>
                </ul>
            </aside>
        </div>

        <MenuItemModal v-model:open="editorOpen" :item="editing" :link-types="linkTypes" :is-new="editingIsNew" @save="applyItem" />
    </div>
</template>
