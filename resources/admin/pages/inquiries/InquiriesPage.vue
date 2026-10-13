<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { ArrowDownUp, CalendarRange, Check, Download, Inbox, LayoutList, Mail, MailOpen, MessageCircle, MessageSquareText, Phone, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import InquiryListItem from './InquiryListItem.vue';
import headerPhoto from '@admin/assets/inquiries-header.webp';
import { useConfirm } from '@admin/composables/useConfirm';
import { useListQuery } from '@admin/composables/useListQuery';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { formatDate, titleCase } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import { inquirySource, inquiryStatus, whatsappLink, type InquiryRow, type InquiryStatus } from './types';

type InquiriesResponse = Paginated<InquiryRow> & {
    counts: { unread: number; new: number; statuses?: Partial<Record<InquiryStatus, number>>; sources?: Record<string, number> };
};

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const unread = ref(0);
const list = useListQuery<InquiryRow, InquiriesResponse>(
    '/inquiries',
    { status: '', source: '', read: '', from: '', to: '', sort: '' },
    {
        onLoaded: (response) => {
            unread.value = response.counts.unread;
            app.inquiriesUnread = response.counts.unread;
        },
    },
);

const detailOpen = ref(false);
const filtersOpen = ref(false);
const current = ref<InquiryRow | null>(null);
const editStatus = ref<InquiryStatus>('new');
const editNotes = ref('');
const saving = ref(false);

const canUpdate = computed(() => auth.can('inquiries.update'));
const canDelete = computed(() => auth.can('inquiries.delete'));
const total = computed(() => list.meta.value?.total ?? 0);
const counts = computed(() => list.response.value?.counts);

/** Every filter in the drawer ('' = any), with counts where the API gives them. */
const filterGroups = computed<FilterGroup[]>(() => {
    const statusCounts = counts.value?.statuses ?? {};
    const sourceCounts = counts.value?.sources ?? {};
    const sourceKeys = [...new Set(['contact_form', 'lead_form', ...Object.keys(sourceCounts)])];
    const all = Object.values(statusCounts).reduce<number>((sum, n) => sum + (n ?? 0), 0);
    return [
        {
            key: 'status',
            label: 'Status',
            icon: LayoutList,
            tone: '#818cf8',
            options: [
                { value: '', label: 'All', count: counts.value ? all : null },
                ...(Object.keys(inquiryStatus) as InquiryStatus[]).map((key) => ({ value: key, label: inquiryStatus[key].label, count: statusCounts[key] ?? 0 })),
            ],
        },
        {
            key: 'source',
            label: 'Source',
            icon: MessageSquareText,
            tone: '#38bdf8',
            options: [{ value: '', label: 'All sources' }, ...sourceKeys.map((key) => ({ value: key, label: inquirySource(key).label, count: sourceCounts[key] ?? 0 }))],
        },
        {
            key: 'read',
            label: 'Read',
            icon: MailOpen,
            tone: '#fbbf24',
            options: [
                { value: '', label: 'Any' },
                { value: 'unread', label: 'Unread', count: counts.value ? counts.value.unread : null },
                { value: 'read', label: 'Read' },
            ],
        },
        {
            key: 'sort',
            label: 'Sort by',
            icon: ArrowDownUp,
            tone: '#34d399',
            options: [
                { value: '', label: 'Newest first' },
                { value: 'oldest', label: 'Oldest first' },
            ],
        },
    ];
});

const activeFilters = computed(
    () => (['status', 'source', 'read'] as const).filter((key) => list.filters[key] !== '').length + (list.filters.from || list.filters.to ? 1 : 0),
);
const filtered = computed(() => Boolean(list.filters.search) || activeFilters.value > 0);

const fromDate = computed({ get: () => (list.filters.from as string) || null, set: (v: string | null) => (list.filters.from = v ?? '') });
const toDate = computed({ get: () => (list.filters.to as string) || null, set: (v: string | null) => (list.filters.to = v ?? '') });

const whatsapp = computed(() => whatsappLink(current.value?.phone ?? null));

function markRead(row: InquiryRow): void {
    if (row.read) return;
    http.get<{ data: InquiryRow }>(`/inquiries/${row.id}`)
        .then(({ data }) => {
            Object.assign(row, data.data);
            unread.value = Math.max(0, unread.value - 1);
            app.inquiriesUnread = unread.value;
        })
        .catch(() => {
            // Marking as read is not critical.
        });
}

function open(row: InquiryRow): void {
    current.value = row;
    editStatus.value = row.status;
    editNotes.value = row.notes ?? '';
    detailOpen.value = true;
    markRead(row);
}

async function setStatus(row: InquiryRow, status: InquiryStatus): Promise<void> {
    try {
        const { data } = await http.put<{ data: InquiryRow }>(`/inquiries/${row.id}`, { status, notes: row.notes });
        Object.assign(row, data.data);
        toast.success(`Marked as ${inquiryStatus[status].label.toLowerCase()}`);
    } catch (e) {
        toast.error(errorMessage(e));
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

async function remove(row: InquiryRow): Promise<void> {
    const ok = await confirm({ title: 'Delete this inquiry?', message: 'This cannot be undone.', confirmLabel: 'Delete' });
    if (!ok) return;
    try {
        await http.delete(`/inquiries/${row.id}`);
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
    const keys = ['status', 'source', 'read', 'from', 'to', 'search'] as const;
    const params = new URLSearchParams(keys.filter((k) => list.filters[k] !== '').map((k) => [k, String(list.filters[k])]));
    window.location.href = `/api/admin/inquiries/export?${params}`;
}

onMounted(() => list.load());
</script>

<template>
    <div>
        <!-- Header with a Madinah photo -->
        <ListHero title="Inquiries" description="Messages from the contact form and registrations from campaign pages." :icon="Inbox" tone="#38bdf8" :photo="headerPhoto" photo-position="50% 40%">
            <template #actions>
                <span v-if="unread" class="iq-unread"><span class="size-2 rounded-full bg-amber-500" aria-hidden="true" />{{ unread }} unread</span>
                <AppButton variant="secondary" :icon="Download" @click="exportCsv">Export CSV</AppButton>
            </template>
        </ListHero>

        <!-- Search and filters in one row -->
        <ListToolbar v-model:search="list.filters.search as string" placeholder="Search name, phone or email" :active="activeFilters" @filters="filtersOpen = true" />

        <!-- Inquiries -->
        <div v-if="list.loading.value && !list.rows.value.length" class="grid gap-4" aria-busy="true" aria-label="Loading inquiries">
            <div v-for="n in 3" :key="n" class="card h-[220px] animate-pulse sm:h-[120px]" />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="Inbox"
                :title="filtered ? 'No inquiries match' : 'No inquiries yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Submissions from your website forms will appear here.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
            </EmptyState>
        </div>
        <div v-else class="iq-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="grid gap-3">
                <InquiryListItem
                    v-for="(row, i) in list.rows.value"
                    :key="row.id"
                    :row="row"
                    :index="i"
                    :can-update="canUpdate"
                    :can-delete="canDelete"
                    @open="open(row)"
                    @status="(status) => setStatus(row, status)"
                    @remove="remove(row)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.rows.value.length" :meta="list.meta.value" label="inquiries" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['inquiry', 'inquiries']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="list.clear()"
        >
            <fieldset class="min-w-0">
                <legend class="mb-2.5 flex items-center gap-2 text-[13.5px] font-bold text-ink">
                    <span class="fd-icon" style="--tone: #fb923c"><CalendarRange class="size-4" aria-hidden="true" /></span>
                    Received between
                </legend>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="iq-from" class="mb-1 block text-[12.5px] font-semibold text-ink-soft">From</label>
                        <AppDatePicker id="iq-from" v-model="fromDate" placeholder="Any day" :max-date="toDate" />
                    </div>
                    <div>
                        <label for="iq-to" class="mb-1 block text-[12.5px] font-semibold text-ink-soft">To</label>
                        <AppDatePicker id="iq-to" v-model="toDate" placeholder="Any day" :min-date="fromDate" />
                    </div>
                </div>
            </fieldset>
        </FilterDrawer>

        <!-- Details, follow-up and actions -->
        <AppDrawer
            v-model:open="detailOpen"
            width="lg"
            :title="current?.name || current?.phone || 'Inquiry'"
            :description="current ? `${inquirySource(current.source).label} · ${formatDate(current.created_at, true)}` : ''"
            :icon="Inbox"
        >
            <div v-if="current" class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    <AppBadge tone="neutral">{{ inquirySource(current.source).label }}</AppBadge>
                    <AppBadge v-if="current.page" tone="brand">{{ current.page.title }}</AppBadge>
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

                <blockquote v-if="current.data.message" class="rounded-2xl border-l-4 border-sky-300 bg-sky-50/60 px-4 py-3 text-[14.5px] leading-relaxed whitespace-pre-line text-ink">
                    {{ current.data.message }}
                </blockquote>

                <dl class="dl rounded-2xl bg-canvas p-4">
                    <template v-for="(value, label) in { Name: current.name, Phone: current.phone, Email: current.email, Page: current.page?.title, Language: current.locale?.toUpperCase() }" :key="label">
                        <template v-if="value">
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </template>
                    </template>
                    <template v-for="(value, key) in current.data" :key="key">
                        <template v-if="!['message', 'page', 'name', 'phone', 'email'].includes(String(key))">
                            <dt>{{ titleCase(String(key)) }}</dt>
                            <dd class="whitespace-pre-line">{{ value }}</dd>
                        </template>
                    </template>
                </dl>

                <fieldset :disabled="!canUpdate" class="m-0 min-w-0 space-y-4 border-0 p-0">
                    <legend class="sr-only">Follow-up</legend>
                    <div>
                        <p class="label" id="iq-status-label">Status</p>
                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="iq-status-label">
                            <label v-for="(meta, key) in inquiryStatus" :key="key" class="fd-chip" :class="{ 'is-on': editStatus === key }" :style="{ '--tone': meta.tone }">
                                <input v-model="editStatus" type="radio" class="sr-only" name="iq-status" :value="key" />
                                <Check v-if="editStatus === key" class="size-3.5" aria-hidden="true" />
                                {{ meta.label }}
                            </label>
                        </div>
                    </div>
                    <FormField label="Internal notes" for="inquiry-notes">
                        <textarea id="inquiry-notes" v-model="editNotes" rows="3" class="control resize-y" placeholder="e.g. Called on Sunday, will visit the office" />
                    </FormField>
                </fieldset>
            </div>
            <template #footer>
                <AppButton v-if="canDelete && current" variant="ghost" :icon="Trash2" class="mr-auto text-red-600 hover:bg-red-50 hover:text-red-700" @click="remove(current)">Delete</AppButton>
                <AppButton variant="secondary" class="ml-auto" @click="detailOpen = false">Close</AppButton>
                <AppButton v-if="canUpdate" :loading="saving" @click="saveDetail">Save</AppButton>
            </template>
        </AppDrawer>
    </div>
</template>

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.iq-list {
    container: iq-list / inline-size;
}

.iq-unread {
    min-height: 2.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    background: #fffbeb;
    padding: 0 0.9rem;
    font-size: 13.5px;
    font-weight: 600;
    color: #92400e;
    box-shadow: inset 0 0 0 1px #fde68a;
}
</style>
