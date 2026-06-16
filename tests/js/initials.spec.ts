import { describe, it, expect } from 'vitest';
import { initials } from '@/lib/initials';

describe('initials', () => {
    it('uses first + last name initials', () => {
        expect(initials('Janë Doe')).toBe('JD');
    });

    it('uppercases a single name to one letter', () => {
        expect(initials('alan')).toBe('A');
    });

    it('takes the first and last word of three+ names', () => {
        expect(initials('Mary Jane Watson')).toBe('MW');
    });

    it('collapses extra whitespace', () => {
        expect(initials('  Ada   Lovelace  ')).toBe('AL');
    });

    it('falls back to empty string for blank input', () => {
        expect(initials('')).toBe('');
        expect(initials('   ')).toBe('');
    });
});
