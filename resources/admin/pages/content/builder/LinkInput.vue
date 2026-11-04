<script setup lang="ts">
import { computed, inject, ref, useId, type ComputedRef } from 'vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import type { Translations } from '@admin/types';
import { LINK_TARGETS, type LinkTarget } from './types';

interface LinkValue {
    label: Translations;
    url: string | null;
    /** A section on this page (campaign pages), instead of an address. */
    section?: string | null;
}

type LinkType = 'page' | 'url' | 'section';

const model = defineModel<LinkValue | null>({ default: null });
const props = defineProps<{ label: string; hint?: string; errors: Record<string, string[]>; path: string }>();

const id = useId();
const value = computed<LinkValue>(() => model.value ?? { label: {}, url: null });
const serverError = computed(() => props.errors[`${props.path}.url`]?.[0] ?? props.errors[`${props.path}.section`]?.[0]);

// Other sections of the page a button can jump to (campaign pages only).
const targets = inject<ComputedRef<LinkTarget[] | null> | null>(LINK_TARGETS, null);
const sections = computed(() => targets?.value ?? null);

const EXTERNAL = /^(https?:\/\/|mailto:|tel:)/i;
const type = ref<LinkType>(value.value.section ? 'section' : EXTERNAL.test(value.value.url ?? '') ? 'url' : 'page');
const missing = computed(() => !!value.value.section && !sections.value?.some((s) => s.ref === value.value.section));

const typeError = computed(() => {
    const url = value.value.url?.trim();
    if (!url) return null;
    if (type.value === 'page' && !/^[/#]/.test(url)) return 'An internal page starts with /, e.g. /contact.';
    if (type.value === 'url' && !EXTERNAL.test(url)) return 'An external URL starts with https://, or use tel: or mailto:.';
    return null;
});

function update(patch: Partial<LinkValue>): void {
    model.value = { ...value.value, ...patch };
}

function setType(next: LinkType): void {
    type.value = next;
    update({ url: null, section: null });
}
</script>

<template>
    <fieldset class="rounded-xl border border-line p-3.5">
        <legend class="px-1 text-[13px] font-semibold text-ink">{{ label }}</legend>
        <p v-if="hint" class="-mt-1 mb-3 text-[12px] text-ink-muted">{{ hint }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <TranslatableInput
                :model-value="value.label"
                label="Button text"
                :errors="errors"
                :error-key="`${path}.label`"
                @update:model-value="update({ label: $event })"
            />
            <div>
                <label :for="`${id}-type`" class="label">Link type</label>
                <select :id="`${id}-type`" :value="type" class="control" @change="setType(($event.target as HTMLSelectElement).value as LinkType)">
                    <option value="page">Internal page</option>
                    <option value="url">External URL</option>
                    <option v-if="sections" value="section">Target component</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <template v-if="type === 'section'">
                    <label :for="id" class="label">Target component</label>
                    <select :id="id" :value="value.section ?? ''" class="control" :class="{ 'control-invalid': serverError }" @change="update({ section: ($event.target as HTMLSelectElement).value || null, url: null })">
                        <option value="">Choose a section of this page…</option>
                        <option v-if="missing" :value="value.section ?? ''">A removed section</option>
                        <option v-for="s in sections ?? []" :key="s.ref" :value="s.ref">{{ s.label }}</option>
                    </select>
                    <p v-if="missing" class="mt-1.5 text-[13px] font-medium text-amber-700">That section was removed, so the button is hidden. Choose another one.</p>
                    <p v-else-if="!(sections ?? []).length" class="mt-1.5 text-[13px] text-ink-muted">Add another section to the page first.</p>
                </template>
                <template v-else>
                    <label :for="id" class="label">{{ type === 'page' ? 'Page address' : 'URL' }}</label>
                    <input
                        :id="id"
                        :value="value.url ?? ''"
                        class="control"
                        :class="{ 'control-invalid': typeError || serverError }"
                        :inputmode="type === 'url' ? 'url' : undefined"
                        :placeholder="type === 'page' ? '/contact' : 'https://…'"
                        @input="update({ url: ($event.target as HTMLInputElement).value || null, section: null })"
                    />
                    <p v-if="typeError || serverError" class="mt-1.5 text-[13px] font-medium text-red-600">
                        {{ typeError ?? 'Use a link starting with /, https://, tel:, mailto: or #.' }}
                    </p>
                </template>
            </div>
        </div>
    </fieldset>
</template>
