import { CATEGORY_COLORS } from './categoryColors';

export const FileStatus = {
    PENDING: 'pending',
    INFECTED: 'infected',
    FAILED: 'failed',
    CLEAN: 'clean',
} as const;

export const BackupStatus = {
    PENDING: 'pending',
    READY: 'ready',
    FAILED: 'failed',
} as const;

export const BookmarkStatus = {
    PENDING: 'pending',
    ALIVE: 'alive',
    DEAD: 'dead',
} as const;

export interface FileCategory {
    label: string;
    filterLabel: string;
    icon: string;
    color: string;
    exts: string[];
    mimePrefix?: string;
    previewable: boolean;
    editable: boolean;
}

export const FILE_CATEGORIES: Record<string, FileCategory> = {
    image: {
        label: 'Image',
        filterLabel: 'Images',
        icon: 'file-earmark-image',
        color: CATEGORY_COLORS.image,
        exts: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        mimePrefix: 'image/',
        previewable: true,
        editable: false,
    },
    video: {
        label: 'Video',
        filterLabel: 'Videos',
        icon: 'file-earmark-play',
        color: CATEGORY_COLORS.video,
        exts: ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v'],
        mimePrefix: 'video/',
        previewable: false,
        editable: false,
    },
    audio: {
        label: 'Audio',
        filterLabel: 'Audio',
        icon: 'file-earmark-music',
        color: CATEGORY_COLORS.audio,
        exts: ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'],
        mimePrefix: 'audio/',
        previewable: false,
        editable: false,
    },
    pdf: {
        label: 'PDF',
        filterLabel: 'PDF',
        icon: 'file-earmark-pdf',
        color: CATEGORY_COLORS.pdf,
        exts: ['pdf'],
        previewable: false,
        editable: false,
    },
    document: {
        label: 'Document',
        filterLabel: 'Documents',
        icon: 'file-earmark-text',
        color: CATEGORY_COLORS.document,
        exts: ['doc', 'docx', 'rtf', 'txt', 'md', 'odt'],
        previewable: false,
        editable: true,
    },
    spreadsheet: {
        label: 'Spreadsheet',
        filterLabel: 'Spreadsheets',
        icon: 'file-earmark-spreadsheet',
        color: CATEGORY_COLORS.spreadsheet,
        exts: ['xls', 'xlsx', 'csv', 'ods'],
        previewable: false,
        editable: true,
    },
    archive: {
        label: 'Archive',
        filterLabel: 'Archives',
        icon: 'file-earmark-zip',
        color: CATEGORY_COLORS.archive,
        exts: ['zip', 'rar', '7z', 'tar', 'gz'],
        previewable: false,
        editable: false,
    },
};
