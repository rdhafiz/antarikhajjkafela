<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, type RouteLocationNormalized } from 'vue-router';
import { ArrowLeft, Check, CheckCircle2, ChevronDown, Eye, LayoutGrid, Settings2, Sparkles, Undo2 } from 'lucide-vue-next';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppDrawer from '@admin/components/ui/AppDrawer.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import headerPhoto from '@admin/assets/settings-header.webp';
import SettingField from './SettingField.vue';
import SettingsHub from './SettingsHub.vue';
import ThemePreview from './ThemePreview.vue';
import { groupIcon, groupTone } from './meta';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { AdminTheme } from '@admin/types';
import type { SettingFieldDefinition, SettingsGroup, SettingsNavSection } from './types';

const route = useRoute();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const navigation = ref<SettingsNavSection[]>([]);
const group = ref<SettingsGroup | null>(null);
const loading = ref(false);
const form = useForm({ values: {} as Record<string, unknown> });
const saved = ref('');
const switcherOpen = ref(false);
const justSaved = ref(false);

const groupKey = computed(() => String(route.params.group ?? ''));
const allGroups = computed(() => navigation.value.flatMap((section) => section.groups));
const filledTotals = computed(() => allGroups.value.reduce((t, g) => ({ filled: t.filled + g.filled, total: t.total + g.total }), { filled: 0, total: 0 }));
const current = computed(() => allGroups.value.find((item) => item.key === (group.value?.key ?? groupKey.value)) ?? null);
const tone = computed(() => groupTone(current.value?.icon));
const canEdit = computed(() => auth.can('settings.update'));
// Nothing loaded yet (saved is empty) is never "unsaved".
const dirty = computed(() => saved.value !== '' && JSON.stringify(form.data.values) !== saved.value);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/** Fields grouped under their optional section heading, keeping order. */
const sections = computed(() => {
    const result: { title: string | null; fields: SettingFieldDefinition[] }[] = [];

    for (const field of group.value?.fields ?? []) {
        const title = field.section ?? null;
        const last = result[result.length - 1];
        last && last.title === title ? last.fields.push(field) : result.push({ title, fields: [field] });
    }

    return result;
});

const pairable = ['color', 'string', 'phone', 'email', 'url', 'select', 'secret'];
const twoColumns = (fields: SettingFieldDefinition[]) => fields.length > 1 && fields.every((f) => pairable.includes(f.type) && !f.translatable);

/** Secrets come back as true/false; the inputs start empty. */
function editableValues(values: Record<string, unknown>): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    for (const field of group.value?.fields ?? []) {
        result[field.key] = field.type === 'secret' ? null : JSON.parse(JSON.stringify(values[field.key] ?? null));
    }

    return result;
}

