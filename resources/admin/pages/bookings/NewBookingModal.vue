<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CalendarPlus } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PhoneInput from '@admin/components/ui/PhoneInput.vue';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';
import { money } from './types';

interface PackageChoice {
    id: number;
    name: string;
    status: string;
    service: string | null;
    travel: { mode: 'none' | 'month_year' | 'hajj_year'; years: number[]; months: Record<string, string>; currentYear: number; currentMonth: number };
    options: { id: number; label: string; unit: 'person' | 'room' | 'group'; price: number }[];
}

export interface BookingPrefill {
    name?: string | null;
    phone?: string | null;
    email?: string | null;
    travelers?: number;
    inquiryId?: number | null;
    locale?: string | null;
}

/**
 * A booking made by staff (from a phone call, or from an inquiry): package,
 * option, travelers and travel date, and the customer's details. The server
 * works out the amounts exactly as on the website.
 */
const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ prefill?: BookingPrefill | null }>();
const emit = defineEmits<{ created: [id: number] }>();

const toast = useToastStore();
const packages = ref<PackageChoice[]>([]);
const loading = ref(false);

const form = useForm({
    package_id: null as number | null,
    option: null as number | null,
    travelers: 1,
    rooms: 1,
    travel_year: null as number | null,
    travel_month: null as number | null,
    name: '',
    phone: null as string | null,
    email: '',
    note: '',
    locale: 'en',
    notify: true,
    inquiry_id: null as number | null,
});

const pkg = computed(() => packages.value.find((p) => p.id === form.data.package_id) ?? null);
const option = computed(() => pkg.value?.options.find((o) => o.id === form.data.option) ?? null);
const mode = computed(() => pkg.value?.travel.mode ?? 'none');
/** Months still ahead in the chosen year. */
const months = computed(() => {
    const travel = pkg.value?.travel;
    if (!travel) return [];
    const from = form.data.travel_year === travel.currentYear ? travel.currentMonth : 1;
    return Object.entries(travel.months).filter(([m]) => Number(m) >= from).map(([value, label]) => ({ value: Number(value), label }));
});
const estimate = computed(() => {
    if (!option.value) return null;
    const quantity = option.value.unit === 'group' ? 1 : option.value.unit === 'room' ? form.data.rooms : form.data.travelers;
    return option.value.price * Math.max(1, quantity || 1);
});
const grouped = computed(() => {
    const groups = new Map<string, PackageChoice[]>();
    for (const p of packages.value) groups.set(p.service ?? 'Other', [...(groups.get(p.service ?? 'Other') ?? []), p]);
    return [...groups.entries()];
});

