/**
 * Human-readable byte size (e.g. 1536 → "1.5 KB"). Whole bytes show no decimal;
 * larger units show one. Shared by the storage meter, treemap, upload list, and
 * backup table so they format identically.
 */
export function fmtBytes(n: number): string {
    if (!n) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(i ? 1 : 0)} ${units[i]}`;
}
