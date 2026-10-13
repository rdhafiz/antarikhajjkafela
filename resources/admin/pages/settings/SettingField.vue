<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { Check, Eye, EyeOff, Lock, ShieldCheck } from 'lucide-vue-next';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import ColorInput from '@admin/components/ui/ColorInput.vue';
import FormField from '@admin/components/ui/FormField.vue';
import MapEmbedInput from '@admin/components/ui/MapEmbedInput.vue';
import PhoneInput from '@admin/components/ui/PhoneInput.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import type { SettingFieldDefinition } from './types';

/** Renders one setting according to its type from the server schema. */
const model = defineModel<unknown>();
const props = defineProps<{
    field: SettingFieldDefinition;
    errors: Record<string, string[]>;
    /** For secrets: whether a value is already stored. */
    secretStored?: boolean;
}>();

const id = useId();
const error = computed(() => props.errors[props.field.key]?.[0] ?? null);
const revealed = ref(false);

/** Short option lists show as tappable cards instead of a dropdown. */
const asChoices = computed(() => props.field.type === 'select' && !props.field.readonly && (props.field.options?.length ?? 0) > 0 && (props.field.options?.length ?? 0) <= 3);
const choiceList = computed<{ value: string | number | null; label: string }[]>(() =>
    asChoices.value ? [...(props.field.clearable ? [{ value: null, label: 'Off' }] : []), ...(props.field.options ?? [])] : [],
);

const text = computed({
    get: () => (model.value as string | null) ?? '',
    set: (value: string) => (model.value = value === '' ? null : value),
});
</script>

<template>
    <TranslatableInput
        v-if="field.translatable"
        v-model="model as Record<string, string | null>"
        :label="field.label"
        :hint="field.hint"
        :multiline="field.type === 'text'"
        :error-key="field.key"
        :errors="errors"
    />

    <ColorInput v-else-if="field.type === 'color'" v-model="model as string | null" :label="field.label" :hint="field.hint" :error="error" />

    <MediaField
        v-else-if="field.type === 'media'"
        v-model="model as number | null"
        :label="field.label"
        :hint="field.hint"
        :usage="field.usage ?? 'general'"
        :aspect="field.usage === 'icon' ? '1 / 1' : field.usage === 'og' ? '1200 / 630' : '3 / 1'"
        :error="error"
    />

    <MapEmbedInput v-else-if="field.type === 'map'" v-model="model as string | null" :label="field.label" :hint="field.hint" :error="error" />

    <div v-else-if="field.type === 'boolean'" class="rounded-lg border border-line p-3.5">
        <AppSwitch v-model="model as boolean" :label="field.label" :description="field.hint" />
    </div>

    <fieldset v-else-if="asChoices" class="min-w-0" :aria-describedby="error || field.hint ? `${id}-help` : undefined">
        <legend class="label">{{ field.label }}</legend>
        <div class="grid gap-2" :class="['grid-cols-[minmax(0,1fr)]', ['', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-4'][Math.min(choiceList.length, 4) - 1]]">
            <label v-for="option in choiceList" :key="String(option.value)" class="sf-choice" :class="{ 'is-on': model === option.value }">
                <input v-model="model" type="radio" class="sr-only" :name="id" :value="option.value" />
                <span class="sf-radio" aria-hidden="true"><Check class="size-3" :stroke-width="3" /></span>
                <span class="min-w-0 truncate">{{ option.label }}</span>
            </label>
        </div>
        <p v-if="error" :id="`${id}-help`" class="mt-1.5 text-[13px] font-medium text-red-600" role="alert">{{ error }}</p>
        <p v-else-if="field.hint" :id="`${id}-help`" class="mt-1.5 text-[13px] text-ink-muted">{{ field.hint }}</p>
    </fieldset>

    <FormField v-else-if="field.type === 'select' || field.type === 'timezone'" :label="field.label" :for="id" :hint="field.hint" :error="error">
        <div class="relative">
            <select :id="id" v-model="model" class="control" :class="{ 'control-invalid': error, 'pr-9': field.readonly }" :disabled="field.readonly">
                <option v-if="field.type === 'select'" :value="null">— None —</option>
                <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <Lock v-if="field.readonly" class="pointer-events-none absolute top-1/2 right-8 size-3.5 -translate-y-1/2 text-ink-muted" />
        </div>
        <p v-if="field.options?.length === 0" class="mt-1.5 text-[13px] text-ink-muted">No options available yet.</p>
    </FormField>

    <FormField v-else-if="field.type === 'json' || field.type === 'text'" :label="field.label" :for="id" :hint="field.hint" :error="error">
        <textarea
            :id="id"
            v-model="text"
            :rows="field.type === 'json' ? 8 : 3"
            spellcheck="false"
            class="control resize-y"
            :class="{ 'control-invalid': error, 'font-mono text-[12px]': field.type === 'json' }"
        />
    </FormField>

    <FormField v-else-if="field.type === 'phone'" :label="field.label" :for="id" :hint="field.hint">
        <PhoneInput :id="id" v-model="model as string | null" :error="error" :disabled="field.readonly" />
    </FormField>

    <FormField v-else-if="field.type === 'secret'" :label="field.label" :for="id" :hint="field.hint ?? (secretStored ? 'Stored encrypted. Type a new value only to replace it.' : undefined)" :error="error">
        <template #aside>
            <span v-if="secretStored" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11.5px] font-semibold text-emerald-700"><ShieldCheck class="size-3.5" aria-hidden="true" />Saved</span>
            <span v-else class="inline-flex items-center rounded-full bg-canvas px-2 py-0.5 text-[11.5px] font-semibold text-ink-soft">Not set</span>
        </template>
        <div class="relative">
            <Lock class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-muted" aria-hidden="true" />
            <input
                :id="id"
                v-model="text"
                :type="revealed ? 'text' : 'password'"
                autocomplete="new-password"
                spellcheck="false"
                class="control pr-11 pl-10 font-mono placeholder:font-sans"
                :class="{ 'control-invalid': error }"
                :placeholder="secretStored ? '••••••••••••' : 'Not set'"
                :aria-invalid="error ? true : undefined"
            />
            <button
                v-if="text"
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-ink-muted hover:bg-canvas hover:text-ink"
                :aria-label="revealed ? 'Hide what you typed' : 'Show what you typed'"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <EyeOff v-if="revealed" class="size-4" /><Eye v-else class="size-4" />
            </button>
        </div>
    </FormField>

    <AppInput
        v-else
        v-model="text"
        :type="field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : field.type === 'phone' ? 'tel' : 'text'"
        :label="field.label"
        :hint="field.hint"
        :placeholder="field.type === 'url' ? 'https://' : undefined"
        :error="error"
    />
</template>
