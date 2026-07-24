import { ref, type Ref } from 'vue';
import { FILE_CATEGORIES } from '../lib/constants';

export type DateTarget = 'uploaded' | 'edited';
export type SizeUnit = 'KB' | 'MB' | 'GB';
export type SearchScope = 'all' | 'folder';

/** The search filter set as it travels over the wire (sizes in bytes). */
export interface AdvFilters {
    search?: string | null;
    date_target?: DateTarget;
    date_from?: string | null;
    date_to?: string | null;
    size_min?: number | string | null;
    size_max?: number | string | null;
    ftype?: string | null;
    tag?: number | string | null;
    scope?: SearchScope;
}

/** The modal's working form (sizes in the chosen unit). */
export interface AdvState {
    search: string;
    date_target: DateTarget;
    date_from: string;
    date_to: string;
    size_min: number | string;
    size_max: number | string;
    size_unit: SizeUnit;
    ftype: string;
    tag: number | string;
    scope: SearchScope;
}

export const TYPE_OPTIONS = [
    { value: '', text: 'Any type' },
    ...Object.entries(FILE_CATEGORIES).map(([key, cat]) => ({ value: key, text: cat.filterLabel })),
];

export const DATE_TARGET_OPTIONS = [
    { value: 'uploaded', text: 'Date uploaded' },
    { value: 'edited', text: 'Date edited' },
];

export const SIZE_UNIT_OPTIONS = [
    { value: 'KB', text: 'KB' },
    { value: 'MB', text: 'MB' },
    { value: 'GB', text: 'GB' },
];

const UNIT_BYTES: Record<SizeUnit, number> = { KB: 1024, MB: 1024 ** 2, GB: 1024 ** 3 };

/** Convert a value in `unit` to whole bytes, or '' when blank/invalid. */
export function unitToBytes(value: number | string, unit: SizeUnit): number | '' {
    if (value === '' || value == null) return '';
    const n = Number(value);
    if (Number.isNaN(n) || n < 0) return '';
    return Math.round(n * UNIT_BYTES[unit]);
}

/** Pick the largest unit that divides `bytes` evenly (falls back to MB). */
function bestUnit(bytes: number): SizeUnit {
    for (const unit of ['GB', 'MB', 'KB'] as SizeUnit[]) {
        if (bytes >= UNIT_BYTES[unit] && bytes % UNIT_BYTES[unit] === 0) return unit;
    }
    return 'MB';
}

function blankState(): AdvState {
    return {
        search: '',
        date_target: 'uploaded',
        date_from: '',
        date_to: '',
        size_min: '',
        size_max: '',
        size_unit: 'MB',
        ftype: '',
        tag: '',
        scope: 'all',
    };
}

export function useAdvancedSearch(filters: Ref<AdvFilters>) {
    const advOpen = ref(false);
    const adv = ref<AdvState>(blankState());

    /** Seed the form from the current (wire) filters, converting bytes → unit. */
    function openAdvanced(): void {
        const f = filters.value ?? {};
        const minBytes = f.size_min != null && f.size_min !== '' ? Number(f.size_min) : null;
        const maxBytes = f.size_max != null && f.size_max !== '' ? Number(f.size_max) : null;
        const unit = bestUnit(minBytes ?? maxBytes ?? 0);

        adv.value = {
            search: f.search ?? '',
            date_target: f.date_target === 'edited' ? 'edited' : 'uploaded',
            date_from: f.date_from ?? '',
            date_to: f.date_to ?? '',
            size_min: minBytes != null ? minBytes / UNIT_BYTES[unit] : '',
            size_max: maxBytes != null ? maxBytes / UNIT_BYTES[unit] : '',
            size_unit: unit,
            ftype: f.ftype ?? '',
            tag: f.tag ?? '',
            scope: f.scope === 'folder' ? 'folder' : 'all',
        };
        advOpen.value = true;
    }

    /** Filled-in filters only, sizes in bytes — ready for router.get / saving. */
    function advParams(): Record<string, string | number> {
        const s = adv.value;
        const params: Record<string, string | number> = {
            search: typeof s.search === 'string' ? s.search.trim() : s.search,
            date_from: s.date_from,
            date_to: s.date_to,
            size_min: unitToBytes(s.size_min, s.size_unit),
            size_max: unitToBytes(s.size_max, s.size_unit),
            ftype: s.ftype,
            tag: s.tag,
        };
        // A date target only matters when a date is actually set.
        if (s.date_target === 'edited' && (s.date_from || s.date_to)) {
            params.date_target = 'edited';
        }
        if (s.scope === 'folder') {
            params.scope = 'folder';
        }
        return Object.fromEntries(
            Object.entries(params).filter(([, v]) => v !== '' && v != null),
        );
    }

    return { advOpen, adv, typeOptions: TYPE_OPTIONS, openAdvanced, advParams };
}
