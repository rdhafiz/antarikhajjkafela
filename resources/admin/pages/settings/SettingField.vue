<script setup lang="ts">
import { computed, useId } from 'vue';
import { Lock } from 'lucide-vue-next';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import ColorInput from '@admin/components/ui/ColorInput.vue';
import FormField from '@admin/components/ui/FormField.vue';
import MapEmbedInput from '@admin/components/ui/MapEmbedInput.vue';
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

    <AppInput
        v-else-if="field.type === 'secret'"
        v-model="text"
        type="password"
        autocomplete="new-password"
        :label="field.label"
        :placeholder="secretStored ? '•••••••• saved. Type to replace.' : 'Not set'"
        :hint="field.hint"
        :error="error"
    />

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
