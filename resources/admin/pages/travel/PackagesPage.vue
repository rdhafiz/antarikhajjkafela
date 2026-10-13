<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
    CalendarClock,
    Copy,
    ExternalLink,
    LayoutGrid,
    Layers,
    List,
    Package as PackageIcon,
    Pencil,
    Plus,
    Star,
    Trash2,
    Wallet,
    X,
} from "lucide-vue-next";
import AppBadge from "@admin/components/ui/AppBadge.vue";
import AppButton from "@admin/components/ui/AppButton.vue";
import AppDrawer from "@admin/components/ui/AppDrawer.vue";
import AppModal from "@admin/components/ui/AppModal.vue";
import AppPagination from "@admin/components/ui/AppPagination.vue";
import EmptyState from "@admin/components/ui/EmptyState.vue";
import FilterBar from "@admin/components/ui/FilterBar.vue";
import FormField from "@admin/components/ui/FormField.vue";
import TranslatableInput from "@admin/components/ui/TranslatableInput.vue";
import PackageListItem, {
    type PackageRow as ItemRow,
} from "./PackageListItem.vue";
import banner from "@admin/assets/packages-banner.webp";
import { pickText } from "@admin/pages/content/builder/types";
import { useConfirm } from "@admin/composables/useConfirm";
import { useForm } from "@admin/composables/useForm";
import { useListQuery } from "@admin/composables/useListQuery";
import { errorMessage, http } from "@admin/lib/http";
import { formatDate } from "@admin/lib/format";
import { useAppStore } from "@admin/stores/app";
import { useAuthStore } from "@admin/stores/auth";
import { useToastStore } from "@admin/stores/toast";
import type { Translations } from "@admin/types";

interface PackageRow {
    id: number;
    name: Translations;
    slug: string;
    url: string;
    service: Translations | null;
    service_id?: number;
    status: "draft" | "published";
    is_featured: boolean;
    booking_state: "open" | "upcoming" | "closed";
    booking_ends_on: string | null;
    duration_days: number | null;
    min_price: number | null;
    types_count: number;
    banner_url: string | null;
    prices_count: number;
    hotels_count: number;
    flights_count: number;
    sections_count: number;
    updated_at: string;
}

const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = useListQuery<PackageRow>("/packages", {
    service_id: "",
    status: "",
    featured: "",
});
const services = ref<{ id: number; name: Translations }[]>([]);

const current = ref<PackageRow | null>(null);
const drawerOpen = ref(false);
const createOpen = ref(false);
const createForm = useForm({
    service_id: "" as number | "",
    name: {} as Translations,
});

// List or grid, remembered on this device.
const VIEW_KEY = "ahk.packages.view";
const view = ref<"list" | "grid">("list");
try {
    if (localStorage.getItem(VIEW_KEY) === "grid") view.value = "grid";
} catch {
    // Storage can be blocked; the list view is the default.
}
watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Not remembered, which is fine.
    }
});

const money = new Intl.NumberFormat("en-IN");
const bookingTone = {
    open: "success",
    upcoming: "warning",
    closed: "danger",
} as const;
const bookingLabel = {
    open: "Booking open",
    upcoming: "Opens soon",
    closed: "Booking closed",
} as const;
const price = (value: number | null) =>
    value !== null ? `৳${money.format(value)}` : "—";
const filtered = computed(() =>
    Boolean(list.filters.search || list.active.value),
);
const total = computed(() => list.meta.value?.total ?? 0);

/** Price-box colour per service, so packages of one service share a colour. */
const serviceTones = [
    "#34d399",
    "#38bdf8",
    "#fb923c",
    "#a78bfa",
    "#f472b6",
    "#fbbf24",
];
function toneFor(row: PackageRow): string {
    const name = pickText(row.service, app.defaultLocale);
    const index = services.value.findIndex(
        (s) => pickText(s.name, app.defaultLocale) === name,
    );
    return serviceTones[(index < 0 ? 0 : index) % serviceTones.length];
}

const item = (row: PackageRow): ItemRow => ({
    ...row,
    name: pickText(row.name, app.defaultLocale),
    service: row.service ? pickText(row.service, app.defaultLocale) : null,
});

function open(row: PackageRow): void {
    current.value = row;
    drawerOpen.value = true;
}

function openCreate(): void {
    createForm.reset({ service_id: services.value[0]?.id ?? "", name: {} });
    createOpen.value = true;
}

async function create(): Promise<void> {
    const response = await createForm.submit(() =>
        http.post<{ data: { id: number } }>("/packages", createForm.data),
    );
    if (response) {
        createOpen.value = false;
        router.push({
            name: "package-editor",
            params: { id: response.data.data.id },
        });
    }
}

