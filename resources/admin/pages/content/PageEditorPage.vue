<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { VueDraggable } from 'vue-draggable-plus';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { ArrowLeft, Copy, ExternalLink, Eye, EyeOff, GripVertical, Lock, MoreHorizontal, Plus, Settings2, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import ComponentPicker from './builder/ComponentPicker.vue';
import FieldList from './builder/FieldList.vue';
import PageSettingsPanel, { type PageForm } from './builder/PageSettingsPanel.vue';
import { componentIcon } from './builder/componentIcons';
import { deepClone, pickText, uid, type BuilderMeta, type ComponentDef, type PageDetail, type SectionData } from './builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useErrorClearing } from '@admin/composables/useErrorClearing';
import { errorMessage, http, validationErrors, type ValidationErrors } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const page = ref<PageDetail | null>(null);
const meta = ref<BuilderMeta | null>(null);
const form = ref<PageForm | null>(null);
const sections = ref<SectionData[]>([]);
const selected = ref<string>('settings');
const tab = ref<'content' | 'style'>('content');
const pickerOpen = ref(false);
const saving = ref(false);
const errors = ref<ValidationErrors>({});
// Section errors are keyed by position (sections.2.content.title), like the data sent.
useErrorClearing(() => ({ ...form.value, sections: sections.value }), errors);
const saved = ref('');

const pageId = computed(() => Number(route.params.id));
const scope = computed(() => (page.value?.type === 'campaign' ? 'campaigns' : 'pages'));
const canEdit = computed(() => auth.can(`${scope.value}.update`));
const components = computed(() => Object.fromEntries((meta.value?.components ?? []).map((c) => [c.key, c])) as Record<string, ComponentDef>);
const pickable = computed(() => (meta.value?.components ?? []).filter((c) => meta.value?.pickable.includes(c.key)));
const current = computed(() => sections.value.find((s) => s.uid === selected.value) ?? null);
const currentIndex = computed(() => sections.value.findIndex((s) => s.uid === selected.value));
const currentDef = computed(() => (current.value ? components.value[current.value.component] : null));
const dirty = computed(() => saved.value !== '' && snapshot() !== saved.value);
const listPath = computed(() => (page.value?.type === 'campaign' ? '/admin/campaigns' : '/admin/pages'));

function snapshot(): string {
    return JSON.stringify({ form: form.value, sections: sections.value.map(({ uid: _u, ...s }) => s) });
}

function fromDetail(detail: PageDetail): void {
    page.value = detail;
    form.value = {
        title: { ...detail.title },
        slug: detail.slug,
        status: detail.status,
        seo: { title: { ...detail.seo.title }, description: { ...detail.seo.description }, image: detail.seo.image, noindex: detail.seo.noindex },
        starts_at: detail.starts_at,
        expires_at: detail.expires_at,
        focused_layout: detail.focused_layout,
        tracking: { gtm_id: detail.tracking.gtm_id ?? null, facebook_pixel_id: detail.tracking.facebook_pixel_id ?? null },
    };
    const previous = sections.value;
    sections.value = detail.sections.map((s, i) => ({
        ...deepClone(s),
        // Keep the editor focused on the same section after saving.
        uid: previous[i]?.id === s.id ? previous[i]!.uid : uid(),
    }));
    saved.value = snapshot();
}

async function load(): Promise<void> {
    try {
        const { data } = await http.get<{ data: PageDetail }>(`/pages/${pageId.value}`);
        const builder = await http.get<BuilderMeta>('/page-builder', { params: { type: data.data.type } });
        meta.value = builder.data;
        fromDetail(data.data);
    } catch (e) {
        toast.error(errorMessage(e));
        router.replace('/admin/pages');
    }
}

function sectionTitle(section: SectionData): string {
    const content = section.content as Record<string, unknown>;
    const text = pickText(content.title, app.defaultLocale) || pickText(content.eyebrow, app.defaultLocale);
    return text || components.value[section.component]?.description || '';
}

function sectionHasError(index: number): boolean {
    return Object.keys(errors.value).some((k) => k.startsWith(`sections.${index}.`));
}

function addSection(component: ComponentDef): void {
    const section: SectionData = {
        uid: uid(),
        component: component.key,
        is_visible: true,
        content: deepClone(component.defaults),
        style: {},
    };
    const insertAt = currentIndex.value >= 0 ? currentIndex.value + 1 : sections.value.length;
    sections.value.splice(insertAt, 0, section);
    selected.value = section.uid;
    tab.value = 'content';
}

function duplicate(section: SectionData): void {
    const def = components.value[section.component];
    if (def && !def.repeatable) return toast.info('Only one of these sections is allowed per page.');

    const copy: SectionData = { ...deepClone(section), id: undefined, uid: uid() };
    sections.value.splice(sections.value.indexOf(section) + 1, 0, copy);
    selected.value = copy.uid;
}

async function remove(section: SectionData): Promise<void> {
    const ok = await confirm({
        title: `Remove the "${components.value[section.component]?.name}" section?`,
        message: 'Its content will be deleted when you save the page.',
        confirmLabel: 'Remove section',
    });
    if (!ok) return;

    const index = sections.value.indexOf(section);
    sections.value.splice(index, 1);
    selected.value = sections.value[Math.max(0, index - 1)]?.uid ?? 'settings';
}

function isLocked(section: SectionData): boolean {
    return section.component === 'page_listing';
}

async function save(): Promise<boolean> {
    if (!form.value || !page.value) return false;

    saving.value = true;
    errors.value = {};

    try {
        const { data } = await http.put<{ data: PageDetail }>(`/pages/${page.value.id}`, {
            ...form.value,
            sections: sections.value.map(({ uid: _u, ...section }) => section),
        });
        fromDetail(data.data);
        toast.success('Page saved', form.value.status === 'published' ? 'Changes are live on the website.' : 'Saved as a draft.');
        return true;
    } catch (e) {
        const validation = validationErrors(e);
        if (validation) {
            errors.value = validation;
            const firstKey = Object.keys(validation)[0] ?? '';
            const sectionIndex = /^sections\.(\d+)\./.exec(firstKey)?.[1];
            if (sectionIndex !== undefined && sections.value[Number(sectionIndex)]) {
                selected.value = sections.value[Number(sectionIndex)]!.uid;
                tab.value = firstKey.includes('.style.') ? 'style' : 'content';
            } else if (!firstKey.startsWith('sections')) {
                selected.value = 'settings';
            }
            const messages = [...new Set(Object.values(validation).flat())];
            toast.error(
                messages.length === 1 ? 'One field needs fixing' : `${messages.length} fields need fixing`,
                messages.slice(0, 4).join(' ') + (messages.length > 4 ? ` …and ${messages.length - 4} more.` : ''),
            );
        } else {
            toast.error('Could not save the page', errorMessage(e));
        }
        return false;
    } finally {
        saving.value = false;
    }
}

async function preview(): Promise<void> {
    if (!page.value) return;
    if (dirty.value) {
        const ok = await confirm({ title: 'Save before previewing?', message: 'The preview shows the last saved version.', confirmLabel: 'Save and preview', tone: 'primary' });
        if (!ok || !(await save())) return;
    }
    const tabRef = window.open('', '_blank');
    try {
        const { data } = await http.get<{ url: string }>(`/pages/${page.value.id}/preview-url`);
        if (tabRef) tabRef.location.href = data.url;
    } catch (e) {
        tabRef?.close();
        toast.error(errorMessage(e));
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

onBeforeRouteLeave(async () =>
    !dirty.value || confirm({ title: 'Leave without saving?', message: 'Your changes to this page have not been saved.', confirmLabel: 'Leave page' }),
);

watch(selected, () => (tab.value = 'content'));

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', beforeUnload);
    load();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', beforeUnload);
});

const stateTone = { draft: 'neutral', published: 'success', live: 'success', scheduled: 'warning', expired: 'danger' } as const;
</script>

<template>
    <div v-if="!page || !form || !meta" class="flex justify-center py-24 text-brand"><AppSpinner class="size-6" /></div>

    <div v-else>
        <!-- Top bar -->
        <div class="sticky top-16 z-20 -mx-4 mb-6 flex flex-wrap items-center gap-3 border-b border-line bg-canvas/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <RouterLink :to="listPath" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white hover:text-ink" aria-label="Back to the list">
                <ArrowLeft class="size-5" />
            </RouterLink>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h1 class="truncate text-[17px] font-bold text-ink">{{ pickText(form.title, app.defaultLocale) || 'Untitled page' }}</h1>
                    <AppBadge :tone="stateTone[page.state]">{{ page.state }}</AppBadge>
                </div>
                <a :href="page.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[12px] text-ink-muted hover:text-brand">
                    {{ page.url.replace(/^https?:\/\//, '') }} <ExternalLink class="size-3" />
                </a>
            </div>
            <span v-if="dirty" class="hidden text-[13px] font-semibold text-amber-700 sm:inline">Unsaved changes</span>
            <AppButton variant="secondary" :icon="Eye" @click="preview">Preview</AppButton>
            <AppButton v-if="canEdit" :loading="saving" :disabled="!dirty" @click="save">Save</AppButton>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[320px_minmax(0,1fr)] xl:grid-cols-[340px_minmax(0,1fr)]">
            <!-- Outline -->
            <aside class="space-y-3 lg:sticky lg:top-40 lg:max-h-[calc(100dvh-11rem)] lg:self-start lg:overflow-y-auto lg:pr-1">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors"
                    :class="selected === 'settings' ? 'border-brand bg-white shadow-[var(--shadow-card)]' : 'border-line bg-white/70 hover:bg-white'"
                    @click="selected = 'settings'"
                >
                    <span class="flex size-9 items-center justify-center rounded-lg bg-gold/20 text-[#6b5400]"><Settings2 class="size-4.5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-ink">Page settings</span>
                        <span class="block truncate text-[12px]" :class="Object.keys(errors).some((k) => !k.startsWith('sections')) ? 'text-red-600' : 'text-ink-muted'">Title, address, status, SEO</span>
                    </span>
                </button>

                <p class="px-1 pt-2 text-[11px] font-bold tracking-[0.08em] text-ink-muted uppercase">Sections · {{ sections.length }}</p>

                <VueDraggable v-model="sections" handle=".section-handle" :animation="160" :disabled="!canEdit" class="space-y-2">
                    <div
                        v-for="(section, index) in sections"
                        :key="section.uid"
                        class="group flex items-center gap-1 rounded-xl border bg-white py-1.5 pr-1.5 pl-1 transition-[border-color,box-shadow]"
                        :class="[
                            selected === section.uid ? 'border-brand shadow-[var(--shadow-card)]' : sectionHasError(index) ? 'border-red-300' : 'border-line hover:border-[#cbd3df]',
                            !section.is_visible && 'opacity-60',
                        ]"
                    >
                        <button v-if="canEdit" type="button" class="section-handle flex size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas active:cursor-grabbing" aria-label="Drag to reorder">
                            <GripVertical class="size-4" />
                        </button>
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-2.5 py-0.5 text-left" @click="selected = section.uid">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg" :class="selected === section.uid ? 'bg-brand text-white' : 'bg-brand-soft text-brand'">
                                <component :is="componentIcon(components[section.component]?.icon ?? '')" class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5 text-[13px] font-semibold" :class="sectionHasError(index) ? 'text-red-600' : 'text-ink'">
                                    {{ components[section.component]?.name ?? section.component }}
                                    <Lock v-if="isLocked(section)" class="size-3 text-ink-muted" />
                                </span>
                                <span class="block truncate text-[12px] text-ink-muted">{{ sectionTitle(section) }}</span>
                            </span>
                        </button>
                        <button
                            v-if="canEdit"
                            type="button"
                            class="rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink"
                            :aria-label="section.is_visible ? 'Hide section' : 'Show section'"
                            :title="section.is_visible ? 'Hide on the website' : 'Show on the website'"
                            @click="section.is_visible = !section.is_visible"
                        >
                            <Eye v-if="section.is_visible" class="size-4" />
                            <EyeOff v-else class="size-4" />
                        </button>
                        <DropdownMenuRoot v-if="canEdit && !isLocked(section)">
                            <DropdownMenuTrigger class="rounded-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" aria-label="More actions">
                                <MoreHorizontal class="size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuPortal>
                                <DropdownMenuContent align="end" :side-offset="4" class="z-50 min-w-40 rounded-xl border border-line bg-white p-1.5 shadow-[var(--shadow-pop)]">
                                    <DropdownMenuItem class="flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-ink outline-none data-[highlighted]:bg-canvas" @select="duplicate(section)">
                                        <Copy class="size-4 text-ink-muted" /> Duplicate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-[13px] text-red-600 outline-none data-[highlighted]:bg-red-50" @select="remove(section)">
                                        <Trash2 class="size-4" /> Remove
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenuPortal>
                        </DropdownMenuRoot>
                    </div>
                </VueDraggable>

                <button
                    v-if="canEdit"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#cfd7e2] py-3 text-sm font-semibold text-ink-soft transition-colors hover:border-brand/50 hover:bg-white hover:text-brand"
                    @click="pickerOpen = true"
                >
                    <Plus class="size-4" /> Add section
                </button>
                <p class="px-1 text-[12px] leading-relaxed text-ink-muted">Drag sections to reorder them. New sections are added below the selected one. Save with Ctrl + S.</p>
            </aside>

            <!-- Editor -->
            <section class="card min-w-0" :aria-label="current ? 'Section editor' : 'Page settings'">
                <template v-if="selected === 'settings'">
                    <header class="border-b border-line px-6 py-4">
                        <h2 class="text-[15px] font-bold text-ink">Page settings</h2>
                    </header>
                    <fieldset :disabled="!canEdit" class="p-6">
                        <PageSettingsPanel v-model="form" :type="page.type" :is-home="page.system_key === 'home'" :errors="errors" />
                    </fieldset>
                </template>

                <template v-else-if="current && currentDef">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                <component :is="componentIcon(currentDef.icon)" class="size-5" />
                            </span>
                            <div>
                                <h2 class="text-[15px] font-bold text-ink">{{ currentDef.name }}</h2>
                                <p class="text-[12px] text-ink-muted">{{ currentDef.description }}</p>
                            </div>
                        </div>
                        <div v-if="!isLocked(current)" class="flex rounded-lg bg-canvas p-1" role="tablist">
                            <button
                                v-for="option in (['content', 'style'] as const)"
                                :key="option"
                                type="button"
                                role="tab"
                                :aria-selected="tab === option"
                                class="rounded-md px-3.5 py-1.5 text-[13px] font-semibold capitalize transition-colors"
                                :class="tab === option ? 'bg-white text-brand shadow-sm' : 'text-ink-muted hover:text-ink'"
                                @click="tab = option"
                            >
                                {{ option }}
                            </button>
                        </div>
                    </header>

                    <fieldset :disabled="!canEdit" class="p-6">
                        <p v-if="isLocked(current)" class="rounded-xl bg-canvas p-5 text-sm leading-relaxed text-ink-soft">
                            This is where the page's list of items appears (albums, reviews or posts). Move it up or down to place other sections around it.
                            The items themselves are managed in their own section of the admin.
                        </p>
                        <p v-else-if="currentDef.fields.length === 0" class="text-sm text-ink-muted">This section has no settings.</p>
                        <FieldList
                            v-else-if="tab === 'content'"
                            :key="`${current.uid}-content`"
                            v-model="current.content"
                            :fields="currentDef.fields"
                            :errors="errors"
                            :path="`sections.${currentIndex}.content`"
                            :icons="meta.icons"
                        />
                        <div v-else :key="`${current.uid}-style`">
                            <p class="mb-5 rounded-xl bg-canvas px-4 py-3 text-[13px] text-ink-soft">
                                These settings only affect this section on this page. Text colours adjust automatically to the background unless you set one.
                            </p>
                            <FieldList v-model="current.style" :fields="meta.style_fields" :errors="errors" :path="`sections.${currentIndex}.style`" :icons="meta.icons" />
                        </div>
                    </fieldset>
                </template>
            </section>
        </div>

        <ComponentPicker v-model:open="pickerOpen" :components="pickable" :used="sections.map((s) => s.component)" @pick="addSection" />
    </div>
</template>
