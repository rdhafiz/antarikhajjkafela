<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { Download, Inbox, Mail, MessageCircle, Phone, Search, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate, titleCase } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';

interface InquiryRow {
    id: number;
    source: 'contact_form' | 'lead_form' | string;
    name: string | null;
    phone: string | null;
    email: string | null;
    data: Record<string, string>;
    page: { id: number; title: string; type: string } | null;
    status: 'new' | 'in_progress' | 'closed';
    notes: string | null;
    locale: string | null;
    read: boolean;
    created_at: string;
}

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const rows = ref<InquiryRow[]>([]);
const meta = ref<Paginated<InquiryRow>['meta'] | null>(null);
const counts = ref({ unread: 0, new: 0 });
const loading = ref(true);
const filters = reactive({ status: '', source: '', search: '', page: 1 });

const detailOpen = ref(false);
const current = ref<InquiryRow | null>(null);
const editStatus = ref<InquiryRow['status']>('new');
const editNotes = ref('');
const saving = ref(false);

const statusLabels = { new: 'New', in_progress: 'In progress', closed: 'Closed' } as const;
const statusTones = { new: 'gold', in_progress: 'brand', closed: 'neutral' } as const;
const sourceLabels: Record<string, string> = { contact_form: 'Contact form', lead_form: 'Campaign form' };

const whatsapp = computed(() => {
    const digits = (current.value?.phone ?? '').replace(/\D/g, '');
    if (!digits) return null;
    const international = digits.startsWith('880') ? digits : digits.startsWith('0') ? `88${digits}` : digits;
    return `https://wa.me/${international}`;
});

