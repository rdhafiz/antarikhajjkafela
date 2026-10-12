<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { CalendarClock, ChevronRight, Copy, ExternalLink, Layers, Package as PackageIcon, Pencil, Plus, Star, Trash2, Wallet } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface PackageRow {
    id: number;
    name: Translations;
    slug: string;
    url: string;
    service: Translations | null;
    status: 'draft' | 'published';
    is_featured: boolean;
    booking_state: 'open' | 'upcoming' | 'closed';
    booking_ends_on: string | null;
    duration_days: number | null;
    min_price: number | null;
    types_count: number;
    updated_at: string;
}

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<PackageRow>('/packages', { service_id: '', status: '', featured: '' });
const services = ref<{ id: number; name: Translations }[]>([]);

const current = ref<PackageRow | null>(null);
const drawerOpen = ref(false);
const createOpen = ref(false);
const createForm = useForm({ service_id: '' as number | '', name: {} as Translations });

const money = new Intl.NumberFormat('en-IN');
const bookingTone = { open: 'success', upcoming: 'warning', closed: 'danger' } as const;
const bookingLabel = { open: 'Booking open', upcoming: 'Opens soon', closed: 'Booking closed' } as const;
const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'package' : 'packages'}` : ''));
const price = (value: number | null) => (value !== null ? `৳${money.format(value)}` : '—');

function open(row: PackageRow): void {
    current.value = row;
    drawerOpen.value = true;
}

function openCreate(): void {
    createForm.reset({ service_id: services.value[0]?.id ?? '', name: {} });
    createOpen.value = true;
}

async function create(): Promise<void> {
    const response = await createForm.submit(() => http.post<{ data: { id: number } }>('/packages', createForm.data));
    if (response) {
        createOpen.value = false;
        router.push({ name: 'package-editor', params: { id: response.data.data.id } });
    }
}

async function duplicate(row: PackageRow): Promise<void> {
    try {
        await http.post(`/packages/${row.id}/duplicate`);
        toast.success('Copy created as a draft');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(row: PackageRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(row.name)}"?`, message: 'Its prices and page content will be deleted. This cannot be undone.', confirmLabel: 'Delete package' }))) return;
    try {
        await http.delete(`/packages/${row.id}`);
        toast.success('Package deleted');
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(async () => {
    list.load();
    try {
        services.value = (await http.get<{ services: { id: number; name: Translations }[] }>('/packages/schema')).data.services;
    } catch {
        // The service filter is optional.
    }
});
</script>

<template>
    <div>
        <PageHeader title="Packages" description="Hajj and Umrah packages with their types, prices, hotels, flights and page content.">
            <template #actions>
                <AppButton v-if="auth.can('packages.create')" :icon="Plus" @click="openCreate">New package</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="list.filters.search as string" placeholder="Search packages by name" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.service_id" class="control" aria-label="Filter by service">
                <option value="">All services</option>
                <option v-for="service in services" :key="service.id" :value="String(service.id)">{{ pickText(service.name, app.defaultLocale) }}</option>
            </select>
            <select v-model="list.filters.status" class="control" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
            <select v-model="list.filters.featured" class="control" aria-label="Filter featured packages">
                <option value="">Featured or not</option>
                <option value="yes">Featured only</option>
                <option value="no">Not featured</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="PackageIcon"
                :title="list.filters.search || list.active.value ? 'No packages match' : 'No packages yet'"
                :description="list.filters.search || list.active.value ? 'Try another search or clear the filters.' : 'Create your first Hajj or Umrah package.'"
            >
                <AppButton v-if="list.filters.search || list.active.value" variant="secondary" @click="list.clear(); list.filters.search = ''">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('packages.create')" :icon="Plus" @click="openCreate">New package</AppButton>
            </EmptyState>
            <table v-else class="dt" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>From</th>
                        <th>Booking</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="dt-trigger"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in list.rows.value" :key="row.id" class="dt-row" @click="open(row)">
                        <td>
                            <p class="flex items-center gap-1.5 font-semibold text-ink">
                                <Star v-if="row.is_featured" class="size-3.5 shrink-0 fill-gold text-gold" aria-label="Featured" />
                                {{ pickText(row.name, app.defaultLocale) }}
                            </p>
                            <p class="mt-0.5 text-[12.5px] text-ink-muted">
                                {{ pickText(row.service, app.defaultLocale) }} · {{ row.types_count }} types<template v-if="row.duration_days"> · {{ row.duration_days }} days</template>
                            </p>
                        </td>
                        <td data-label="From" class="font-semibold whitespace-nowrap text-ink">{{ price(row.min_price) }}</td>
                        <td data-label="Booking"><AppBadge :tone="bookingTone[row.booking_state]">{{ bookingLabel[row.booking_state] }}</AppBadge></td>
                        <td data-label="Status"><AppBadge :tone="row.status === 'published' ? 'success' : 'neutral'">{{ row.status === 'published' ? 'Published' : 'Draft' }}</AppBadge></td>
                        <td data-label="Updated" class="text-[13px] whitespace-nowrap text-ink-soft">{{ formatDate(row.updated_at) }}</td>
                        <td class="dt-trigger">
                            <button type="button" class="dt-open" :aria-label="`Open ${pickText(row.name, app.defaultLocale)}`" @click.stop="open(row)"><ChevronRight class="size-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="packages" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? pickText(current.name, app.defaultLocale) : ''" :description="current ? pickText(current.service, app.defaultLocale) ?? '' : ''" :icon="PackageIcon">
            <template v-if="current">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="current.status === 'published' ? 'success' : 'neutral'">{{ current.status === 'published' ? 'Published' : 'Draft' }}</AppBadge>
                    <AppBadge :tone="bookingTone[current.booking_state]">{{ bookingLabel[current.booking_state] }}</AppBadge>
                    <AppBadge v-if="current.is_featured" tone="gold"><Star class="size-3 fill-current" />Featured</AppBadge>
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Wallet class="size-4 text-emerald-600" />From</dt>
                    <dd>{{ price(current.min_price) }}</dd>
                    <dt class="flex items-center gap-2"><Layers class="size-4 text-violet-600" />Types</dt>
                    <dd>{{ current.types_count }}</dd>
                    <dt class="flex items-center gap-2"><CalendarClock class="size-4 text-sky-600" />Duration</dt>
                    <dd>{{ current.duration_days ? `${current.duration_days} days` : '—' }}</dd>
                    <template v-if="current.booking_ends_on">
                        <dt>Booking until</dt>
                        <dd>{{ formatDate(current.booking_ends_on) }}</dd>
                    </template>
                    <dt>Updated</dt>
                    <dd>{{ formatDate(current.updated_at, true) }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <RouterLink :to="{ name: 'package-editor', params: { id: current.id } }" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit package
                    </RouterLink>
                    <a :href="current.url" target="_blank" rel="noopener" class="ad-action">
                        <span class="ad-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-if="auth.can('packages.create')" type="button" class="ad-action" @click="duplicate(current)">
                        <span class="ad-action-icon" style="--tone: #a78bfa"><Copy class="size-4" /></span>Duplicate as a draft
                    </button>
                    <button v-if="auth.can('packages.delete')" type="button" class="ad-action is-danger" @click="remove(current)">
                        <span class="ad-action-icon"><Trash2 class="size-4" /></span>Delete package
                    </button>
                </div>
            </template>
        </AppDrawer>

        <AppModal v-model:open="createOpen" title="New package" description="Saved as a draft. You'll add types, prices, flights and content next." :persistent="createForm.processing">
            <form id="new-package" class="space-y-4" novalidate @submit.prevent="create">
                <FormField label="Service" for="new-package-service" :error="createForm.error('service_id')" required>
                    <select id="new-package-service" v-model="createForm.data.service_id" class="control">
                        <option v-for="service in services" :key="service.id" :value="service.id">{{ pickText(service.name, app.defaultLocale) }}</option>
                    </select>
                </FormField>
                <TranslatableInput v-model="createForm.data.name" label="Package name" required :errors="createForm.errors" error-key="name" hint="The web address is created from the English name." placeholder="Hajj Premium Package 2027" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="new-package" :loading="createForm.processing">Create and edit</AppButton>
            </template>
        </AppModal>
    </div>
</template>
