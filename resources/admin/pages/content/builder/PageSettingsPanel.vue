<script setup lang="ts">
import { computed, useId } from 'vue';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import { useAppStore } from '@admin/stores/app';
import type { Translations } from '@admin/types';

export interface PageForm {
    title: Translations;
    slug: string;
    status: 'draft' | 'published';
    seo: { title: Translations; description: Translations; image: number | null; noindex: boolean };
    starts_at: string | null;
    expires_at: string | null;
    focused_layout: boolean;
    tracking: { gtm_id?: string | null; facebook_pixel_id?: string | null };
}

const form = defineModel<PageForm>({ required: true });
const props = defineProps<{ type: 'default' | 'custom' | 'campaign'; isHome: boolean; errors: Record<string, string[]> }>();

const id = useId();
const app = useAppStore();
const siteHost = computed(() => app.siteUrl.replace(/^https?:\/\//, '').replace(/\/$/, ''));

const previewTitle = computed(() => form.value.seo.title[app.defaultLocale] || form.value.title[app.defaultLocale] || 'Page title');
const previewDescription = computed(() => form.value.seo.description[app.defaultLocale] || 'Add a description to control what Google shows under the title.');

function error(key: string): string | undefined {
    return props.errors[key]?.[0];
}

function slugify(): void {
    form.value.slug = form.value.slug
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

/** The date picker works with "YYYY-MM-DDTHH:mm"; the server may send seconds too. */
function local(value: string | null): string | null {
    return value ? value.slice(0, 16) : null;
}
</script>

<template>
    <div class="space-y-7">
        <section class="space-y-4">
            <h3 class="text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">Page</h3>
            <TranslatableInput v-model="form.title" label="Page title" required :errors="errors" error-key="title" hint="Shown in breadcrumbs, the browser tab and as the default heading." />

            <FormField v-if="type !== 'default'" label="Web address" :for="`${id}-slug`" :error="error('slug')" required>
                <div class="flex items-stretch overflow-hidden rounded-lg border bg-white focus-within:border-brand focus-within:ring-3 focus-within:ring-brand/12" :class="error('slug') ? 'border-red-400' : 'border-line'">
                    <span class="flex items-center border-r border-line bg-canvas px-3 text-[13px] text-ink-muted">{{ siteHost }}/</span>
                    <input :id="`${id}-slug`" v-model="form.slug" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none" spellcheck="false" @blur="slugify" />
                </div>
            </FormField>
            <p v-else class="rounded-lg bg-canvas px-3.5 py-2.5 text-[13px] text-ink-soft">
                Built-in page at <strong class="text-ink">{{ isHome ? '/' : `/${form.slug}` }}</strong>. Its address can't be changed.
            </p>

            <FormField label="Status" :for="`${id}-status`">
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="option in [{ value: 'draft', label: 'Draft', text: 'Hidden from visitors' }, { value: 'published', label: 'Published', text: type === 'campaign' ? 'Live within its dates' : 'Visible on the website' }]"
                        :key="option.value"
                        class="cursor-pointer rounded-lg border p-3 transition-colors"
                        :class="form.status === option.value ? 'border-brand bg-brand-soft' : 'border-line hover:border-[#cbd3df]'"
                    >
                        <input v-model="form.status" type="radio" :value="option.value" class="sr-only" />
                        <span class="block text-sm font-semibold" :class="form.status === option.value ? 'text-brand' : 'text-ink'">{{ option.label }}</span>
                        <span class="block text-[12px] text-ink-soft">{{ option.text }}</span>
                    </label>
                </div>
            </FormField>
        </section>

        <section v-if="type === 'campaign'" class="space-y-4">
            <h3 class="text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">Campaign</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Starts" :for="`${id}-start`" :error="error('starts_at')" hint="Empty = as soon as published.">
                    <AppDatePicker :id="`${id}-start`" mode="datetime" :model-value="local(form.starts_at)" placeholder="When published" :max-date="local(form.expires_at)" :invalid="Boolean(error('starts_at'))" @update:model-value="form.starts_at = $event || null" />
                </FormField>
                <FormField label="Expires" :for="`${id}-end`" :error="error('expires_at')" hint="Visitors are redirected afterwards.">
                    <AppDatePicker :id="`${id}-end`" mode="datetime" :model-value="local(form.expires_at)" placeholder="No end date" :min-date="local(form.starts_at)" :invalid="Boolean(error('expires_at'))" @update:model-value="form.expires_at = $event || null" />
                </FormField>
            </div>
            <div class="rounded-lg border border-line p-3.5">
                <AppSwitch v-model="form.focused_layout" label="Focused landing layout" description="Hides the main menu and footer links so visitors focus on the offer." />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Extra Tag Manager ID" :for="`${id}-gtm`" :error="error('tracking.gtm_id')" hint="Optional, in addition to the site-wide one.">
                    <input :id="`${id}-gtm`" v-model="form.tracking.gtm_id" class="control" placeholder="GTM-XXXXXXX" />
                </FormField>
                <FormField label="Extra Facebook Pixel ID" :for="`${id}-pixel`" :error="error('tracking.facebook_pixel_id')">
                    <input :id="`${id}-pixel`" v-model="form.tracking.facebook_pixel_id" class="control" inputmode="numeric" />
                </FormField>
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">Search & sharing</h3>
            <div class="rounded-xl border border-line bg-white p-4" aria-label="Google search preview">
                <p class="truncate text-[12px] text-[#4d5156]">{{ siteHost }}{{ isHome ? '' : ` › ${form.slug}` }}</p>
                <p class="mt-0.5 truncate text-[18px] leading-snug text-[#1a0dab]">{{ previewTitle }}</p>
                <p class="mt-1 line-clamp-2 text-[13px] text-[#4d5156]">{{ previewDescription }}</p>
            </div>
            <TranslatableInput v-model="form.seo.title" label="SEO title" placeholder="Defaults to the page title" :errors="errors" error-key="seo.title" hint="Up to about 60 characters." />
            <TranslatableInput v-model="form.seo.description" label="SEO description" multiline :rows="3" :errors="errors" error-key="seo.description" hint="Around 150 characters that make people want to click." />
            <MediaField v-model="form.seo.image" label="Social share image" usage="og" aspect="1200 / 630" hint="Shown when the page is shared on Facebook or WhatsApp." />
            <div class="rounded-lg border border-line p-3.5">
                <AppSwitch v-model="form.seo.noindex" label="Hide from search engines" description="Adds noindex, e.g. for thank-you or private pages." />
            </div>
        </section>
    </div>
</template>