async function loadNavigation(): Promise<void> {
    try {
        navigation.value = (await http.get<{ data: SettingsNavSection[] }>('/settings')).data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function loadGroup(key: string): Promise<void> {
    if (!key) {
        group.value = null;
        saved.value = '';
        return;
    }

    loading.value = true;

    try {
        const { data } = await http.get<{ data: SettingsGroup }>(`/settings/${key}`);
        group.value = data.data;
        form.reset({ values: editableValues(data.data.values) });
        saved.value = JSON.stringify(form.data.values);
    } catch (e) {
        toast.error(errorMessage(e));
        group.value = null;
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<void> {
    if (!group.value || !dirty.value || form.processing) return;

    // Unchanged secrets are left out so the stored value is kept.
    const payload = Object.fromEntries(
        Object.entries(form.data.values).filter(([key, value]) => !(group.value!.fields.find((f) => f.key === key)?.type === 'secret' && !value)),
    );

    const response = await form.submit(() => http.put<{ data: SettingsGroup }>(`/settings/${group.value!.key}`, payload));
    if (!response) {
        await nextTick();
        document.querySelector<HTMLElement>('.st-form [aria-invalid="true"], .st-form [role="alert"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    group.value = response.data.data;
    form.reset({ values: editableValues(response.data.data.values) });
    saved.value = JSON.stringify(form.data.values);
    toast.success(`${group.value.label} saved`);
    justSaved.value = true;
    window.setTimeout(() => (justSaved.value = false), 2400);
    loadNavigation();

    if (['branding', 'admin_theme', 'media', 'localization'].includes(group.value.key)) {
        await app.load(true);
    }
}

function undo(): void {
    form.reset({ values: JSON.parse(saved.value) });
}

async function confirmDiscard(): Promise<boolean> {
    if (!dirty.value) return true;

    return confirm({
        title: 'Discard unsaved changes?',
        message: 'You have changes in this section that have not been saved.',
        confirmLabel: 'Discard changes',
    });
}

// Admin theme colours preview live while editing.
watch(
    () => (group.value?.key === 'admin_theme' ? form.data.values : null),
    (values) => values && app.applyTheme(values as unknown as AdminTheme),
    { deep: true },
);

function restoreTheme(): void {
    if (group.value?.key === 'admin_theme' && dirty.value) {
        app.applyTheme(JSON.parse(saved.value) as AdminTheme);
    }
}

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Scrolls to a field (from a search result) and highlights it briefly. */
async function focusField(hash: string): Promise<void> {
    if (!hash.startsWith('#f-')) return;
    // The section fades in after loading, so wait (up to a second) for the field to exist.
    let el: HTMLElement | null = null;
    for (let i = 0; i < 60 && !el; i++) {
        await new Promise((resolve) => requestAnimationFrame(resolve));
        el = document.getElementById(hash.slice(1));
    }
    if (!el) return;
    el.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
    el.classList.remove('st-flash');
    void el.offsetWidth;
    el.classList.add('st-flash');
    const target = el.querySelector<HTMLElement>('input:not([type=hidden]):not([type=radio]):not([type=search]), textarea, select') ?? el.querySelector<HTMLElement>('input, button');
    target?.focus({ preventScroll: true });
    window.setTimeout(() => el.classList.remove('st-flash'), 2000);
}

async function enter(to: RouteLocationNormalized): Promise<void> {
    const key = String(to.params.group ?? '');
    if (key !== (group.value?.key ?? '')) await loadGroup(key);
    if (key) focusField(to.hash);
    else window.scrollTo({ top: 0 });
}

onBeforeRouteUpdate(async (to, from) => {
    if (String(to.params.group ?? '') === String(from.params.group ?? '')) {
        focusField(to.hash);
        return true;
    }
    if (!(await confirmDiscard())) return false;
    restoreTheme();
    switcherOpen.value = false;
    await enter(to);
    return true;
});

onBeforeRouteLeave(async () => {
    if (!(await confirmDiscard())) return false;
    restoreTheme();
    return true;
});

function beforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value) event.preventDefault();
}

// Ctrl/Cmd + S saves the open section.
function onKey(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && group.value) {
        event.preventDefault();
        if (canEdit.value) save();
    }
}

onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);
    window.addEventListener('keydown', onKey);
    await Promise.all([loadNavigation(), enter(route)]);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div>
        <!-- Hub: search and all sections -->
        <template v-if="!groupKey">
            <ListHero title="Settings" description="Website details, appearance, integrations and payment. Search for any setting or pick a section." :icon="Settings2" tone="#cbd5e1" :photo="headerPhoto" photo-position="70% 50%">
                <template v-if="filledTotals.total" #actions>
                    <span class="st-overall"><span class="st-overall-ring" :style="{ '--p': `${(filledTotals.filled / filledTotals.total) * 100}%` }" aria-hidden="true" />{{ filledTotals.filled }} of {{ filledTotals.total }} settings filled in</span>
                </template>
            </ListHero>
            <div v-if="!navigation.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
                <div class="card h-14 animate-pulse md:col-span-2 xl:col-span-3" />
                <div v-for="n in 6" :key="n" class="card h-44 animate-pulse" />
            </div>
            <SettingsHub v-else :navigation="navigation" />
        </template>

        <!-- One section -->
        <div v-else class="grid grid-cols-[minmax(0,1fr)] gap-7 lg:grid-cols-[264px_minmax(0,1fr)]" :style="{ '--tone': tone }">
            <nav class="st-rail card hidden h-fit p-3 lg:sticky lg:top-24 lg:block" aria-label="Settings sections">
                <RouterLink :to="{ name: 'settings' }" class="flex items-center gap-3 rounded-xl px-2.5 py-2 text-[14px] font-semibold text-ink-soft hover:bg-canvas hover:text-ink">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-canvas text-ink-soft"><LayoutGrid class="size-4" /></span>
                    All settings
                </RouterLink>
                <div class="mt-2 space-y-4 border-t border-line pt-4">
                    <div v-for="section in navigation" :key="section.label">
                        <p class="mb-1.5 px-2.5 text-[11px] font-bold tracking-[0.12em] text-ink-muted uppercase">{{ section.label }}</p>
                        <div v-for="item in section.groups" :key="item.key" class="mb-0.5">
                            <RouterLink
                                :to="{ name: 'settings', params: { group: item.key } }"
                                class="st-link st-rail-link flex items-center gap-3 rounded-xl px-2.5 py-2 text-[14px] font-medium"
                                :class="groupKey === item.key ? 'is-active font-semibold text-ink' : 'text-ink-soft hover:bg-canvas hover:text-ink'"
                                :style="{ '--tone': groupTone(item.icon) }"
                                :aria-current="groupKey === item.key ? 'page' : undefined"
                            >
                                <span class="st-icon flex size-8 shrink-0 items-center justify-center rounded-lg"><component :is="groupIcon(item.icon)" class="size-4" /></span>
                                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                            </RouterLink>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="min-w-0">
                <!-- Phones: back to all settings + switch section -->
                <div class="mb-4 flex items-center gap-2 lg:hidden">
                    <RouterLink :to="{ name: 'settings' }" class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-line bg-white text-ink" aria-label="All settings">
                        <ArrowLeft class="size-5" />
                    </RouterLink>
                    <button type="button" class="flex h-12 min-w-0 flex-1 items-center gap-3 rounded-xl border border-line bg-white px-3 text-left" aria-haspopup="dialog" @click="switcherOpen = true">
                        <span class="st-icon flex size-8 shrink-0 items-center justify-center rounded-lg" :style="{ '--tone': tone }"><component :is="groupIcon(current?.icon)" class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] font-semibold tracking-[0.1em] text-ink-muted uppercase">Switch section</span>
                            <span class="block truncate text-[14.5px] font-semibold text-ink">{{ current?.label ?? 'Settings' }}</span>
                        </span>
                        <ChevronDown class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                    </button>
                </div>

                <Transition name="st-swap" mode="out-in">
                    <div v-if="loading || !group" key="loading" class="space-y-5" aria-busy="true" aria-label="Loading settings">
                        <div class="card h-36 animate-pulse" />
                        <div class="card h-72 animate-pulse" />
                    </div>

                    <form v-else :key="group.key" class="st-form" novalidate @submit.prevent="save">
                        <!-- Compact section header -->
                        <header class="flex flex-wrap items-center gap-x-3.5 gap-y-2">
                            <span class="ph-tile flex size-10 shrink-0 items-center justify-center rounded-xl" aria-hidden="true"><component :is="groupIcon(current?.icon)" class="size-5" /></span>
                            <div class="min-w-0 flex-1 basis-60">
                                <h1 class="text-[19px] leading-tight font-bold text-ink">{{ group.label }}</h1>
                                <p v-if="group.description" class="mt-0.5 text-[13.5px] text-ink-soft">{{ group.description }}</p>
                            </div>
                            <span v-if="!canEdit" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-[12.5px] font-semibold text-ink-soft ring-1 ring-line"><Eye class="size-3.5" />View only</span>
                            <span v-else-if="dirty" class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[12.5px] font-semibold text-amber-800 ring-1 ring-amber-200"><span class="size-1.5 rounded-full bg-amber-500" />Unsaved changes</span>
                            <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[12.5px] font-semibold text-emerald-700 ring-1 ring-emerald-200" :class="{ 'st-pop': justSaved }"><CheckCircle2 class="size-3.5" />All changes saved</span>
                        </header>

                        <div class="mt-5" :class="{ 'xl:grid xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start xl:gap-6': group.key === 'theme' }">
                            <fieldset class="m-0 min-w-0 space-y-5 border-0 p-0" :disabled="!canEdit">
                                <legend class="sr-only">{{ group.label }}</legend>

                                <div v-if="group.key === 'theme'" class="xl:hidden">
                                    <ThemePreview :primary="String(form.data.values.primary_color)" :secondary="String(form.data.values.secondary_color)" :accent="String(form.data.values.accent_color)" />
                                </div>
                                <p v-if="group.key === 'admin_theme'" class="flex items-start gap-2.5 rounded-2xl border border-line bg-white px-4 py-3 text-[13.5px] text-ink-soft">
                                    <Sparkles class="mt-0.5 size-4 shrink-0 text-violet-600" aria-hidden="true" />
                                    Colours preview live across the portal while you edit. Undo or leave without saving to go back.
                                </p>

                                <section v-for="(section, i) in sections" :id="`st-s-${i}`" :key="i" class="st-card card scroll-mt-24 p-5 sm:p-6" :aria-labelledby="section.title ? `st-h-${i}` : undefined">
                                    <h2 v-if="section.title" :id="`st-h-${i}`" class="mb-5 flex items-center gap-2.5 text-[15px] font-bold text-ink">
                                        <span class="st-dot size-2 rounded-full" aria-hidden="true" />{{ section.title }}
                                        <span class="ml-auto text-[12px] font-medium text-ink-muted">{{ section.fields.length }} {{ section.fields.length === 1 ? 'setting' : 'settings' }}</span>
                                    </h2>
                                    <div class="grid grid-cols-[minmax(0,1fr)] gap-x-5 gap-y-6" :class="{ 'md:grid-cols-2': twoColumns(section.fields) }">
                                        <div v-for="field in section.fields" :id="`f-${field.key}`" :key="field.key" class="st-field scroll-mt-28 rounded-xl">
                                            <SettingField
                                                v-model="form.data.values[field.key]"
                                                :field="field"
                                                :errors="form.errors"
                                                :secret-stored="field.type === 'secret' ? group.values[field.key] === true : undefined"
                                            />
                                        </div>
                                    </div>
                                </section>
                            </fieldset>

                            <aside v-if="group.key === 'theme'" class="hidden xl:sticky xl:top-24 xl:block" aria-label="Preview">
                                <p class="mb-2.5 text-[12px] font-bold tracking-[0.12em] text-ink-muted uppercase">Preview</p>
                                <ThemePreview :primary="String(form.data.values.primary_color)" :secondary="String(form.data.values.secondary_color)" :accent="String(form.data.values.accent_color)" />
                            </aside>
                        </div>

                        <!-- Appears only when something changed -->
                        <Transition name="st-bar">
                            <div v-if="canEdit && dirty" class="st-bar sticky bottom-3 z-20 mt-5 sm:bottom-5" role="region" aria-label="Unsaved changes">
                                <div class="flex items-center gap-2 rounded-2xl bg-[#0b1d33] py-2.5 pr-2.5 pl-4 text-white shadow-[0_18px_44px_-14px_rgb(2_12_27/0.55)] sm:gap-3 sm:pl-5">
                                    <span class="st-pulse size-2 shrink-0 rounded-full bg-amber-400" aria-hidden="true" />
                                    <p class="min-w-0 flex-1 truncate text-[14px] font-semibold">
                                        Unsaved changes
                                        <span class="ml-2 hidden font-normal text-white/60 md:inline"><kbd class="rounded border border-white/20 px-1.5 py-0.5 font-sans text-[11.5px]">{{ isMac ? '⌘' : 'Ctrl' }} S</kbd> to save</span>
                                    </p>
                                    <button type="button" class="flex h-10 items-center gap-1.5 rounded-xl px-3 text-[14px] font-semibold text-white/80 hover:bg-white/10 hover:text-white disabled:opacity-50" :disabled="form.processing" @click="undo">
                                        <Undo2 class="size-4" aria-hidden="true" />Undo
                                    </button>
                                    <button type="submit" class="st-save flex h-10 items-center gap-1.5 rounded-xl px-4 text-[14px] font-bold disabled:opacity-70" :disabled="form.processing" :aria-busy="form.processing">
                                        <AppSpinner v-if="form.processing" class="size-4" /><Check v-else class="size-4" aria-hidden="true" />{{ form.processing ? 'Saving' : 'Save' }}
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </form>
                </Transition>
            </div>

            <!-- Phones: every section -->
            <AppDrawer v-model:open="switcherOpen" title="Settings sections" :icon="Settings2" :tone="tone">
                <div class="space-y-5">
                    <RouterLink :to="{ name: 'settings' }" class="flex items-center gap-3 rounded-xl px-2 py-2.5 text-[14.5px] font-semibold text-ink hover:bg-canvas">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-canvas"><LayoutGrid class="size-[18px] text-ink-soft" /></span>All settings
                    </RouterLink>
                    <div v-for="section in navigation" :key="section.label">
                        <p class="mb-1.5 px-1 text-[11px] font-bold tracking-[0.1em] text-ink-muted uppercase">{{ section.label }}</p>
                        <RouterLink
                            v-for="item in section.groups"
                            :key="item.key"
                            :to="{ name: 'settings', params: { group: item.key } }"
                            class="st-link flex min-h-12 items-center gap-3 rounded-xl px-2 py-2 text-[14.5px] font-semibold text-ink hover:bg-canvas"
                            :class="{ 'is-active bg-canvas': groupKey === item.key }"
                            :style="{ '--tone': groupTone(item.icon) }"
                        >
                            <span class="st-icon flex size-9 shrink-0 items-center justify-center rounded-lg"><component :is="groupIcon(item.icon)" class="size-[18px]" /></span>
                            <span class="flex-1">{{ item.label }}</span>
                            <Check v-if="groupKey === item.key" class="size-4 text-brand" aria-hidden="true" />
                        </RouterLink>
                    </div>
                </div>
            </AppDrawer>
        </div>
    </div>
</template>
