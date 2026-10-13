<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, type Component } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { Activity, Check, CheckCheck, Crown, Eraser, Plus, Search, ShieldCheck, Trash2, Undo2, Users, Wallet, X } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import headerPhoto from '@admin/assets/roles-header.webp';
import { navigation } from '@admin/navigation';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { titleCase } from '@admin/lib/format';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { PermissionModule, Role } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const roles = ref<Role[]>([]);
const modules = ref<PermissionModule[]>([]);
const loading = ref(true);
const selectedId = ref<number | null>(null);
const draft = ref<Set<string>>(new Set());
const search = ref('');

const selected = computed(() => roles.value.find((r) => r.id === selectedId.value) ?? null);
const readonly = computed(() => !selected.value || selected.value.is_super_admin || !auth.can('roles.update'));
const dirty = computed(() => {
    const original = new Set(selected.value?.permissions ?? []);
    return original.size !== draft.value.size || [...draft.value].some((p) => !original.has(p));
});
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

const save = useForm({});
const create = useForm({ name: '' });
const createOpen = ref(false);

/** Each permission module wears its sidebar colour and icon. */
const navItems = navigation.flatMap((section) => section.items);
const extras: Record<string, { icon: Component; tone: string }> = {
    payments: { icon: Wallet, tone: '#34d399' },
    activity: { icon: Activity, tone: '#94a3b8' },
};
function look(key: string): { icon: Component; tone: string } {
    const item = navItems.find((i) => (key === 'dashboard' ? i.to === '/admin' : i.to === `/admin/${key}` || (key === 'blog' && i.to === '/admin/blog')));
    return item ? { icon: item.icon, tone: item.tone } : (extras[key] ?? { icon: ShieldCheck, tone: '#818cf8' });
}

const totalPermissions = computed(() => modules.value.reduce((sum, m) => sum + m.actions.length, 0));
function coverage(role: Role): number {
    return role.is_super_admin ? totalPermissions.value : (role.permissions?.length ?? 0);
}

const visibleModules = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return modules.value;
    return modules.value.filter((m) => m.label.toLowerCase().includes(term) || m.actions.some((a) => a.includes(term)));
});

