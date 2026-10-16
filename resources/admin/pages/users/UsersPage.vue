<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { ArrowDownUp, CircleCheck, Clock, Mail, Pencil, Phone, Plus, ShieldCheck, Trash2, UserCheck, UserX, Users } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import UserFormModal from './UserFormModal.vue';
import UserListItem from './UserListItem.vue';
import headerPhoto from '@admin/assets/users-header.webp';
import type { Paginated } from '@admin/lib/http';
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

type UsersResponse = Paginated<AdminUser> & {
    counts: { total: number; active: number; inactive: number; never: number; roles: Record<string, number> };
};

const list = useListQuery<AdminUser, UsersResponse>('/users', { role: '', status: '', signed_in: '', sort: '' });
const filtersOpen = ref(false);
const roles = ref<Role[]>([]);

const formOpen = ref(false);
const editing = ref<AdminUser | null>(null);
const current = ref<AdminUser | null>(null);
const drawerOpen = ref(false);
const toggling = ref(false);

const counts = computed(() => list.response.value?.counts);
const total = computed(() => list.meta.value?.total ?? 0);
const activeFilters = computed(() => (['role', 'status', 'signed_in'] as const).filter((key) => list.filters[key] !== '').length);
const filtered = computed(() => !!list.filters.search || activeFilters.value > 0);

const filterGroups = computed<FilterGroup[]>(() => {
    const c = counts.value;
    return [
        {
            key: 'role',
            label: 'Role',
            icon: ShieldCheck,
            tone: '#4ade80',
            options: [{ value: '', label: 'All roles', count: c?.total ?? null }, ...roles.value.map((r) => ({ value: r.name, label: r.label, count: c?.roles[r.name] ?? 0 }))],
        },
        {
            key: 'status',
            label: 'Status',
            icon: CircleCheck,
            tone: '#34d399',
            options: [
                { value: '', label: 'Any status' },
                { value: 'active', label: 'Active', count: c?.active ?? null },
                { value: 'inactive', label: 'Inactive', count: c?.inactive ?? null },
            ],
        },
        {
            key: 'signed_in',
            label: 'Last sign-in',
            icon: Clock,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Any time' },
                { value: 'week', label: 'This week' },
                { value: 'month', label: 'This month' },
                { value: 'never', label: 'Never signed in', count: c?.never ?? null },
            ],
        },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#38bdf8',
            options: [
                { value: '', label: 'Name A–Z' },
                { value: 'last_login', label: 'Recent sign-in' },
                { value: 'newest', label: 'Newest accounts' },
            ],
        },
    ];
});
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
        message: 'They will lose access to the admin portal immediately. It goes to the Trash, where a super admin can restore it.',
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
        <!-- Header with a photo -->
        <ListHero title="Users" description="People who can sign in to the admin portal." :icon="Users" tone="#93c5fd" :photo="headerPhoto" photo-position="60% 30%">
            <template #actions>
                <AppButton v-if="auth.can('users.create')" :icon="Plus" @click="openCreate">Add user</AppButton>
            </template>
        </ListHero>

        <!-- Search and filters in one row -->
        <ListToolbar v-model:search="list.filters.search as string" placeholder="Search by name or email" :active="activeFilters" @filters="filtersOpen = true" />

        <!-- Users -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-3" aria-busy="true" aria-label="Loading users">
            <div v-for="n in 3" :key="n" class="card h-[190px] animate-pulse sm:h-[96px]" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Users"
                :title="filtered ? 'No users match' : 'No users yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Add the first admin user.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearFilters">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('users.create')" :icon="Plus" @click="openCreate">Add user</AppButton>
            </EmptyState>
        </div>
        <div v-else class="us-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="grid gap-3">
                <UserListItem
                    v-for="(user, i) in list.rows.value"
                    :key="user.id"
                    :user="user"
                    :role-label="roleLabel"
                    :is-self="isSelf(user)"
                    :index="i"
                    :can-update="auth.can('users.update')"
                    :can-delete="auth.can('users.delete')"
                    @open="open(user)"
                    @edit="openEdit(user)"
                    @toggle="toggleActive(user)"
                    @remove="remove(user)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="users" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['user', 'users']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="list.clear()"
        />

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

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.us-list {
    container: us-list / inline-size;
}
</style>
