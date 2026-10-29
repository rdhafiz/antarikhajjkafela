<script setup lang="ts">
import { computed, useId } from 'vue';
import FormField from './FormField.vue';

/**
 * SEO title and description: one text each, in whichever language the admin
 * writes (they are not translated per language). Counts characters against
 * what Google shows.
 */
const title = defineModel<string | null>('title', { default: null });
const description = defineModel<string | null>('description', { default: null });

const props = withDefaults(
    defineProps<{
        errors?: Record<string, string[] | undefined>;
        titlePlaceholder?: string;
        descriptionPlaceholder?: string;
        rows?: number;
    }>(),
    { errors: () => ({}), rows: 3 },
);

const titleId = useId();
const descriptionId = useId();

const titleCount = computed(() => (title.value ?? '').length);
const descriptionCount = computed(() => (description.value ?? '').length);

const tone = (count: number, ideal: number, max: number) => (count > max ? 'text-red-600' : count > ideal ? 'text-amber-700' : 'text-ink-muted');
</script>

<template>
    <FormField label="SEO title" :for="titleId" :error="props.errors['seo.title']" hint="One title, in English or Bangla, as you prefer. Up to about 60 characters show fully in Google.">
        <template #aside>
            <span class="text-[12px] tabular-nums" :class="tone(titleCount, 60, 70)">{{ titleCount }} / 60</span>
        </template>
        <input :id="titleId" v-model="title" type="text" maxlength="70" class="control" :class="{ 'control-invalid': props.errors['seo.title'] }" :placeholder="titlePlaceholder" />
    </FormField>
    <FormField label="SEO description" :for="descriptionId" :error="props.errors['seo.description']" hint="One description, in English or Bangla. Around 150 characters that make people want to click.">
        <template #aside>
            <span class="text-[12px] tabular-nums" :class="tone(descriptionCount, 160, 200)">{{ descriptionCount }} / 160</span>
        </template>
        <textarea
            :id="descriptionId"
            v-model="description"
            :rows="rows"
            maxlength="200"
            class="control resize-y"
            :class="{ 'control-invalid': props.errors['seo.description'] }"
            :placeholder="descriptionPlaceholder"
        />
    </FormField>
</template>