async function load(): Promise<void> {
    try {
        const [rolesResponse, modulesResponse] = await Promise.all([http.get<{ data: Role[] }>('/roles'), http.get<{ data: PermissionModule[] }>('/permissions')]);
        roles.value = rolesResponse.data.data;
        modules.value = modulesResponse.data.data;

        if (!selected.value && roles.value.length) select(roles.value.find((r) => !r.is_super_admin) ?? roles.value[0]!);
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

async function pick(role: Role): Promise<void> {
    if (role.id === selectedId.value) return;
    if (dirty.value && !(await confirm({ title: 'Discard unsaved changes?', message: `Your changes to ${selected.value?.label} have not been saved.`, confirmLabel: 'Discard changes' }))) return;
    select(role);
}

function select(role: Role): void {
    selectedId.value = role.id;
    draft.value = new Set(role.permissions ?? []);
}

function has(permission: string): boolean {
    return selected.value?.is_super_admin === true || draft.value.has(permission);
}

function toggle(permission: string): void {
    if (readonly.value) return;
    const next = new Set(draft.value);
    next.has(permission) ? next.delete(permission) : next.add(permission);
    draft.value = next;
}

function grantedCount(module: PermissionModule): number {
    return module.actions.filter((a) => has(`${module.key}.${a}`)).length;
}

function setModule(module: PermissionModule, on: boolean): void {
    const next = new Set(draft.value);
    for (const action of module.actions) on ? next.add(`${module.key}.${action}`) : next.delete(`${module.key}.${action}`);
    draft.value = next;
}

function setAll(on: boolean): void {
    draft.value = on ? new Set(modules.value.flatMap((m) => m.actions.map((a) => `${m.key}.${a}`))) : new Set();
}

function undo(): void {
    if (selected.value) select(selected.value);
}

async function savePermissions(): Promise<void> {
    const role = selected.value;
    if (!role || readonly.value || !dirty.value || save.processing) return;

    const response = await save.submit(() => http.put<{ data: Role }>(`/roles/${role.id}`, { name: role.label, permissions: [...draft.value] }));

    if (response) {
        const index = roles.value.findIndex((r) => r.id === role.id);
        roles.value[index] = { ...response.data.data, users_count: role.users_count };
        select(roles.value[index]!);
        toast.success('Permissions saved', `${response.data.data.label} updated.`);
    }
}

async function createRole(): Promise<void> {
    const response = await create.submit(() => http.post<{ data: Role }>('/roles', { name: create.data.name, permissions: [] }));

    if (response) {
        roles.value.push({ ...response.data.data, users_count: 0 });
        select(response.data.data);
        createOpen.value = false;
        create.reset();
        toast.success('Role created', 'Now choose its permissions.');
    }
}

async function removeRole(role: Role): Promise<void> {
    const ok = await confirm({ title: `Delete the ${role.label} role?`, message: 'This cannot be undone.', confirmLabel: 'Delete role' });
    if (!ok) return;

    try {
        await http.delete(`/roles/${role.id}`);
        roles.value = roles.value.filter((r) => r.id !== role.id);
        if (roles.value[0]) select(roles.value[0]);
        toast.success('Role deleted');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

// Ctrl/Cmd + S saves; leaving with unsaved changes asks first.
function onKey(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && selected.value) {
        event.preventDefault();
        savePermissions();
    }
}
function beforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) event.preventDefault();
}
onBeforeRouteLeave(async () => !dirty.value || (await confirm({ title: 'Discard unsaved changes?', message: 'Your permission changes have not been saved.', confirmLabel: 'Discard changes' })));

onMounted(() => {
    window.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', beforeUnload);
    load();
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<template>
    <div>
        <!-- Header with a photo -->
        <ListHero title="Roles & permissions" description="Decide what each type of admin can see and change." :icon="ShieldCheck" tone="#4ade80" :photo="headerPhoto" photo-position="20% 50%">
            <template #actions>
                <AppButton v-if="auth.can('roles.create')" :icon="Plus" @click="createOpen = true">New role</AppButton>
            </template>
        </ListHero>

        <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true" aria-label="Loading roles">
            <div v-for="n in 4" :key="n" class="card h-[118px] animate-pulse" />
        </div>

        <template v-else>
            <!-- Roles -->
            <div class="rl-roles mb-6" role="group" aria-label="Roles">
                <button
                    v-for="(role, i) in roles"
                    :key="role.id"
                    type="button"
                    class="rl-role"
                    :class="{ 'is-on': role.id === selectedId, 'is-super': role.is_super_admin }"
                    :style="{ '--i': i }"
                    :aria-pressed="role.id === selectedId"
                    @click="pick(role)"
                >
                    <span class="flex items-start gap-3">
                        <span class="rl-role-icon" aria-hidden="true"><component :is="role.is_super_admin ? Crown : ShieldCheck" class="size-5" /></span>
                        <span class="min-w-0 flex-1 text-left">
                            <span class="block truncate text-[15px] font-bold text-ink">{{ role.label }}</span>
                            <span class="mt-0.5 flex items-center gap-1 text-[12.5px] text-ink-soft"><Users class="size-3.5" aria-hidden="true" />{{ role.users_count ?? 0 }} {{ role.users_count === 1 ? 'user' : 'users' }}<template v-if="role.is_system"> · Default</template></span>
                        </span>
                        <Check v-if="role.id === selectedId" class="size-5 shrink-0 text-brand" aria-hidden="true" />
                    </span>
                    <span class="mt-3.5 block">
                        <span class="rl-meter" aria-hidden="true"><span :style="{ width: `${totalPermissions ? (coverage(role) / totalPermissions) * 100 : 0}%` }" /></span>
                        <span class="mt-1.5 block text-left text-[12px] font-semibold text-ink-soft">{{ role.is_super_admin ? 'Full access' : `${coverage(role)} of ${totalPermissions} permissions` }}</span>
                    </span>
                </button>
            </div>

            <!-- Permissions of the chosen role -->
            <section v-if="selected" :key="selected.id" class="rl-editor" :aria-label="`${selected.label} permissions`">
                <header class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-3">
                    <div class="min-w-0 flex-1 basis-64">
                        <h2 class="flex items-center gap-2 text-[19px] font-bold text-ink">
                            {{ selected.label }}
                            <span v-if="selected.is_super_admin" class="rl-tag is-super"><Crown class="size-3.5" aria-hidden="true" />Full access</span>
                            <span v-else-if="selected.is_system" class="rl-tag">Default role</span>
                        </h2>
                        <p class="mt-0.5 text-[13.5px] text-ink-soft">
                            {{ selected.is_super_admin ? 'Super admins always have full access to everything.' : readonly ? 'You can view these permissions but not change them.' : 'Tap a permission to turn it on or off.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="!readonly">
                            <button type="button" class="rl-quick" @click="setAll(true)"><CheckCheck class="size-4" aria-hidden="true" />Allow everything</button>
                            <button type="button" class="rl-quick" @click="setAll(false)"><Eraser class="size-4" aria-hidden="true" />Clear all</button>
                        </template>
                        <button v-if="!selected.is_system && auth.can('roles.delete')" type="button" class="rl-quick is-danger" @click="removeRole(selected)"><Trash2 class="size-4" aria-hidden="true" />Delete role</button>
                    </div>
                </header>

                <label class="relative mb-4 block">
                    <span class="sr-only">Search permissions</span>
                    <Search class="pointer-events-none absolute top-1/2 left-4 size-[18px] -translate-y-1/2 text-ink-muted" aria-hidden="true" />
                    <input v-model="search" type="search" placeholder="Search permissions, e.g. bookings or delete" class="control rl-search pr-10 pl-11" />
                    <button v-if="search" type="button" class="absolute top-1/2 right-2.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Clear search" @click="search = ''">
                        <X class="size-4" />
                    </button>
                </label>

                <p v-if="!visibleModules.length" class="card px-6 py-10 text-center text-[14px] text-ink-soft">No permissions match "{{ search }}".</p>
                <ul v-else class="rl-modules">
                    <li v-for="module in visibleModules" :key="module.key" class="rl-module" :style="{ '--tone': look(module.key).tone }">
                        <div class="flex items-center gap-3">
                            <span class="rl-module-icon" aria-hidden="true"><component :is="look(module.key).icon" class="size-[18px]" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[14.5px] font-bold text-ink">{{ module.label }}</span>
                                <span class="block text-[12px] text-ink-soft">{{ grantedCount(module) }} of {{ module.actions.length }} allowed</span>
                            </span>
                            <button
                                v-if="!readonly"
                                type="button"
                                class="rl-all"
                                :class="{ 'is-on': grantedCount(module) === module.actions.length }"
                                :aria-label="grantedCount(module) === module.actions.length ? `Turn off all ${module.label} permissions` : `Allow all ${module.label} permissions`"
                                @click="setModule(module, grantedCount(module) !== module.actions.length)"
                            >
                                {{ grantedCount(module) === module.actions.length ? 'All on' : 'Allow all' }}
                            </button>
                        </div>
                        <div class="rl-meter mt-3" aria-hidden="true"><span :style="{ width: `${(grantedCount(module) / module.actions.length) * 100}%` }" /></div>
                        <div class="mt-3 flex flex-wrap gap-2" role="group" :aria-label="`${module.label} permissions`">
                            <button
                                v-for="action in module.actions"
                                :key="action"
                                type="button"
                                class="rl-perm"
                                :class="{ 'is-on': has(`${module.key}.${action}`) }"
                                role="switch"
                                :aria-checked="has(`${module.key}.${action}`)"
                                :disabled="readonly"
                                @click="toggle(`${module.key}.${action}`)"
                            >
                                <Check v-if="has(`${module.key}.${action}`)" class="size-3.5" :stroke-width="3" aria-hidden="true" />{{ titleCase(action) }}
                            </button>
                        </div>
                    </li>
                </ul>

                <!-- Appears only when something changed -->
                <Transition name="st-bar">
                    <div v-if="!readonly && dirty" class="st-bar sticky bottom-3 z-20 mt-5 sm:bottom-5" role="region" aria-label="Unsaved changes">
                        <div class="flex items-center gap-2 rounded-2xl bg-[#0b1d33] py-2.5 pr-2.5 pl-4 text-white shadow-[0_18px_44px_-14px_rgb(2_12_27/0.55)] sm:gap-3 sm:pl-5">
                            <span class="st-pulse size-2 shrink-0 rounded-full bg-amber-400" aria-hidden="true" />
                            <p class="min-w-0 flex-1 truncate text-[14px] font-semibold">
                                Unsaved changes to {{ selected.label }}
                                <span class="ml-2 hidden font-normal text-white/60 md:inline"><kbd class="rounded border border-white/20 px-1.5 py-0.5 font-sans text-[11.5px]">{{ isMac ? '⌘' : 'Ctrl' }} S</kbd> to save</span>
                            </p>
                            <button type="button" class="flex h-10 items-center gap-1.5 rounded-xl px-3 text-[14px] font-semibold text-white/80 hover:bg-white/10 hover:text-white disabled:opacity-50" :disabled="save.processing" @click="undo">
                                <Undo2 class="size-4" aria-hidden="true" />Undo
                            </button>
                            <button type="button" class="st-save flex h-10 items-center gap-1.5 rounded-xl px-4 text-[14px] font-bold disabled:opacity-70" :disabled="save.processing" @click="savePermissions">
                                <Check class="size-4" aria-hidden="true" />{{ save.processing ? 'Saving' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </section>
        </template>

        <AppModal v-model:open="createOpen" title="New role" size="sm" :persistent="create.processing">
            <form id="role-form" novalidate @submit.prevent="createRole">
                <AppInput v-model="create.data.name" label="Role name" placeholder="e.g. Marketing" required :error="create.error('name') ?? create.error('slug')" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="role-form" :loading="create.processing">Create role</AppButton>
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
.rl-roles {
    display: grid;
    gap: 0.85rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr));
}

/* Phones: two compact role cards per row so the permissions start sooner. */
@media (max-width: 639px) {
    .rl-roles {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.6rem;
    }

    .rl-role {
        padding: 0.8rem;
    }

    .rl-role .rl-role-icon {
        display: none;
    }
}

.rl-role {
    --tone: #4ade80;
    display: block;
    border-radius: 1.1rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 1rem 1.1rem;
    box-shadow: var(--shadow-card);
    animation: rl-in 460ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--i) * 45ms);
    transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease, background-color 200ms ease;

    &:hover {
        transform: translateY(-2px);
        border-color: #cbd3df;
    }

    &.is-super {
        --tone: #fbbf24;
    }

    &.is-on {
        border-color: color-mix(in oklab, var(--admin-primary) 55%, white);
        background: color-mix(in oklab, var(--admin-primary) 4%, white);
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--admin-primary) 55%, white), var(--shadow-card);
    }
}

.rl-role-icon,
.rl-module-icon {
    display: flex;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    background: color-mix(in oklab, var(--tone) 18%, white);
    color: color-mix(in oklab, var(--tone) 40%, black);
}

.rl-module-icon {
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 0.7rem;
}

.rl-meter {
    display: block;
    height: 0.4rem;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in oklab, var(--tone) 16%, white);

    > span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: color-mix(in oklab, var(--tone) 75%, black 10%);
        transition: width 400ms cubic-bezier(0.22, 1, 0.36, 1);
    }
}

