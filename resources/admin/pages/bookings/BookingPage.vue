<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, BellRing, Globe, CheckCircle2, ChevronDown, Copy, ExternalLink, FileText, Files, History, Image as ImageIcon, Mail, MailCheck, MailX, MailOpen, StickyNote, MessageCircle, MessageSquareText, Package as PackageIcon, Phone, Plus, RefreshCw, Trash2, TriangleAlert, UserRound, Wallet } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import FormField from '@admin/components/ui/FormField.vue';
import NotesPanel from '@admin/components/ui/NotesPanel.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { formatBytes, formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import { money, statusLabels, statusTone, type BookingActivity, type BookingDetail, type BookingStatus, type BookingUpdate } from './types';

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const booking = ref<BookingDetail | null>(null);
const statuses: Record<BookingStatus, string> = statusLabels;

// Activity log: every entry, or one kind at a time.
const logFilter = ref<'all' | BookingActivity['category']>('all');
const logFilters = [
    { key: 'all', label: 'All' },
    { key: 'staff', label: 'Staff' },
    { key: 'payment', label: 'Payments' },
    { key: 'email', label: 'Emails' },
    { key: 'customer', label: 'Customer' },
] as const;
const logShown = ref(25);
const activity = computed(() => (booking.value?.activity ?? []).filter((a) => logFilter.value === 'all' || a.category === logFilter.value));
const logMeta: Record<BookingActivity['category'], { icon: typeof Mail; tone: string }> = {
    staff: { icon: UserRound, tone: 'bg-violet-50 text-violet-600' },
    payment: { icon: Wallet, tone: 'bg-sky-50 text-sky-600' },
    email: { icon: Mail, tone: 'bg-pink-50 text-pink-600' },
    customer: { icon: Globe, tone: 'bg-emerald-50 text-emerald-600' },
};
function logIcon(entry: BookingActivity): { icon: typeof Mail; tone: string } {
    if (entry.event === 'email_failed') return { icon: MailX, tone: 'bg-red-50 text-red-600' };
    if (entry.event === 'overpaid') return { icon: TriangleAlert, tone: 'bg-amber-50 text-amber-600' };
    if (entry.event === 'email_sent') return { icon: MailCheck, tone: logMeta.email.tone };
    return logMeta[entry.category] ?? logMeta.staff;
}
const statusName = (key: unknown) => statuses[key as BookingStatus] ?? String(key ?? '—');

/**
 * Partially/advance/fully paid follow the payments, and Pending/Confirmed only
 * apply while nothing is paid; Completed and Cancelled can always be chosen.
 */
const paymentStatuses = ['partially_paid', 'advance_paid', 'fully_paid'];
function statusChoosable(key: string): boolean {
    if (!booking.value || key === booking.value.status || key === 'completed' || key === 'cancelled') return true;
    if (paymentStatuses.includes(key)) return false;
    return Number(booking.value.paid_amount) === 0;
}
/** The details of an entry as short "label: value" lines. */
function logDetails(entry: BookingActivity): string[] {
    const d = entry.details;
    const lines: string[] = [];
    if ('from' in d && 'to' in d && entry.event !== 'notes_changed') lines.push(`${statusName(d.from)} → ${statusName(d.to)}`);
    if (typeof d.amount === 'number') lines.push(money(d.amount));
    if (d.method) lines.push(String(d.method));
    if (d.gateway) lines.push(String(d.gateway));
    if (d.reference) lines.push(`Ref ${d.reference}`);
    if (d.gateway_reference) lines.push(`Gateway ref ${d.gateway_reference}`);
    if (d.transaction) lines.push(String(d.transaction));
    if (d.email) lines.push(`${d.email} → ${d.to}`);
    if (d.name) lines.push(`${d.kind ?? 'Document'}: ${d.name}`);
    if (entry.event === 'overpaid') lines.push(`Paid ${money(Number(d.paid))} of ${money(Number(d.total))}`);
    if (entry.event === 'created') {
        const parts = [d.customer, d.option, d.travelers ? `${d.travelers} traveller${d.travelers === 1 ? '' : 's'}` : null, d.total ? money(Number(d.total)) : null, d.documents ? `${d.documents} document(s)` : null];
        lines.push(parts.filter(Boolean).join(' · '));
    }
    if (d.notify === false && (entry.event === 'status_changed' || entry.event === 'message_sent')) lines.push('Customer not emailed');
    if (d.ip) lines.push(`IP ${d.ip}`);
    return lines.filter(Boolean);
}
const edit = useForm({ status: 'pending' as BookingStatus, customer_message: '', notify: true });
const paymentOpen = ref(false);
const paymentForm = useForm({ amount: 0 as number | '', method: 'bank', paid_at: '', reference: '', note: '', customer_message: '', notify: true });