async function duplicate(row: PackageRow): Promise<void> {
    try {
        await http.post(`/packages/${row.id}/duplicate`);
        toast.success("Copy created as a draft");
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(row: PackageRow): Promise<void> {
    if (
        !(await confirm({
            title: `Delete "${pickText(row.name)}"?`,
            message:
                "Its prices and page content will be deleted. This cannot be undone.",
            confirmLabel: "Delete package",
        }))
    )
        return;
    try {
        await http.delete(`/packages/${row.id}`);
        toast.success("Package deleted");
        drawerOpen.value = false;
        list.load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function clearAll(): void {
    list.clear();
    list.filters.search = "";
}

onMounted(async () => {
    list.load();
    try {
        services.value = (
            await http.get<{ services: { id: number; name: Translations }[] }>(
                "/packages/schema",
            )
        ).data.services;
    } catch {
        // The service filter is optional.
    }
});
</script>

<template>
    <div>
        <!-- Banner header -->
        <header
            class="pk-hero relative mb-5 overflow-hidden rounded-[var(--radius-card)] border border-line"
        >
            <img :src="banner" alt="" class="pk-hero-img" aria-hidden="true" />
            <div
                class="relative flex flex-wrap items-center gap-x-5 gap-y-4 px-5 py-6 sm:px-7 sm:py-7"
            >
                <span
                    class="ph-tile flex size-14 shrink-0 items-center justify-center rounded-2xl sm:size-16"
                    style="--tone: #fbbf24"
                    aria-hidden="true"
                >
                    <PackageIcon class="size-7" />
                </span>
                <div class="min-w-0 flex-1 basis-64">
                    <h1
                        class="text-[26px] leading-tight font-bold tracking-[-0.01em] text-ink sm:text-[30px]"
                    >
                        Packages
                    </h1>
                    <p class="mt-1 max-w-md text-[14.5px] text-ink-soft">
                        Hajj and Umrah packages with their types, prices,
                        hotels, flights and page content.
                    </p>
                </div>
                <AppButton
                    v-if="auth.can('packages.create')"
                    :icon="Plus"
                    class="pk-new"
                    @click="openCreate"
                    >New package</AppButton
                >
            </div>
        </header>

        <!-- Search and filters -->
        <FilterBar
            v-model:search="list.filters.search as string"
            placeholder="Search packages"
            :active="0"
            class="pk-filters"
        >
            <label class="pk-select">
                <span class="sr-only">Filter by service</span>
                <LayoutGrid
                    class="pk-select-icon text-sky-600"
                    aria-hidden="true"
                />
                <select v-model="list.filters.service_id" class="control">
                    <option value="">All services</option>
                    <option
                        v-for="service in services"
                        :key="service.id"
                        :value="String(service.id)"
                    >
                        {{ pickText(service.name, app.defaultLocale) }}
                    </option>
                </select>
            </label>
            <label class="pk-select">
                <span class="sr-only">Filter by status</span>
                <Layers
                    class="pk-select-icon text-violet-600"
                    aria-hidden="true"
                />
                <select v-model="list.filters.status" class="control">
                    <option value="">Any status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </label>
            <label class="pk-select">
                <span class="sr-only">Filter featured packages</span>
                <Star class="pk-select-icon text-rose-500" aria-hidden="true" />
                <select v-model="list.filters.featured" class="control">
                    <option value="">Featured or not</option>
                    <option value="yes">Featured only</option>
                    <option value="no">Not featured</option>
                </select>
            </label>
        </FilterBar>

        <!-- Count, clear and view switch -->
        <div class="mb-3.5 flex items-center justify-between gap-3">
            <p
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[14px] text-ink-soft"
                aria-live="polite"
            >
                <span v-if="list.meta.value"
                    ><strong class="font-semibold text-ink">{{ total }}</strong>
                    {{ total === 1 ? "package" : "packages" }}</span
                >
                <button
                    v-if="filtered"
                    type="button"
                    class="inline-flex items-center gap-1 text-[13px] font-semibold text-brand hover:underline"
                    @click="clearAll"
                >
                    <X class="size-3.5" aria-hidden="true" />Clear filters
                </button>
            </p>
            <div
                class="pk-views flex rounded-xl border border-line bg-white p-1"
                role="group"
                aria-label="Show packages as"
            >
                <button
                    type="button"
                    class="pk-view"
                    :class="{ 'is-on': view === 'grid' }"
                    :aria-pressed="view === 'grid'"
                    aria-label="Grid"
                    title="Grid"
                    @click="view = 'grid'"
                >
                    <LayoutGrid class="size-[18px]" />
                </button>
                <button
                    type="button"
                    class="pk-view"
                    :class="{ 'is-on': view === 'list' }"
                    :aria-pressed="view === 'list'"
                    aria-label="List"
                    title="List"
                    @click="view = 'list'"
                >
                    <List class="size-[18px]" />
                </button>
            </div>
        </div>

        <!-- Packages -->
        <div
            v-if="list.loading.value && !list.rows.value.length"
            class="grid gap-4"
            :class="{ 'sm:grid-cols-2 xl:grid-cols-3': view === 'grid' }"
            aria-busy="true"
            aria-label="Loading packages"
        >
            <div
                v-for="n in 3"
                :key="n"
                class="card animate-pulse"
                :class="
                    view === 'list' ? 'h-[300px] lg:h-[134px]' : 'h-[360px]'
                "
            />
        </div>
        <div v-else-if="!list.rows.value.length" class="card">
            <EmptyState
                :icon="PackageIcon"
                :title="filtered ? 'No packages match' : 'No packages yet'"
                :description="
                    filtered
                        ? 'Try another search or clear the filters.'
                        : 'Create your first Hajj or Umrah package.'
                "
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll"
                    >Clear filters</AppButton
                >
                <AppButton
                    v-else-if="auth.can('packages.create')"
                    :icon="Plus"
                    @click="openCreate"
                    >New package</AppButton
                >
            </EmptyState>
        </div>
        <div
            v-else
            :key="view"
            class="pk-list"
            :class="{ 'opacity-60 transition-opacity': list.loading.value }"
        >
            <div class="pk-grid" :class="`is-${view}`">
                <PackageListItem
                    v-for="(row, i) in list.rows.value"
                    :key="row.id"
                    :row="item(row)"
                    :layout="view"
                    :tone="toneFor(row)"
                    :index="i"
                    :can-create="auth.can('packages.create')"
                    :can-delete="auth.can('packages.delete')"
                    @open="open(row)"
                    @duplicate="duplicate(row)"
                    @remove="remove(row)"
                />
            </div>
        </div>

        <AppPagination
            v-if="list.meta.value && list.rows.value.length"
            :meta="list.meta.value"
            label="packages"
            @change="list.goTo"
            @per-page="list.setPerPage"
        />

        <!-- Preview and actions -->
        <AppDrawer
            v-model:open="drawerOpen"
            :title="current ? pickText(current.name, app.defaultLocale) : ''"
            :description="
                current
                    ? (pickText(current.service, app.defaultLocale) ?? '')
                    : ''
            "
            :icon="PackageIcon"
        >
            <template v-if="current">
                <img
                    v-if="current.banner_url"
                    :src="current.banner_url"
                    alt=""
                    class="mb-5 aspect-[16/9] w-full rounded-2xl object-cover"
                />
                <div class="flex flex-wrap gap-2">
                    <AppBadge
                        :tone="
                            current.status === 'published'
                                ? 'success'
                                : 'neutral'
                        "
                        >{{
                            current.status === "published"
                                ? "Published"
                                : "Draft"
                        }}</AppBadge
                    >
                    <AppBadge :tone="bookingTone[current.booking_state]">{{
                        bookingLabel[current.booking_state]
                    }}</AppBadge>
                    <AppBadge v-if="current.is_featured" tone="gold"
                        ><Star class="size-3 fill-current" />Featured</AppBadge
                    >
                </div>

                <dl class="dl mt-5 rounded-2xl bg-canvas p-4">
                    <dt class="flex items-center gap-2">
                        <Wallet class="size-4 text-emerald-600" />From
                    </dt>
                    <dd>{{ price(current.min_price) }}</dd>
                    <dt class="flex items-center gap-2">
                        <Layers class="size-4 text-violet-600" />Types
                    </dt>
                    <dd>
                        {{ current.types_count }} types ·
                        {{ current.prices_count }} room options
                    </dd>
                    <dt class="flex items-center gap-2">
                        <CalendarClock class="size-4 text-sky-600" />Duration
                    </dt>
                    <dd>
                        {{
                            current.duration_days
                                ? `${current.duration_days} days`
                                : "—"
                        }}
                    </dd>
                    <dt>Includes</dt>
                    <dd>
                        {{ current.hotels_count }} hotels ·
                        {{ current.flights_count }} flights ·
                        {{ current.sections_count }} page sections
                    </dd>
                    <template v-if="current.booking_ends_on">
                        <dt>Booking until</dt>
                        <dd>{{ formatDate(current.booking_ends_on) }}</dd>
                    </template>
                    <dt>Updated</dt>
                    <dd>{{ formatDate(current.updated_at, true) }}</dd>
                </dl>

                <p
                    class="mt-6 mb-2 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase"
                >
                    Actions
                </p>
                <div class="-mx-1 space-y-1">
                    <RouterLink
                        :to="{
                            name: 'package-editor',
                            params: { id: current.id },
                        }"
                        class="ad-action"
                    >
                        <span class="ad-action-icon" style="--tone: #fbbf24"
                            ><Pencil class="size-4" /></span
                        >Edit package
                    </RouterLink>
                    <a
                        :href="current.url"
                        target="_blank"
                        rel="noopener"
                        class="ad-action"
                    >
                        <span class="ad-action-icon" style="--tone: #38bdf8"
                            ><ExternalLink class="size-4" /></span
                        >View on the website
                    </a>
                    <button
                        v-if="auth.can('packages.create')"
                        type="button"
                        class="ad-action"
                        @click="duplicate(current)"
                    >
                        <span class="ad-action-icon" style="--tone: #a78bfa"
                            ><Copy class="size-4" /></span
                        >Duplicate as a draft
                    </button>
                    <button
                        v-if="auth.can('packages.delete')"
                        type="button"
                        class="ad-action is-danger"
                        @click="remove(current)"
                    >
                        <span class="ad-action-icon"
                            ><Trash2 class="size-4" /></span
                        >Delete package
                    </button>
                </div>
            </template>
        </AppDrawer>

        <AppModal
            v-model:open="createOpen"
            title="New package"
            description="Saved as a draft. You'll add types, prices, flights and content next."
            :persistent="createForm.processing"
        >
            <form
                id="new-package"
                class="space-y-4"
                novalidate
                @submit.prevent="create"
            >
                <FormField
                    label="Service"
                    for="new-package-service"
                    :error="createForm.error('service_id')"
                    required
                >
                    <select
                        id="new-package-service"
                        v-model="createForm.data.service_id"
                        class="control"
                    >
                        <option
                            v-for="service in services"
                            :key="service.id"
                            :value="service.id"
                        >
                            {{ pickText(service.name, app.defaultLocale) }}
                        </option>
                    </select>
                </FormField>
                <TranslatableInput
                    v-model="createForm.data.name"
                    label="Package name"
                    required
                    :errors="createForm.errors"
                    error-key="name"
                    hint="The web address is created from the English name."
                    placeholder="Hajj Premium Package 2027"
                />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="createOpen = false"
                    >Cancel</AppButton
                >
                <AppButton
                    type="submit"
                    form="new-package"
                    :loading="createForm.processing"
                    >Create and edit</AppButton
                >
            </template>
        </AppModal>
    </div>
</template>

<style scoped>
.pk-hero {
    background: linear-gradient(100deg, #fbfcfe 0%, #f3f6fb 45%, #eef3fa 100%);
}

.pk-hero-img {
    position: absolute;
    inset: 0 0 0 auto;
    width: 46%;
    height: 100%;
    object-fit: cover;
    object-position: 50% 58%;
    mask-image: linear-gradient(
        to right,
        transparent 0%,
        rgb(0 0 0 / 0.6) 30%,
        #000 60%
    );
}

/* Keep the title readable where the photo starts. */
.pk-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        #fbfcfe 0%,
        #fbfcfe 40%,
        rgb(251 252 254 / 0.75) 52%,
        rgb(251 252 254 / 0) 72%
    );
    pointer-events: none;
}

.pk-hero > div {
    z-index: 1;
}

.pk-new {
    box-shadow: 0 10px 24px -12px rgb(2 12 27 / 0.6);
}

@media (min-width: 1280px) {
    .pk-hero-img {
        width: 58%;
    }
}

@media (max-width: 767px) {
    .pk-hero-img {
        width: 100%;
        opacity: 0.35;
        mask-image: linear-gradient(to bottom, #000, transparent);
    }

    .pk-hero::after {
        background: linear-gradient(
            180deg,
            rgb(251 252 254 / 0.55),
            #fbfcfe 80%
        );
    }
}

.pk-filters :deep(input[type="search"]) {
    height: 3rem;
    border-radius: 0.9rem;
    box-shadow: var(--shadow-card);
}

.pk-select {
    position: relative;
    display: block;

    select {
        height: 3rem;
        width: 10rem;
        border-radius: 0.9rem;
        padding-left: 2.6rem;
        box-shadow: var(--shadow-card);
        font-weight: 500;
    }
}

@media (min-width: 1280px) {
    .pk-select select {
        width: 12.5rem;
    }
}

/* The list measures its own width, so rows adapt to the space next to the sidebar. */
.pk-list {
    container: pk-list / inline-size;
}

.pk-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
}

@container pk-list (min-width: 860px) {
    .pk-grid.is-list {
        grid-template-columns: minmax(0, 1fr);
    }
}

.pk-select-icon {
    position: absolute;
    top: 50%;
    left: 0.9rem;
    width: 1.1rem;
    height: 1.1rem;
    transform: translateY(-50%);
    pointer-events: none;
}

.pk-view {
    display: flex;
    width: 2.5rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    color: var(--color-ink-soft);
    transition:
        background-color 180ms ease,
        color 180ms ease;

    &:hover {
        color: var(--color-ink);
    }

    &.is-on {
        background: var(--admin-primary);
        color: #fff;
    }
}
</style>
