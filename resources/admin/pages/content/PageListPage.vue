<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CalendarClock, Copy, ExternalLink, FileText, Lock, Megaphone, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import EmptyState from '@admin/components/ui/EmptyState.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import CreatePageModal from './CreatePageModal.vue';
import { pickText, type PageSummary } from './builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';

/**
 * Lists pages. On /admin/pages: built-in and custom pages; on /admin/campaigns: campaign pages.
 */
const route = useRoute();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const isCampaigns = computed(() => route.name === 'campaigns');
const scope = computed(() => (isCampaigns.value ? 'campaigns' : 'pages'));
const builtIn = ref<PageSummary[]>([]);
const custom = ref<PageSummary[]>([]);
const loading = ref(true);
const createOpen = ref(false);

const stateTone = { draft: 'neutral', published: 'success', live: 'success', scheduled: 'warning', expired: 'danger' } as const;
const stateLabel = { draft: 'Draft', published: 'Published', live: 'Live', scheduled: 'Scheduled', expired: 'Expired' } as const;

async function load(): Promise<void> {
    loading.value = true;

    try {
        if (isCampaigns.value) {
            builtIn.value = [];
            custom.value = (await http.get<{ data: PageSummary[] }>('/pages', { params: { type: 'campaign' } })).data.data;
        } else {
            const [defaults, customs] = await Promise.all([
                http.get<{ data: PageSummary[] }>('/pages', { params: { type: 'default' } }),
                http.get<{ data: PageSummary[] }>('/pages', { params: { type: 'custom' } }),
            ]);
            builtIn.value = defaults.data.data;
            custom.value = customs.data.data;
        }
    } catch (e) {
        toast.error(errorMessage(e));
    } finally {
        loading.value = false;
    }
}

async function duplicate(page: PageSummary): Promise<void> {
    try {
        await http.post(`/pages/${page.id}/duplicate`);
        toast.success('Copy created', 'The copy is saved as a draft.');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function remove(page: PageSummary): Promise<void> {
    const ok = await confirm({
        title: `Delete "${pickText(page.title, app.defaultLocale)}"?`,
        message: 'The page and all its sections will be removed. Menu links to it will stop working.',
        confirmLabel: 'Delete page',
    });
    if (!ok) return;

    try {
        await http.delete(`/pages/${page.id}`);
        toast.success('Page deleted');
        load();
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

function schedule(page: PageSummary): string {
    if (!page.starts_at && !page.expires_at) return 'No end date';
    return `${page.starts_at ? formatDate(page.starts_at) : 'Now'} – ${page.expires_at ? formatDate(page.expires_at) : 'no end'}`;
}

watch(() => route.name, load);
onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            :title="isCampaigns ? 'Campaign pages' : 'Pages'"
            :description="isCampaigns ? 'Landing pages for marketing and ads, live between their start and expiry dates.' : 'Built-in website pages and your own pages, each built from sections.'"
        >
            <template #actions>
                <AppButton v-if="auth.can(`${scope}.create`)" :icon="Plus" @click="createOpen = true">{{ isCampaigns ? 'New campaign' : 'New page' }}</AppButton>
            </template>
        </PageHeader>

        <div v-if="loading" class="flex justify-center py-16 text-brand"><AppSpinner class="size-6" /></div>

        <template v-else>
            <template v-if="!isCampaigns">
                <h2 class="mb-3 text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">Built-in pages</h2>
                <div class="mb-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <RouterLink
                        v-for="page in builtIn"
                        :key="page.id"
                        :to="{ name: 'page-editor', params: { id: page.id } }"
                        class="card group flex items-center gap-3.5 p-4 transition-[border-color,box-shadow] hover:border-brand/30 hover:shadow-[var(--shadow-pop)]"
                    >
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand group-hover:bg-brand group-hover:text-white">
                            <FileText class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5 font-semibold text-ink">{{ pickText(page.title, app.defaultLocale) }} <Lock class="size-3 text-ink-muted" /></span>
                            <span class="block text-[12px] text-ink-muted">{{ page.system_key === 'home' ? '/' : `/${page.slug}` }} · {{ page.sections_count }} sections</span>
                        </span>
                        <AppBadge v-if="page.status !== 'published'" tone="neutral">Draft</AppBadge>
                    </RouterLink>
                </div>
                <h2 class="mb-3 text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">Your pages</h2>
            </template>

            <div class="card">
                <EmptyState
                    v-if="!custom.length"
                    :icon="isCampaigns ? Megaphone : FileText"
                    :title="isCampaigns ? 'No campaigns yet' : 'No custom pages yet'"
                    :description="isCampaigns ? 'Create a landing page for your next registration drive or ad campaign.' : 'Create pages such as a refund policy or a Hajj guide.'"
                >
                    <AppButton v-if="auth.can(`${scope}.create`)" :icon="Plus" @click="createOpen = true">{{ isCampaigns ? 'New campaign' : 'New page' }}</AppButton>
                </EmptyState>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-[12px] font-semibold tracking-wide text-ink-muted uppercase">
                                <th class="px-4 py-3">Page</th>
                                <th class="px-4 py-3">Status</th>
                                <th v-if="isCampaigns" class="px-4 py-3">Runs</th>
                                <th class="px-4 py-3">Updated</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="page in custom" :key="page.id" class="hover:bg-canvas/60">
                                <td class="px-4 py-3">
                                    <RouterLink :to="{ name: 'page-editor', params: { id: page.id } }" class="font-semibold text-ink hover:text-brand">
                                        {{ pickText(page.title, app.defaultLocale) }}
                                    </RouterLink>
                                    <a :href="page.url" target="_blank" rel="noopener" class="mt-0.5 flex items-center gap-1 text-[12px] text-ink-muted hover:text-brand">/{{ page.slug }} <ExternalLink class="size-3" /></a>
                                </td>
                                <td class="px-4 py-3"><AppBadge :tone="stateTone[page.state]">{{ stateLabel[page.state] }}</AppBadge></td>
                                <td v-if="isCampaigns" class="px-4 py-3 text-[13px] text-ink-soft">
                                    <span class="inline-flex items-center gap-1.5"><CalendarClock class="size-3.5 text-ink-muted" /> {{ schedule(page) }}</span>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-ink-soft">{{ formatDate(page.updated_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <RouterLink :to="{ name: 'page-editor', params: { id: page.id } }" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" :aria-label="`Edit ${pickText(page.title)}`">
                                            <Pencil class="size-4" />
                                        </RouterLink>
                                        <button v-if="auth.can(`${scope}.create`)" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-white hover:text-brand" :aria-label="`Duplicate ${pickText(page.title)}`" @click="duplicate(page)">
                                            <Copy class="size-4" />
                                        </button>
                                        <button v-if="auth.can(`${scope}.delete`)" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${pickText(page.title)}`" @click="remove(page)">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <CreatePageModal v-model:open="createOpen" :type="isCampaigns ? 'campaign' : 'custom'" />
    </div>
</template>
