<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { ArrowRight, ChevronRight, CircleDashed, CornerDownLeft, LayoutGrid, SearchX } from 'lucide-vue-next';
import FilterDrawer, { type FilterGroup } from '@admin/components/ui/FilterDrawer.vue';
import ListToolbar from '@admin/components/ui/ListToolbar.vue';
import { groupIcon, groupTone } from './meta';
import type { SettingsNavGroup, SettingsNavSection } from './types';

/**
 * The settings start page: search every setting by name, or pick a section.
 * Phones get a grouped list; wider screens a grid of cards.
 */
const props = defineProps<{ navigation: SettingsNavSection[] }>();

const query = ref('');
const filtersOpen = ref(false);
/** Which sections show: by area ('' = all) and by how complete they are. */
const filters = reactive({ area: '', completion: '' });

const allGroups = computed(() => props.navigation.flatMap((s) => s.groups));
const isComplete = (group: SettingsNavGroup) => group.filled >= group.total;
const filterGroups = computed<FilterGroup[]>(() => [
    {
        key: 'area',
        label: 'Area',
        icon: LayoutGrid,
        tone: '#cbd5e1',
        options: [{ value: '', label: 'Everything', count: allGroups.value.length }, ...props.navigation.map((s) => ({ value: s.label, label: s.label, count: s.groups.length }))],
    },
    {
        key: 'completion',
        label: 'Filled in',
        icon: CircleDashed,
        tone: '#fbbf24',
        options: [
            { value: '', label: 'Any' },
            { value: 'empty', label: 'Has empty fields', count: allGroups.value.filter((g) => !isComplete(g)).length },
            { value: 'complete', label: 'Fully filled in', count: allGroups.value.filter(isComplete).length },
        ],
    },
]);
const activeFilters = computed(() => Object.values(filters).filter((v) => v !== '').length);

/** Sections after the filters, keeping their areas. */
const visibleSections = computed(() =>
    props.navigation
        .filter((section) => !filters.area || section.label === filters.area)
        .map((section) => ({
            ...section,
            groups: section.groups.filter((g) => !filters.completion || (filters.completion === 'complete') === isComplete(g)),
        }))
        .filter((section) => section.groups.length),
);
const visibleCount = computed(() => visibleSections.value.reduce((sum, s) => sum + s.groups.length, 0));

function clearFilters(): void {
    filters.area = '';
    filters.completion = '';
}

interface Hit {
    group: SettingsNavGroup;
    field: { key: string; label: string; section: string | null } | null;
}

const hits = computed<Hit[]>(() => {
    const terms = query.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    if (!terms.length) return [];
    const matches = (text: string) => terms.every((t) => text.toLowerCase().includes(t));
    const result: Hit[] = [];

    for (const group of props.navigation.flatMap((s) => s.groups)) {
        if (matches(`${group.label} ${group.description}`)) result.push({ group, field: null });
        for (const field of group.fields) {
            if (matches(`${field.label} ${field.section ?? ''} ${group.label}`)) result.push({ group, field });
        }
    }

    return result.slice(0, 12);
});

const percent = (group: SettingsNavGroup) => (group.total ? Math.round((group.filled / group.total) * 100) : 0);

function target(hit: Hit) {
    return { name: 'settings', params: { group: hit.group.key }, hash: hit.field ? `#f-${hit.field.key}` : undefined };
}

