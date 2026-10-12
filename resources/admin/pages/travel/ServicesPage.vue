<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ExternalLink, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import IconPicker from '@admin/pages/content/builder/IconPicker.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';

interface ServiceRow {
    id: number;
    name: Translations;
    slug: string;
    url: string;
    summary: Translations;
    headline: Translations;
    highlight: Translations;
    description: Translations;
    icon: string | null;
    image_id: number | null;
    is_active: boolean;
    seo: { title: Translations; description: Translations; image: number | null };
    packages_count: number;
}

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const services = ref<ServiceRow[]>([]);
const icons = ref<{ name: string; svg: string }[]>([]);
const loading = ref(true);
const editing = ref<ServiceRow | null>(null);
const open = ref(false);
let slugTouched = false;

const blank = { name: {} as Translations, slug: '', summary: {} as Translations, headline: {} as Translations, highlight: {} as Translations, description: {} as Translations, icon: null as string | null, image_id: null as number | null, is_active: true, seo: { title: {} as Translations, description: {} as Translations, image: null as number | null } };
const form = useForm({ ...blank });

async function load(): Promise<void> {
    try {
        const [list, schema] = await Promise.all([
            http.get<{ data: ServiceRow[] }>('/services'),
            icons.value.length ? Promise.resolve(null) : http.get<{ icons: { name: string; svg: string }[] }>('/packages/schema'),
        ]);
        services.value = list.data.data;
        if (schema) icons.value = schema.data.icons;
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

function slugify(value: string): string {
    return value.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

function edit(service: ServiceRow | null): void {
    editing.value = service;
    slugTouched = !!service;
    form.reset(service ? JSON.parse(JSON.stringify({ ...blank, ...service })) : { ...blank });
    open.value = true;
}

watch(() => form.data.name.en, (name) => {
    if (!slugTouched) form.data.slug = slugify(name ?? '');
});

async function save(): Promise<void> {
    const payload = { ...form.data };
    const response = await form.submit(() =>
        editing.value ? http.put(`/services/${editing.value.id}`, payload) : http.post('/services', payload),
    );
    if (response) {
        open.value = false;
        toast.success(editing.value ? 'Service updated' : 'Service added');
        load();
    }
}

async function remove(service: ServiceRow): Promise<void> {
    if (!(await confirm({ title: `Delete "${pickText(service.name)}"?`, message: 'This cannot be undone.', confirmLabel: 'Delete' }))) return;
    try {
        await http.delete(`/services/${service.id}`);
        toast.success('Service deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function saveOrder(): Promise<void> {
    try {
        await http.put('/services/reorder', { ids: services.value.map((s) => s.id) });
        toast.success('Order saved');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader title="Services" description="Hajj, Umrah and other travel services. Each has its own page listing its packages.">
            <template #actions>
                <AppButton v-if="auth.can('services.create')" :icon="Plus" @click="edit(null)">Add service</AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <VueDraggable v-else v-model="services" handle=".drag" :animation="160" :disabled="!auth.can('services.update')" class="space-y-3" @end="saveOrder">
            <div v-for="service in services" :key="service.id" class="card flex items-center gap-3 p-3 sm:p-4">
                <button v-if="auth.can('services.update')" type="button" class="drag flex size-8 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas" aria-label="Drag to reorder"><GripVertical class="size-4" /></button>
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand [&_svg]:size-5" v-html="icons.find((i) => i.name === service.icon)?.svg ?? ''" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-semibold text-ink">
                        {{ pickText(service.name, app.defaultLocale) }}
                        <AppBadge v-if="!service.is_active" tone="neutral">Hidden</AppBadge>
                    </p>
                    <a :href="service.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[12px] text-ink-muted hover:text-brand">/{{ service.slug }} <ExternalLink class="size-3" /></a>
                </div>
                <span class="hidden text-[13px] text-ink-soft sm:block">{{ service.packages_count }} packages</span>
                <button v-if="auth.can('services.update')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-brand" :aria-label="`Edit ${pickText(service.name)}`" @click="edit(service)"><Pencil class="size-4" /></button>
                <button v-if="auth.can('services.delete')" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${pickText(service.name)}`" @click="remove(service)"><Trash2 class="size-4" /></button>
            </div>
        </VueDraggable>

        <AppModal v-model:open="open" :title="editing ? 'Edit service' : 'Add service'" size="lg" :persistent="form.processing">
            <form id="service-form" class="space-y-4" novalidate @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <TranslatableInput v-model="form.data.name" label="Name" required :errors="form.errors" error-key="name" />
                    <FormField label="Web address" for="service-slug" :error="form.error('slug')" required>
                        <div class="flex items-stretch overflow-hidden rounded-lg border border-line bg-white focus-within:border-brand">
                            <span class="flex items-center border-r border-line bg-canvas px-3 text-[13px] text-ink-muted">/</span>
                            <input id="service-slug" v-model="form.data.slug" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none" @input="slugTouched = true" @blur="form.data.slug = slugify(form.data.slug)" />
                        </div>
                    </FormField>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <TranslatableInput v-model="form.data.headline" label="Hero headline" :errors="form.errors" error-key="headline" hint="Large title at the top of the service page. Empty: the service name." />
                    <TranslatableInput v-model="form.data.highlight" label="Highlight line" :errors="form.errors" error-key="highlight" hint="Shown in gold italics under the headline." />
                </div>
                <TranslatableInput v-model="form.data.summary" label="Short summary" multiline :rows="2" :errors="form.errors" error-key="summary" hint="Shown under the headline on the service page." />
                <TranslatableInput v-model="form.data.description" label="Description" multiline :rows="5" :errors="form.errors" error-key="description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <IconPicker v-model="form.data.icon" label="Icon" :icons="icons" />
                    <MediaField v-model="form.data.image_id" label="Banner image" usage="banner" aspect="16 / 6" />
                </div>
                <div class="rounded-lg border border-line p-3.5">
                    <AppSwitch v-model="form.data.is_active" label="Show on the website" description="Hidden services and their packages are not visible to visitors." />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <TranslatableInput v-model="form.data.seo.title" label="SEO title" :errors="form.errors" error-key="seo.title" />
                    <TranslatableInput v-model="form.data.seo.description" label="SEO description" :errors="form.errors" error-key="seo.description" />
                    <MediaField v-model="form.data.seo.image" label="Social share image" usage="og" aspect="1200 / 630" hint="Shown when the page is shared on Facebook or WhatsApp. Defaults to the banner." />
                </div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
                <AppButton type="submit" form="service-form" :loading="form.processing">Save</AppButton>
            </template>
        </AppModal>
    </div>
</template>
