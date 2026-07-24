import { describe, it, expect } from 'vitest';
import { FILE_CATEGORIES, FileStatus, BackupStatus, BookmarkStatus } from '@/lib/constants';
import { TYPE_OPTIONS } from '@/composables/useAdvancedSearch';

const EXPECTED_CATEGORIES = ['image', 'video', 'audio', 'pdf', 'document', 'spreadsheet', 'archive'];

describe('FILE_CATEGORIES', () => {
    it('has all 7 expected category keys', () => {
        expect(Object.keys(FILE_CATEGORIES)).toEqual(EXPECTED_CATEGORIES);
    });

    it('only image is previewable', () => {
        for (const [key, cat] of Object.entries(FILE_CATEGORIES)) {
            expect(cat.previewable).toBe(key === 'image');
        }
    });

    it('document and spreadsheet are editable; others are not', () => {
        const editable = ['document', 'spreadsheet'];
        for (const [key, cat] of Object.entries(FILE_CATEGORIES)) {
            expect(cat.editable).toBe(editable.includes(key));
        }
    });

    it('each category has required fields', () => {
        for (const cat of Object.values(FILE_CATEGORIES)) {
            expect(cat).toHaveProperty('label');
            expect(cat).toHaveProperty('icon');
            expect(cat).toHaveProperty('color');
            expect(cat).toHaveProperty('exts');
            expect(Array.isArray(cat.exts)).toBe(true);
        }
    });

    it('image category contains common image exts', () => {
        expect(FILE_CATEGORIES.image.exts).toContain('jpg');
        expect(FILE_CATEGORIES.image.exts).toContain('png');
    });
});

describe('FileStatus', () => {
    it('has correct string values', () => {
        expect(FileStatus.PENDING).toBe('pending');
        expect(FileStatus.INFECTED).toBe('infected');
        expect(FileStatus.FAILED).toBe('failed');
        expect(FileStatus.CLEAN).toBe('clean');
    });
});

describe('BackupStatus', () => {
    it('has correct string values', () => {
        expect(BackupStatus.PENDING).toBe('pending');
        expect(BackupStatus.READY).toBe('ready');
        expect(BackupStatus.FAILED).toBe('failed');
    });
});

describe('BookmarkStatus', () => {
    it('has correct string values', () => {
        expect(BookmarkStatus.PENDING).toBe('pending');
        expect(BookmarkStatus.ALIVE).toBe('alive');
        expect(BookmarkStatus.DEAD).toBe('dead');
    });
});

describe('TYPE_OPTIONS (useAdvancedSearch)', () => {
    it('is derived from FILE_CATEGORIES (same keys, same order)', () => {
        const categoryKeys = Object.keys(FILE_CATEGORIES);
        const optionKeys = TYPE_OPTIONS.filter((o) => o.value !== '').map((o) => o.value);
        expect(optionKeys).toEqual(categoryKeys);
    });
});
