import { describe, it, expect } from 'vitest';
import { linkifyNotes } from '@/lib/linkifyNotes';

describe('linkifyNotes', () => {
    it('turns a wikilink into a create-or-open link', () => {
        expect(linkifyNotes('See [[Roadmap]].')).toBe('See [Roadmap](/notes?create=Roadmap).');
    });

    it('uses the alias as the label but the title as the target', () => {
        expect(linkifyNotes('[[Roadmap|the plan]]')).toBe('[the plan](/notes?create=Roadmap)');
    });

    it('url-encodes titles with spaces', () => {
        expect(linkifyNotes('[[Project X]]')).toBe('[Project X](/notes?create=Project%20X)');
    });

    it('bolds mentions', () => {
        expect(linkifyNotes('ping @alice now')).toBe('ping **@alice** now');
    });

    it('leaves code spans untouched', () => {
        expect(linkifyNotes('`[[Nope]]` and ```\n@nobody\n```')).toBe('`[[Nope]]` and ```\n@nobody\n```');
    });

    it('does not bold emails', () => {
        expect(linkifyNotes('a@b.com')).toBe('a@b.com');
    });
});
