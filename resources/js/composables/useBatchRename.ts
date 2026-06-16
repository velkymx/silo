import { ref, computed, type Ref } from 'vue';

export type RenameMode = 'replace' | 'add' | 'number';

export interface RenameOptions {
    mode: RenameMode;
    find: string;
    replace: string;
    text: string;
    position: 'before' | 'after';
    base: string;
    start: number;
    pad: number;
}

export interface NamedItem {
    id: number;
    name: string;
}

export interface RenamePreviewRow {
    id: number;
    from: string;
    to: string;
}

const defaults = (): RenameOptions => ({
    mode: 'replace', find: '', replace: '', text: '', position: 'before', base: '', start: 1, pad: 2,
});

/** Finder-style batch rename: find/replace, add text (before/after), numbering. */
export function useBatchRename(items: Ref<NamedItem[]>) {
    const renameOpts = ref<RenameOptions>(defaults());

    function splitExt(name: string): [string, string] {
        const i = name.lastIndexOf('.');
        return i > 0 ? [name.slice(0, i), name.slice(i)] : [name, ''];
    }

    function computeName(item: NamedItem, seq: number): string {
        const o = renameOpts.value;
        const [stem, ext] = splitExt(item.name);

        if (o.mode === 'replace') {
            return o.find ? stem.split(o.find).join(o.replace) + ext : item.name;
        }
        if (o.mode === 'add') {
            return (o.position === 'before' ? o.text + stem : stem + o.text) + ext;
        }
        const n = String(o.start + seq).padStart(Math.max(1, o.pad), '0');
        return `${o.base || stem}${n}${ext}`;
    }

    const renamePreview = computed<RenamePreviewRow[]>(() =>
        items.value.map((item, i) => ({ id: item.id, from: item.name, to: computeName(item, i) })),
    );

    return { renameOpts, renamePreview };
}
