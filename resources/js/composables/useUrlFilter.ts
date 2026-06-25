import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

interface UseUrlFilterOptions<T extends Record<string, unknown>> {
    basePath: string;
    initialFilters: T;
    debounceMs?: number;
}

export function useUrlFilter<T extends Record<string, unknown>>({
    basePath,
    initialFilters,
    debounceMs = 250,
}: UseUrlFilterOptions<T>) {
    const filters = ref<T>({ ...initialFilters }) as import('vue').Ref<T>;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const isDirty = ref(
        Object.values(initialFilters).some((v) => v !== '' && v !== null && v !== undefined),
    );

    function push() {
        const params: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(filters.value)) {
            if (v !== '' && v !== null && v !== undefined) {
                params[k] = v;
            }
        }
        isDirty.value = Object.keys(params).length > 0;
        router.get(basePath, params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function setFilter(key: keyof T, value: unknown) {
        (filters.value as Record<string, unknown>)[key as string] = value;
    }

    function clearFilters() {
        for (const k of Object.keys(filters.value)) {
            (filters.value as Record<string, unknown>)[k] = '';
        }
        isDirty.value = false;
        router.get(basePath, {}, { preserveScroll: true });
    }

    watch(
        filters,
        () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(push, debounceMs);
        },
        { deep: true },
    );

    onBeforeUnmount(() => {
        if (timer) clearTimeout(timer);
    });

    return { filters, setFilter, clearFilters, isDirty };
}
