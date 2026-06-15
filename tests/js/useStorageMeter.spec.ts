import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useStorageMeter } from '@/composables/useStorageMeter';

describe('useStorageMeter', () => {
    it('computes percentage used', () => {
        const { pct } = useStorageMeter(ref({ used: 250, quota: 1000 }));
        expect(pct.value).toBe(25);
    });

    it('returns 0% for unlimited (quota 0)', () => {
        const { pct } = useStorageMeter(ref({ used: 500, quota: 0 }));
        expect(pct.value).toBe(0);
    });

    it('caps at 100%', () => {
        const { pct } = useStorageMeter(ref({ used: 2000, quota: 1000 }));
        expect(pct.value).toBe(100);
    });

    it('escalates the bar variant with usage', () => {
        expect(useStorageMeter(ref({ used: 500, quota: 1000 })).bars.value[0].variant).toBe('success');
        expect(useStorageMeter(ref({ used: 800, quota: 1000 })).bars.value[0].variant).toBe('warning');
        expect(useStorageMeter(ref({ used: 950, quota: 1000 })).bars.value[0].variant).toBe('danger');
    });

    it('reacts to source changes', () => {
        const src = ref({ used: 100, quota: 1000 });
        const { pct } = useStorageMeter(src);
        expect(pct.value).toBe(10);
        src.value = { used: 500, quota: 1000 };
        expect(pct.value).toBe(50);
    });
});
