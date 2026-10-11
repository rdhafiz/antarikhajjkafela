<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import UserFormModal from './UserFormModal.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate, initials } from '@admin/lib/format';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { AdminUser, Role } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const users = ref<AdminUser[]>([]);
const meta = ref<Paginated<AdminUser>['meta'] | null>(null);
const roles = ref<Role[]>([]);
const loading = ref(true);
const filters = reactive({ search: '', role: '', page: 1 });

const formOpen = ref(false);
const editing = ref<AdminUser | null>(null);

async function load(): Promise<void> {
    loading.value = true;

    try {
        const { data } = await http.get<Paginated<AdminUser>>('/users', { params: filters });
        users.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

const searchChanged = useDebounceFn(() => {
    filters.page = 1;
    load();
}, 300);

watch(() => filters.search, searchChanged);
watch(() => filters.role, () => {
    filters.page = 1;
    load();
});

function roleLabel(name: string): string {
    return roles.value.find((r) => r.name === name)?.label ?? name;
}

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(user: AdminUser): void {
    editing.value = user;
    formOpen.value = true;
}

function saved(user: AdminUser): void {
    toast.success(editing.value ? 'User updated' : 'User added', user.email);
    load();
}

async function remove(user: AdminUser): Promise<void> {
    const ok = await confirm({
        title: `Delete ${user.name}?`,
        message: 'They will lose access to the admin portal immediately. This cannot be undone.',
        confirmLabel: 'Delete user',
    });
    if (!ok) return;

    try {
        await http.delete(`/users/${user.id}`);
        toast.success('User deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(async () => {
    const [{ data }] = await Promise.all([http.get<{ data: Role[] }>('/users/role-options'), load()]);
    roles.value = data.data;
});
</script>

<template>
    <div>
        <PageHeader title="Users" description="People who can sign in to the admin portal.">
            <template #actions>
                <AppButton v-if="auth.can('users.create')" :icon="Plus" @click="openCreate">Add user</AppButton>
            </template>
        </PageHeader>

        <div class="card">
            <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search users</span>
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                    <input v-model="filters.search" type="search" placeholder="Search by name or email" class="control pl-9" />
                </label>
                <label class="sm:w-56">
                    <span class="sr-only">Filter by role</span>
                    <select v-model="filters.role" class="control">
                        <option value="">All roles</option>
                        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.label }}</option>
                    </select>
                </label>
            </div>

            <div v-if="loading && !users.length" class="flex justify-center py-16 text-brand">
                <AppSpinner class="size-6" />
            </div>

            <EmptyState
                v-else-if="!users.length"
                :icon="Users"
                title="No users found"
                :description="filters.search || filters.role ? 'Try a different search or filter.' : 'Add the first admin user.'"
            />

            <div v-else class="overflow-x-auto" :class="{ 'opacity-60 transition-opacity': loading }">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last sign-in</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-canvas/60">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[13px] font-bold text-brand">
                                        {{ initials(user.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-ink">
                                            {{ user.name }}
                                            <span v-if="user.id === auth.user?.id" class="ml-1 text-[12px] font-medium text-ink-muted">(you)</span>
                                        </p>
                                        <p class="truncate text-[13px] text-ink-muted">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <AppBadge v-for="role in user.roles" :key="role" :tone="role === 'super-admin' ? 'gold' : 'brand'">
                                        {{ roleLabel(role) }}
                                    </AppBadge>
                                    <span v-if="!user.roles?.length" class="text-[13px] text-ink-muted">No role</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <AppBadge :tone="user.is_active ? 'success' : 'neutral'">{{ user.is_active ? 'Active' : 'Inactive' }}</AppBadge>
                            </td>
                            <td class="px-4 py-3 text-[13px] text-ink-soft">{{ formatDate(user.last_login_at, true) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button
                                        v-if="auth.can('users.update')"
                                        type="button"
                                        class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand"
                                        :aria-label="`Edit ${user.name}`"
                                        @click="openEdit(user)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="auth.can('users.delete') && user.id !== auth.user?.id"
                                        type="button"
                                        class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600"
                                        :aria-label="`Delete ${user.name}`"
                                        @click="remove(user)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AppPagination v-if="meta" :meta="meta" @change="(page) => { filters.page = page; load(); }" />

        <UserFormModal v-model:open="formOpen" :user="editing" :roles="roles" @saved="saved" />
    </div>
</template>
