<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import FormField from './FormField.vue';
import { useAppStore } from '@admin/stores/app';
import type { Translations } from '@admin/types';

/**
 * One input per configured language, switched with small language tabs.
 * Languages come from the server, so a new language appears automatically.
 */
const model = defineModel<Translations>({ default: () => ({}) });

const props = withDefaults(
    defineProps<{
        label: string;
        multiline?: boolean;
        rows?: number;
        placeholder?: string;
        required?: boolean;
        errors?: Record<string, string[]>;
        /** Error key prefix, e.g. "alt" matches errors "alt.en", "alt.bn". */
        errorKey?: string;
        hint?: string;
    }>(),
    { rows: 3 },
);

const app = useAppStore();
const active = ref(app.defaultLocale);
const id = useId();

const locales = computed(() => app.locales);

function errorFor(code: string): string | undefined {
    return props.errorKey ? props.errors?.[`${props.errorKey}.${code}`]?.[0] : undefined;
}

const firstError = computed(() => locales.value.map((l) => errorFor(l.code)).find(Boolean));

function update(code: string, value: string): void {
    model.value = { ...model.value, [code]: value };
}
</script>

<template>
    <FormField :label="label" :for="`${id}-${active}`" :error="firstError" :hint="hint" :required="required">
        <template #aside>
            <div class="-mb-0.5 flex rounded-md bg-canvas p-0.5" role="tablist" :aria-label="`${label} language`">
                <button
                    v-for="locale in locales"
                    :key="locale.code"
                    type="button"
                    role="tab"
                    :aria-selected="active === locale.code"
                    class="relative rounded px-2 py-0.5 text-[11px] font-bold tracking-wide uppercase transition-colors"
                    :class="active === locale.code ? 'bg-white text-brand shadow-sm' : 'text-ink-muted hover:text-ink'"
                    @click="active = locale.code"
                >
                    {{ locale.code }}
                    <span
                        v-if="!model?.[locale.code]"
                        class="absolute -top-0.5 -right-0.5 size-1.5 rounded-full"
                        :class="errorFor(locale.code) ? 'bg-red-500' : 'bg-[#c3ccd8]'"
                        :title="`${locale.name} is empty`"
                    />
                </button>
            </div>
        </template>
        <template v-for="locale in locales" :key="locale.code">
            <div v-show="active === locale.code" :lang="locale.code" :dir="locale.dir">
                <textarea
                    v-if="multiline"
                    :id="`${id}-${locale.code}`"
                    :value="model?.[locale.code] ?? ''"
                    :rows="rows"
                    :placeholder="placeholder"
                    class="control resize-y"
                    :class="{ 'control-invalid': errorFor(locale.code) }"
                    @input="update(locale.code, ($event.target as HTMLTextAreaElement).value)"
                />
                <input
                    v-else
                    :id="`${id}-${locale.code}`"
                    :value="model?.[locale.code] ?? ''"
                    :placeholder="placeholder"
                    class="control"
                    :class="{ 'control-invalid': errorFor(locale.code) }"
                    @input="update(locale.code, ($event.target as HTMLInputElement).value)"
                />
            </div>
        </template>
    </FormField>
</template>