async function load(): Promise<void> {
    loading.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
        const { data } = await http.get<Paginated<InquiryRow> & { counts: typeof counts.value }>('/inquiries', { params });
        rows.value = data.data;
        meta.value = data.meta;
        counts.value = data.counts;
        app.inquiriesUnread = data.counts.unread;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

const reload = useDebounceFn(() => {
    filters.page = 1;
    load();
}, 300);

watch(() => filters.search, reload);
watch(() => [filters.status, filters.source], () => {
    filters.page = 1;
    load();
});

async function open(row: InquiryRow): Promise<void> {
    current.value = row;
    editStatus.value = row.status;
    editNotes.value = row.notes ?? '';
    detailOpen.value = true;

    if (!row.read) {
        try {
            const { data } = await http.get<{ data: InquiryRow }>(`/inquiries/${row.id}`);
            Object.assign(row, data.data);
            counts.value.unread = Math.max(0, counts.value.unread - 1);
            app.inquiriesUnread = counts.value.unread;
        } catch {
            // Marking as read is not critical.
        }
    }
}

async function saveDetail(): Promise<void> {
    if (!current.value) return;
    saving.value = true;
    try {
        const { data } = await http.put<{ data: InquiryRow }>(`/inquiries/${current.value.id}`, { status: editStatus.value, notes: editNotes.value || null });
        Object.assign(current.value, data.data);
        toast.success('Inquiry updated');
        detailOpen.value = false;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        saving.value = false;
    }
}

async function remove(): Promise<void> {
    if (!current.value) return;
    const ok = await confirm({ title: 'Delete this inquiry?', message: 'This cannot be undone.', confirmLabel: 'Delete' });
    if (!ok) return;
    try {
        await http.delete(`/inquiries/${current.value.id}`);
        detailOpen.value = false;
        toast.success('Inquiry deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function exportCsv(): void {
    const params = new URLSearchParams(Object.entries({ status: filters.status, source: filters.source, search: filters.search }).filter(([, v]) => v) as [string, string][]);
    window.location.href = `/api/admin/inquiries/export?${params}`;
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Inquiries" description="Messages from the contact form and registrations from campaign pages.">
            <template #actions>
                <AppBadge v-if="counts.unread" tone="gold">{{ counts.unread }} unread</AppBadge>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </PageHeader>

        <div class="card">
            <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search inquiries</span>
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                    <input v-model="filters.search" type="search" placeholder="Search name, phone or email" class="control pl-9" />
                </label>
                <select v-model="filters.status" class="control sm:w-44" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <select v-model="filters.source" class="control sm:w-48" aria-label="Filter by source">
                    <option value="">All sources</option>
                    <option v-for="(label, value) in sourceLabels" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>

            <div v-if="loading && !rows.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState v-else-if="!rows.length" :icon="Inbox" title="No inquiries yet" description="Submissions from your website forms will appear here." />

            <ul v-else class="divide-y divide-line" :class="{ 'opacity-60': loading }">
                <li v-for="row in rows" :key="row.id">
                    <button type="button" class="flex w-full items-start gap-4 px-4 py-3.5 text-left transition-colors hover:bg-canvas/60" @click="open(row)">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="row.read ? 'bg-transparent' : 'bg-gold'" :aria-label="row.read ? undefined : 'Unread'" />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <span class="text-sm" :class="row.read ? 'font-medium text-ink' : 'font-bold text-ink'">{{ row.name || row.phone || 'Anonymous' }}</span>
                                <span class="text-[13px] text-ink-muted">{{ row.phone }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-[13px] text-ink-soft">
                                {{ sourceLabels[row.source] ?? row.source }}<template v-if="row.page"> · {{ row.page.title }}</template>
                                <template v-if="row.data.message"> · “{{ row.data.message }}”</template>
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1.5">
                            <AppBadge :tone="statusTones[row.status]">{{ statusLabels[row.status] }}</AppBadge>
                            <span class="text-[12px] text-ink-muted">{{ formatDate(row.created_at, true) }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>

        <AppPagination v-if="meta" :meta="meta" @change="(p) => { filters.page = p; load(); }" />

        <AppModal v-model:open="detailOpen" :title="current?.name || 'Inquiry'" :description="current ? `${sourceLabels[current.source] ?? current.source} · ${formatDate(current.created_at, true)}` : ''" size="lg">
            <div v-if="current" class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    <AppButton v-if="current.phone" :href="`tel:${current.phone}`" variant="secondary" size="sm" :icon="Phone">Call {{ current.phone }}</AppButton>
                    <AppButton v-if="whatsapp" :href="whatsapp" variant="secondary" size="sm" :icon="MessageCircle" target="_blank" rel="noopener">WhatsApp</AppButton>
                    <AppButton v-if="current.email" :href="`mailto:${current.email}`" variant="secondary" size="sm" :icon="Mail">Email</AppButton>
                </div>

                <dl class="grid gap-x-6 gap-y-3 rounded-xl bg-canvas p-4 text-sm sm:grid-cols-[160px_1fr]">
                    <template v-for="(value, label) in { Name: current.name, Phone: current.phone, Email: current.email, Page: current.page?.title, Language: current.locale?.toUpperCase() }" :key="label">
                        <template v-if="value">
                            <dt class="text-ink-muted">{{ label }}</dt>
                            <dd class="font-medium text-ink">{{ value }}</dd>
                        </template>
                    </template>
                    <template v-for="(value, key) in current.data" :key="key">
                        <dt class="text-ink-muted">{{ titleCase(String(key)) }}</dt>
                        <dd class="whitespace-pre-line text-ink">{{ value }}</dd>
                    </template>
                </dl>

                <fieldset :disabled="!auth.can('inquiries.update')" class="grid gap-4 sm:grid-cols-[200px_1fr]">
                    <FormField label="Status" for="inquiry-status">
                        <select id="inquiry-status" v-model="editStatus" class="control">
                            <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Internal notes" for="inquiry-notes">
                        <textarea id="inquiry-notes" v-model="editNotes" rows="3" class="control resize-y" placeholder="e.g. Called on Sunday, will visit the office" />
                    </FormField>
                </fieldset>
            </div>
            <template #footer>
                <AppButton v-if="auth.can('inquiries.delete')" variant="ghost" :icon="Trash2" class="text-red-600 hover:bg-red-50 hover:text-red-700 sm:mr-auto" @click="remove">Delete</AppButton>
                <AppButton variant="secondary" @click="detailOpen = false">Close</AppButton>
                <AppButton v-if="auth.can('inquiries.update')" :loading="saving" @click="saveDetail">Save</AppButton>
            </template>
        </AppModal>
    </div>
</template>
