<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import {
    Activity,
    BadgeCheck,
    CreditCard,
    Image as ImageIcon,
    Languages,
    Megaphone,
    Paintbrush,
    Palette,
    Phone,
    Search,
    Share2,
    Settings2,
} from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import SettingField from './SettingField.vue';
import ThemePreview from './ThemePreview.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { AdminTheme } from '@admin/types';
import type { SettingFieldDefinition, SettingsGroup, SettingsNavSection } from './types';

const icons: Record<string, unknown> = {
    badge: BadgeCheck,
    palette: Palette,
    languages: Languages,
    phone: Phone,
    share: Share2,
    search: Search,
    activity: Activity,
    megaphone: Megaphone,
    paintbrush: Paintbrush,
    image: ImageIcon,
    'credit-card': CreditCard,
};

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const navigation = ref<SettingsNavSection[]>([]);
const group = ref<SettingsGroup | null>(null);
const loading = ref(true);
const form = useForm({ values: {} as Record<string, unknown> });
const saved = ref('');

const groupKey = computed(() => String(route.params.group ?? ''));
const canEdit = computed(() => auth.can('settings.update'));
const dirty = computed(() => JSON.stringify(form.data.values) !== saved.value);

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

/** Secrets come back as true/false; the inputs start empty. */
function editableValues(values: Record<string, unknown>): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    for (const field of group.value?.fields ?? []) {
        result[field.key] = field.type === 'secret' ? null : JSON.parse(JSON.stringify(values[field.key] ?? null));
    }

    return result;
}

async function loadGroup(key: string): Promise<void> {
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
    if (!group.value) return;

    // Unchanged secrets are left out so the stored value is kept.
    const payload = Object.fromEntries(
        Object.entries(form.data.values).filter(
            ([key, value]) => !(group.value!.fields.find((f) => f.key === key)?.type === 'secret' && !value),
        ),
    );

    const response = await form.submit(() => http.put<{ data: SettingsGroup }>(`/settings/${group.value!.key}`, payload));
    if (!response) return;

    group.value = response.data.data;
    form.reset({ values: editableValues(response.data.data.values) });
    saved.value = JSON.stringify(form.data.values);
    toast.success(`${group.value.label} saved`);

    if (['branding', 'admin_theme', 'media', 'localization'].includes(group.value.key)) {
        await app.load(true);
    }
}

async function confirmDiscard(): Promise<boolean> {
    if (!dirty.value) return true;

    return confirm({
        title: 'Discard unsaved changes?',
        message: 'You have changes in this section that have not been saved.',
        confirmLabel: 'Discard changes',
    });
}

// Admin theme colors preview live while editing.
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

onBeforeRouteUpdate(async (to) => {
    if (!(await confirmDiscard())) return false;
    restoreTheme();
    await loadGroup(String(to.params.group));
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

onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);

    const { data } = await http.get<{ data: SettingsNavSection[] }>('/settings');
    navigation.value = data.data;

    if (!groupKey.value) {
        router.replace({ name: 'settings', params: { group: data.data[0]?.groups[0]?.key ?? 'branding' } });
        return;
    }

    await loadGroup(groupKey.value);
});

onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <div>
        <PageHeader title="Settings" description="Website details, appearance, integrations and payment." />

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
            <!-- Section navigation -->
            <nav class="h-fit lg:sticky lg:top-24" aria-label="Settings sections">
                <div class="scroll-thin -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:block lg:space-y-5 lg:overflow-visible lg:px-0">
                    <div v-for="section in navigation" :key="section.label" class="contents lg:block">
                        <p class="mb-1.5 hidden px-3 text-[11px] font-bold tracking-[0.08em] text-ink-muted uppercase lg:block">{{ section.label }}</p>
                        <RouterLink
                            v-for="item in section.groups"
                            :key="item.key"
                            :to="{ name: 'settings', params: { group: item.key } }"
                            class="flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-[14px] font-medium whitespace-nowrap transition-colors"
                            :class="groupKey === item.key ? 'bg-white text-brand shadow-[var(--shadow-card)] ring-1 ring-line' : 'text-ink-soft hover:bg-white/70 hover:text-ink'"
                        >
                            <component :is="icons[item.icon] ?? Settings2" class="size-4 shrink-0" :class="groupKey === item.key ? 'text-brand' : 'text-ink-muted'" />
                            {{ item.label }}
                        </RouterLink>
                    </div>
                </div>
            </nav>

            <!-- Group form -->
            <div class="min-w-0">
                <div v-if="loading" class="card flex justify-center py-20 text-brand"><AppSpinner class="size-6" /></div>

                <form v-else-if="group" class="card" novalidate @submit.prevent="save">
                    <header class="border-b border-line px-6 py-5">
                        <h2 class="text-[17px] font-bold text-ink">{{ group.label }}</h2>
                        <p v-if="group.description" class="mt-0.5 text-[13px] text-ink-muted">{{ group.description }}</p>
                    </header>

                    <div class="space-y-8 px-6 py-6">
                        <div v-if="group.key === 'theme'" class="max-w-md">
                            <p class="label">Preview</p>
                            <ThemePreview
                                :primary="String(form.data.values.primary_color)"
                                :secondary="String(form.data.values.secondary_color)"
                                :accent="String(form.data.values.accent_color)"
                            />
                        </div>

                        <section v-for="(section, i) in sections" :key="i">
                            <h3 v-if="section.title" class="mb-4 flex items-center gap-3 text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">
                                {{ section.title }}
                                <span class="h-px flex-1 bg-line" />
                            </h3>
                            <div
                                class="grid gap-5"
                                :class="{ 'sm:grid-cols-2': section.fields.every((f) => ['color', 'string', 'phone', 'email', 'url', 'select', 'secret'].includes(f.type) && !f.translatable) && section.fields.length > 1 }"
                            >
                                <SettingField
                                    v-for="field in section.fields"
                                    :key="field.key"
                                    v-model="form.data.values[field.key]"
                                    :field="field"
                                    :errors="form.errors"
                                    :secret-stored="field.type === 'secret' ? group.values[field.key] === true : undefined"
                                />
                            </div>
                        </section>
                    </div>

                    <footer
                        v-if="canEdit"
                        class="sticky bottom-0 flex items-center justify-between gap-3 rounded-b-[var(--radius-card)] border-t border-line bg-white/95 px-6 py-3.5 backdrop-blur"
                    >
                        <p class="text-[13px]" :class="dirty ? 'font-semibold text-amber-700' : 'text-ink-muted'">
                            {{ dirty ? 'You have unsaved changes' : 'All changes saved' }}
                        </p>
                        <div class="flex gap-2">
                            <AppButton v-if="dirty" variant="ghost" :disabled="form.processing" @click="form.reset({ values: JSON.parse(saved) })">Undo</AppButton>
                            <AppButton type="submit" :loading="form.processing" :disabled="!dirty">Save changes</AppButton>
                        </div>
                    </footer>
                </form>
            </div>
        </div>
    </div>
</template>
