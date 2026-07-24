import { describe, it, expect } from 'vitest';
import { notificationIcon } from '@/lib/notificationIcon';

describe('notificationIcon', () => {
    it('maps a type to its area icon (rail parity)', () => {
        expect(notificationIcon('rss.feed.new_items')).toBe('rss-fill');
        expect(notificationIcon('file.uploaded')).toBe('device-hdd-fill');
        expect(notificationIcon('bookmark.dead')).toBe('bookmark-fill');
        expect(notificationIcon('vault.secret.rotated')).toBe('lock-fill');
        expect(notificationIcon('saved_search.new_results')).toBe('search');
        expect(notificationIcon('automation.notification')).toBe('lightning-charge-fill');
    });

    it('falls back to the bell for unknown or missing types', () => {
        expect(notificationIcon('mystery.event')).toBe('bell-fill');
        expect(notificationIcon(null)).toBe('bell-fill');
        expect(notificationIcon(undefined)).toBe('bell-fill');
    });
});
