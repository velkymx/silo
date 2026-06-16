export interface FolderRef {
    id: number;
    parent_id: number | null;
}

/**
 * All ids in the subtree rooted at `folderId` (inclusive). Builds a
 * parent → children index once, then does a single BFS — O(n) instead of the
 * previous O(n²) repeated full-list scans. A visited set guards against cycles.
 */
export function descendantIds(folderId: number, folders: FolderRef[]): Set<number> {
    const childrenByParent = new Map<number, number[]>();
    for (const f of folders) {
        if (f.parent_id == null) continue;
        const bucket = childrenByParent.get(f.parent_id);
        if (bucket) bucket.push(f.id);
        else childrenByParent.set(f.parent_id, [f.id]);
    }

    const ids = new Set<number>([folderId]);
    const queue: number[] = [folderId];
    while (queue.length) {
        const current = queue.shift() as number;
        for (const child of childrenByParent.get(current) ?? []) {
            if (!ids.has(child)) {
                ids.add(child);
                queue.push(child);
            }
        }
    }
    return ids;
}
