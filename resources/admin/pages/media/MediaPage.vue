<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { ArrowDownUp, Images, LayoutGrid, List, Sparkles, Tags, Upload, X } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppPagination from '@admin/components/ui/AppPagination.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import MediaDetailsModal from '@admin/components/media/MediaDetailsModal.vue';
import MediaDropzone from '@admin/components/media/MediaDropzone.vue';
import { mediaIsFree } from '@admin/components/media/mediaInUse';
import MediaListItem from './MediaListItem.vue';
import headerPhoto from '@admin/assets/media-header.webp';
import { useConfirm } from '@admin/composables/useConfirm';
import { useMediaList } from '@admin/composables/useMediaList';
import { errorMessage, http } from '@admin/lib/http';
import { fetchUsages } from '@admin/lib/media';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Media, MediaUsage } from '@admin/types';

const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();
const list = useMediaList();

const usages = ref<MediaUsage[]>([]);
const mode = ref<'automatic' | 'manual'>('automatic');
const uploadUsage = ref('general');
const showUploader = ref(false);
const detailsOpen = ref(false);
const filtersOpen = ref(false);
const current = ref<Media | null>(null);

const activeFilters = computed(() => (list.filters.usage ? 1 : 0));
const filtered = computed(() => !!list.filters.search || activeFilters.value > 0);
const total = computed(() => list.meta.value?.total ?? 0);

/** A colour per image usage, so the badge on each picture says where it is used. */
const palette = ['#38bdf8', '#a78bfa', '#fb923c', '#34d399', '#f472b6', '#fbbf24', '#2dd4bf', '#60a5fa', '#fb7185', '#a3e635'];
const toneOf = (key: string) => palette[Math.max(0, usages.value.findIndex((u) => u.key === key)) % palette.length];
const labelOf = (key: string) => usages.value.find((u) => u.key === key)?.label ?? key;

// Grid or list, remembered on this device.
const VIEW_KEY = 'ahk.media.view';
const view = ref<'list' | 'grid'>('grid');
try {
    if (localStorage.getItem(VIEW_KEY) === 'list') view.value = 'list';
} catch {
    // Storage can be blocked; the grid view is the default.
}
watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Not remembered, which is fine.
    }
});

const filterGroups = computed<FilterGroup[]>(() => [
    {
        key: 'usage',
        label: 'Used as',
        icon: Tags,
        tone: '#38bdf8',
        options: [
            { value: '', label: 'All images', count: list.counts.value?.total ?? null },
            ...usages.value.map((u) => ({ value: u.key, label: u.label, count: list.counts.value?.usages[u.key] ?? 0 })),
        ],
    },
    {
        key: 'sort',
        label: 'Sort by',
        icon: ArrowDownUp,
        tone: '#fbbf24',
        options: [
            { value: '', label: 'Newest first' },
            { value: 'oldest', label: 'Oldest first' },
            { value: 'largest', label: 'Largest uploads' },
            { value: 'smallest', label: 'Smallest uploads' },
            { value: 'name', label: 'File name A–Z' },
        ],
    },
]);

function clearFilters(): void {
    list.filters.usage = '';
    list.filters.sort = '';
}

function clearAll(): void {
    clearFilters();
    list.filters.search = '';
}

function open(media: Media): void {
    current.value = media;
    detailsOpen.value = true;
}

function uploaded(media: Media): void {
    list.prepend(media);
    toast.success('Image uploaded', media.original_name);
}

async function copyLink(media: Media): Promise<void> {
    const text = media.url ?? '';
    try {
        // The clipboard API needs https; older copy works on plain http (local sites) too.
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const area = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
            area.style.cssText = 'position:fixed;opacity:0';
            document.body.append(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            if (!ok) throw new Error('copy failed');
        }
        toast.success('Link copied');
    } catch {
        toast.error('Could not copy the link');
    }
}