// "/" jumps to the search box, as in many admin tools.
function onKey(event: KeyboardEvent): void {
    const typing = event.target instanceof HTMLElement && /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName);
    if (event.key === '/' && !typing) {
        event.preventDefault();
        document.getElementById('settings-search')?.focus();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="space-y-7">
        <!-- Search and filters in one row ("/" jumps to the search) -->
        <ListToolbar v-model:search="query" input-id="settings-search" placeholder="Search any setting (press /): WhatsApp, logo…" :active="activeFilters" @filters="filtersOpen = true" />

        <!-- Results -->
        <section v-if="query.trim()" aria-live="polite" aria-label="Search results">
            <p class="mb-2.5 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">{{ hits.length ? `${hits.length} result${hits.length === 1 ? '' : 's'}` : 'Results' }}</p>
            <div v-if="!hits.length" class="card flex flex-col items-center px-6 py-10 text-center">
                <SearchX class="size-7 text-ink-muted" aria-hidden="true" />
                <p class="mt-3 text-[15px] font-semibold text-ink">Nothing matches "{{ query.trim() }}"</p>
                <p class="mt-1 text-[13.5px] text-ink-muted">Try a shorter word, such as "phone" or "color".</p>
            </div>
            <ul v-else class="card divide-y divide-line overflow-hidden">
                <li v-for="hit in hits" :key="`${hit.group.key}-${hit.field?.key ?? ''}`">
                    <RouterLink :to="target(hit)" class="sh-hit flex items-center gap-3.5 px-4 py-3.5 sm:px-5" :style="{ '--tone': groupTone(hit.group.icon) }">
                        <span class="st-icon flex size-10 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><component :is="groupIcon(hit.group.icon)" class="size-[18px]" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[14.5px] font-semibold text-ink">{{ hit.field?.label ?? hit.group.label }}</span>
                            <span class="block truncate text-[12.5px] text-ink-muted">
                                {{ hit.field ? [hit.group.label, hit.field.section].filter(Boolean).join(' › ') : 'Settings section' }}
                            </span>
                        </span>
                        <CornerDownLeft class="sh-enter size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                    </RouterLink>
                </li>
            </ul>
        </section>

        <!-- Sections -->
        <template v-else>
            <div v-if="!visibleCount" class="card flex flex-col items-center px-6 py-10 text-center">
                <SearchX class="size-7 text-ink-muted" aria-hidden="true" />
                <p class="mt-3 text-[15px] font-semibold text-ink">No sections match these filters</p>
                <button type="button" class="mt-2 text-[13.5px] font-semibold text-brand hover:underline" @click="clearFilters">Clear filters</button>
            </div>
            <section v-for="section in visibleSections" :key="section.label" :aria-labelledby="`sh-${section.label}`">
                <h2 :id="`sh-${section.label}`" class="mb-3 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">{{ section.label }}</h2>

                <!-- Phones: one grouped list -->
                <ul class="card divide-y divide-line overflow-hidden md:hidden">
                    <li v-for="group in section.groups" :key="group.key">
                        <RouterLink :to="{ name: 'settings', params: { group: group.key } }" class="flex min-h-16 items-center gap-3.5 px-4 py-3 active:bg-canvas" :style="{ '--tone': groupTone(group.icon) }">
                            <span class="st-icon flex size-10 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><component :is="groupIcon(group.icon)" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-semibold text-ink">{{ group.label }}</span>
                                <span class="block truncate text-[13px] text-ink-muted">{{ group.description }}</span>
                            </span>
                            <ChevronRight class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                        </RouterLink>
                    </li>
                </ul>

                <!-- Wider screens: cards -->
                <div class="hidden gap-4 md:grid md:grid-cols-2 xl:grid-cols-3">
                    <RouterLink
                        v-for="group in section.groups"
                        :key="group.key"
                        :to="{ name: 'settings', params: { group: group.key } }"
                        class="sh-card card group relative flex flex-col overflow-hidden p-5"
                        :style="{ '--tone': groupTone(group.icon) }"
                    >
                        <span class="sh-card-glow pointer-events-none absolute -top-16 -right-16 size-40 rounded-full" aria-hidden="true" />
                        <span class="flex items-start justify-between gap-3">
                            <span class="ph-tile flex size-12 items-center justify-center rounded-2xl" aria-hidden="true"><component :is="groupIcon(group.icon)" class="size-[22px]" /></span>
                            <ArrowRight class="size-4 text-ink-muted transition-transform duration-300 group-hover:translate-x-1" aria-hidden="true" />
                        </span>
                        <span class="mt-4 block text-[16px] font-bold text-ink">{{ group.label }}</span>
                        <span class="mt-1 block flex-1 text-[13.5px] leading-relaxed text-ink-muted">{{ group.description }}</span>
                        <span class="mt-5 flex items-center gap-3">
                            <span class="sh-meter h-1.5 flex-1 overflow-hidden rounded-full" aria-hidden="true"><span class="block h-full rounded-full" :style="{ width: `${percent(group)}%` }" /></span>
                            <span class="shrink-0 text-[12px] font-semibold text-ink-soft">{{ group.filled }} of {{ group.total }} filled</span>
                        </span>
                    </RouterLink>
                </div>
            </section>
        </template>

        <FilterDrawer v-model:open="filtersOpen" :filters="filters" :groups="filterGroups" :total="visibleCount" :noun="['section', 'sections']" :can-clear="activeFilters > 0" @clear="clearFilters" />
    </div>
</template>

<style scoped>
.sh-hit {
    transition: background-color 160ms ease;

    &:hover,
    &:focus-visible {
        background: var(--color-canvas, #f5f7fa);
    }

    .sh-enter {
        opacity: 0;
        transition: opacity 160ms ease;
    }

    &:hover .sh-enter,
    &:focus-visible .sh-enter {
        opacity: 1;
    }
}

.sh-card {
    transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 260ms ease, border-color 260ms ease;

    &:hover,
    &:focus-visible {
        transform: translateY(-3px);
        border-color: color-mix(in oklab, var(--tone) 45%, white);
        box-shadow: 0 18px 40px -24px color-mix(in oklab, var(--tone) 70%, black), var(--shadow-card);
    }
}

.sh-card-glow {
    background: radial-gradient(circle, color-mix(in oklab, var(--tone) 22%, transparent), transparent 70%);
    opacity: 0.7;
    transition: opacity 260ms ease;
}

.sh-card:hover .sh-card-glow {
    opacity: 1;
}

.sh-meter {
    background: color-mix(in oklab, var(--tone) 14%, white);

    > span {
        background: color-mix(in oklab, var(--tone) 75%, black 8%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sh-card,
    .sh-card:hover {
        transform: none;
        transition: none;
    }
}
</style>