watch(open, async (isOpen) => {
    if (!isOpen) return;
    form.reset({
        ...form.data,
        package_id: null, option: null, travelers: props.prefill?.travelers ?? 1, rooms: 1, travel_year: null, travel_month: null,
        name: props.prefill?.name ?? '', phone: props.prefill?.phone ?? null, email: props.prefill?.email ?? '', note: '',
        locale: props.prefill?.locale === 'bn' ? 'bn' : 'en', notify: Boolean(props.prefill?.email), inquiry_id: props.prefill?.inquiryId ?? null,
    });
    if (packages.value.length) return;
    loading.value = true;
    try {
        packages.value = (await http.get<{ data: PackageChoice[] }>('/bookings/options')).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
});

// A new package: its first option and travel year.
watch(() => form.data.package_id, () => {
    form.data.option = pkg.value?.options[0]?.id ?? null;
    form.data.travel_year = pkg.value?.travel.years[0] ?? null;
    form.data.travel_month = null;
});
// No email, no confirmation to send: the box follows whether there is an address.
watch(() => Boolean(form.data.email.trim()), (hasEmail) => (form.data.notify = hasEmail), { immediate: true });
watch(() => form.data.travel_year, () => {
    if (form.data.travel_month && !months.value.some((m) => m.value === form.data.travel_month)) form.data.travel_month = null;
});

async function save(): Promise<void> {
    const payload = {
        ...form.data,
        email: form.data.email.trim() || null,
        rooms: option.value?.unit === 'room' ? form.data.rooms : null,
        travel_month: mode.value === 'month_year' ? form.data.travel_month : null,
        travel_year: mode.value === 'none' ? null : form.data.travel_year,
        notify: form.data.notify && Boolean(form.data.email.trim()),
    };
    const response = await form.submit(() => http.post<{ data: { id: number; reference: string } }>('/bookings', payload));
    if (!response) return;
    toast.success(`Booking ${response.data.data.reference} created`, form.data.inquiry_id ? 'The inquiry is closed and linked to it.' : undefined);
    open.value = false;
    emit('created', response.data.data.id);
}
</script>

<template>
    <AppModal v-model:open="open" :title="prefill?.inquiryId ? 'Convert to a booking' : 'New booking'" description="The amounts are worked out as on the website. No online payment is started." size="lg" :persistent="form.processing">
        <form id="new-booking" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
            <FormField label="Package" for="nb-package" :error="form.error('package_id')" required class="sm:col-span-2">
                <select id="nb-package" v-model="form.data.package_id" class="control" :disabled="loading">
                    <option :value="null" disabled>{{ loading ? 'Loading packages…' : 'Choose a package' }}</option>
                    <optgroup v-for="[service, list] in grouped" :key="service" :label="service">
                        <option v-for="p in list" :key="p.id" :value="p.id">{{ p.name }}{{ p.status !== 'published' ? ' (draft)' : '' }}</option>
                    </optgroup>
                </select>
            </FormField>
            <FormField label="Option" for="nb-option" :error="form.error('option')" required class="sm:col-span-2">
                <select id="nb-option" v-model="form.data.option" class="control" :disabled="!pkg">
                    <option v-for="o in pkg?.options ?? []" :key="o.id" :value="o.id">{{ o.label }} · {{ money(o.price) }} per {{ o.unit }}</option>
                </select>
            </FormField>
            <FormField label="Travelers" for="nb-travelers" :error="form.error('travelers')" required>
                <input id="nb-travelers" v-model.number="form.data.travelers" type="number" min="1" max="50" class="control" />
            </FormField>
            <FormField v-if="option?.unit === 'room'" label="Rooms" for="nb-rooms" :error="form.error('rooms')" required>
                <input id="nb-rooms" v-model.number="form.data.rooms" type="number" min="1" max="50" class="control" />
            </FormField>
            <FormField v-if="mode !== 'none'" :label="mode === 'hajj_year' ? 'Hajj year' : 'Travel year'" for="nb-year" :error="form.error('travel_year')" required>
                <select id="nb-year" v-model="form.data.travel_year" class="control">
                    <option v-for="y in pkg?.travel.years ?? []" :key="y" :value="y">{{ mode === 'hajj_year' ? `Hajj ${y}` : y }}</option>
                </select>
            </FormField>
            <FormField v-if="mode === 'month_year'" label="Travel month" for="nb-month" :error="form.error('travel_month')" required>
                <select id="nb-month" v-model="form.data.travel_month" class="control">
                    <option :value="null" disabled>Choose a month</option>
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </FormField>

            <div class="sm:col-span-2 mt-1 border-t border-line pt-4 text-[12px] font-semibold tracking-wide text-ink-muted uppercase">Customer</div>
            <FormField label="Full name" for="nb-name" :error="form.error('name')" required>
                <input id="nb-name" v-model="form.data.name" class="control" autocomplete="off" />
            </FormField>
            <FormField label="Mobile number" for="nb-phone" :error="form.error('phone')" required>
                <PhoneInput id="nb-phone" v-model="form.data.phone" :error="null" />
            </FormField>
            <FormField label="Email" for="nb-email" :error="form.error('email')" hint="For the confirmation and later updates.">
                <input id="nb-email" v-model="form.data.email" type="email" class="control" autocomplete="off" />
            </FormField>
            <FormField label="Language for emails" for="nb-locale">
                <select id="nb-locale" v-model="form.data.locale" class="control">
                    <option value="en">English</option>
                    <option value="bn">Bangla</option>
                </select>
            </FormField>
            <FormField label="Customer's note" for="nb-note" :error="form.error('note')" class="sm:col-span-2" hint="Shown on the booking like a note the customer wrote; for staff remarks use Notes on the booking.">
                <textarea id="nb-note" v-model="form.data.note" rows="2" maxlength="2000" class="control" />
            </FormField>
            <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-canvas px-3 py-2.5">
                <AppCheckbox v-model="form.data.notify" :disabled="!form.data.email.trim()" label="Email the customer the booking confirmation" />
                <span v-if="estimate !== null" class="text-[13px] text-ink-soft">About <strong class="text-ink">{{ money(estimate) }}</strong> before any advance</span>
            </div>
        </form>
        <template #footer>
            <AppButton variant="secondary" :disabled="form.processing" @click="open = false">Cancel</AppButton>
            <AppButton type="submit" form="new-booking" :icon="CalendarPlus" :loading="form.processing" :disabled="!form.data.package_id || !form.data.option">Create booking</AppButton>
        </template>
    </AppModal>
</template>
