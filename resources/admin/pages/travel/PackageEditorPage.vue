<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { ArrowLeft, ExternalLink, FileText, LayoutList, Plane, Search, Wallet } from 'lucide-vue-next';
import AppDatePicker from '@admin/components/ui/AppDatePicker.vue';
import TabSwitcher from '@admin/components/ui/TabSwitcher.vue';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import FieldList from '@admin/pages/content/builder/FieldList.vue';
import { pickText, type FieldDef } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http, validationErrors, type ValidationErrors } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface Schema {
    flights: FieldDef[];
    types: FieldDef[];
    sections: FieldDef[];
    services: { id: number; name: Translations; is_active: boolean }[];
    advance_types: Record<string, string>;
    icons: { name: string; svg: string }[];
}

type PackageData = Record<string, unknown> & {
    id: number;
    name: Translations;
    slug: string;
    url: string;
    status: 'draft' | 'published';
    booking_state: 'open' | 'upcoming' | 'closed';
    seo: { title: Translations; description: Translations; image: number | null; noindex: boolean };
};

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const schema = ref<Schema | null>(null);
const data = ref<PackageData | null>(null);
const errors = ref<ValidationErrors>({});
const saving = ref(false);
const saved = ref('');
const tab = ref<'details' | 'prices' | 'flights' | 'sections' | 'seo'>('details');

const canEdit = computed(() => auth.can('packages.update'));
const dirty = computed(() => saved.value !== '' && JSON.stringify(data.value) !== saved.value);

const tabs = [
    { key: 'details', label: 'Details', match: /^(service_id|name|summary|banner_id|booking_|duration|status|is_featured|advance_)/ },
    { key: 'prices', label: 'Types, prices & hotels', match: /^types/ },
    { key: 'flights', label: 'Flights', match: /^flights/ },
    { key: 'sections', label: 'Page sections', match: /^sections/ },
    { key: 'seo', label: 'SEO', match: /^seo/ },
] as const;

const tabIcons = { details: [FileText, '#60a5fa'], prices: [Wallet, '#34d399'], flights: [Plane, '#38bdf8'], sections: [LayoutList, '#a78bfa'], seo: [Search, '#fbbf24'] } as const;
const tabOptions = computed(() => tabs.map((t) => ({ value: t.key, label: t.label, icon: tabIcons[t.key][0], tone: tabIcons[t.key][1], alert: tabHasError(t.match) })));

function tabHasError(match: RegExp): boolean {
    return Object.keys(errors.value).some((k) => match.test(k));
}

function apply(payload: PackageData): void {
    data.value = JSON.parse(JSON.stringify(payload));
    saved.value = JSON.stringify(data.value);
}

async function load(): Promise<void> {
    try {
        const [pkg, meta] = await Promise.all([
            http.get<{ data: PackageData }>(`/packages/${route.params.id}`),
            http.get<Schema>('/packages/schema'),
        ]);
        schema.value = meta.data;
        apply(pkg.data.data);
    } catch (e) {
        toast.error(errorMessage(e));
        router.replace('/admin/packages');
    }
}

async function save(): Promise<boolean> {
    if (!data.value) return false;
    saving.value = true;
    errors.value = {};

    try {
        const response = await http.put<{ data: PackageData }>(`/packages/${data.value.id}`, data.value);
        const slugChanged = response.data.data.slug !== data.value.slug;
        apply(response.data.data);
        toast.success('Package saved', slugChanged ? 'The web address changed; the old one redirects automatically.' : undefined);
        return true;
    } catch (e) {
        const validation = validationErrors(e);
        if (validation) {
            errors.value = validation;
            const first = Object.keys(validation)[0] ?? '';
            const target = tabs.find((t) => t.match.test(first));
            if (target) tab.value = target.key;
            toast.error('Please fix the highlighted fields', validation[first]?.[0]);
        } else {
            toast.error('Could not save the package', errorMessage(e));
        }
        return false;
    } finally {
        saving.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        if (canEdit.value && !saving.value) save();
    }
}

function beforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) event.preventDefault();
}

