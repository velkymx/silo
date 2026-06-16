import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useBatchRename, type NamedItem } from '@/composables/useBatchRename';

const items = () => ref<NamedItem[]>([
    { id: 1, name: 'IMG_001.jpg' },
    { id: 2, name: 'IMG_002.jpg' },
    { id: 3, name: 'notes' },
]);

describe('useBatchRename', () => {
    it('find & replace preserves the extension; empty find = unchanged', () => {
        const { renameOpts, renamePreview } = useBatchRename(items());
        renameOpts.value.mode = 'replace';
        renameOpts.value.find = 'IMG_';
        renameOpts.value.replace = 'Trip-';
        expect(renamePreview.value.map((r) => r.to)).toEqual(['Trip-001.jpg', 'Trip-002.jpg', 'notes']);

        renameOpts.value.find = '';
        expect(renamePreview.value[0].to).toBe('IMG_001.jpg');
    });

    it('add text before/after the stem, keeping the extension', () => {
        const { renameOpts, renamePreview } = useBatchRename(items());
        renameOpts.value.mode = 'add';
        renameOpts.value.text = '2026-';
        renameOpts.value.position = 'before';
        expect(renamePreview.value[0].to).toBe('2026-IMG_001.jpg');

        renameOpts.value.position = 'after';
        renameOpts.value.text = '-edited';
        expect(renamePreview.value[2].to).toBe('notes-edited');
    });

    it('numbering pads and increments from start, uses base or stem', () => {
        const { renameOpts, renamePreview } = useBatchRename(items());
        renameOpts.value.mode = 'number';
        renameOpts.value.base = 'Photo';
        renameOpts.value.start = 5;
        renameOpts.value.pad = 3;
        expect(renamePreview.value.map((r) => r.to)).toEqual(['Photo005.jpg', 'Photo006.jpg', 'Photo007']);
    });

    it('numbering with no base falls back to the original stem', () => {
        const { renameOpts, renamePreview } = useBatchRename(items());
        renameOpts.value.mode = 'number';
        renameOpts.value.pad = 1;
        expect(renamePreview.value[0].to).toBe('IMG_0011.jpg');
    });
});
