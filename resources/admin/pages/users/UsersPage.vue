<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { ChevronRight, Clock, Mail, Pencil, Phone, Plus, ShieldCheck, Trash2, UserCheck, UserX, Users } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import UserFormModal from './UserFormModal.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate, initials } from '@admin/lib/format';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { AdminUser, Role } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<AdminUser>('/users', { role: '' });
const roles = ref<Role[]>([]);

const formOpen = ref(false);
const editing = ref<AdminUser | null>(null);
const current = ref<AdminUser | null>(null);
const drawerOpen = ref(false);
const toggling = ref(false);

const filtered = computed(() => !!list.filters.search || list.active.value > 0);
const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'user' : 'users'}` : ''));
const isSelf = (user: AdminUser) => user.id === auth.user?.id;

function roleLabel(name: string): string {
    return roles.value.find((r) => r.name === name)?.label ?? name;
}

function open(user: AdminUser): void {
    current.value = user;
    drawerOpen.value = true;
}

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(user: AdminUser): void {
    editing.value = user;
    drawerOpen.value = false;
    formOpen.value = true;
}

function saved(user: AdminUser): void {
    toast.success(editing.value ? 'User updated' : 'User added', user.email);
    list.load();
}

function clearFilters(): void {
    list.clear();
    list.filters.search = '';
}

async function toggleActive(user: AdminUser): Promise<void> {
    const activating = !user.is_active;
    if (!activating) {
        const ok = await confirm({
            title: `Deactivate ${user.name}?`,
            message: 'They will not be able to sign in until the account is activated again.',
            confirmLabel: 'Deactivate',
        });
        if (!ok) return;
    }

    toggling.value = true;
    try {
        await http.put(`/users/${user.id}`, { name: user.name, email: user.email, phone: user.phone, is_active: activating });
        toast.success(activating ? 'User activated' : 'User deactivated', user.email);
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        toggling.value = false;
    }
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
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(async () => {
    list.load();
    try {
        roles.value = (await http.get<{ data: Role[] }>('/users/role-options')).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    }
});
</script>

<template>
    <div>
        <PageHeader title="Users" description="People who can sign in to the admin portal.">
            <template #actions>
                <AppButton v-if="auth.can('users.create')" :icon="Plus" @click="openCreate">Add user</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="list.filters.search as string" placeholder="Search by name or email" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.role" class="control" aria-label="Filter by role">
                <option value="">All roles</option>
                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.label }}</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="Users"
                :title="filtered ? 'No users match' : 'No users yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Add the first admin user.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearFilters">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('users.create')" :icon="Plus" @click="openCreate">Add user</AppButton>
            </EmptyState>
            <table v-else class="dt" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th class="dt-trigger"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in list.rows.value" :key="user.id" class="dt-row" @click="open(user)">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[13px] font-bold text-brand" aria-hidden="true">
                                    {{ initials(user.name) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-ink">
                                        {{ user.name }}
                                        <span v-if="isSelf(user)" class="ml-1 text-[12px] font-medium text-ink-muted">(you)</span>
                                    </p>
                                    <p class="truncate text-[12.5px] text-ink-muted">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td data-label="Roles">
                            <div class="flex flex-wrap gap-1">
                                <AppBadge v-for="role in user.roles" :key="role" :tone="role === 'super-admin' ? 'gold' : 'brand'">{{ roleLabel(role) }}</AppBadge>
                                <span v-if="!user.roles?.length" class="text-[13px] text-ink-muted">No role</span>
                            </div>
                        </td>
                        <td data-label="Status"><AppBadge :tone="user.is_active ? 'success' : 'neutral'">{{ user.is_active ? 'Active' : 'Inactive' }}</AppBadge></td>
                        <td data-label="Last sign-in" class="dt-hide-sm text-[13px] whitespace-nowrap text-ink-soft">{{ formatDate(user.last_login_at, true) }}</td>
                        <td class="dt-trigger">
                            <button type="button" class="dt-open" :aria-label="`Open ${user.name}`" @click.stop="open(user)"><ChevronRight class="size-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="users" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current?.name ?? ''" :description="current?.email" :icon="Users">
            <template v-if="current">
                <div class="flex items-center gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[18px] font-bold text-brand" aria-hidden="true">{{ initials(current.name) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-[15px] font-bold text-ink">
                            {{ current.name }}
                            <span v-if="isSelf(current)" class="ml-1 text-[12px] font-medium text-ink-muted">(you)</span>
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <AppBadge :tone="current.is_active ? 'success' : 'neutral'">{{ current.is_active ? 'Active' : 'Inactive' }}</AppBadge>
                            <AppBadge v-for="role in current.roles" :key="role" :tone="role === 'super-admin' ? 'gold' : 'brand'">{{ roleLabel(role) }}</AppBadge>
                        </div>
                    </div>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Mail class="size-4 text-sky-600" />Email</dt>
                    <dd class="break-all"><a :href="`mailto:${current.email}`" class="hover:text-brand hover:underline">{{ current.email }}</a></dd>
                    <dt class="flex items-center gap-2"><Phone class="size-4 text-emerald-600" />Phone</dt>
                    <dd>
                        <a v-if="current.phone" :href="`tel:${current.phone.replace(/[^\d+]/g, '')}`" class="hover:text-brand hover:underline">{{ current.phone }}</a>
                        <template v-else>—</template>
                    </dd>
                    <dt class="flex items-center gap-2"><ShieldCheck class="size-4 text-violet-600" />Roles</dt>
                    <dd>{{ current.roles?.length ? current.roles.map(roleLabel).join(', ') : 'No role' }}</dd>
                    <dt class="flex items-center gap-2"><Clock class="size-4 text-amber-600" />Last sign-in</dt>
                    <dd>{{ formatDate(current.last_login_at, true) }}</dd>
                    <dt>Added</dt>
                    <dd>{{ formatDate(current.created_at) }}</dd>
                </dl>

                <template v-if="auth.can('users.update') || (auth.can('users.delete') && !isSelf(current))">
                    <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                    <div class="-mx-1 space-y-1">
                        <button v-if="auth.can('users.update')" type="button" class="dw-action" @click="openEdit(current)">
                            <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit user
                        </button>
                        <button v-if="auth.can('users.update') && !isSelf(current)" type="button" class="dw-action" :disabled="toggling" @click="toggleActive(current)">
                            <span v-if="current.is_active" class="dw-action-icon" style="--tone: #fb923c"><UserX class="size-4" /></span>
                            <span v-else class="dw-action-icon" style="--tone: #34d399"><UserCheck class="size-4" /></span>
                            {{ current.is_active ? 'Deactivate account' : 'Activate account' }}
                        </button>
                        <button v-if="auth.can('users.delete') && !isSelf(current)" type="button" class="dw-action is-danger" @click="remove(current)">
                            <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete user
                        </button>
                    </div>
                </template>
            </template>
        </AppDrawer>

        <UserFormModal v-model:open="formOpen" :user="editing" :roles="roles" @saved="saved" />
    </div>
</template>
