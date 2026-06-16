/**
 * Single source of truth for category accent colors, shared by the file
 * grid/list icons (fileTypes), the sidebar tree (FolderTree), and the storage
 * treemap + legend (Storage). Previously duplicated in four places.
 */
export const CATEGORY_COLORS = {
    image: '#10b981',
    pdf: '#ef4444',
    video: '#6366f1',
    audio: '#ec4899',
    archive: '#f59e0b',
    spreadsheet: '#22c55e',
    document: '#3b82f6',
    folder: '#f59e0b',
    other: '#6b7280',
} as const;

export type Category = keyof typeof CATEGORY_COLORS;

export function categoryColor(category: string): string {
    return CATEGORY_COLORS[category as Category] ?? CATEGORY_COLORS.other;
}
