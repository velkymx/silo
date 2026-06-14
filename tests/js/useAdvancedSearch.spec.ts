import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useAdvancedSearch, type AdvFilters } from '@/composables/useAdvancedSearch';

describe('useAdvancedSearch', () => {
    it('openAdvanced seeds the form from current filters', () => {
        const filters = ref<AdvFilters>({ search: 'report', ftype: 'image', size_min: 5 });
        const { adv, advOpen, openAdvanced } = useAdvancedSearch(filters);
        openAdvanced();
        expect(advOpen.value).toBe(true);
        expect(adv.value.search).toBe('report');
        expect(adv.value.ftype).toBe('image');
        expect(adv.value.size_min).toBe(5);
        expect(adv.value.tag).toBe('');
    });

    it('advParams drops empty/null fields', () => {
        const filters = ref<AdvFilters>({});
        const { adv, advParams } = useAdvancedSearch(filters);
        adv.value.search = 'x';
        adv.value.ftype = 'pdf';
        adv.value.tag = 3;
        adv.value.size_min = '';
        expect(advParams()).toEqual({ search: 'x', ftype: 'pdf', tag: 3 });
    });

    it('exposes the type options', () => {
        const { typeOptions } = useAdvancedSearch(ref({}));
        expect(typeOptions[0]).toEqual({ value: '', text: 'Any type' });
        expect(typeOptions.map((o) => o.value)).toContain('spreadsheet');
    });
});
