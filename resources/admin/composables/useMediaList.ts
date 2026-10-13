import { reactive, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';
import type { Media } from '@admin/types';

/** Paginated, filterable media list shared by the library page and picker. */
export function useMediaList(initialUsage = '', perPage = 24) {
    const items = ref<Media[]>([]);
    const meta = ref<Paginated<Media>['meta'] | null>(null);
    const loading = ref(false);
    const filters = reactive({ search: '', usage: initialUsage, sort: '', page: 1, per_page: perPage });
    /** How many images each usage has (library page filters). */
    const counts = ref<{ usages: Record<string, number>; total: number } | null>(null);

    async function load(): Promise<void> {
        loading.value = true;

        try {
            const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
            const { data } = await http.get<Paginated<Media> & { counts?: { usages: Record<string, number>; total: number } }>('/media', { params });
            items.value = data.data;
            meta.value = data.meta;
            counts.value = data.counts ?? null;
        } catch (e) {
            useToastStore().error(errorMessage(e));
        } finally {
            loading.value = false;
        }
    }

    const reload = useDebounceFn(() => {
        filters.page = 1;
        load();
    }, 300);

    watch(() => filters.search, reload);
    watch(() => [filters.usage, filters.sort], () => {
        filters.page = 1;
        load();
    });

    function goTo(page: number): void {
        filters.page = page;
        load();
    }

    function setPerPage(size: number): void {
        filters.per_page = size;
        filters.page = 1;
        load();
    }

    function prepend(media: Media): void {
        items.value = [media, ...items.value.filter((m) => m.id !== media.id)];
        if (meta.value) meta.value.total++;
    }

    function replace(media: Media): void {
        items.value = items.value.map((m) => (m.id === media.id ? media : m));
    }

    function remove(id: number): void {
        items.value = items.value.filter((m) => m.id !== id);
        if (meta.value) meta.value.total--;
    }

    return { items, meta, counts, loading, filters, load, goTo, setPerPage, prepend, replace, remove };
}
