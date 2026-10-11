<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Check, ExternalLink, ImagePlus, Pencil, Play, Star, Trash2, Video } from 'lucide-vue-next';
import AppBadge from '@admin/components/ui/AppBadge.vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSpinner from '@admin/components/ui/AppSpinner.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import MediaDropzone from '@admin/components/media/MediaDropzone.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import MediaPickerModal from '@admin/components/media/MediaPickerModal.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http, validationErrors, type ValidationErrors } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { Media, Translations } from '@admin/types';

interface Item {
    id: number;
    type: 'image' | 'video';
    media_id: number | null;
    thumb: string | null;
    video_url: string | null;
    caption: Translations;
}

interface Album {
    id: number;
    title: Translations;
    slug: string;
    url: string;
    description: Translations;
    cover_id: number | null;
    event_date: string | null;
    is_published: boolean;
    items: Item[];
}

const route = useRoute();
const router = useRouter();
const app = useAppStore();
const auth = useAuthStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const album = ref<Album | null>(null);
const details = ref<Omit<Album, 'items' | 'url'> | null>(null);
const errors = ref<ValidationErrors>({});
const saving = ref(false);
const selected = ref<number[]>([]);
const pickerOpen = ref(false);
const videoOpen = ref(false);
const videoForm = useForm({ video_url: '', caption: {} as Translations });
const captionItem = ref<Item | null>(null);
const captionForm = useForm({ caption: {} as Translations });

const canEdit = computed(() => auth.can('gallery.update'));
const items = computed(() => album.value?.items ?? []);
const allSelected = computed(() => items.value.length > 0 && selected.value.length === items.value.length);

function applyDetails(data: Album): void {
    const { items: _items, url: _url, ...rest } = data;
    details.value = JSON.parse(JSON.stringify(rest));
}

async function load(): Promise<void> {
    try {
        const { data } = await http.get<{ data: Album }>(`/gallery/${route.params.id}`);
        album.value = data.data;
        applyDetails(data.data);
    } catch (e) {
        toast.error(errorMessage(e));
        router.replace('/admin/gallery');
    }
}

async function saveDetails(): Promise<void> {
    if (!details.value || !album.value) return;
    saving.value = true;
    errors.value = {};
    try {
        const { data } = await http.put<{ data: Album }>(`/gallery/${album.value.id}`, { ...details.value, event_date: details.value.event_date || null });
        album.value = { ...album.value, ...data.data, items: album.value.items };
        applyDetails(album.value);
        toast.success('Album saved');
    } catch (e) {
        const validation = validationErrors(e);
        validation ? (errors.value = validation) : toast.error('Could not save the album', errorMessage(e));
    } finally {
        saving.value = false;
    }
}

async function addMedia(ids: number[]): Promise<void> {
    if (!album.value || !ids.length) return;
    try {
        const { data } = await http.post<{ data: Item[] }>(`/gallery/${album.value.id}/items`, { media_ids: ids });
        album.value.items = [...data.data, ...album.value.items];
    } catch (e) {
        toast.error('Could not add the photo', errorMessage(e));
    }
}

// Uploads finish one by one; group those that finish close together into one request.
let pending: number[] = [];
let timer: number | undefined;
function uploaded(media: Media): void {
    pending.push(media.id);
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        const ids = pending;
        pending = [];
        addMedia(ids);
    }, 400);
}

function picked(media: Media): void {
    if (items.value.some((i) => i.media_id === media.id)) {
        toast.info('That photo is already in this album');
        return;
    }
    addMedia([media.id]);
}

async function addVideo(): Promise<void> {
    if (!album.value) return;
    const response = await videoForm.submit(() => http.post<{ data: Item[] }>(`/gallery/${album.value!.id}/items`, videoForm.data));
    if (response && album.value) {
        album.value.items = [...response.data.data, ...album.value.items];
        videoOpen.value = false;
        toast.success('Video added');
    }
}

function editCaption(item: Item): void {
    captionItem.value = item;
    captionForm.reset({ caption: { ...item.caption } });
}

async function saveCaption(): Promise<void> {
    const item = captionItem.value;
    if (!item || !album.value) return;
    const response = await captionForm.submit(() => http.put<{ data: Item }>(`/gallery/${album.value!.id}/items/${item.id}`, captionForm.data));
    if (response && album.value) {
        album.value.items = album.value.items.map((i) => (i.id === item.id ? response.data.data : i));
        captionItem.value = null;
    }
}

