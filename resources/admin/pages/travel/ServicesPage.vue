<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { Eye, EyeOff, ExternalLink, Link2, Package as PackageIcon, Pencil, Plus, Plane, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import IconPicker from '@admin/pages/content/builder/IconPicker.vue';
import ServiceListItem, { type ServiceItem } from './ServiceListItem.vue';
import headerPhoto from '@admin/assets/services-header.webp';
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
    travel_date: 'none' | 'month_year' | 'hajj_year';
    seo: { title: Translations; description: Translations; image: number | null };
    packages_count: number;
    live_packages_count: number;
    image_url: string | null;
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

const filtersOpen = ref(false);
/** Filters run on the page: the list is short and keeps its drag-and-drop order. */
const filters = reactive({ visibility: '', packages: '' });
const filterGroups: FilterGroup[] = [
    {
        key: 'visibility',
        label: 'Website',
        icon: Eye,
        tone: '#34d399',
        options: [
            { value: '', label: 'Shown or hidden' },
            { value: 'shown', label: 'Shown' },
            { value: 'hidden', label: 'Hidden' },
        ],
    },
    {
        key: 'packages',
        label: 'Packages',
        icon: PackageIcon,
        tone: '#a78bfa',
        options: [
            { value: '', label: 'Any' },
            { value: 'with', label: 'Has packages' },
            { value: 'live', label: 'Has live packages' },
            { value: 'none', label: 'No packages' },
        ],
    },
];

const term = computed(() => search.value.trim().toLowerCase());
const activeFilters = computed(() => Object.values(filters).filter((v) => v !== '').length);
const filtered = computed(() => Boolean(term.value) || activeFilters.value > 0);
const canSort = computed(() => auth.can('services.update') && !filtered.value);
const matches = (service: ServiceRow) =>
    (!term.value || service.slug.toLowerCase().includes(term.value) || Object.values(service.name ?? {}).some((name) => (name ?? '').toLowerCase().includes(term.value))) &&
    (filters.visibility === '' || service.is_active === (filters.visibility === 'shown')) &&
    (filters.packages === '' ||
        (filters.packages === 'with' && service.packages_count > 0) ||
        (filters.packages === 'live' && service.live_packages_count > 0) ||
        (filters.packages === 'none' && service.packages_count === 0));
const visibleCount = computed(() => services.value.filter(matches).length);

const iconSvg = (service: ServiceRow) => icons.value.find((i) => i.name === service.icon)?.svg ?? '';
const item = (service: ServiceRow): ServiceItem => ({
    id: service.id,
    name: pickText(service.name, app.defaultLocale),
    slug: service.slug,
    url: service.url,
    summary: pickText(service.summary, app.defaultLocale),
    is_active: service.is_active,
    packages_count: service.packages_count,
    live_packages_count: service.live_packages_count,
    image_url: service.image_url,
});

function clearFilters(): void {
    filters.visibility = '';
    filters.packages = '';
}

function clearAll(): void {
    search.value = '';
    clearFilters();
}

function preview(service: ServiceRow): void {
    current.value = service;
    drawerOpen.value = true;
}

const blank = { name: {} as Translations, slug: '', summary: {} as Translations, headline: {} as Translations, highlight: {} as Translations, description: {} as Translations, icon: null as string | null, image_id: null as number | null, is_active: true, travel_date: 'none' as ServiceRow['travel_date'], seo: { title: {} as Translations, description: {} as Translations, image: null as number | null } };
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
    if (!(await confirm({ title: `Delete "${pickText(service.name)}"?`, message: 'It goes to the Trash, where a super admin can restore it.', confirmLabel: 'Delete' }))) return;
    try {
        await http.delete(`/services/${service.id}`);
        toast.success('Service deleted');
        drawerOpen.value = false;
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

/** Show or hide a service straight from its menu. */
async function toggle(service: ServiceRow): Promise<void> {
    try {
        const payload = { ...JSON.parse(JSON.stringify(service)), is_active: !service.is_active };
        await http.put(`/services/${service.id}`, payload);
        toast.success(payload.is_active ? 'Service shown on the website' : 'Service hidden from the website');
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
        <!-- Header with a Makkah photo -->
        <ListHero title="Services" description="Hajj, Umrah and other travel services. Each has its own page listing its packages." :icon="Plane" tone="#a78bfa" :photo="headerPhoto" photo-position="70% 45%">
            <template #actions>
                <AppButton v-if="auth.can('services.create')" :icon="Plus" @click="edit(null)">Add service</AppButton>
            </template>
        </ListHero>

        <!-- Search and filters in one row -->
        <ListToolbar v-model:search="search" placeholder="Search services by name or address" :active="activeFilters" @filters="filtersOpen = true" />

        <!-- Services (drag the grip to reorder) -->
        <div v-if="loading" class="grid gap-4" aria-busy="true" aria-label="Loading services">
            <div v-for="n in 2" :key="n" class="card h-[340px] animate-pulse sm:h-[136px]" />
        </div>
        <div v-else-if="!services.length || !visibleCount" class="card">
            <EmptyState
                :icon="Plane"
                :title="services.length ? 'No services match' : 'No services yet'"
                :description="services.length ? 'Try another search or clear the filters.' : 'Add Hajj, Umrah or another travel service. Packages are grouped under services.'"
            >
                <AppButton v-if="services.length" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('services.create')" :icon="Plus" @click="edit(null)">Add service</AppButton>
            </EmptyState>
        </div>
        <div v-else class="sv-list">
            <p v-if="filtered && auth.can('services.update')" class="mb-3 px-0.5 text-[13px] text-ink-muted">Clear the search and filters to drag services into a new order.</p>
            <VueDraggable v-model="services" handle=".drag" :animation="200" :disabled="!canSort" class="grid gap-4" ghost-class="sv-ghost" @end="saveOrder">
                <ServiceListItem
                    v-for="(service, i) in services"
                    v-show="matches(service)"
                    :key="service.id"
                    :service="item(service)"
                    :icon-svg="iconSvg(service)"
                    :index="i"
                    :can-sort="canSort"
                    :can-update="auth.can('services.update')"
                    :can-delete="auth.can('services.delete')"
                    @open="preview(service)"
                    @edit="edit(service)"
                    @toggle="toggle(service)"
                    @remove="remove(service)"
                />
            </VueDraggable>
        </div>

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="filters"
            :groups="filterGroups"
            :total="visibleCount"
            :noun="['service', 'services']"
            :can-clear="activeFilters > 0"
            @clear="clearFilters"
        />

        <!-- Preview and actions -->
        <AppDrawer v-model:open="drawerOpen" :title="current ? pickText(current.name, app.defaultLocale) : ''" :description="current ? `/${current.slug}` : ''" :icon="Plane">
            <template v-if="current">
                <img v-if="current.image_url" :src="current.image_url" alt="" class="mb-5 aspect-[16/7] w-full rounded-2xl object-cover" />
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
                    <dd>{{ current.packages_count }} · {{ current.live_packages_count }} live on the website</dd>
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
                <FormField label="Booking form asks for" for="service-travel-date" :error="form.error('travel_date')" hint="When the pilgrim wants to travel. The Hajj year starts from the first Hajj still open for registration.">
                    <select id="service-travel-date" v-model="form.data.travel_date" class="control">
                        <option value="none">Nothing</option>
                        <option value="month_year">Travel month and year (Umrah)</option>
                        <option value="hajj_year">Hajj year (Hajj)</option>
                    </select>
                </FormField>
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

<style scoped>
/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.sv-list {
    container: sv-list / inline-size;
}

/* The spot a dragged service will drop into. */
.sv-list :deep(.sv-ghost) {
    opacity: 0.45;
    outline: 2px dashed color-mix(in oklab, #a78bfa 60%, white);
    outline-offset: -2px;
}
</style>
