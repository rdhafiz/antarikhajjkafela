<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ChevronRight, Eye, EyeOff, ExternalLink, GripVertical, Link2, Package as PackageIcon, Pencil, Plus, Plane, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterBar from '@admin/components/ui/FilterBar.vue';
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

const search = ref('');
const current = ref<ServiceRow | null>(null);
const drawerOpen = ref(false);

const term = computed(() => search.value.trim().toLowerCase());
const canSort = computed(() => auth.can('services.update') && !term.value);
const matches = (service: ServiceRow) =>
    !term.value || service.slug.toLowerCase().includes(term.value) || Object.values(service.name ?? {}).some((name) => (name ?? '').toLowerCase().includes(term.value));
const visibleCount = computed(() => services.value.filter(matches).length);
const summary = computed(() => {
    const total = `${services.value.length} ${services.value.length === 1 ? 'service' : 'services'}`;
    return term.value ? `${visibleCount.value} of ${total}` : total;
});

function preview(service: ServiceRow): void {
    current.value = service;
    drawerOpen.value = true;
}

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
    drawerOpen.value = false;
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
        drawerOpen.value = false;
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

        <FilterBar v-model:search="search" placeholder="Search services by name or address" :summary="services.length ? summary : ''" />

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>
        <div v-else-if="!services.length || !visibleCount" class="card">
            <EmptyState
                :icon="Plane"
                :title="services.length ? 'No services match' : 'No services yet'"
                :description="services.length ? 'Try another search.' : 'Add Hajj, Umrah or another travel service. Packages are grouped under services.'"
            >
                <AppButton v-if="services.length" variant="secondary" @click="search = ''">Clear search</AppButton>
                <AppButton v-else-if="auth.can('services.create')" :icon="Plus" @click="edit(null)">Add service</AppButton>
            </EmptyState>
        </div>
        <template v-else>
            <p v-if="term && auth.can('services.update')" class="mb-3 px-0.5 text-[13px] text-ink-muted">Clear the search to drag services into a new order.</p>
            <VueDraggable v-model="services" handle=".drag" :animation="160" :disabled="!canSort" class="space-y-3" @end="saveOrder">
                <div
                    v-for="service in services"
                    v-show="matches(service)"
                    :key="service.id"
                    class="card flex cursor-pointer items-center gap-3 p-3 transition-shadow hover:shadow-md sm:p-4"
                    @click="preview(service)"
                >
                    <button v-if="canSort" type="button" class="drag flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas" aria-label="Drag to reorder" @click.stop><GripVertical class="size-4" /></button>
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand [&_svg]:size-5" aria-hidden="true" v-html="icons.find((i) => i.name === service.icon)?.svg ?? ''" />
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-ink">
                            <span class="min-w-0 truncate">{{ pickText(service.name, app.defaultLocale) }}</span>
                            <AppBadge v-if="!service.is_active" tone="neutral">Hidden</AppBadge>
                        </p>
                        <p class="truncate text-[12.5px] text-ink-muted">/{{ service.slug }}<span class="sm:hidden"> · {{ service.packages_count }} packages</span></p>
                    </div>
                    <span class="hidden shrink-0 text-[13px] text-ink-soft sm:block">{{ service.packages_count }} packages</span>
                    <button type="button" class="dt-open shrink-0" :aria-label="`Open ${pickText(service.name, app.defaultLocale)}`" @click.stop="preview(service)"><ChevronRight class="size-4" /></button>
                </div>
            </VueDraggable>
        </template>

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? pickText(current.name, app.defaultLocale) : ''" :description="current ? `/${current.slug}` : ''" :icon="Plane">
            <template v-if="current">
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="current.is_active ? 'success' : 'neutral'">
                        <component :is="current.is_active ? Eye : EyeOff" class="size-3" />{{ current.is_active ? 'Shown on the website' : 'Hidden' }}
                    </AppBadge>
                </div>

                <p v-if="pickText(current.summary, app.defaultLocale)" class="mt-4 text-[14px] text-ink-soft">{{ pickText(current.summary, app.defaultLocale) }}</p>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2"><Link2 class="size-4 text-sky-600" />Address</dt>
                    <dd class="break-all">/{{ current.slug }}</dd>
                    <dt class="flex items-center gap-2"><PackageIcon class="size-4 text-violet-600" />Packages</dt>
                    <dd>{{ current.packages_count }}</dd>
                    <dt class="flex items-center gap-2"><Eye class="size-4 text-emerald-600" />Visible</dt>
                    <dd>{{ current.is_active ? 'Yes' : 'No' }}</dd>
                </dl>

                <p class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Actions</p>
                <div class="-mx-1 space-y-1">
                    <button v-if="auth.can('services.update')" type="button" class="dw-action" @click="edit(current)">
                        <span class="dw-action-icon" style="--tone: #fbbf24"><Pencil class="size-4" /></span>Edit service
                    </button>
                    <a :href="current.url" target="_blank" rel="noopener" class="dw-action">
                        <span class="dw-action-icon" style="--tone: #38bdf8"><ExternalLink class="size-4" /></span>View on the website
                    </a>
                    <button v-if="auth.can('services.delete')" type="button" class="dw-action is-danger" @click="remove(current)">
                        <span class="dw-action-icon"><Trash2 class="size-4" /></span>Delete service
                    </button>
                </div>
            </template>
        </AppDrawer>

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
