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

const model = defineModel<LinkValue | null>({ default: null });
const props = defineProps<{ label: string; hint?: string; errors: Record<string, string[]>; path: string }>();

const id = useId();
const value = computed<LinkValue>(() => model.value ?? { label: {}, url: null });
const urlError = computed(() => props.errors[`${props.path}.url`]?.[0]);

const targets = inject<ComputedRef<LinkTarget[] | null> | null>(LINK_TARGETS, null);
const sections = computed(() => targets?.value ?? null);
const mode = ref<'url' | 'section'>(value.value.section ? 'section' : 'url');
const missing = computed(() => !!value.value.section && !sections.value?.some((s) => s.ref === value.value.section));

function update(patch: Partial<LinkValue>): void {
    model.value = { ...value.value, ...patch };
}

function setMode(next: 'url' | 'section'): void {
    mode.value = next;
    update(next === 'url' ? { section: null } : { url: null });
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
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <label :for="id" class="label mb-0">{{ mode === 'section' ? 'Jump to section' : 'Link' }}</label>
                    <div v-if="sections" class="inline-flex rounded-lg bg-canvas p-0.5 text-[12px] font-semibold" role="group" aria-label="Link to">
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1"
                            :class="mode === 'url' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                            :aria-pressed="mode === 'url'"
                            @click="setMode('url')"
                        >
                            Web address
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1"
                            :class="mode === 'section' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted hover:text-ink'"
                            :aria-pressed="mode === 'section'"
                            @click="setMode('section')"
                        >
                            Section on this page
                        </button>
                    </div>
                </div>
                <template v-if="sections && mode === 'section'">
                    <select :id="id" :value="value.section ?? ''" class="control" @change="update({ section: ($event.target as HTMLSelectElement).value || null, url: null })">
                        <option value="">Choose a section…</option>
                        <option v-if="missing" :value="value.section ?? ''">A removed section</option>
                        <option v-for="s in sections" :key="s.ref" :value="s.ref">{{ s.label }}</option>
                    </select>
                    <p v-if="missing" class="mt-1.5 text-[13px] font-medium text-amber-700">That section was removed, so the button is hidden. Choose another one.</p>
                </template>
                <input
                    v-else
                    :id="id"
                    :value="value.url ?? ''"
                    class="control"
                    :class="{ 'control-invalid': urlError }"
                    placeholder="/contact or https://…"
                    @input="update({ url: ($event.target as HTMLInputElement).value || null })"
                />
                <p v-if="urlError" class="mt-1.5 text-[13px] font-medium text-red-600">Use a link starting with /, https://, tel:, mailto: or #.</p>
            </div>
        </div>
    </fieldset>
</template>
