<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { ChevronRight, Download, Inbox, Mail, MessageCircle, Phone, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useListQuery } from '@admin/composables/useListQuery';
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

type InquiriesResponse = Paginated<InquiryRow> & { counts: { unread: number; new: number } };

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const unread = ref(0);
const list = useListQuery<InquiryRow, InquiriesResponse>(
    '/inquiries',
    { status: '', source: '' },
    {
        onLoaded: (response) => {
            unread.value = response.counts.unread;
            app.inquiriesUnread = response.counts.unread;
        },
    },
);

const detailOpen = ref(false);
const current = ref<InquiryRow | null>(null);
const editStatus = ref<InquiryRow['status']>('new');
const editNotes = ref('');
const saving = ref(false);

const statusLabels = { new: 'New', in_progress: 'In progress', closed: 'Closed' } as const;
const statusTones = { new: 'gold', in_progress: 'brand', closed: 'neutral' } as const;
const sourceLabels: Record<string, string> = { contact_form: 'Contact form', lead_form: 'Campaign form' };

const summary = computed(() => (list.meta.value ? `${list.meta.value.total} ${list.meta.value.total === 1 ? 'inquiry' : 'inquiries'}` : ''));
const hasFilters = computed(() => Boolean(list.filters.search) || list.active.value > 0);

const whatsapp = computed(() => {
    const digits = (current.value?.phone ?? '').replace(/\D/g, '');
    if (!digits) return null;
    const international = digits.startsWith('880') ? digits : digits.startsWith('0') ? `88${digits}` : digits;
    return `https://wa.me/${international}`;
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
            unread.value = Math.max(0, unread.value - 1);
            app.inquiriesUnread = unread.value;
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
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function clearAll(): void {
    list.clear();
    list.filters.search = '';
}

function exportCsv(): void {
    const params = new URLSearchParams(
        Object.entries({ status: list.filters.status, source: list.filters.source, search: list.filters.search })
            .filter(([, v]) => v !== '')
            .map(([k, v]) => [k, String(v)]),
    );
    window.location.href = `/api/admin/inquiries/export?${params}`;
}

onMounted(() => list.load());
</script>

<template>
    <div>
        <PageHeader title="Inquiries" description="Messages from the contact form and registrations from campaign pages.">
            <template #actions>
                <AppBadge v-if="unread" tone="gold">{{ unread }} unread</AppBadge>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </PageHeader>

        <FilterBar v-model:search="list.filters.search as string" placeholder="Search name, phone or email" :active="list.active.value" :summary="summary" @clear="list.clear">
            <select v-model="list.filters.status" class="control md:w-44" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
            </select>
            <select v-model="list.filters.source" class="control md:w-48" aria-label="Filter by source">
                <option value="">All sources</option>
                <option v-for="(label, value) in sourceLabels" :key="value" :value="value">{{ label }}</option>
            </select>
        </FilterBar>

        <div class="card overflow-hidden">
            <div v-if="list.loading.value && !list.rows.value.length" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
            <EmptyState
                v-else-if="!list.rows.value.length"
                :icon="Inbox"
                :title="hasFilters ? 'No inquiries match' : 'No inquiries yet'"
                :description="hasFilters ? 'Try another search or clear the filters.' : 'Submissions from your website forms will appear here.'"
            >
                <AppButton v-if="hasFilters" variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>

            <ul v-else class="divide-y divide-line" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
                <li v-for="row in list.rows.value" :key="row.id">
                    <button type="button" class="flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-canvas/60 sm:gap-4" @click="open(row)">
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
                        <ChevronRight class="mt-1 hidden size-4 shrink-0 text-ink-muted sm:block" aria-hidden="true" />
                    </button>
                </li>
            </ul>
        </div>

        <AppPagination v-if="list.meta.value" :meta="list.meta.value" label="inquiries" @change="list.goTo" @per-page="list.setPerPage" />

        <AppDrawer
            v-model:open="detailOpen"
            width="lg"
            :title="current?.name || 'Inquiry'"
            :description="current ? `${sourceLabels[current.source] ?? current.source} · ${formatDate(current.created_at, true)}` : ''"
            :icon="Inbox"
        >
            <div v-if="current" class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="statusTones[current.status]">{{ statusLabels[current.status] }}</AppBadge>
                    <AppBadge tone="neutral">{{ sourceLabels[current.source] ?? current.source }}</AppBadge>
                </div>

                <div v-if="current.phone || current.email" class="-mx-1 space-y-1">
                    <a v-if="current.phone" :href="`tel:${current.phone}`" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><Phone class="size-4" /></span>Call {{ current.phone }}
                    </a>
                    <a v-if="whatsapp" :href="whatsapp" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #34d399"><MessageCircle class="size-4" /></span>WhatsApp
                    </a>
                    <a v-if="current.email" :href="`mailto:${current.email}`" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #f472b6"><Mail class="size-4" /></span>Email {{ current.email }}
                    </a>
                </div>

                <dl class="dl rounded-2xl bg-canvas p-4">
                    <template v-for="(value, label) in { Name: current.name, Phone: current.phone, Email: current.email, Page: current.page?.title, Language: current.locale?.toUpperCase() }" :key="label">
                        <template v-if="value">
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </template>
                    </template>
                    <template v-for="(value, key) in current.data" :key="key">
                        <dt>{{ titleCase(String(key)) }}</dt>
                        <dd class="whitespace-pre-line">{{ value }}</dd>
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
                <AppButton v-if="auth.can('inquiries.delete')" variant="ghost" :icon="Trash2" class="mr-auto text-red-600 hover:bg-red-50 hover:text-red-700" @click="remove">Delete</AppButton>
                <AppButton variant="secondary" class="ml-auto" @click="detailOpen = false">Close</AppButton>
                <AppButton v-if="auth.can('inquiries.update')" :loading="saving" @click="saveDetail">Save</AppButton>
            </template>
        </AppDrawer>
    </div>
</template>
