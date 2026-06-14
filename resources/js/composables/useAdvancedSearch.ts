import { ref, type Ref } from 'vue';

export interface AdvFilters {
    search?: string;
    date_from?: string | null;
    date_to?: string | null;
    size_min?: string | number | null;
    size_max?: string | number | null;
    ftype?: string | null;
}

export interface AdvState {
    search: string;
    date_from: string;
    date_to: string;
    size_min: string | number;
    size_max: string | number;
    ftype: string;
    tag: string | number;
}

export const TYPE_OPTIONS = [
    { value: '', text: 'Any type' },
    { value: 'image', text: 'Images' },
    { value: 'video', text: 'Videos' },
    { value: 'audio', text: 'Audio' },
    { value: 'pdf', text: 'PDF' },
    { value: 'document', text: 'Documents' },
    { value: 'spreadsheet', text: 'Spreadsheets' },
    { value: 'archive', text: 'Archives' },
];

/** Advanced (Gmail-style) search form state + querystring builder. */
export function useAdvancedSearch(filters: Ref<AdvFilters>) {
    const advOpen = ref(false);
    const adv = ref<AdvState>({ search: '', date_from: '', date_to: '', size_min: '', size_max: '', ftype: '', tag: '' });

    function openAdvanced(): void {
        const f = filters.value;
        adv.value = {
            search: f.search || '',
            date_from: f.date_from || '',
            date_to: f.date_to || '',
            size_min: f.size_min ?? '',
            size_max: f.size_max ?? '',
            ftype: f.ftype || '',
            tag: '',
        };
        advOpen.value = true;
    }

    /** Only the filled-in filters, ready for the query string. */
    function advParams(): Record<string, string | number> {
        return Object.fromEntries(
            Object.entries(adv.value).filter(([, v]) => v !== '' && v != null),
        ) as Record<string, string | number>;
    }

    return { advOpen, adv, typeOptions: TYPE_OPTIONS, openAdvanced, advParams };
}