// What the customer is told: a new status or a message goes to their history and, if ticked, to their email.
const customerChange = computed(() => booking.value !== null && (edit.data.status !== booking.value.status || edit.data.customer_message.trim() !== ''));
const updateMeta: Record<BookingUpdate['kind'], { icon: typeof Mail; tone: string }> = {
    created: { icon: CheckCircle2, tone: 'bg-emerald-50 text-emerald-600' },
    status: { icon: RefreshCw, tone: 'bg-violet-50 text-violet-600' },
    payment: { icon: Wallet, tone: 'bg-sky-50 text-sky-600' },
    message: { icon: MessageSquareText, tone: 'bg-pink-50 text-pink-600' },
};
function updateTitle(update: BookingUpdate): string {
    switch (update.kind) {
        case 'created':
            return 'Booking received';
        case 'payment':
            return `Payment received: ${money(update.amount ?? 0)}`;
        case 'status':
            return `Status changed to ${update.status ? statuses[update.status] : '—'}`;
        default:
            return 'Message to the customer';
    }
}
const openLog = ref<number | null>(null);

const paidPercent = computed(() => (booking.value && booking.value.total > 0 ? Math.min(100, Math.round((booking.value.paid_amount / booking.value.total) * 100)) : 0));
const unitLabel = { person: 'per person', room: 'per room', group: 'per group' } as const;
const paymentTone = { paid: 'success', initiated: 'neutral', failed: 'danger', cancelled: 'neutral' } as const;
const whatsapp = computed(() => {
    const digits = booking.value?.customer_phone.replace(/\D/g, '') ?? '';
    return digits ? `https://wa.me/${digits.startsWith('0') ? `88${digits}` : digits}` : null;
});

function apply(data: BookingDetail): void {
    booking.value = data;
    edit.reset({ status: data.status, customer_message: '', notify: Boolean(data.customer_email) });
}

// Emails go out just after a save; reload quietly a few times until every requested one shows as sent.
let followUp: ReturnType<typeof setTimeout> | undefined;
function followEmails(tries = 4): void {
    clearTimeout(followUp);
    if (!booking.value?.updates.some((u) => u.notify && !u.emailed) || tries === 0) return;
    followUp = setTimeout(async () => {
        try {
            const { data } = await http.get<{ data: BookingDetail }>(`/bookings/${booking.value!.id}`);
            // Keep what staff are typing: only the lists are refreshed while the form has changes.
            const b = booking.value!;
            const dirty = edit.data.status !== b.status || edit.data.customer_message.trim() !== '';
            if (dirty) booking.value = { ...booking.value!, updates: data.data.updates, activity: data.data.activity };
            else apply(data.data);
        } catch {
            return;
        }
        followEmails(tries - 1);
    }, 5000);
}
onBeforeUnmount(() => clearTimeout(followUp));

/** Whether an update's email is still on its way (asked for, not sent, and recent). */
const emailPending = (update: BookingUpdate) => update.notify && !update.emailed && Date.now() - new Date(update.created_at).getTime() < 3 * 60 * 1000;

async function load(): Promise<void> {
    try {
        const { data } = await http.get<{ data: BookingDetail; was_unread: boolean }>(`/bookings/${route.params.id}`);
        apply(data.data);
        if (data.was_unread) app.bookingsUnread = Math.max(0, app.bookingsUnread - 1);
    } catch (e) {
        toast.error(errorMessage(e));
        router.replace('/admin/bookings');
    }
}

