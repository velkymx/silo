import { describe, it, expect } from 'vitest';
import { typeLabel, colorFor, iconFor, isImageType } from '@/lib/fileTypes';

describe('fileTypes', () => {
    it('typeLabel categorizes by extension + folder', () => {
        expect(typeLabel({ is_dir: true })).toBe('Folder');
        expect(typeLabel({ type: 'png' })).toBe('Image');
        expect(typeLabel({ type: 'pdf' })).toBe('PDF');
        expect(typeLabel({ type: 'mp4' })).toBe('Video');
        expect(typeLabel({ type: 'mp3' })).toBe('Audio');
        expect(typeLabel({ type: 'zip' })).toBe('Archive');
        expect(typeLabel({ type: 'docx' })).toBe('Document');
        expect(typeLabel({ type: 'xlsx' })).toBe('Spreadsheet');
        expect(typeLabel({ type: 'bin' })).toBe('BIN');
        expect(typeLabel({})).toBe('File');
    });

    it('colorFor maps categories to colors', () => {
        expect(colorFor({ is_dir: true })).toBe('#f59e0b');
        expect(colorFor({ type: 'gif' })).toBe('#10b981');
        expect(colorFor({ type: 'pdf' })).toBe('#ef4444');
        expect(colorFor({ type: 'mov' })).toBe('#6366f1');
        expect(colorFor({ type: 'wav' })).toBe('#ec4899');
        expect(colorFor({ type: 'csv' })).toBe('#22c55e');
        expect(colorFor({ type: 'md' })).toBe('#3b82f6');
        expect(colorFor({ type: 'xyz' })).toBe('#6b7280');
    });

    it('iconFor returns bootstrap icon names', () => {
        expect(iconFor('jpeg')).toBe('file-earmark-image');
        expect(iconFor('pdf')).toBe('file-earmark-pdf');
        expect(iconFor('tar')).toBe('file-earmark-zip');
        expect(iconFor('txt')).toBe('file-earmark-text');
        expect(iconFor('exe')).toBe('file-earmark');
        expect(iconFor()).toBe('file-earmark');
    });

    it('isImageType', () => {
        expect(isImageType('png')).toBe(true);
        expect(isImageType('pdf')).toBe(false);
        expect(isImageType()).toBe(false);
    });
});
