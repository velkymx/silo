import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Page-level loading flag driven by Inertia visit lifecycle events. Server-
 * rendered data is already present on first paint (so it starts idle), but the
 * flag flips `true` during navigations / partial reloads so a page can show a
 * skeleton instead of stale or empty content.
 */
export function usePageLoading() {
    const loading = ref(false);
    const offs: Array<() => void> = [];

    // Background partial reloads (Inertia `only:` visits, e.g. status polling)
    // must not flash a skeleton over already-rendered content.
    function isPartial(event?: unknown): boolean {
        const only = (event as { detail?: { visit?: { only?: unknown[] } } } | undefined)?.detail?.visit?.only;
        return Array.isArray(only) && only.length > 0;
    }

    onMounted(() => {
        offs.push(router.on('start', (e) => { if (!isPartial(e)) loading.value = true; }));
        offs.push(router.on('finish', () => { loading.value = false; }));
        offs.push(router.on('error', () => { loading.value = false; }));
    });

    onBeforeUnmount(() => {
        offs.forEach((off) => off());
    });

    return { loading };
}
