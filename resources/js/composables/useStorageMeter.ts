import { computed, type Ref } from 'vue';

export interface StorageUsage {
    used: number;
    quota: number;
}

export interface StorageBar {
    value: number;
    variant: 'success' | 'warning' | 'danger';
}

/**
 * Shared storage-quota meter: percentage used (capped, 0 when unlimited) and a
 * VibeProgress bar whose color escalates as the quota fills. Used by both the
 * sidebar meter (AppLayout) and the Files page.
 */
export function useStorageMeter(storage: Ref<StorageUsage>) {
    const pct = computed(() =>
        storage.value.quota > 0
            ? Math.min(100, Math.round((storage.value.used / storage.value.quota) * 100))
            : 0,
    );

    const bars = computed<StorageBar[]>(() => [{
        value: pct.value,
        variant: pct.value > 90 ? 'danger' : pct.value > 75 ? 'warning' : 'success',
    }]);

    return { pct, bars };
}
