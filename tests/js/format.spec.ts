import { describe, it, expect } from 'vitest';
import { fmtBytes, pluralize } from '@/lib/format';

describe('fmtBytes', () => {
    it('returns 0 B for zero/falsy', () => {
        expect(fmtBytes(0)).toBe('0 B');
    });

    it('shows whole bytes without decimals', () => {
        expect(fmtBytes(512)).toBe('512 B');
    });

    it('scales to KB/MB/GB with one decimal', () => {
        expect(fmtBytes(1024)).toBe('1.0 KB');
        expect(fmtBytes(1536)).toBe('1.5 KB');
        expect(fmtBytes(1024 * 1024)).toBe('1.0 MB');
        expect(fmtBytes(1024 ** 3)).toBe('1.0 GB');
    });

    it('caps at TB', () => {
        expect(fmtBytes(1024 ** 5)).toContain('TB');
    });
});

describe('pluralize', () => {
    it('returns singular for 1', () => {
        expect(pluralize(1, 'item')).toBe('1 item');
    });
    it('returns default plural (s) for 0/2+', () => {
        expect(pluralize(0, 'item')).toBe('0 items');
        expect(pluralize(2, 'item')).toBe('2 items');
        expect(pluralize(42, 'item')).toBe('42 items');
    });
    it('accepts an irregular plural', () => {
        expect(pluralize(1, 'person', 'people')).toBe('1 person');
        expect(pluralize(3, 'person', 'people')).toBe('3 people');
    });
});
