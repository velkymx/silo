/**
 * Avatar initials from a display name: first letter of the first and last word
 * (e.g. "Mary Jane Watson" → "MW"). A single word yields one letter. Handles
 * extra whitespace and non-ASCII letters gracefully.
 */
export function initials(name: string): string {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '';
    const first = [...parts[0]][0] ?? '';
    const last = parts.length > 1 ? ([...parts[parts.length - 1]][0] ?? '') : '';
    return (first + last).toUpperCase();
}