async function remove(media: Media): Promise<void> {
    try {
        if (!(await mediaIsFree(media.id))) return;
    } catch (e) {
        toast.error(errorMessage(e));
        return;
    }
    const ok = await confirm({ title: `Delete "${media.original_name}"?`, message: 'It is not used anywhere on the website. It goes to the Trash, where a super admin can restore it.', confirmLabel: 'Delete image' });
    if (!ok) return;
    try {
        await http.delete(`/media/${media.id}`);
        list.remove(media.id);
        toast.success('Image deleted');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(async () => {
    list.load();
    const config = await fetchUsages(true);
    usages.value = config.data;
    mode.value = config.mode;
});
</script>

<template>
    <div>
        <!-- Header with a photo -->
        <ListHero title="Media library" description="Every image is converted to WebP and resized for where it's used." :icon="Images" tone="#22d3ee" :photo="headerPhoto" photo-position="50% 40%">
            <template #actions>
                <span class="md-mode" :class="{ 'is-manual': mode === 'manual' }"><Sparkles class="size-4" aria-hidden="true" />{{ mode === 'manual' ? 'Manual optimization' : 'Automatic optimization' }}</span>
                <AppButton v-if="auth.can('media.create')" :icon="showUploader ? X : Upload" @click="showUploader = !showUploader">{{ showUploader ? 'Close' : 'Upload' }}</AppButton>
            </template>
        </ListHero>

        <!-- Upload -->
        <Transition name="md-drop">
            <section v-if="showUploader" class="card mb-5 overflow-hidden" aria-label="Upload images">
                <div class="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[15px] font-bold text-ink">Upload images</p>
                        <p class="text-[13px] text-ink-soft">Choose where the images will be used so they get the right sizes.</p>
                    </div>
                    <label class="sm:w-64">
                        <span class="label">Use as</span>
                        <select v-model="uploadUsage" class="control">
                            <option v-for="usage in usages" :key="usage.key" :value="usage.key">{{ usage.label }}</option>
                        </select>
                    </label>
                </div>
                <div class="p-5"><MediaDropzone :usage="uploadUsage" @uploaded="uploaded" /></div>
            </section>
        </Transition>

        <!-- Search, filters and view switch in one row -->
        <ListToolbar v-model:search="list.filters.search" placeholder="Search by file name" :active="activeFilters" @filters="filtersOpen = true">
            <div class="hidden rounded-xl border border-line bg-white p-1 sm:flex" role="group" aria-label="Show images as">
                <button type="button" class="md-view" :class="{ 'is-on': view === 'grid' }" :aria-pressed="view === 'grid'" aria-label="Grid" title="Grid" @click="view = 'grid'"><LayoutGrid class="size-[18px]" /></button>
                <button type="button" class="md-view" :class="{ 'is-on': view === 'list' }" :aria-pressed="view === 'list'" aria-label="List" title="List" @click="view = 'list'"><List class="size-[18px]" /></button>
            </div>
        </ListToolbar>

        <!-- Images -->
        <div v-if="list.loading.value && !list.items.value.length" class="md-grid is-grid" aria-busy="true" aria-label="Loading images">
            <div v-for="n in 8" :key="n" class="card aspect-[4/4] animate-pulse" />
        </div>
        <div v-else-if="!list.items.value.length" class="card">
            <EmptyState
                :icon="Images"
                :title="filtered ? 'No images match' : 'No images yet'"
                :description="filtered ? 'Try another search or clear the filters.' : 'Upload images to use them across the website.'"
            >
                <AppButton v-if="filtered" variant="secondary" @click="clearAll">Clear filters</AppButton>
                <AppButton v-else-if="auth.can('media.create')" :icon="Upload" @click="showUploader = true">Upload images</AppButton>
            </EmptyState>
        </div>
        <div v-else :key="view" class="md-list" :class="{ 'opacity-60 transition-opacity': list.loading.value }">
            <div class="md-grid" :class="`is-${view}`">
                <MediaListItem
                    v-for="(media, i) in list.items.value"
                    :key="media.id"
                    :media="media"
                    :usage-label="labelOf(media.usage)"
                    :usage-tone="toneOf(media.usage)"
                    :layout="view"
                    :index="i"
                    :locale="app.defaultLocale"
                    :can-delete="auth.can('media.delete')"
                    @open="open(media)"
                    @copy="copyLink(media)"
                    @remove="remove(media)"
                />
            </div>
        </div>

        <AppPagination v-if="list.meta.value && list.items.value.length" :meta="list.meta.value" label="images" :per-page-options="[24, 48, 96]" @change="list.goTo" @per-page="list.setPerPage" />

        <!-- All filters -->
        <FilterDrawer
            v-model:open="filtersOpen"
            :filters="list.filters"
            :groups="filterGroups"
            :total="total"
            :noun="['image', 'images']"
            :loading="list.loading.value"
            :can-clear="activeFilters > 0 || list.filters.sort !== ''"
            @clear="clearFilters"
        />

        <MediaDetailsModal v-model:open="detailsOpen" :media="current" @updated="list.replace" @deleted="list.remove" />
    </div>
</template>

<style scoped>
/* The list measures its own width, so cards and rows adapt to the space next to the sidebar. */
.md-list {
    container: md-list / inline-size;
}

.md-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 170px), 1fr));
}

@container md-list (min-width: 700px) {
    .md-grid.is-list {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.75rem;
    }
}

.md-mode {
    display: inline-flex;
    min-height: 2.5rem;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    background: #ecfdf5;
    padding: 0 0.9rem;
    font-size: 13px;
    font-weight: 600;
    color: #047857;
    box-shadow: inset 0 0 0 1px #a7f3d0;

    &.is-manual {
        background: #fffbeb;
        color: #92400e;
        box-shadow: inset 0 0 0 1px #fde68a;
    }
}

.md-view {
    display: flex;
    width: 2.5rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.6rem;
    color: var(--color-ink-soft);
    transition: background-color 180ms ease, color 180ms ease;

    &:hover {
        color: var(--color-ink);
    }

    &.is-on {
        background: var(--admin-primary);
        color: #fff;
    }
}

.md-drop-enter-active {
    transition: opacity 260ms ease, transform 320ms cubic-bezier(0.22, 1, 0.36, 1);
}

.md-drop-leave-active {
    transition: opacity 160ms ease;
}

.md-drop-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.md-drop-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .md-drop-enter-from {
        transform: none;
    }
}
</style>