async function save(): Promise<void> {
    const response = await edit.submit(() => http.put<{ data: BookingDetail }>(`/bookings/${booking.value!.id}`, edit.data));
    if (response) {
        const emailing = customerChange.value && response.data.data.updates[0]?.notify;
        apply(response.data.data);
        toast.success('Booking updated', emailing ? 'The customer is being emailed the latest details.' : undefined);
        followEmails();
    }
}

function openPayment(): void {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    paymentForm.reset({ amount: booking.value?.balance ?? '', method: 'bank', paid_at: now.toISOString().slice(0, 16), reference: '', note: '', customer_message: '', notify: Boolean(booking.value?.customer_email) });
    paymentOpen.value = true;
}

async function recordPayment(): Promise<void> {
    const response = await paymentForm.submit(() => http.post<{ data: BookingDetail }>(`/bookings/${booking.value!.id}/payments`, paymentForm.data));
    if (response) {
        apply(response.data.data);
        paymentOpen.value = false;
        toast.success('Payment recorded', response.data.data.updates[0]?.notify ? 'A receipt is being emailed to the customer.' : undefined);
        followEmails();
    }
}

async function removePayment(id: number): Promise<void> {
    if (!(await confirm({ title: 'Remove this payment?', message: 'Use this only for a payment recorded by mistake. The paid amount is recalculated.', confirmLabel: 'Remove' }))) return;
    try {
        const { data } = await http.delete<{ data: BookingDetail }>(`/bookings/${booking.value!.id}/payments/${id}`);
        apply(data.data);
        toast.success('Payment removed');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(): Promise<void> {
    if (!(await confirm({ title: `Delete booking ${booking.value!.reference}?`, message: 'Its payments and uploaded documents go to the Trash with it, where a super admin can restore them.', confirmLabel: 'Delete booking' }))) return;
    try {
        await http.delete(`/bookings/${booking.value!.id}`);
        toast.success('Booking deleted');
        router.replace('/admin/bookings');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

/** Back to unread (to deal with later) and back to the list, so opening it again does not mark it read. */
async function markUnread(): Promise<void> {
    if (!booking.value) return;
    try {
        await http.post(`/bookings/${booking.value.id}/unread`);
        app.bookingsUnread += 1;
        toast.success('Marked as unread');
        router.push('/admin/bookings');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function copyLink(): Promise<void> {
    await navigator.clipboard.writeText(booking.value!.public_url);
    toast.success('Customer link copied', 'Share it with the customer to show their booking and pay online.');
}

onMounted(load);
</script>

<template>
    <div v-if="!booking" class="flex justify-center py-24 text-brand"><AppSpinner class="size-6" /></div>

    <div v-else>
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <RouterLink to="/admin/bookings" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white hover:text-ink" aria-label="Back to bookings"><ArrowLeft class="size-5" /></RouterLink>
            <!-- At least 14rem for the reference: on phones the buttons move to their own row. -->
            <div class="min-w-0 flex-1 basis-56">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-[17px] font-bold whitespace-nowrap text-ink">{{ booking.reference }}</h1>
                    <AppBadge :tone="statusTone[booking.status]">{{ statuses[booking.status] }}</AppBadge>
                </div>
                <p class="text-[12px] text-ink-muted">Booked {{ formatDate(booking.created_at, true) }} · {{ booking.locale === 'bn' ? 'Bangla' : 'English' }} visitor</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton variant="ghost" :icon="MailOpen" @click="markUnread">Mark as unread</AppButton>
                <AppButton variant="secondary" :icon="Copy" @click="copyLink">Customer link</AppButton>
                <AppButton v-if="auth.can('bookings.delete')" variant="ghost" :icon="Trash2" @click="remove">Delete</AppButton>
            </div>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-6">
                <!-- Booking -->
                <section class="card p-6" aria-labelledby="b-package">
                    <h2 id="b-package" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><PackageIcon class="size-4 text-violet-600" aria-hidden="true" />Package</h2>
                    <p class="mt-1.5 flex items-center gap-2 text-[16px] font-semibold [overflow-wrap:anywhere] text-ink">
                        {{ booking.package }}
                        <a v-if="booking.package_url" :href="booking.package_url" target="_blank" rel="noopener" class="text-ink-muted hover:text-brand" aria-label="Open package page"><ExternalLink class="size-3.5" /></a>
                    </p>
                    <p class="text-sm text-ink-soft">{{ booking.option }}</p>
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-[12px] text-ink-muted">Travelers</dt><dd class="font-semibold text-ink">{{ booking.travelers }}</dd></div>
                        <div v-if="booking.travel_date"><dt class="text-[12px] text-ink-muted">Wants to travel</dt><dd class="font-semibold text-ink">{{ booking.travel_date }}</dd></div>
                        <div><dt class="text-[12px] text-ink-muted">Price</dt><dd class="font-semibold text-ink">{{ money(booking.unit_price) }} <span class="font-normal text-ink-muted">{{ unitLabel[booking.unit] }}</span></dd></div>
                        <div v-if="booking.unit === 'room'"><dt class="text-[12px] text-ink-muted">Rooms</dt><dd class="font-semibold text-ink">{{ booking.quantity }}</dd></div>
                        <div><dt class="text-[12px] text-ink-muted">Advance rule</dt><dd class="font-semibold text-ink">{{ booking.advance_type === 'fixed' ? `${money(booking.advance_value ?? 0)} per traveler` : booking.advance_type === 'percentage' ? `${booking.advance_value}% of total` : 'None' }}</dd></div>
                    </dl>
                </section>

                <!-- Customer -->
                <section class="card p-6" aria-labelledby="b-customer">
                    <h2 id="b-customer" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><UserRound class="size-4 text-sky-600" aria-hidden="true" />Customer</h2>
                    <p class="mt-1.5 text-[16px] font-semibold text-ink">{{ booking.customer_name }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a :href="`tel:${booking.customer_phone}`" class="inline-flex items-center gap-1.5 rounded-lg bg-canvas px-3 py-1.5 text-sm font-medium text-ink hover:text-brand"><Phone class="size-4 shrink-0 text-sky-600" />{{ booking.customer_phone }}</a>
                        <a v-if="whatsapp" :href="whatsapp" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-canvas px-3 py-1.5 text-sm font-medium text-ink hover:text-brand"><MessageCircle class="size-4 shrink-0 text-emerald-600" />WhatsApp</a>
                        <a v-if="booking.customer_email" :href="`mailto:${booking.customer_email}`" class="inline-flex max-w-full min-w-0 items-center gap-1.5 rounded-lg bg-canvas px-3 py-1.5 text-sm font-medium text-ink hover:text-brand"><Mail class="size-4 shrink-0 text-pink-600" /><span class="min-w-0 [overflow-wrap:anywhere]">{{ booking.customer_email }}</span></a>
                    </div>
                    <p v-if="booking.customer_address" class="mt-3 text-sm [overflow-wrap:anywhere] text-ink-soft">{{ booking.customer_address }}</p>
                    <p v-if="booking.customer_note" class="mt-3 rounded-lg bg-canvas px-4 py-3 text-sm [overflow-wrap:anywhere] whitespace-pre-line text-ink">{{ booking.customer_note }}</p>
                </section>

                <!-- Documents -->
                <section class="card p-6" aria-labelledby="b-docs">
                    <h2 id="b-docs" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><Files class="size-4 text-amber-600" aria-hidden="true" />Documents</h2>
                    <p v-if="!booking.documents.length" class="mt-2 text-sm text-ink-muted">No documents were uploaded.</p>
                    <ul v-else class="mt-3 grid gap-2 sm:grid-cols-2">
                        <li v-for="doc in booking.documents" :key="doc.id">
                            <a :href="doc.url" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg border border-line p-3 hover:border-brand/40 hover:bg-canvas">
                                <component :is="doc.is_image ? ImageIcon : FileText" class="size-5 shrink-0 text-brand" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-ink">{{ doc.name }}</span>
                                    <span class="text-[12px] text-ink-muted">{{ doc.kind_label }} · {{ formatBytes(doc.size) }}</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </section>

                <!-- Payments -->
                <section class="card p-6" aria-labelledby="b-payments">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="b-payments" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><Wallet class="size-4 text-emerald-600" aria-hidden="true" />Payments</h2>
                        <AppButton v-if="auth.can('payments.create')" size="sm" :icon="Plus" @click="openPayment">Record payment</AppButton>
                    </div>
                    <p v-if="!booking.payments.length" class="mt-2 text-sm text-ink-muted">No payments yet.</p>
                    <ul v-else class="mt-3 divide-y divide-line">
                        <li v-for="payment in booking.payments" :key="payment.id" class="py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                                        {{ money(payment.amount) }}
                                        <AppBadge :tone="paymentTone[payment.status]">{{ payment.status }}</AppBadge>
                                        <span class="font-normal text-ink-soft">{{ payment.method_label }}</span>
                                    </p>
                                    <p class="text-[12px] [overflow-wrap:anywhere] text-ink-muted">
                                        {{ formatDate(payment.paid_at ?? payment.created_at, true) }}
                                        <template v-if="payment.reference"> · Ref {{ payment.reference }}</template>
                                        <template v-if="payment.gateway_reference"> · Gateway ref {{ payment.gateway_reference }}</template>
                                        <template v-if="payment.transaction_id"> · {{ payment.transaction_id }}</template>
                                        <template v-if="payment.recorded_by"> · recorded by {{ payment.recorded_by }}</template>
                                    </p>
                                    <p v-if="payment.note" class="mt-1 text-[13px] [overflow-wrap:anywhere] text-ink-soft">{{ payment.note }}</p>
                                </div>
                                <button v-if="payment.is_online && payment.log.length" type="button" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[12px] font-semibold text-ink-muted hover:bg-canvas hover:text-ink" :aria-expanded="openLog === payment.id" @click="openLog = openLog === payment.id ? null : payment.id">
                                    Gateway log <ChevronDown class="size-3.5 transition-transform" :class="{ 'rotate-180': openLog === payment.id }" />
                                </button>
                                <button v-if="!payment.is_online && auth.can('payments.create')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label="Remove payment" @click="removePayment(payment.id)"><Trash2 class="size-4" /></button>
                            </div>
                            <pre v-if="openLog === payment.id" class="mt-3 max-h-80 overflow-x-hidden overflow-y-auto rounded-lg bg-ink p-3 text-[11px] leading-relaxed [overflow-wrap:anywhere] whitespace-pre-wrap text-white/85">{{ JSON.stringify(payment.log, null, 2) }}</pre>
                        </li>
                    </ul>
                </section>

                <!-- Activity log: everything that happened to this booking, newest first -->
                <section class="card p-6" aria-labelledby="b-activity">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="b-activity" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><History class="size-4 text-amber-600" aria-hidden="true" />Activity log</h2>
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Show">
                            <button
                                v-for="f in logFilters"
                                :key="f.key"
                                type="button"
                                class="rounded-full px-3 py-1 text-[12px] font-semibold transition-colors"
                                :class="logFilter === f.key ? 'bg-ink text-white' : 'bg-canvas text-ink-soft hover:text-ink'"
                                :aria-pressed="logFilter === f.key"
                                @click="logFilter = f.key; logShown = 25"
                            >{{ f.label }}</button>
                        </div>
                    </div>
                    <p class="mt-1 text-[13px] text-ink-muted">Staff actions, payments and the gateway, emails, and the customer's visits. Only staff see this.</p>
                    <p v-if="!activity.length" class="mt-4 text-sm text-ink-muted">Nothing logged here yet.</p>
                    <ol v-else class="mt-4 space-y-3.5">
                        <li v-for="entry in activity.slice(0, logShown)" :key="entry.id" class="flex gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full" :class="logIcon(entry).tone"><component :is="logIcon(entry).icon" class="size-4" aria-hidden="true" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-ink" :class="{ 'text-red-700': entry.event === 'email_failed', 'text-amber-800': entry.event === 'overpaid' }">{{ entry.label }}</p>
                                <p class="text-[12px] text-ink-muted">{{ formatDate(entry.at, true) }} · {{ entry.by ?? (entry.category === 'customer' ? 'Customer' : 'System') }}</p>
                                <p v-for="line in logDetails(entry)" :key="line" class="mt-0.5 text-[13px] [overflow-wrap:anywhere] text-ink-soft">{{ line }}</p>
                                <p v-if="entry.details.message" class="mt-1 rounded-lg bg-canvas px-3 py-2 text-[13px] [overflow-wrap:anywhere] whitespace-pre-line text-ink">{{ entry.details.message }}</p>
                                <div v-if="entry.event === 'notes_changed'" class="mt-1 grid gap-1 text-[13px] sm:grid-cols-2">
                                    <p class="rounded-lg bg-red-50/70 px-3 py-2 [overflow-wrap:anywhere] whitespace-pre-line text-ink-soft line-through decoration-red-300">{{ entry.details.from || '(empty)' }}</p>
                                    <p class="rounded-lg bg-emerald-50/70 px-3 py-2 [overflow-wrap:anywhere] whitespace-pre-line text-ink">{{ entry.details.to || '(empty)' }}</p>
                                </div>
                                <p v-if="entry.details.error" class="mt-1 rounded-lg bg-red-50 px-3 py-2 text-[12px] [overflow-wrap:anywhere] text-red-800">{{ entry.details.error }}</p>
                            </div>
                        </li>
                    </ol>
                    <button v-if="activity.length > logShown" type="button" class="mt-4 text-[13px] font-semibold text-brand hover:underline" @click="logShown += 50">Show {{ Math.min(50, activity.length - logShown) }} more</button>
                </section>

                <!-- Customer updates: what the customer sees on their booking page -->
                <section class="card p-6" aria-labelledby="b-updates">
                    <h2 id="b-updates" class="flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><BellRing class="size-4 text-pink-600" aria-hidden="true" />Customer updates</h2>
                    <p class="mt-1 text-[13px] text-ink-muted">Shown on the customer's booking page. Each one says whether it was emailed.</p>
                    <p v-if="!booking.updates.length" class="mt-3 text-sm text-ink-muted">No updates yet.</p>
                    <ol v-else class="mt-4 space-y-4">
                        <li v-for="update in booking.updates" :key="update.id" class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full" :class="updateMeta[update.kind].tone"><component :is="updateMeta[update.kind].icon" class="size-4" aria-hidden="true" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                                    {{ updateTitle(update) }}
                                    <span v-if="update.emailed" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"><MailCheck class="size-3" aria-hidden="true" />Emailed</span>
                                    <span v-else-if="emailPending(update)" class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700"><Mail class="size-3" aria-hidden="true" />Sending…</span>
                                    <span v-else-if="update.notify" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700"><MailX class="size-3" aria-hidden="true" />Email not sent</span>
                                    <span v-else class="rounded-full bg-canvas px-2 py-0.5 text-[11px] font-semibold text-ink-muted">Not emailed</span>
                                </p>
                                <p class="text-[12px] text-ink-muted">{{ formatDate(update.created_at, true) }}<template v-if="update.by"> · {{ update.by }}</template></p>
                                <p v-if="update.message" class="mt-1.5 rounded-lg bg-canvas px-3 py-2 text-[13px] [overflow-wrap:anywhere] whitespace-pre-line text-ink">{{ update.message }}</p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-5" aria-labelledby="b-amounts">
                    <h2 id="b-amounts" class="sr-only">Amounts</h2>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-soft">Total</dt><dd class="font-semibold text-ink">{{ money(booking.total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-soft">Advance</dt><dd class="text-ink">{{ money(booking.advance_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-soft">Paid</dt><dd class="font-semibold text-emerald-700">{{ money(booking.paid_amount) }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2.5 text-base"><dt class="font-semibold text-ink">Balance</dt><dd class="font-bold text-ink">{{ money(booking.balance) }}</dd></div>
                        <div v-if="booking.overpaid > 0" class="flex justify-between rounded-lg bg-amber-50 px-3 py-2 text-amber-900"><dt class="font-semibold">Overpaid</dt><dd class="font-bold">{{ money(booking.overpaid) }}</dd></div>
                    </dl>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-canvas" role="progressbar" :aria-valuenow="paidPercent" aria-valuemin="0" aria-valuemax="100" aria-label="Paid"><div class="h-full rounded-full bg-emerald-600" :style="{ width: `${paidPercent}%` }" /></div>
                    <p class="mt-1.5 text-right text-[12px] text-ink-muted">{{ paidPercent }}% paid</p>
                </section>

                <form class="card space-y-4 p-5" novalidate @submit.prevent="save">
                    <fieldset :disabled="!auth.can('bookings.update')" class="space-y-4">
                        <FormField label="Status" for="b-status" :error="edit.error('status')">
                            <select id="b-status" v-model="edit.data.status" class="control">
                                <option v-for="(label, key) in statuses" :key="key" :value="key" :disabled="!statusChoosable(key)">{{ label }}{{ statusChoosable(key) ? '' : ' (follows payments)' }}</option>
                            </select>
                        </FormField>
                        <FormField label="Message to the customer" for="b-message" :error="edit.error('customer_message')" hint="Optional. Shown on their booking page and in the email.">
                            <textarea id="b-message" v-model="edit.data.customer_message" rows="3" class="control" placeholder="e.g. Your visa has been submitted." />
                        </FormField>
                        <div class="rounded-lg bg-canvas px-3 py-2.5">
                            <AppCheckbox v-model="edit.data.notify" :disabled="!booking.customer_email" label="Email the customer about this change" />
                            <p class="mt-1 text-[12px] text-ink-muted">
                                <template v-if="!booking.customer_email">This booking has no email address.</template>
                                <template v-else-if="customerChange">They get the new status{{ edit.data.customer_message.trim() ? ', your message' : '' }} and the latest details.</template>
                                <template v-else>Sent when you change the status or write a message.</template>
                            </p>
                        </div>
                        <AppButton v-if="auth.can('bookings.update')" type="submit" block :loading="edit.processing">Save</AppButton>
                    </fieldset>
                </form>

                <!-- Staff notes: added one by one, with who and when. -->
                <section class="card p-5" aria-labelledby="b-notes-title">
                    <h2 id="b-notes-title" class="mb-3 flex items-center gap-2 text-[12px] font-semibold tracking-wide text-ink-muted uppercase"><StickyNote class="size-4 text-amber-500" aria-hidden="true" />Notes</h2>
                    <NotesPanel v-model:notes="booking.notes" type="bookings" :id="booking.id" :can-add="auth.can('bookings.update')" />
                </section>
            </aside>
        </div>

        <AppModal v-model:open="paymentOpen" title="Record a payment" description="A payment received at the office: bank deposit, mobile banking or cash." :persistent="paymentForm.processing">
            <form id="payment-form" class="space-y-4" novalidate @submit.prevent="recordPayment">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Amount (BDT)" for="p-amount" :error="paymentForm.error('amount')" :hint="`${money(booking.balance)} still due`" required>
                        <input id="p-amount" v-model.number="paymentForm.data.amount" type="number" min="1" :max="booking.balance" step="0.01" class="control" />
                    </FormField>
                    <FormField label="Method" for="p-method" :error="paymentForm.error('method')" required>
                        <select id="p-method" v-model="paymentForm.data.method" class="control">
                            <option v-for="(label, key) in booking.offline_methods" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Received on" for="p-date" :error="paymentForm.error('paid_at')" required>
                        <AppDatePicker id="p-date" v-model="paymentForm.data.paid_at" mode="datetime" :clearable="false" :invalid="Boolean(paymentForm.error('paid_at'))" />
                    </FormField>
                    <FormField label="Reference" for="p-ref" :error="paymentForm.error('reference')" hint="Bank slip, transaction ID…">
                        <input id="p-ref" v-model="paymentForm.data.reference" class="control" />
                    </FormField>
                </div>
                <FormField label="Internal note" for="p-note" :error="paymentForm.error('note')" hint="Only visible to staff.">
                    <textarea id="p-note" v-model="paymentForm.data.note" rows="2" class="control" />
                </FormField>
                <FormField label="Message to the customer" for="p-message" :error="paymentForm.error('customer_message')" hint="Optional. Included in the receipt email and on their booking page.">
                    <textarea id="p-message" v-model="paymentForm.data.customer_message" rows="2" class="control" />
                </FormField>
                <AppCheckbox v-model="paymentForm.data.notify" :disabled="!booking.customer_email" :label="booking.customer_email ? 'Email the customer a receipt' : 'No email on this booking'" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="paymentOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="payment-form" :loading="paymentForm.processing">Record payment</AppButton>
            </template>
        </AppModal>
    </div>
</template>
