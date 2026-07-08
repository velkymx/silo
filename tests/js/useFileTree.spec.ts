import { describe, it, expect, vi, beforeEach } from 'vitest';

const get = vi.fn();
vi.mock('@/lib/http', () => ({ http: { get: (...a: any[]) => get(...a) } }));

import { useFileTree } from '@/composables/useFileTree';

beforeEach(() => get.mockReset());

describe('useFileTree', () => {
    it('fetches a folder\'s children once on first expand, caches after', async () => {
        get.mockResolvedValue({ folders: [], files: [{ id: 5, name: 'x.md' }] });
        const t = useFileTree();
        await t.toggle(1);
        expect(get).toHaveBeenCalledWith('/files/tree?parent=1');
        expect(t.expanded.value.has(1)).toBe(true);
        expect(t.childrenCache.value.get(1)!.files[0].id).toBe(5);

        await t.toggle(1); // collapse
        expect(t.expanded.value.has(1)).toBe(false);
        await t.toggle(1); // re-expand: no new fetch
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('ensureRoot loads the null bucket once', async () => {
        get.mockResolvedValue({ folders: [{ id: 1, name: 'Docs', has_children: false, item_count: 0 }], files: [] });
        const t = useFileTree();
        await t.ensureRoot();
        await t.ensureRoot();
        expect(get).toHaveBeenCalledTimes(1);
        expect(t.childrenCache.value.get(null)!.folders[0].name).toBe('Docs');
    });

    it('preloadPath fetches + expands each id in order', async () => {
        get.mockResolvedValue({ folders: [], files: [] });
        const t = useFileTree();
        await t.preloadPath([2, 3]);
        expect(get).toHaveBeenCalledWith('/files/tree?parent=2');
        expect(get).toHaveBeenCalledWith('/files/tree?parent=3');
        expect(t.expanded.value.has(2)).toBe(true);
        expect(t.expanded.value.has(3)).toBe(true);
    });
});
