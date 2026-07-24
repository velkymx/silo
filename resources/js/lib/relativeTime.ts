// Compact "N ago" phrasing for the home screen. `now` is injectable so tests
// can pin the clock without touching Date globally.
export function timeAgo(iso: string, now: number = Date.now()): string {
    const then = new Date(iso).getTime();
    const secs = Math.max(0, Math.round((now - then) / 1000));
    if (secs < 60) return 'just now';
    const mins = Math.round(secs / 60);
    if (mins < 60) return `${mins} min ago`;
    const hours = Math.round(mins / 60);
    if (hours < 24) return `${hours} hr ago`;
    const days = Math.round(hours / 24);
    return days === 1 ? 'yesterday' : `${days} days ago`;
}
