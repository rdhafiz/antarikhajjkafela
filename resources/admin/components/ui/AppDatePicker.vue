<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';
import type { Instance } from 'flatpickr/dist/types/instance';
import { CalendarDays, Clock, X } from 'lucide-vue-next';

/**
 * Date or date-time field (flatpickr). The value keeps the format the API uses:
 * "YYYY-MM-DD" for dates, "YYYY-MM-DDTHH:mm" for date-times (local time).
 * The visitor sees a readable date ("12 Oct 2026, 2:30 PM").
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        id?: string;
        mode?: 'date' | 'datetime';
        placeholder?: string;
        minDate?: string | Date | null;
        maxDate?: string | null;
        /** A date that stays selectable before minDate (the date a record already has). */
        keepDate?: string | null;
        clearable?: boolean;
        disabled?: boolean;
        invalid?: boolean;
    }>(),
    { mode: 'date', clearable: true },
);

const input = ref<HTMLInputElement | null>(null);
let picker: Instance | null = null;

const dayOf = (value: string | Date) => new Date(typeof value === 'string' ? value.slice(0, 10) + 'T00:00' : new Date(value).setHours(0, 0, 0, 0));

/** minDate as flatpickr limits; a kept earlier date turns it into an "enable" rule so that day stays pickable. */
function lowerLimit(): { minDate?: string | Date; enable?: ((date: Date) => boolean)[] } {
    if (!props.minDate) return {};
    if (!props.keepDate || dayOf(props.keepDate) >= dayOf(props.minDate)) return { minDate: props.minDate };

    const min = dayOf(props.minDate).getTime();
    const kept = dayOf(props.keepDate).getTime();

    return { enable: [(date: Date) => date.getTime() >= min || dayOf(date).getTime() === kept] };
}

const valueFormat = () => (props.mode === 'datetime' ? 'Y-m-d\\TH:i' : 'Y-m-d');

onMounted(() => {
    if (!input.value) return;

    picker = flatpickr(input.value, {
        dateFormat: valueFormat(),
        altInput: true,
        altFormat: props.mode === 'datetime' ? 'j M Y, h:i K' : 'j M Y',
        altInputClass: 'control dp-alt',
        enableTime: props.mode === 'datetime',
        time_24hr: false,
        minuteIncrement: 5,
        allowInput: false,
        disableMobile: true,
        monthSelectorType: 'static',
        ...lowerLimit(),
        maxDate: props.maxDate ?? undefined,
        defaultDate: model.value || undefined,
        onChange: (_dates, value) => {
            model.value = value || null;
        },
    }) as Instance;

    const alt = picker.altInput;
    if (alt) {
        if (props.id) alt.id = props.id;
        alt.placeholder = props.placeholder ?? (props.mode === 'datetime' ? 'Pick a date and time' : 'Pick a date');
        alt.disabled = props.disabled;
        if (props.invalid) alt.setAttribute('aria-invalid', 'true');
    }
});

watch(model, (value) => {
    if (!picker) return;
    const current = picker.selectedDates[0] ? picker.formatDate(picker.selectedDates[0], valueFormat()) : null;
    if ((value || null) !== current) picker.setDate(value || '', false);
});

watch(
    () => [props.minDate, props.keepDate],
    () => {
        if (!picker) return;
        const limit = lowerLimit();
        picker.set({ minDate: limit.minDate ?? undefined, enable: limit.enable ?? [() => true] });
    },
);
watch(() => props.maxDate, (value) => picker?.set('maxDate', value ?? undefined));
watch(() => props.disabled, (value) => picker?.altInput && (picker.altInput.disabled = value));
watch(() => props.invalid, (value) => picker?.altInput?.toggleAttribute('aria-invalid', value));

function clear(): void {
    picker?.clear();
    model.value = null;
}

onBeforeUnmount(() => picker?.destroy());
</script>

<template>
    <div class="dp relative" :class="{ 'is-invalid': invalid }">
        <component :is="mode === 'datetime' ? Clock : CalendarDays" class="dp-icon pointer-events-none absolute top-1/2 left-3 z-[1] size-4 -translate-y-1/2" aria-hidden="true" />
        <input ref="input" type="text" class="control" tabindex="-1" aria-hidden="true" />
        <button
            v-if="clearable && model && !disabled"
            type="button"
            class="absolute top-1/2 right-2 z-[1] flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink"
            aria-label="Clear date"
            @click="clear"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
