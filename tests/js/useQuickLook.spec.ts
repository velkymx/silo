import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useQuickLook } from '@/composables/useQuickLook';

const files = () => ref([
    { id: 10, name: 'a' },
    { id: 20, name: 'b' },
    { id: 30, name: 'c' },
]);

describe('useQuickLook', () => {
    it('open() shows the clicked file and syncs the active index', () => {
        const ql = useQuickLook(files());
        ql.open({ id: 30, name: 'c' });
        expect(ql.quickOpen.value).toBe(true);
        expect(ql.quickFile.value?.id).toBe(30);
        expect(ql.selectedIndex.value).toBe(2); // active index follows the opened file
    });

    it('setActive() moves the index without opening (row click off the name)', () => {
        const ql = useQuickLook(files());
        ql.setActive({ id: 20, name: 'b' });
        expect(ql.quickOpen.value).toBe(false);
        expect(ql.selectedIndex.value).toBe(1);
    });

    it('openAtSelected() opens at the active row (spacebar)', () => {
        const ql = useQuickLook(files());
        ql.setActive({ id: 20, name: 'b' });
        ql.openAtSelected();
        expect(ql.quickOpen.value).toBe(true);
        expect(ql.quickFile.value?.id).toBe(20);
    });

    it('step() wraps around the list', () => {
        const ql = useQuickLook(files());
        ql.open({ id: 30, name: 'c' }); // index 2
        ql.step(1);
        expect(ql.quickFile.value?.id).toBe(10); // wrapped to 0
        ql.step(-1);
        expect(ql.quickFile.value?.id).toBe(30);
    });

    it('close() hides the preview', () => {
        const ql = useQuickLook(files());
        ql.open({ id: 10, name: 'a' });
        ql.close();
        expect(ql.quickOpen.value).toBe(false);
    });

    it('initialIndex seeds the active + quick indices', () => {
        const ql = useQuickLook(files(), 2);
        expect(ql.selectedIndex.value).toBe(2);
        expect(ql.quickFile.value?.id).toBe(30);
    });

    it('initialIndex is clamped to the file range', () => {
        const ql = useQuickLook(files(), 99);
        expect(ql.selectedIndex.value).toBe(2);
    });
});
