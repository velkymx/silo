import { describe, it, expect } from 'vitest';
import { descendantIds } from '@/lib/folderTree';

const folders = [
    { id: 1, parent_id: null },
    { id: 2, parent_id: 1 },
    { id: 3, parent_id: 2 },
    { id: 4, parent_id: 1 },
    { id: 5, parent_id: null },
    { id: 6, parent_id: 5 },
];

describe('descendantIds', () => {
    it('includes the folder and all nested descendants', () => {
        expect([...descendantIds(1, folders)].sort((a, b) => a - b)).toEqual([1, 2, 3, 4]);
    });

    it('returns just the folder when it has no children', () => {
        expect([...descendantIds(3, folders)]).toEqual([3]);
    });

    it('does not cross into sibling subtrees', () => {
        expect([...descendantIds(5, folders)].sort((a, b) => a - b)).toEqual([5, 6]);
    });

    it('tolerates cycles without looping forever', () => {
        const cyclic = [
            { id: 1, parent_id: 2 },
            { id: 2, parent_id: 1 },
        ];
        expect([...descendantIds(1, cyclic)].sort((a, b) => a - b)).toEqual([1, 2]);
    });
});
