import { computed, type Ref, type ComputedRef } from 'vue';

interface CategoryFolderOptions {
    /** Section root shown as the tree's top node (e.g. "Bookmarks"). */
    rootName: string;
    rootIcon: string;
    /** Flat category names, already sorted. */
    names: Ref<string[]> | ComputedRef<string[]>;
    /** Item counts keyed by category name. */
    counts: Ref<Record<string, number>> | ComputedRef<Record<string, number>>;
    /** Total item count, shown on the root badge. */
    total: Ref<number> | ComputedRef<number>;
    /** Currently selected category name (null = root / everything). */
    selected: Ref<string | null>;
    /** Called with the picked category name, or null for the root. */
    onPick: (name: string | null) => void;
}

/**
 * Adapts a flat category-string taxonomy (Bookmarks, Vault, Directory) to
 * FolderAccordion's {id, name, parent_id} rows: the section root is node 0
 * and every category hangs off it under a stable positional pseudo-id.
 */
export function useCategoryFolders(opts: CategoryFolderOptions) {
    const ROOT_ID = 0;

    const folders = computed(() => [
        { id: ROOT_ID, name: opts.rootName, parent_id: null as number | null, icon: opts.rootIcon },
        ...opts.names.value.map((name, i) => ({ id: i + 1, name, parent_id: ROOT_ID as number | null })),
    ]);

    const counts = computed<Record<number, number>>(() => {
        const map: Record<number, number> = { [ROOT_ID]: opts.total.value };
        folders.value.forEach((f) => {
            if (f.id !== ROOT_ID) map[f.id] = opts.counts.value[f.name] ?? 0;
        });
        return map;
    });

    const selectedId = computed(() =>
        opts.selected.value === null
            ? ROOT_ID
            : (folders.value.find((f) => f.name === opts.selected.value)?.id ?? null));

    function pickById(id: number): void {
        if (id === ROOT_ID) { opts.onPick(null); return; }
        const folder = folders.value.find((f) => f.id === id);
        if (folder) opts.onPick(folder.name);
    }

    // The root is always expanded so categories are visible on load.
    const openIds = new Set([ROOT_ID]);

    return { folders, counts, selectedId, pickById, openIds };
}
