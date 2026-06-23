import { describe, it, expect } from 'vitest';
import { detectTrigger, insertionFor } from '@/lib/noteTriggers';

describe('detectTrigger', () => {
    it('detects an open wikilink token', () => {
        expect(detectTrigger('See [[Road')).toEqual({ type: 'wiki', query: 'Road', matchLen: 6 });
    });

    it('detects an empty wikilink token', () => {
        expect(detectTrigger('start [[')).toEqual({ type: 'wiki', query: '', matchLen: 2 });
    });

    it('stops the wikilink token at the closing bracket', () => {
        expect(detectTrigger('done [[Roadmap]] now')).toBeNull();
    });

    it('detects a mention token', () => {
        expect(detectTrigger('ping @ali')).toEqual({ type: 'mention', query: 'ali', matchLen: 4 });
    });

    it('detects a mention at the start of the line', () => {
        expect(detectTrigger('@bob')).toEqual({ type: 'mention', query: 'bob', matchLen: 4 });
    });

    it('does not trigger inside an email', () => {
        expect(detectTrigger('mail foo@bar')).toBeNull();
    });

    it('returns null with no active trigger', () => {
        expect(detectTrigger('just some text')).toBeNull();
    });
});

describe('insertionFor', () => {
    it('wraps wiki titles in brackets', () => {
        expect(insertionFor('wiki', 'Roadmap')).toBe('[[Roadmap]]');
    });

    it('prefixes mentions with @', () => {
        expect(insertionFor('mention', 'alice')).toBe('@alice');
    });
});
