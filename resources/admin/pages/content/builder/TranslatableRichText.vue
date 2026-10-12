<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from '@admin/components/ui/FormField.vue';
import RichTextEditor from '@admin/components/ui/RichTextEditor.vue';
import { useAppStore } from '@admin/stores/app';
import type { Translations } from '@admin/types';

const model = defineModel<Translations>({ default: () => ({}) });
const props = defineProps<{ label: string; hint?: string; required?: boolean; errors?: Record<string, string[]>; errorKey?: string }>();

const app = useAppStore();
const active = ref(app.defaultLocale);

function errorFor(code: string): string | undefined {
    return props.errorKey ? props.errors?.[`${props.errorKey}.${code}`]?.[0] : undefined;
}

const firstError = computed(() => app.locales.map((l) => errorFor(l.code)).find(Boolean));
</script>

<template>
    <FormField :label="label" :hint="hint" :error="firstError" :required="required">
        <template #aside>
            <div class="-mb-0.5 flex rounded-md bg-canvas p-0.5" role="tablist" :aria-label="`${label} language`">
                <button
                    v-for="locale in app.locales"
                    :key="locale.code"
                    type="button"
                    role="tab"
                    :aria-selected="active === locale.code"
                    class="relative rounded px-2 py-0.5 text-[11px] font-bold tracking-wide uppercase"
                    :class="active === locale.code ? 'bg-white text-brand shadow-sm' : 'text-ink-muted hover:text-ink'"
                    @click="active = locale.code"
                >
                    {{ locale.code }}
                    <span v-if="!model?.[locale.code]" class="absolute -top-0.5 -right-0.5 size-1.5 rounded-full" :class="errorFor(locale.code) ? 'bg-red-500' : 'bg-[#c3ccd8]'" />
                </button>
            </div>
        </template>
        <template v-for="locale in app.locales" :key="locale.code">
            <RichTextEditor
                v-if="active === locale.code"
                :model-value="model?.[locale.code] ?? null"
                :invalid="!!errorFor(locale.code)"
                :lang="locale.code"
                :label="`${label} (${locale.name ?? locale.code})`"
                :dir="locale.dir"
                @update:model-value="model = { ...model, [locale.code]: $event }"
            />
        </template>
    </FormField>
</template>
