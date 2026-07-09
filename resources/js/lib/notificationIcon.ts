// Map a notification's dotted type to the icon of the area it came from,
// mirroring the GlobalRail icons so a notification is visually traceable to
// its surface (rss.* looks like the RSS rail item, etc.).
const AREA_ICONS: Array<[prefix: string, icon: string]> = [
    ['rss.', 'rss-fill'],
    ['file.', 'device-hdd-fill'],
    ['note.', 'journal-text'],
    ['bookmark.', 'bookmark-fill'],
    ['vault.', 'lock-fill'],
    ['photo.', 'images'],
    ['saved_search.', 'search'],
    ['automation.', 'lightning-charge-fill'],
    ['backup.', 'archive'],
];

export function notificationIcon(type: string | null | undefined): string {
    if (!type) return 'bell-fill';
    const hit = AREA_ICONS.find(([prefix]) => type.startsWith(prefix));
    return hit ? hit[1] : 'bell-fill';
}
