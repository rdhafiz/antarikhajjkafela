<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Copy, ExternalLink, Package as PackageIcon, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
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

const packages = ref<PackageRow[]>([]);
const services = ref<{ id: number; name: Translations }[]>([]);
const loading = ref(true);
const filters = reactive({ service_id: '', status: '' });
const createOpen = ref(false);
const createForm = useForm({ service_id: '' as number | '', name: {} as Translations });

const money = new Intl.NumberFormat('en-IN');
const bookingTone = { open: 'success', upcoming: 'warning', closed: 'danger' } as const;
const bookingLabel = { open: 'Booking open', upcoming: 'Opens soon', closed: 'Booking closed' } as const;
const count = computed(() => packages.value.length);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
        const [list, schema] = await Promise.all([
            http.get<{ data: PackageRow[] }>('/packages', { params }),
            services.value.length ? Promise.resolve(null) : http.get<{ services: { id: number; name: Translations }[] }>('/packages/schema'),
        ]);
        packages.value = list.data.data;
        if (schema) services.value = schema.data.services;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

watch(filters, load);

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
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(row: PackageRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(row.name)}"?`, message: 'Its prices and page content will be deleted. This cannot be undone.', confirmLabel: 'Delete package' }))) return;
    try {
        await http.delete(`/packages/${row.id}`);
        toast.success('Package deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Packages" description="Hajj and Umrah packages with their types, prices, hotels, flights and page content.">
            <template #actions>
                <AppButton v-if="auth.can('packages.create')" :icon="Plus" @click="openCreate">New package</AppButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <select v-model="filters.service_id" class="control sm:w-52" aria-label="Filter by service">
                <option value="">All services</option>
                <option v-for="service in services" :key="service.id" :value="service.id">{{ pickText(service.name, app.defaultLocale) }}</option>
            </select>
            <select v-model="filters.status" class="control sm:w-44" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
            <p class="self-center text-[13px] text-ink-muted sm:ml-auto">{{ count }} {{ count === 1 ? 'package' : 'packages' }}</p>
        </div>

        <div v-if="loading && !packages.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!packages.length" class="card">
            <EmptyState :icon="PackageIcon" title="No packages yet" description="Create your first Hajj or Umrah package.">
                <AppButton v-if="auth.can('packages.create')" :icon="Plus" @click="openCreate">New package</AppButton>
            </EmptyState>
        </div>
        <div v-else class="card overflow-x-auto" :class="{ 'opacity-60': loading }">
            <table class="w-full min-w-[820px] text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                        <th class="px-4 py-3">Package</th>
                        <th class="px-4 py-3">From</th>
                        <th class="px-4 py-3">Booking</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Updated</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="row in packages" :key="row.id" class="hover:bg-canvas/60">
                        <td class="px-4 py-3">
                            <RouterLink :to="{ name: 'package-editor', params: { id: row.id } }" class="flex items-center gap-1.5 font-semibold text-ink hover:text-brand">
                                <Star v-if="row.is_featured" class="size-3.5 fill-gold text-gold" aria-label="Featured" />
                                {{ pickText(row.name, app.defaultLocale) }}
                            </RouterLink>
                            <p class="text-[12px] text-ink-muted">
                                {{ pickText(row.service, app.defaultLocale) }} · {{ row.types_count }} types<template v-if="row.duration_days"> · {{ row.duration_days }} days</template>
                            </p>
                        </td>
                        <td class="px-4 py-3 font-semibold text-ink">{{ row.min_price !== null ? `৳${money.format(row.min_price)}` : '—' }}</td>
                        <td class="px-4 py-3">
                            <AppBadge :tone="bookingTone[row.booking_state]">{{ bookingLabel[row.booking_state] }}</AppBadge>
                            <p v-if="row.booking_ends_on && row.booking_state === 'open'" class="mt-1 text-[12px] text-ink-muted">until {{ formatDate(row.booking_ends_on) }}</p>
                        </td>
                        <td class="px-4 py-3"><AppBadge :tone="row.status === 'published' ? 'success' : 'neutral'">{{ row.status === 'published' ? 'Published' : 'Draft' }}</AppBadge></td>
                        <td class="px-4 py-3 text-[13px] text-ink-soft">{{ formatDate(row.updated_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <a :href="row.url" target="_blank" rel="noopener" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" aria-label="View on website"><ExternalLink class="size-4" /></a>
                                <RouterLink :to="{ name: 'package-editor', params: { id: row.id } }" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" aria-label="Edit"><Pencil class="size-4" /></RouterLink>
                                <button v-if="auth.can('packages.create')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" aria-label="Duplicate" @click="duplicate(row)"><Copy class="size-4" /></button>
                                <button v-if="auth.can('packages.delete')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label="Delete" @click="remove(row)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