function toggle(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((s) => s !== id) : [...selected.value, id];
}

function toggleAll(): void {
    selected.value = allSelected.value ? [] : items.value.map((i) => i.id);
}

async function removeSelected(): Promise<void> {
    if (!album.value || !selected.value.length) return;
    const count = selected.value.length;
    if (!(await confirm({ title: `Remove ${count} item${count > 1 ? 's' : ''} from this album?`, message: 'Photos stay in the media library.', confirmLabel: 'Remove' }))) return;
    try {
        await http.post(`/gallery/${album.value.id}/items/remove`, { ids: selected.value });
        album.value.items = album.value.items.filter((i) => !selected.value.includes(i.id));
        selected.value = [];
        toast.success('Removed from the album');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function setCover(item: Item): Promise<void> {
    if (!details.value || !item.media_id) return;
    details.value.cover_id = item.media_id;
    await saveDetails();
}

async function removeAlbum(): Promise<void> {
    if (!album.value) return;
    if (!(await confirm({ title: 'Delete this album?', message: 'The album disappears from the website. Its photos stay in the media library.', confirmLabel: 'Delete album' }))) return;
    try {
        await http.delete(`/gallery/${album.value.id}`);
        toast.success('Album deleted');
        router.replace('/admin/gallery');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

onMounted(load);
</script>

<template>
    <div v-if="!album || !details" class="flex justify-center py-24 text-brand"><AppSpinner class="size-6" /></div>

    <div v-else>
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <RouterLink to="/admin/gallery" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:bg-white hover:text-ink" aria-label="Back to gallery"><ArrowLeft class="size-5" /></RouterLink>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-[17px] font-bold text-ink">{{ pickText(album.title, app.defaultLocale) }}</h1>
                    <AppBadge :tone="album.is_published ? 'success' : 'neutral'">{{ album.is_published ? 'Published' : 'Hidden' }}</AppBadge>
                </div>
                <a :href="album.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[12px] text-ink-muted hover:text-brand">/gallery/{{ album.slug }} <ExternalLink class="size-3" /></a>
            </div>
            <AppButton v-if="auth.can('gallery.delete')" variant="ghost" :icon="Trash2" @click="removeAlbum">Delete album</AppButton>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <!-- Photos & videos -->
            <section class="card p-5 sm:p-6" aria-labelledby="items-title">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <h2 id="items-title" class="flex-1 text-[15px] font-semibold text-ink">Photos & videos <span class="font-normal text-ink-muted">({{ items.length }})</span></h2>
                    <template v-if="canEdit">
                        <AppButton variant="secondary" size="sm" :icon="ImagePlus" @click="pickerOpen = true">From library</AppButton>
                        <AppButton variant="secondary" size="sm" :icon="Video" @click="videoForm.reset({ video_url: '', caption: {} }); videoOpen = true">Add video</AppButton>
                    </template>
                </div>

                <MediaDropzone v-if="canEdit && auth.can('media.create')" usage="gallery" class="mb-5" @uploaded="uploaded" />

                <div v-if="items.length && canEdit" class="mb-3 flex items-center gap-3 text-[13px]">
                    <button type="button" class="font-semibold text-brand hover:underline" @click="toggleAll">{{ allSelected ? 'Clear selection' : 'Select all' }}</button>
                    <template v-if="selected.length">
                        <span class="text-ink-muted">{{ selected.length }} selected</span>
                        <AppButton variant="danger" size="sm" :icon="Trash2" class="ml-auto" @click="removeSelected">Remove</AppButton>
                    </template>
                </div>

                <p v-if="!items.length" class="rounded-xl bg-canvas px-4 py-10 text-center text-sm text-ink-muted">This album is empty. Upload photos or add a YouTube video; albums without items are not shown on the website.</p>
                <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <li v-for="item in items" :key="item.id" class="group relative overflow-hidden rounded-xl border bg-canvas" :class="selected.includes(item.id) ? 'border-brand ring-2 ring-brand' : 'border-line'">
                        <button type="button" class="relative block aspect-square w-full" :aria-pressed="selected.includes(item.id)" :aria-label="`Select ${item.type}`" :disabled="!canEdit" @click="toggle(item.id)">
                            <img v-if="item.thumb" :src="item.thumb" alt="" class="size-full object-cover" loading="lazy" />
                            <span v-if="item.type === 'video'" class="absolute top-1/2 left-1/2 flex size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-gold text-brand shadow"><Play class="ml-0.5 size-4 fill-current" /></span>
                            <span v-if="canEdit" class="absolute top-2 left-2 flex size-6 items-center justify-center rounded-md border-2 border-white shadow" :class="selected.includes(item.id) ? 'bg-brand' : 'bg-black/20 opacity-0 group-hover:opacity-100'">
                                <Check v-if="selected.includes(item.id)" class="size-3.5 text-white" :stroke-width="3" />
                            </span>
                            <span v-if="item.media_id && item.media_id === details.cover_id" class="absolute top-2 right-2 inline-flex items-center gap-1 rounded-full bg-gold px-2 py-0.5 text-[11px] font-bold text-brand"><Star class="size-3 fill-current" /> Cover</span>
                        </button>
                        <div class="flex items-center gap-1 border-t border-line bg-white px-2 py-1.5">
                            <p class="min-w-0 flex-1 truncate text-[12px] text-ink-soft" :title="pickText(item.caption, app.defaultLocale)">{{ pickText(item.caption, app.defaultLocale) || 'No caption' }}</p>
                            <template v-if="canEdit">
                                <button v-if="item.type === 'image' && item.media_id !== details.cover_id" type="button" class="rounded p-1 text-ink-muted hover:bg-canvas hover:text-brand" aria-label="Use as album cover" title="Use as cover" @click="setCover(item)"><Star class="size-3.5" /></button>
                                <button type="button" class="rounded p-1 text-ink-muted hover:bg-canvas hover:text-brand" aria-label="Edit caption" title="Edit caption" @click="editCaption(item)"><Pencil class="size-3.5" /></button>
                            </template>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Album details -->
            <aside>
                <form class="card space-y-4 p-5" novalidate @submit.prevent="saveDetails">
                    <fieldset :disabled="!canEdit" class="space-y-4">
                        <TranslatableInput v-model="details.title" label="Title" required :errors="errors" error-key="title" />
                        <TranslatableInput v-model="details.description" label="Description" multiline :rows="3" :errors="errors" error-key="description" />
                        <FormField label="Date" for="album-date" :error="errors.event_date?.[0]">
                            <input id="album-date" v-model="details.event_date" type="date" class="control" />
                        </FormField>
                        <FormField label="Web address" for="album-slug" :error="errors.slug?.[0]">
                            <div class="flex items-stretch overflow-hidden rounded-lg border border-line bg-white focus-within:border-brand">
                                <span class="flex items-center border-r border-line bg-canvas px-2.5 text-[13px] text-ink-muted">/gallery/</span>
                                <input id="album-slug" v-model="details.slug" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none" />
                            </div>
                        </FormField>
                        <MediaField v-model="details.cover_id" label="Cover" usage="gallery" aspect="4 / 3" hint="Defaults to the newest photo." />
                        <div class="rounded-lg border border-line p-3.5">
                            <AppSwitch v-model="details.is_published" label="Show on the website" />
                        </div>
                        <AppButton v-if="canEdit" type="submit" block :loading="saving">Save details</AppButton>
                    </fieldset>
                </form>
            </aside>
        </div>

        <MediaPickerModal v-model:open="pickerOpen" usage="gallery" @select="picked" />

        <AppModal v-model:open="videoOpen" title="Add a YouTube video" :persistent="videoForm.processing">
            <form id="video-form" class="space-y-4" novalidate @submit.prevent="addVideo">
                <FormField label="YouTube link" for="video-url" :error="videoForm.error('video_url')" required>
                    <input id="video-url" v-model="videoForm.data.video_url" type="url" class="control" placeholder="https://www.youtube.com/watch?v=…" />
                </FormField>
                <TranslatableInput v-model="videoForm.data.caption" label="Caption" :errors="videoForm.errors" error-key="caption" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="videoOpen = false">Cancel</AppButton>
                <AppButton type="submit" form="video-form" :loading="videoForm.processing">Add video</AppButton>
            </template>
        </AppModal>

        <AppModal :open="captionItem !== null" title="Caption" :persistent="captionForm.processing" @update:open="(v: boolean) => { if (!v) captionItem = null; }">
            <form id="caption-form" novalidate @submit.prevent="saveCaption">
                <TranslatableInput v-model="captionForm.data.caption" label="Caption" :errors="captionForm.errors" error-key="caption" hint="Shown in the lightbox and used as the image description." />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="captionItem = null">Cancel</AppButton>
                <AppButton type="submit" form="caption-form" :loading="captionForm.processing">Save</AppButton>
            </template>
        </AppModal>
    </div>
</template>
