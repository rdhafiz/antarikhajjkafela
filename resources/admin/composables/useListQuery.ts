import { computed, reactive, ref, watch, type Ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useDebounceFn } from '@vueuse/core';
import { errorMessage, http, type Paginated } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';

type FilterValue = string | number;

/**
 * Server-side list with filters, search and pagination, kept in the address bar
 * (?status=pending&page=2) so refresh and the back button keep the view.
 * Search waits for typing to pause; any other filter change goes back to page 1.
 */
export function useListQuery<Row, Response extends Paginated<Row> = Paginated<Row>>(
    endpoint: string,
    defaults: Record<string, FilterValue>,
    options: { perPage?: number; onLoaded?: (response: Response) => void } = {},
) {
    const route = useRoute();
    const router = useRouter();
    const toast = useToastStore();

    const base: Record<string, FilterValue> = { search: '', ...defaults, page: 1, per_page: options.perPage ?? 20 };
    const filters = reactive<Record<string, FilterValue>>({ ...base });

    // Start from the address bar.
    for (const [key, fallback] of Object.entries(base)) {
        const value = route.query[key];
        if (typeof value === 'string' && value !== '') filters[key] = typeof fallback === 'number' ? Number(value) || fallback : value;
    }

    const rows = ref<Row[]>([]) as Ref<Row[]>;
    const meta = ref<Paginated<Row>['meta'] | null>(null);
    const response = ref<Response | null>(null) as Ref<Response | null>;
    const loading = ref(true);

    /** Filters other than search, page and rows-per-page that differ from their default. */
    const active = computed(() => Object.keys(defaults).filter((key) => key !== 'search' && filters[key] !== defaults[key]).length);

    function params(): Record<string, FilterValue> {
        return Object.fromEntries(Object.entries(filters).filter(([key, value]) => value !== '' && !(key === 'page' && value === 1)));
    }

    let sequence = 0;
    async function load(): Promise<void> {
        const mine = ++sequence;
        loading.value = true;

        const query = Object.fromEntries(Object.entries(params()).filter(([key, value]) => !(key === 'per_page' && value === base.per_page)).map(([k, v]) => [k, String(v)]));
        router.replace({ query });

        try {
            const { data } = await http.get<Response>(endpoint, { params: params() });
            if (mine !== sequence) return;
            rows.value = data.data;
            meta.value = data.meta;
            response.value = data;
            options.onLoaded?.(data);

            // Past the last page (e.g. after deleting): go to the last one.
            if (data.meta.current_page > data.meta.last_page && data.meta.last_page > 0) {
                filters.page = data.meta.last_page;
                return load();
            }
        } catch (e) {
            if (mine === sequence) toast.error(errorMessage(e));
        } finally {
            if (mine === sequence) loading.value = false;
        }
    }

    const searchLater = useDebounceFn(() => {
        filters.page = 1;
        load();
    }, 300);

    watch(() => filters.search, searchLater);
    watch(
        () => Object.keys(defaults).filter((k) => k !== 'search').map((k) => filters[k]),
        () => {
            filters.page = 1;
            load();
        },
    );

    function goTo(page: number): void {
        filters.page = page;
        load();
        document.querySelector('main')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function setPerPage(size: number): void {
        filters.per_page = size;
        filters.page = 1;
        load();
    }

    function clear(): void {
        Object.assign(filters, { ...defaults, search: filters.search });
    }

    return { filters, rows, meta, response, loading, active, load, goTo, setPerPage, clear };
}
