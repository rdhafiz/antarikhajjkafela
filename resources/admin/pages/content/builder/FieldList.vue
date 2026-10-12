<script setup lang="ts">
import { useId } from 'vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import ColorInput from '@admin/components/ui/ColorInput.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import BlocksEditor from './BlocksEditor.vue';
import IconPicker from './IconPicker.vue';
import LinkInput from './LinkInput.vue';
import RepeaterField from './RepeaterField.vue';
import TranslatableRichText from './TranslatableRichText.vue';
import { isFieldVisible, type FieldDef } from './types';

defineOptions({ name: 'FieldList' });

/**
 * Renders editing controls for a list of field definitions. Errors are
 * looked up by their full path, e.g. "sections.2.content.items.0.title.en".
 */
const model = defineModel<Record<string, unknown>>({ required: true });
const props = defineProps<{
    fields: FieldDef[];
    errors: Record<string, string[]>;
    path: string;
    icons: { name: string; svg: string }[];
}>();

const baseId = useId();

function set(key: string, value: unknown): void {
    model.value = { ...model.value, [key]: value };
}

/** Full error path of a field ("sections.2.content.title", or just "types" at the root). */
function fp(key: string): string {
    return props.path ? `${props.path}.${key}` : key;
}

function error(key: string): string | undefined {
    return props.errors[fp(key)]?.[0];
}

function aspectFor(usage?: string): string {
    return { icon: '1 / 1', avatar: '1 / 1', logo: '3 / 1', og: '1200 / 630', card: '4 / 3', banner: '16 / 6', hero: '16 / 9' }[usage ?? ''] ?? '16 / 9';
}

/** The date picker wants "YYYY-MM-DDTHH:mm" in the server's local time. */
function toLocalInput(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value.slice(0, 16) : null;
}
</script>

<template>
    <div class="space-y-4">
        <template v-for="field in fields" :key="field.key">
            <template v-if="isFieldVisible(field, model)">
                <!-- Translatable text -->
                <TranslatableInput
                    v-if="(field.type === 'text' || field.type === 'textarea') && field.translatable"
                    :model-value="(model[field.key] as Record<string, string | null>) ?? {}"
                    :label="field.label"
                    :hint="field.hint"
                    :placeholder="field.placeholder"
                    :multiline="field.type === 'textarea'"
                    :rows="field.rows"
                    :required="field.required"
                    :errors="errors"
                    :error-key="fp(field.key)"
                    @update:model-value="set(field.key, $event)"
                />

                <TranslatableRichText
                    v-else-if="field.type === 'richtext'"
                    :model-value="(model[field.key] as Record<string, string | null>) ?? {}"
                    :label="field.label"
                    :hint="field.hint"
                    :required="field.required"
                    :errors="errors"
                    :error-key="fp(field.key)"
                    @update:model-value="set(field.key, $event)"
                />

                <!-- Plain text / textarea / youtube / number / datetime -->
                <FormField
                    v-else-if="['text', 'textarea', 'youtube', 'number', 'datetime'].includes(field.type)"
                    :label="field.label"
                    :for="`${baseId}-${field.key}`"
                    :hint="field.hint"
                    :error="error(field.key)"
                    :required="field.required"
                >
                    <textarea
                        v-if="field.type === 'textarea'"
                        :id="`${baseId}-${field.key}`"
                        :value="(model[field.key] as string) ?? ''"
                        :rows="field.rows ?? 3"
                        :placeholder="field.placeholder"
                        class="control resize-y"
                        :class="{ 'control-invalid': error(field.key) }"
                        @input="set(field.key, ($event.target as HTMLTextAreaElement).value || null)"
                    />
                    <input
                        v-else-if="field.type === 'number'"
                        :id="`${baseId}-${field.key}`"
                        type="number"
                        :value="(model[field.key] as number) ?? ''"
                        :min="field.min"
                        :max="field.max"
                        class="control"
                        :class="{ 'control-invalid': error(field.key) }"
                        @input="set(field.key, ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))"
                    />
                    <AppDatePicker
                        v-else-if="field.type === 'datetime'"
                        :id="`${baseId}-${field.key}`"
                        mode="datetime"
                        :model-value="toLocalInput(model[field.key])"
                        :placeholder="field.placeholder"
                        :invalid="Boolean(error(field.key))"
                        @update:model-value="set(field.key, $event || null)"
                    />
                    <input
                        v-else
                        :id="`${baseId}-${field.key}`"
                        :type="field.type === 'youtube' ? 'url' : 'text'"
                        :value="(model[field.key] as string) ?? ''"
                        :placeholder="field.placeholder"
                        :maxlength="field.max"
                        class="control"
                        :class="{ 'control-invalid': error(field.key) }"
                        @input="set(field.key, ($event.target as HTMLInputElement).value || null)"
                    />
                </FormField>

                <MediaField
                    v-else-if="field.type === 'image'"
                    :model-value="(model[field.key] as number | null) ?? null"
                    :label="field.label"
                    :hint="field.hint"
                    :usage="field.usage ?? 'general'"
                    :aspect="aspectFor(field.usage)"
                    :error="error(field.key)"
                    @update:model-value="set(field.key, $event)"
                />

                <LinkInput
                    v-else-if="field.type === 'link'"
                    :model-value="(model[field.key] as never) ?? null"
                    :label="field.label"
                    :hint="field.hint"
                    :errors="errors"
                    :path="fp(field.key)"
                    @update:model-value="set(field.key, $event)"
                />

                <FormField v-else-if="field.type === 'select'" :label="field.label" :for="`${baseId}-${field.key}`" :hint="field.hint" :error="error(field.key)">
                    <select
                        :id="`${baseId}-${field.key}`"
                        :value="(model[field.key] as string) ?? ''"
                        class="control"
                        @change="set(field.key, ($event.target as HTMLSelectElement).value || null)"
                    >
                        <option v-if="!field.required && field.default === null" value="">— None —</option>
                        <option v-for="(optionLabel, value) in field.options" :key="value" :value="value">{{ optionLabel }}</option>
                    </select>
                </FormField>

                <div v-else-if="field.type === 'toggle'" class="rounded-lg border border-line bg-white p-3">
                    <AppSwitch :model-value="!!model[field.key]" :label="field.label" :description="field.hint" @update:model-value="set(field.key, $event)" />
                </div>

                <div v-else-if="field.type === 'color'" class="relative">
                    <ColorInput :model-value="(model[field.key] as string | null) ?? null" :label="field.label" :hint="field.hint" :error="error(field.key)" @update:model-value="set(field.key, $event)" />
                    <button v-if="model[field.key]" type="button" class="absolute top-0 right-0 text-[12px] font-semibold text-ink-muted hover:text-red-600" @click="set(field.key, null)">Clear</button>
                </div>

                <IconPicker v-else-if="field.type === 'icon'" :model-value="(model[field.key] as string | null) ?? null" :label="field.label" :icons="icons" @update:model-value="set(field.key, $event)" />

                <BlocksEditor
                    v-else-if="field.type === 'blocks'"
                    :model-value="(model[field.key] as never[]) ?? []"
                    :label="field.label"
                    :errors="errors"
                    :path="fp(field.key)"
                    :max="field.max"
                    @update:model-value="set(field.key, $event)"
                />

                <RepeaterField
                    v-else-if="field.type === 'repeater'"
                    :model-value="(model[field.key] as Record<string, unknown>[]) ?? []"
                    :field="field"
                    :errors="errors"
                    :path="fp(field.key)"
                    :icons="icons"
                    @update:model-value="set(field.key, $event)"
                />
            </template>
        </template>
    </div>
</template>
