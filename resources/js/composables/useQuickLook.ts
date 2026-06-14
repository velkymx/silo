import { ref, computed, type Ref } from 'vue';

export interface QuickLookItem {
    id: number;
}

/**
 * Quick Look preview navigation over a reactive file list.
 *
 * `selectedIndex` is the keyboard "active row" anchor (spacebar opens it,
 * row clicks move it). `quickIndex` is the file currently shown in the
 * preview. Opening a file syncs the active index to it so arrow paging and a
 * subsequent spacebar stay on the same item — fixing the row-click / Quick
 * Look desync (H10).
 */
export function useQuickLook<T extends QuickLookItem>(files: Ref<T[]>) {
    const quickOpen = ref(false);
    const quickIndex = ref(0);
    const selectedIndex = ref(0);

    const quickFile = computed<T | null>(() => files.value[quickIndex.value] ?? null);

    function indexOf(file: T): number {
        const idx = files.value.findIndex((f) => f.id === file.id);
        return idx >= 0 ? idx : 0;
    }

    // Move the active-row anchor without opening (e.g. a row click off the name).
    function setActive(file: T): void {
        selectedIndex.value = indexOf(file);
    }

    function open(file: T): void {
        const idx = indexOf(file);
        quickIndex.value = idx;
        selectedIndex.value = idx;
        quickOpen.value = true;
    }

    // Spacebar: open the preview at the current active row.
    function openAtSelected(): void {
        if (!files.value.length) return;
        quickIndex.value = Math.min(selectedIndex.value, files.value.length - 1);
        quickOpen.value = true;
    }

    function step(delta: number): void {
        if (!files.value.length) return;
        quickIndex.value = (quickIndex.value + delta + files.value.length) % files.value.length;
    }

    function close(): void {
        quickOpen.value = false;
    }

    return { quickOpen, quickIndex, quickFile, selectedIndex, setActive, open, openAtSelected, step, close };
}
