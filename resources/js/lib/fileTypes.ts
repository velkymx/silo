export interface TypedItem {
    is_dir?: boolean;
    type?: string;
}

export const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
export const videoTypes = ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v'];
export const audioTypes = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'];

const archiveTypes = ['zip', 'rar', '7z', 'tar', 'gz'];
const docTypes = ['doc', 'docx', 'rtf', 'txt', 'md'];
const sheetTypes = ['xls', 'xlsx', 'csv'];

/** Human-readable category label for the Type column. */
export function typeLabel(item: TypedItem): string {
    if (item.is_dir) return 'Folder';
    const t = item.type ?? '';
    if (imageTypes.includes(t)) return 'Image';
    if (t === 'pdf') return 'PDF';
    if (videoTypes.includes(t)) return 'Video';
    if (audioTypes.includes(t)) return 'Audio';
    if (archiveTypes.includes(t)) return 'Archive';
    if (docTypes.includes(t)) return 'Document';
    if (sheetTypes.includes(t)) return 'Spreadsheet';
    return t ? t.toUpperCase() : 'File';
}

/** Accent color for an item's icon (shared by grid, list, treemap). */
export function colorFor(item: TypedItem): string {
    if (item.is_dir) return '#f59e0b';
    const t = item.type ?? '';
    if (imageTypes.includes(t)) return '#10b981';
    if (t === 'pdf') return '#ef4444';
    if (videoTypes.includes(t)) return '#6366f1';
    if (audioTypes.includes(t)) return '#ec4899';
    if (archiveTypes.includes(t)) return '#f59e0b';
    if (sheetTypes.includes(t)) return '#22c55e';
    if (docTypes.includes(t)) return '#3b82f6';
    return '#6b7280';
}

/** Bootstrap-icon name for a file extension. */
export function iconFor(type?: string): string {
    const t = type ?? '';
    if (imageTypes.includes(t)) return 'file-earmark-image';
    if (t === 'pdf') return 'file-earmark-pdf';
    if (archiveTypes.includes(t)) return 'file-earmark-zip';
    if (['doc', 'docx', 'txt', 'md'].includes(t)) return 'file-earmark-text';
    return 'file-earmark';
}

export function isImageType(type?: string): boolean {
    return !!type && imageTypes.includes(type);
}
