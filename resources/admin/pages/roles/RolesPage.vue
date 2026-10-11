<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Crown, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
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

const selected = computed(() => roles.value.find((r) => r.id === selectedId.value) ?? null);
const actions = computed(() => [...new Set(modules.value.flatMap((m) => m.actions))]);
const readonly = computed(() => !selected.value || selected.value.is_super_admin || !auth.can('roles.update'));
const dirty = computed(() => {
    const original = new Set(selected.value?.permissions ?? []);
    return original.size !== draft.value.size || [...draft.value].some((p) => !original.has(p));
});

const save = useForm({});
const create = useForm({ name: '' });
const createOpen = ref(false);

async function load(): Promise<void> {
    try {
        const [rolesResponse, modulesResponse] = await Promise.all([
            http.get<{ data: Role[] }>('/roles'),
            http.get<{ data: PermissionModule[] }>('/permissions'),
        ]);
        roles.value = rolesResponse.data.data;
        modules.value = modulesResponse.data.data;

        if (!selected.value && roles.value.length) select(roles.value[0]!);
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

function select(role: Role): void {
    selectedId.value = role.id;
    draft.value = new Set(role.permissions ?? []);
}

function has(permission: string): boolean {
    return selected.value?.is_super_admin === true || draft.value.has(permission);
}

function toggle(permission: string, value: boolean | 'indeterminate'): void {
    const next = new Set(draft.value);
    value === true ? next.add(permission) : next.delete(permission);
    draft.value = next;
}

function moduleState(module: PermissionModule): boolean | 'indeterminate' {
    const granted = module.actions.filter((a) => has(`${module.key}.${a}`)).length;
    return granted === 0 ? false : granted === module.actions.length ? true : 'indeterminate';
}

function toggleModule(module: PermissionModule, value: boolean | 'indeterminate'): void {
    const next = new Set(draft.value);
    for (const action of module.actions) {
        value === true ? next.add(`${module.key}.${action}`) : next.delete(`${module.key}.${action}`);
    }
    draft.value = next;
}

async function savePermissions(): Promise<void> {
    const role = selected.value;
    if (!role) return;

    const response = await save.submit(() =>
        http.put<{ data: Role }>(`/roles/${role.id}`, { name: role.label, permissions: [...draft.value] }),
    );

    if (response) {
        const index = roles.value.findIndex((r) => r.id === role.id);
        roles.value[index] = response.data.data;
        select(response.data.data);
        toast.success('Permissions saved', `${response.data.data.label} updated.`);
    }
}

async function createRole(): Promise<void> {
    const response = await create.submit(() => http.post<{ data: Role }>('/roles', { name: create.data.name, permissions: [] }));

    if (response) {
        roles.value.push(response.data.data);
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

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Roles & permissions" description="Decide what each type of admin can see and change.">
            <template #actions>
                <AppButton v-if="auth.can('roles.create')" :icon="Plus" @click="createOpen = true">New role</AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>

        <div v-else class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <!-- Role list -->
            <div class="card h-fit p-2">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors"
                    :class="selectedId === role.id ? 'bg-brand-soft' : 'hover:bg-canvas'"
                    @click="select(role)"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="role.is_super_admin ? 'bg-[color-mix(in_oklab,var(--admin-secondary)_25%,white)] text-[#6b5400]' : 'bg-white text-brand ring-1 ring-line'"
                    >
                        <Crown v-if="role.is_super_admin" class="size-4" />
                        <ShieldCheck v-else class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold" :class="selectedId === role.id ? 'text-brand' : 'text-ink'">{{ role.label }}</span>
                        <span class="block text-[12px] text-ink-muted">{{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}</span>
                    </span>
                </button>
            </div>

            <!-- Permission matrix -->
            <div v-if="selected" class="card">
                <div class="flex flex-col gap-3 border-b border-line p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-[17px] font-bold text-ink">{{ selected.label }}</h2>
                            <AppBadge v-if="selected.is_system" tone="neutral">Default</AppBadge>
                        </div>
                        <p class="mt-0.5 text-[13px] text-ink-muted">
                            {{ selected.is_super_admin ? 'Super admins always have full access to everything.' : 'Tick what this role is allowed to do.' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <AppButton
                            v-if="!selected.is_system && auth.can('roles.delete')"
                            variant="ghost"
                            size="sm"
                            :icon="Trash2"
                            class="text-red-600 hover:bg-red-50 hover:text-red-700"
                            @click="removeRole(selected)"
                        >
                            Delete
                        </AppButton>
                        <AppButton v-if="!readonly" size="sm" :disabled="!dirty" :loading="save.processing" @click="savePermissions">
                            Save permissions
                        </AppButton>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                                <th class="px-5 py-3 text-left">Module</th>
                                <th v-for="action in actions" :key="action" class="px-3 py-3 text-center">{{ titleCase(action) }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="module in modules" :key="module.key">
                                <td class="px-5 py-3">
                                    <AppCheckbox
                                        :model-value="moduleState(module)"
                                        :disabled="readonly"
                                        @update:model-value="toggleModule(module, $event)"
                                    >
                                        <span class="font-semibold">{{ module.label }}</span>
                                    </AppCheckbox>
                                </td>
                                <td v-for="action in actions" :key="action" class="px-3 py-3 text-center">
                                    <AppCheckbox
                                        v-if="module.actions.includes(action)"
                                        :model-value="has(`${module.key}.${action}`)"
                                        :disabled="readonly"
                                        :aria-label="`${module.label}: ${action}`"
                                        @update:model-value="toggle(`${module.key}.${action}`, $event)"
                                    />
                                    <span v-else class="text-line" aria-hidden="true">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <AppModal v-model:open="createOpen" title="New role" size="sm" :persistent="create.processing">
            <form id="role-form" novalidate @submit.prevent="createRole">
                <AppInput
                    v-model="create.data.name"
                    label="Role name"
                    placeholder="e.g. Marketing"
                    required
                    :error="create.error('name') ?? create.error('slug')"
                />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="role-form" :loading="create.processing">Create role</AppButton>
            </template>
        </AppModal>
    </div>
</template>
