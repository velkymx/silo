import { describe, it, expect } from 'vitest';
import { readableTextColor } from '@/lib/contrast';

describe('readableTextColor', () => {
    it('uses dark text on white', () => {
        expect(readableTextColor('#ffffff')).toBe('#212529');
    });

    it('uses light text on black', () => {
        expect(readableTextColor('#000000')).toBe('#ffffff');
    });

    it('uses dark text on a light amber tile', () => {
        expect(readableTextColor('#f59e0b')).toBe('#212529');
    });

    it('uses light text on a dark slate tile', () => {
        expect(readableTextColor('#1e293b')).toBe('#ffffff');
    });

    it('accepts shorthand and missing-hash hex', () => {
        expect(readableTextColor('fff')).toBe('#212529');
        expect(readableTextColor('#000')).toBe('#ffffff');
    });
});
