import { CATEGORY_COLORS } from './categoryColors';
import { FILE_CATEGORIES } from './constants';

export interface TypedItem {
    is_dir?: boolean;
    type?: string;
}

export const imageTypes = FILE_CATEGORIES.image.exts;
export const videoTypes = FILE_CATEGORIES.video.exts;
export const audioTypes = FILE_CATEGORIES.audio.exts;

const archiveTypes = FILE_CATEGORIES.archive.exts;
const docTypes = FILE_CATEGORIES.document.exts;
const sheetTypes = FILE_CATEGORIES.spreadsheet.exts;

function categoryFor(t: string): typeof FILE_CATEGORIES[string] | null {
    return Object.values(FILE_CATEGORIES).find((c) => c.exts.includes(t) || (c.mimePrefix && t.startsWith(c.mimePrefix))) ?? null;
}

/** Human-readable category label for the Type column. */
export function typeLabel(item: TypedItem): string {
    if (item.is_dir) return 'Folder';
    const t = item.type ?? '';
    const cat = categoryFor(t);
    if (cat) return cat.label;
    return t ? t.toUpperCase() : 'File';
}

/** Accent color for an item's icon (shared by grid, list, treemap). */
export function colorFor(item: TypedItem): string {
    if (item.is_dir) return CATEGORY_COLORS.folder;
    const t = item.type ?? '';
    const cat = categoryFor(t);
    return cat ? cat.color : CATEGORY_COLORS.other;
}

/** Bootstrap-icon name for a file extension. */
export function iconFor(type?: string): string {
    const t = type ?? '';
    const cat = categoryFor(t);
    if (cat) return cat.icon;
    return 'file-earmark';
}

export function isImageType(type?: string): boolean {
    return !!type && imageTypes.includes(type);
}
