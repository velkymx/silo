import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useAdvancedSearch, type AdvFilters } from '@/composables/useAdvancedSearch';

const MB = 1024 * 1024;

describe('useAdvancedSearch', () => {
    it('openAdvanced seeds the form from current filters (bytes → unit)', () => {
        const filters = ref<AdvFilters>({ search: 'report', ftype: 'image', size_min: 5 * MB });
        const { adv, advOpen, openAdvanced } = useAdvancedSearch(filters);
        openAdvanced();
        expect(advOpen.value).toBe(true);
        expect(adv.value.search).toBe('report');
        expect(adv.value.ftype).toBe('image');
        expect(adv.value.size_min).toBe(5);
        expect(adv.value.size_unit).toBe('MB');
        expect(adv.value.tag).toBe('');
        expect(adv.value.scope).toBe('all');
    });

    it('advParams drops empty fields and converts size to bytes', () => {
        const filters = ref<AdvFilters>({});
        const { adv, advParams } = useAdvancedSearch(filters);
        adv.value.search = 'x';
        adv.value.ftype = 'pdf';
        adv.value.tag = 3;
        adv.value.size_min = '';
        adv.value.size_max = 2;
        adv.value.size_unit = 'MB';
        expect(advParams()).toEqual({ search: 'x', ftype: 'pdf', tag: 3, size_max: 2 * MB });
    });

    it('only emits date_target when a date is set', () => {
        const { adv, advParams } = useAdvancedSearch(ref({}));
        adv.value.date_target = 'edited';
        expect(advParams().date_target).toBeUndefined();
        adv.value.date_from = '2026-01-01';
        expect(advParams().date_target).toBe('edited');
    });

    it('emits scope=folder only when chosen', () => {
        const { adv, advParams } = useAdvancedSearch(ref({}));
        expect(advParams().scope).toBeUndefined();
        adv.value.scope = 'folder';
        expect(advParams().scope).toBe('folder');
    });

    it('exposes the type options', () => {
        const { typeOptions } = useAdvancedSearch(ref({}));
        expect(typeOptions[0]).toEqual({ value: '', text: 'Any type' });
        expect(typeOptions.map((o) => o.value)).toContain('spreadsheet');
    });
});
