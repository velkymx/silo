import { describe, it, expect, vi } from 'vitest';
import { ref } from 'vue';
import { useSelection } from '@/composables/useSelection';

const make = () => {
    const items = ref([{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }]);
    const onOpen = vi.fn();
    return { items, onOpen, sel: useSelection(items, onOpen) };
};

describe('useSelection', () => {
    it('plain click opens, does not select', () => {
        const { onOpen, sel } = make();
        sel.onItemClick({ id: 2 });
        expect(onOpen).toHaveBeenCalledWith({ id: 2 });
        expect(sel.selectedIds.value.size).toBe(0);
    });

    it('Cmd/Ctrl click toggles selection', () => {
        const { onOpen, sel } = make();
        sel.onItemClick({ id: 2 }, { metaKey: true } as MouseEvent);
        expect(sel.isSelected(2)).toBe(true);
        expect(onOpen).not.toHaveBeenCalled();
        sel.onItemClick({ id: 2 }, { ctrlKey: true } as MouseEvent);
        expect(sel.isSelected(2)).toBe(false);
    });

    it('select mode toggles on plain click', () => {
        const { onOpen, sel } = make();
        sel.selectMode.value = true;
        sel.onItemClick({ id: 1 });
        sel.onItemClick({ id: 3 });
        expect(sel.batchIds.value.sort()).toEqual([1, 3]);
        expect(onOpen).not.toHaveBeenCalled();
    });

    it('Shift click selects a range from the last click', () => {
        const { sel } = make();
        sel.onItemClick({ id: 1 }, { metaKey: true } as MouseEvent); // anchor at index 0
        sel.onItemClick({ id: 4 }, { shiftKey: true } as MouseEvent); // range 0..3
        expect(sel.batchIds.value.sort()).toEqual([1, 2, 3, 4]);
    });

    it('selectedItems reflects the live list; clearSelection resets', () => {
        const { sel } = make();
        sel.toggleSel(2);
        sel.toggleSel(3);
        expect(sel.selectedItems.value.map((i) => i.id)).toEqual([2, 3]);
        sel.clearSelection();
        expect(sel.selectedIds.value.size).toBe(0);
        expect(sel.selectMode.value).toBe(false);
    });
});