onBeforeRouteLeave(async () => !dirty.value || confirm({ title: 'Leave without saving?', message: 'Your changes to this package have not been saved.', confirmLabel: 'Leave' }));

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', beforeUnload);
    load();
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', beforeUnload);
});

const bookingTone = { open: 'success', upcoming: 'warning', closed: 'danger' } as const;
</script>

<template>
    <div v-if="!data || !schema" class="flex justify-center py-24 text-brand"><AppSpinner class="size-6" /></div>

    <div v-else>
        <div class="sticky top-16 z-20 -mx-4 mb-6 border-b border-line bg-canvas/95 px-4 pt-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center gap-3">
                <RouterLink to="/admin/packages" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white hover:text-ink" aria-label="Back to packages"><ArrowLeft class="size-5" /></RouterLink>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-[17px] font-bold text-ink">{{ pickText(data.name, app.defaultLocale) || 'Untitled package' }}</h1>
                        <AppBadge :tone="data.status === 'published' ? 'success' : 'neutral'">{{ data.status }}</AppBadge>
                        <AppBadge :tone="bookingTone[data.booking_state]">booking {{ data.booking_state }}</AppBadge>
                    </div>
                    <a :href="data.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[12px] text-ink-muted hover:text-brand">/packages/{{ data.slug }} <ExternalLink class="size-3" /></a>
                </div>
                <span v-if="dirty" class="hidden text-[13px] font-semibold text-amber-700 sm:inline">Unsaved changes</span>
                <AppButton v-if="canEdit" :loading="saving" :disabled="!dirty" @click="save">Save</AppButton>
            </div>
            <TabSwitcher v-model="tab" :options="tabOptions" label="Package sections" variant="underline" class="mt-3 pb-3 md:pb-0" />
        </div>

        <fieldset :disabled="!canEdit" class="card p-6">
            <!-- Details -->
            <div v-show="tab === 'details'" class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Service" for="pkg-service" :error="errors.service_id?.[0]" required>
                            <select id="pkg-service" v-model="data.service_id" class="control">
                                <option v-for="service in schema.services" :key="service.id" :value="service.id">{{ pickText(service.name, app.defaultLocale) }}{{ service.is_active ? '' : ' (hidden)' }}</option>
                            </select>
                        </FormField>
                        <TranslatableInput v-model="data.name" label="Package name" required :errors="errors" error-key="name" hint="The web address follows the English name." />
                    </div>
                    <TranslatableInput v-model="(data.summary as Translations)" label="Card summary" multiline :rows="2" :errors="errors" error-key="summary" hint="One or two lines shown on package cards and at the top of the page." />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField label="Booking opens" for="pkg-start" :error="errors.booking_starts_on?.[0]" hint="Empty = open now.">
                            <AppDatePicker id="pkg-start" v-model="(data.booking_starts_on as string | null)" placeholder="Open now" :max-date="data.booking_ends_on as string | null" :invalid="!!errors.booking_starts_on" />
                        </FormField>
                        <FormField label="Booking closes" for="pkg-end" :error="errors.booking_ends_on?.[0]" hint="After this date it shows 'Booking closed'.">
                            <AppDatePicker id="pkg-end" v-model="(data.booking_ends_on as string | null)" placeholder="No closing date" :min-date="data.booking_starts_on as string | null" :invalid="!!errors.booking_ends_on" />
                        </FormField>
                        <FormField label="Duration (days)" for="pkg-days" :error="errors.duration_days?.[0]">
                            <input id="pkg-days" v-model.number="data.duration_days" type="number" min="1" max="365" class="control" />
                        </FormField>
                    </div>

                    <fieldset class="rounded-xl border border-line p-4">
                        <legend class="px-1 text-[13px] font-semibold text-ink">Advance payment</legend>
                        <p class="-mt-1 mb-3 text-[12px] text-ink-muted">What customers pay online when they book (used by the booking system).</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField label="Type" for="pkg-adv-type">
                                <select id="pkg-adv-type" v-model="data.advance_type" class="control">
                                    <option v-for="(label, value) in schema.advance_types" :key="value" :value="value">{{ label }}</option>
                                </select>
                            </FormField>
                            <FormField v-if="data.advance_type !== 'none'" :label="data.advance_type === 'percentage' ? 'Percentage (%)' : 'Amount per traveler (BDT)'" for="pkg-adv" :error="errors.advance_value?.[0]">
                                <input id="pkg-adv" v-model.number="data.advance_value" type="number" min="1" :max="data.advance_type === 'percentage' ? 100 : undefined" class="control" />
                            </FormField>
                        </div>
                    </fieldset>
                </div>

                <div class="space-y-5">
                    <FormField label="Status" for="pkg-status">
                        <div class="grid grid-cols-2 gap-2">
                            <label v-for="option in [{ v: 'draft', l: 'Draft', t: 'Hidden' }, { v: 'published', l: 'Published', t: 'On the website' }]" :key="option.v"
                                   class="cursor-pointer rounded-lg border p-3" :class="data.status === option.v ? 'border-brand bg-brand-soft' : 'border-line'">
                                <input v-model="data.status" type="radio" :value="option.v" class="sr-only" />
                                <span class="block text-sm font-semibold" :class="data.status === option.v ? 'text-brand' : 'text-ink'">{{ option.l }}</span>
                                <span class="block text-[12px] text-ink-soft">{{ option.t }}</span>
                            </label>
                        </div>
                        <p v-if="errors.types?.[0]" class="mt-1.5 text-[13px] font-medium text-red-600">{{ errors.types[0] }}</p>
                    </FormField>
                    <div class="rounded-lg border border-line p-3.5">
                        <AppSwitch v-model="(data.is_featured as boolean)" label="Featured" description="Shown first and in 'featured packages' sections." />
                    </div>
                    <MediaField v-model="(data.banner_id as number | null)" label="Banner image" usage="banner" aspect="16 / 6" hint="Top of the package page and on package cards." />
                </div>
            </div>

            <!-- Types, prices & hotels -->
            <div v-show="tab === 'prices'">
                <p class="mb-5 rounded-xl bg-canvas px-4 py-3 text-[13px] text-ink-soft">
                    Each <strong class="text-ink">package type</strong> (e.g. Premium, Standard) has one or more <strong class="text-ink">pricing options</strong> (e.g. room sharing), and each option lists its <strong class="text-ink">hotel details</strong>. The lowest price becomes "Starting from".
                </p>
                <FieldList v-model="data" :fields="schema.types" :errors="errors" path="" :icons="schema.icons" />
            </div>

            <!-- Flights -->
            <div v-show="tab === 'flights'">
                <FieldList v-model="data" :fields="schema.flights" :errors="errors" path="" :icons="schema.icons" />
            </div>

            <!-- Page sections -->
            <div v-show="tab === 'sections'">
                <p class="mb-5 rounded-xl bg-canvas px-4 py-3 text-[13px] text-ink-soft">
                    Sections appear on the package page below the prices and flights, such as what's included, the itinerary or important notes.
                    Add text, tables, lists, images or videos inside each section and drag them into order.
                </p>
                <FieldList v-model="data" :fields="schema.sections" :errors="errors" path="" :icons="schema.icons" />
            </div>

            <!-- SEO -->
            <div v-show="tab === 'seo'" class="max-w-2xl space-y-4">
                <TranslatableInput v-model="data.seo.title" label="SEO title" placeholder="Defaults to the package name" :errors="errors" error-key="seo.title" />
                <TranslatableInput v-model="data.seo.description" label="SEO description" multiline :rows="3" placeholder="Defaults to the card summary" :errors="errors" error-key="seo.description" />
                <MediaField v-model="data.seo.image" label="Social share image" usage="og" aspect="1200 / 630" hint="Defaults to the banner." />
                <div class="rounded-lg border border-line p-3.5">
                    <AppSwitch v-model="data.seo.noindex" label="Hide from search engines" />
                </div>
            </div>
        </fieldset>
    </div>
</template>
