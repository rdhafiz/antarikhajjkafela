<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import intlTelInput, { type Iso2 } from 'intl-tel-input';
import 'intl-tel-input/styles';
import { CircleCheck, CircleAlert } from 'lucide-vue-next';

/**
 * International phone number field (intl-tel-input with Google's libphonenumber rules).
 * Saves the number in international format ("+880 1711-381901"); the server checks it again.
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        id?: string;
        /** Country used for numbers typed without a "+" code. */
        country?: string;
        disabled?: boolean;
        /** Server-side error for this field, shown under it. */
        error?: string | null;
        placeholder?: string;
    }>(),
    { country: 'bd' },
);

const emit = defineEmits<{ valid: [valid: boolean] }>();

const input = ref<HTMLInputElement | null>(null);
const touched = ref(false);
const state = ref<'empty' | 'valid' | 'invalid'>('empty');
let iti: ReturnType<typeof intlTelInput> | null = null;

const reasons: Record<string, string> = {
    INVALID_COUNTRY_CODE: 'That country code is not valid.',
    TOO_SHORT: 'This number is too short.',
    TOO_LONG: 'This number is too long.',
    NOT_A_NUMBER: 'Enter a phone number.',
};
const reason = ref('Enter a valid phone number for the selected country.');
const message = computed(() => props.error || (touched.value && state.value === 'invalid' ? reason.value : null));

function sync(): void {
    if (!iti || !input.value) return;
    const raw = input.value.value.trim();

    if (!raw) {
        state.value = 'empty';
        model.value = null;
        emit('valid', true);
        return;
    }

    // Before the validation rules load, keep the typed text so nothing is lost.
    if (!intlTelInput.utils) {
        model.value = raw;
        return;
    }

    if (iti.isValidNumber()) {
        state.value = 'valid';
        model.value = iti.getNumber('INTERNATIONAL');
        emit('valid', true);
    } else {
        state.value = 'invalid';
        const code = iti.getValidationError();
        reason.value = (code && reasons[code]) || 'Enter a valid phone number for the selected country.';
        model.value = raw;
        emit('valid', false);
    }
}

onMounted(() => {
    if (!input.value) return;

    iti = intlTelInput(input.value, {
        initialCountry: props.country as Iso2,
        countryOrder: ['bd', 'sa', 'ae', 'qa', 'kw', 'om', 'bh', 'my', 'gb', 'us'],
        separateDialCode: true,
        strictMode: true,
        formatAsYouType: true,
        countrySearch: true,
        containerClass: 'pi-iti',
        loadUtils: () => import('intl-tel-input/utils'),
    });

    if (model.value) iti.setNumber(model.value);
    iti.promise.then(() => {
        if (model.value) iti?.setNumber(model.value);
        sync();
    });
});

watch(model, (value) => {
    if (!iti || !input.value) return;
    const shown = intlTelInput.utils && iti.isValidNumber() ? iti.getNumber('INTERNATIONAL') : input.value.value.trim();
    if ((value ?? '') !== (shown ?? '')) iti.setNumber(value ?? '');
});

onBeforeUnmount(() => iti?.destroy());
</script>

<template>
    <div class="pi" :class="{ 'is-invalid': !!message, 'is-valid': state === 'valid' }">
        <div class="relative">
            <input
                :id="id"
                ref="input"
                type="tel"
                autocomplete="tel"
                class="control"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="message ? 'true' : undefined"
                :aria-describedby="message && id ? `${id}-phone-error` : undefined"
                @input="sync"
                @countrychange="sync"
                @blur="touched = true; sync()"
            />
            <CircleCheck v-if="state === 'valid'" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-emerald-600" aria-hidden="true" />
            <CircleAlert v-else-if="message" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-red-600" aria-hidden="true" />
        </div>
        <p v-if="message" :id="id ? `${id}-phone-error` : undefined" class="mt-1.5 text-[12.5px] font-medium text-red-600" role="alert">{{ message }}</p>
    </div>
</template>
