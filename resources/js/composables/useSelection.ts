import { ref, computed, type Ref } from 'vue';

export interface SelectableItem {
    id: number;
}

/**
 * Finder-style multi-select over a reactive list:
 * - plain click → open (via `onOpen`)
 * - Cmd/Ctrl click or select-mode → toggle
 * - Shift click → range select from the last click
 */
export function useSelection<T extends SelectableItem>(items: Ref<T[]>, onOpen: (item: T) => void) {
    const selectMode = ref(false);
    const selectedIds = ref<Set<number>>(new Set());
    let lastClickedIndex = -1;

    const selectedItems = computed(() => items.value.filter((i) => selectedIds.value.has(i.id)));
    const batchIds = computed(() => [...selectedIds.value]);

    function isSelected(id: number): boolean {
        return selectedIds.value.has(id);
    }

    function toggleSel(id: number): void {
        const next = new Set(selectedIds.value);
        next.has(id) ? next.delete(id) : next.add(id);
        selectedIds.value = next;
    }

    function clearSelection(): void {
        selectedIds.value = new Set();
        selectMode.value = false;
        lastClickedIndex = -1;
    }

    function onItemClick(item: T, event?: MouseEvent): void {
        const idx = items.value.findIndex((i) => i.id === item.id);

        if (event?.shiftKey && lastClickedIndex >= 0) {
            const [a, b] = [lastClickedIndex, idx].sort((x, y) => x - y);
            const next = new Set(selectedIds.value);
            for (let i = a; i <= b; i++) next.add(items.value[i].id);
            selectedIds.value = next;
            return;
        }

        if (selectMode.value || event?.metaKey || event?.ctrlKey) {
            toggleSel(item.id);
            lastClickedIndex = idx;
            return;
        }

        lastClickedIndex = idx;
        onOpen(item);
    }

    return {
        selectMode,
        selectedIds,
        selectedItems,
        batchIds,
        isSelected,
        toggleSel,
        clearSelection,
        onItemClick,
    };
}
