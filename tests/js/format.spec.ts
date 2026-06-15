import { describe, it, expect } from 'vitest';
import { fmtBytes } from '@/lib/format';

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
