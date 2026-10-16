<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, ChevronDown, Copy, ExternalLink, FileText, Files, Image as ImageIcon, Mail, MessageCircle, Package as PackageIcon, Phone, Plus, Trash2, UserRound, Wallet } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import FormField from '@admin/components/ui/FormField.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { formatBytes, formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import { money, statusTone, type BookingDetail, type BookingStatus } from './types';

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const booking = ref<BookingDetail | null>(null);
const statuses: Record<BookingStatus, string> = { pending: 'Pending', confirmed: 'Confirmed', advance_paid: 'Advance paid', fully_paid: 'Fully paid', completed: 'Completed', cancelled: 'Cancelled' };
const edit = useForm({ status: 'pending' as BookingStatus, admin_notes: '' });
const paymentOpen = ref(false);
const paymentForm = useForm({ amount: 0 as number | '', method: 'bank', paid_at: '', reference: '', note: '' });
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
    edit.reset({ status: data.status, admin_notes: data.admin_notes ?? '' });
}

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
        apply(response.data.data);
        toast.success('Booking updated');
    }
}

function openPayment(): void {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    paymentForm.reset({ amount: booking.value?.balance ?? '', method: 'bank', paid_at: now.toISOString().slice(0, 16), reference: '', note: '' });
    paymentOpen.value = true;
}

async function recordPayment(): Promise<void> {
    const response = await paymentForm.submit(() => http.post<{ data: BookingDetail }>(`/bookings/${booking.value!.id}/payments`, paymentForm.data));
    if (response) {
        apply(response.data.data);
        paymentOpen.value = false;
        toast.success('Payment recorded');
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
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-[17px] font-bold text-ink">{{ booking.reference }}</h1>
                    <AppBadge :tone="statusTone[booking.status]">{{ statuses[booking.status] }}</AppBadge>
                </div>
                <p class="text-[12px] text-ink-muted">Booked {{ formatDate(booking.created_at, true) }} · {{ booking.locale === 'bn' ? 'Bangla' : 'English' }} visitor</p>
            </div>
            <AppButton variant="secondary" :icon="Copy" @click="copyLink">Customer link</AppButton>
            <AppButton v-if="auth.can('bookings.delete')" variant="ghost" :icon="Trash2" @click="remove">Delete</AppButton>
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
            </div>

            <aside class="space-y-6">
                <section class="card p-5" aria-labelledby="b-amounts">
                    <h2 id="b-amounts" class="sr-only">Amounts</h2>
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-soft">Total</dt><dd class="font-semibold text-ink">{{ money(booking.total) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-soft">Advance</dt><dd class="text-ink">{{ money(booking.advance_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-soft">Paid</dt><dd class="font-semibold text-emerald-700">{{ money(booking.paid_amount) }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2.5 text-base"><dt class="font-semibold text-ink">Balance</dt><dd class="font-bold text-ink">{{ money(booking.balance) }}</dd></div>
                    </dl>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-canvas" role="progressbar" :aria-valuenow="paidPercent" aria-valuemin="0" aria-valuemax="100" aria-label="Paid"><div class="h-full rounded-full bg-emerald-600" :style="{ width: `${paidPercent}%` }" /></div>
                    <p class="mt-1.5 text-right text-[12px] text-ink-muted">{{ paidPercent }}% paid</p>
                </section>

                <form class="card space-y-4 p-5" novalidate @submit.prevent="save">
                    <fieldset :disabled="!auth.can('bookings.update')" class="space-y-4">
                        <FormField label="Status" for="b-status" :error="edit.error('status')">
                            <select id="b-status" v-model="edit.data.status" class="control">
                                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </FormField>
                        <FormField label="Internal notes" for="b-notes" :error="edit.error('admin_notes')" hint="Only visible to staff.">
                            <textarea id="b-notes" v-model="edit.data.admin_notes" rows="6" class="control" />
                        </FormField>
                        <AppButton v-if="auth.can('bookings.update')" type="submit" block :loading="edit.processing">Save</AppButton>
                    </fieldset>
                </form>
            </aside>
        </div>

        <AppModal v-model:open="paymentOpen" title="Record a payment" description="A payment received at the office: bank deposit, mobile banking or cash." :persistent="paymentForm.processing">
            <form id="payment-form" class="space-y-4" novalidate @submit.prevent="recordPayment">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Amount (BDT)" for="p-amount" :error="paymentForm.error('amount')" required>
                        <input id="p-amount" v-model.number="paymentForm.data.amount" type="number" min="1" step="0.01" class="control" />
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
                <FormField label="Note" for="p-note" :error="paymentForm.error('note')">
                    <textarea id="p-note" v-model="paymentForm.data.note" rows="2" class="control" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="paymentOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="payment-form" :loading="paymentForm.processing">Record payment</AppButton>
            </template>
        </AppModal>
    </div>
</template>