.rl-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    background: var(--color-canvas);
    padding: 0.15rem 0.6rem;
    font-size: 12px;
    font-weight: 600;
    color: var(--color-ink-soft);

    &.is-super {
        background: #fef3c7;
        color: #92400e;
    }
}

.rl-quick {
    display: inline-flex;
    min-height: 2.5rem;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.8rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 0.85rem;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--color-ink);
    transition: border-color 160ms ease, background-color 160ms ease;

    &:hover {
        border-color: #cbd3df;
        background: var(--color-canvas);
    }

    &.is-danger {
        color: #b91c1c;

        &:hover {
            border-color: #fecaca;
            background: #fef2f2;
        }
    }
}

.rl-search {
    height: 3rem;
    border-radius: 0.9rem;
    box-shadow: var(--shadow-card);
}

.rl-modules {
    display: grid;
    gap: 0.85rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}

.rl-module {
    border-radius: 1.05rem;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 1rem;
    box-shadow: var(--shadow-card);
}

.rl-all {
    min-height: 2.25rem;
    flex-shrink: 0;
    border-radius: 999px;
    border: 1px solid var(--color-line);
    padding: 0 0.75rem;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--color-ink-soft);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;

    &:hover {
        color: var(--color-ink);
        border-color: #cbd3df;
    }

    &.is-on {
        border-color: transparent;
        background: color-mix(in oklab, var(--tone) 18%, white);
        color: color-mix(in oklab, var(--tone) 35%, black);
    }
}

.rl-perm {
    display: inline-flex;
    min-height: 2.4rem;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid var(--color-line);
    background: #fff;
    padding: 0 0.9rem;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-ink-soft);
    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease, transform 160ms ease;

    &:hover:not(:disabled) {
        border-color: #cbd3df;
        color: var(--color-ink);
    }

    &:active:not(:disabled) {
        transform: scale(0.96);
    }

    &.is-on {
        border-color: color-mix(in oklab, var(--tone) 60%, black 5%);
        background: color-mix(in oklab, var(--tone) 15%, white);
        color: color-mix(in oklab, var(--tone) 35%, black);
    }

    &:disabled {
        cursor: default;
    }
}

@keyframes rl-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .rl-role {
        animation: none;
    }

    .rl-role,
    .rl-perm,
    .rl-meter > span {
        transition: none;
    }

    .rl-role:hover {
        transform: none;
    }
}
</style>
