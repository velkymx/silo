import { ref } from 'vue';
import { http } from '../lib/http';

interface FolderRow { id: number; name: string; is_dir: true; starred: boolean; item_count: number; has_children: boolean }
interface FileRow { id: number; name: string; is_dir: false; type?: string; size: number; starred?: boolean; status?: string }
type Children = { folders: FolderRow[]; files: FileRow[]; capped?: boolean };

/**
 * Lazy-loads and caches a folder's children for the FileTree. State is held in
 * replaced (not mutated) Set/Map refs so Vue tracks changes and the tree
 * re-renders. A fetch failure is NOT cached and rethrows so the caller can
 * surface a toast and leave the folder collapsed + re-expandable.
 */
export function useFileTree() {
    const childrenCache = ref(new Map<number | null, Children>());
    const expanded = ref(new Set<number>());
    const loading = ref(new Set<number>());

    async function fetchChildren(id: number | null): Promise<void> {
        if (childrenCache.value.has(id)) return;
        if (id !== null) loading.value = new Set(loading.value).add(id);
        try {
            const q = id === null ? '' : `?parent=${id}`;
            const res = await http.get<Children>(`/files/tree${q}`);
            // Normalize so a malformed/empty response never breaks consumers.
            const safe: Children = {
                folders: Array.isArray(res?.folders) ? res.folders : [],
                files: Array.isArray(res?.files) ? res.files : [],
                capped: res?.capped ?? false,
            };
            childrenCache.value = new Map(childrenCache.value).set(id, safe);
        } finally {
            if (id !== null) {
                const n = new Set(loading.value);
                n.delete(id);
                loading.value = n;
            }
        }
    }

    async function toggle(id: number): Promise<void> {
        if (expanded.value.has(id)) {
            const n = new Set(expanded.value);
            n.delete(id);
            expanded.value = n;
            return;
        }
        await fetchChildren(id); // rethrows on failure → not expanded, not cached
        expanded.value = new Set(expanded.value).add(id);
    }

    async function ensureRoot(): Promise<void> {
        await fetchChildren(null);
    }

    async function preloadPath(ids: number[]): Promise<void> {
        for (const id of ids) {
            await fetchChildren(id);
            expanded.value = new Set(expanded.value).add(id);
        }
    }

    /** Drop a folder's cached children so the next expand refetches (after a mutation). */
    function invalidate(id: number | null): void {
        if (!childrenCache.value.has(id)) return;
        const m = new Map(childrenCache.value);
        m.delete(id);
        childrenCache.value = m;
    }

    return { childrenCache, expanded, loading, toggle, ensureRoot, preloadPath, invalidate };
}
