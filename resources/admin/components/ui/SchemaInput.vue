<script setup lang="ts">
import { computed, useId } from 'vue';
import { CheckCircle2, CircleAlert, ExternalLink, WandSparkles } from 'lucide-vue-next';
import FormField from './FormField.vue';

/**
 * A page's own schema.org structured data (JSON-LD), added after the schema the
 * website generates. Checks the JSON as it is typed; the server checks it again.
 */
const model = defineModel<string | null>({ default: null });

defineProps<{ error?: string | string[] | null }>();

const id = useId();

type Check = { ok: true; types: string[] } | { ok: false; message: string } | null;

function nodes(data: unknown): Record<string, unknown>[] {
    const items = Array.isArray(data) ? data : [data];
    return items.flatMap((item) => {
        if (!item || typeof item !== 'object' || Array.isArray(item)) return [];
        const node = item as Record<string, unknown>;
        return Array.isArray(node['@graph']) ? nodes(node['@graph']) : [node];
    });
}

const check = computed<Check>(() => {
    const text = (model.value ?? '').trim();
    if (!text) return null;

    let data: unknown;
    try {
        data = JSON.parse(text);
    } catch (e) {
        return { ok: false, message: `Not valid JSON yet: ${(e as Error).message}` };
    }

    const found = nodes(data);
    if (!found.length) return { ok: false, message: 'Enter a JSON object such as {"@type": "FAQPage", …}, or a list of them.' };
    if (found.some((node) => !node['@type'])) return { ok: false, message: 'Every item needs an "@type", for example "FAQPage" or "Event".' };

    return { ok: true, types: found.map((node) => String(node['@type'])) };
});

function format() {
    try {
        model.value = JSON.stringify(JSON.parse(model.value ?? ''), null, 2);
    } catch {
        // Invalid JSON stays as typed; the message under the box explains why.
    }
}
</script>

<template>
    <FormField
        label="Page schema (JSON-LD)"
        :for="id"
        :error="error"
        hint="Optional. The website already adds the agency, breadcrumbs and this page's basic schema; paste extra schema.org data here, such as FAQPage, Event or Offer. Paste only the JSON, without a script tag."
    >
        <template #aside>
            <button v-if="check?.ok" type="button" class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-brand hover:underline" @click="format">
                <WandSparkles class="size-3.5" aria-hidden="true" />Tidy up
            </button>
        </template>
        <textarea
            :id="id"
            v-model="model"
            rows="8"
            spellcheck="false"
            autocapitalize="off"
            autocomplete="off"
            class="control resize-y font-mono text-[12px] leading-relaxed"
            :class="{ 'control-invalid': error || (check && !check.ok) }"
            placeholder='{ "@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [ … ] }'
            :aria-describedby="check ? `${id}-check` : undefined"
        />
        <div v-if="check" :id="`${id}-check`" class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px]" aria-live="polite">
            <span v-if="check.ok" class="inline-flex items-center gap-1 font-medium text-emerald-700">
                <CheckCircle2 class="size-3.5" aria-hidden="true" />Valid: {{ check.types.join(', ') }}
            </span>
            <span v-else class="inline-flex items-center gap-1 font-medium text-amber-700">
                <CircleAlert class="size-3.5" aria-hidden="true" />{{ check.message }}
            </span>
            <a
                v-if="check.ok"
                href="https://validator.schema.org/"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-ink-muted hover:text-ink"
            >
                Check it in the Schema validator<ExternalLink class="size-3" aria-hidden="true" />
            </a>
        </div>
    </FormField>
</template>
