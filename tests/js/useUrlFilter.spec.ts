import { describe, it, expect, vi, beforeEach } from 'vitest';

const h = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { get: h.get },
}));

const mockGet = h.get;

import { nextTick } from 'vue';
import { useUrlFilter } from '@/composables/useUrlFilter';

beforeEach(() => {
    vi.useFakeTimers();
    mockGet.mockClear();
});

describe('useUrlFilter', () => {
    it('debounces and calls router.get with cleaned query after setFilter', async () => {
        const { setFilter } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: '', type: '' },
        });

        setFilter('search', 'hello');
        await nextTick(); // let watch schedule setTimeout
        expect(mockGet).not.toHaveBeenCalled();

        vi.advanceTimersByTime(250);
        await nextTick();

        expect(mockGet).toHaveBeenCalledWith(
            '/items',
            { search: 'hello' },
            expect.objectContaining({ replace: true }),
        );
    });

    it('omits empty string values from query', async () => {
        const { setFilter } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: 'existing', type: '' },
        });

        setFilter('search', '');
        await nextTick(); // let watch schedule setTimeout
        vi.advanceTimersByTime(250);
        await nextTick();

        expect(mockGet).toHaveBeenCalledOnce();
        const call = mockGet.mock.calls[0];
        expect(call[1]).not.toHaveProperty('search');
        expect(call[1]).not.toHaveProperty('type');
    });

    it('clearFilters resets state and visits basePath with no params', async () => {
        const { filters, clearFilters, isDirty } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: 'hello', type: 'doc' },
        });

        clearFilters();
        await nextTick();

        expect(filters.value.search).toBe('');
        expect(filters.value.type).toBe('');
        expect(isDirty.value).toBe(false);
        expect(mockGet).toHaveBeenCalledWith('/items', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('isDirty is true when initialFilters has non-empty values', () => {
        const { isDirty } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: 'hello' },
        });
        expect(isDirty.value).toBe(true);
    });

    it('isDirty is false when initialFilters are all empty', () => {
        const { isDirty } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: '' },
        });
        expect(isDirty.value).toBe(false);
    });

    it('respects custom debounceMs', async () => {
        const { setFilter } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: '' },
            debounceMs: 500,
        });

        setFilter('search', 'x');
        await nextTick();
        vi.advanceTimersByTime(250);
        await nextTick();
        expect(mockGet).not.toHaveBeenCalled();

        vi.advanceTimersByTime(250);
        await nextTick();
        expect(mockGet).toHaveBeenCalledOnce();
    });

    it('coalesces rapid changes into one request', async () => {
        const { setFilter } = useUrlFilter({
            basePath: '/items',
            initialFilters: { search: '' },
        });

        setFilter('search', 'a');
        await nextTick();
        vi.advanceTimersByTime(100);
        setFilter('search', 'ab');
        await nextTick();
        vi.advanceTimersByTime(100);
        setFilter('search', 'abc');
        await nextTick();
        vi.advanceTimersByTime(250);
        await nextTick();

        expect(mockGet).toHaveBeenCalledOnce();
        expect(mockGet.mock.calls[0][1]).toEqual({ search: 'abc' });
    });
});
