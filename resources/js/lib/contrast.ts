const DARK = '#212529';
const LIGHT = '#ffffff';

function parseHex(hex: string): [number, number, number] {
    let h = hex.replace('#', '').trim();
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    const n = parseInt(h, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function channelLuminance(c: number): number {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
}

/** WCAG relative luminance of an sRGB hex color (0 = black, 1 = white). */
export function relativeLuminance(hex: string): number {
    const [r, g, b] = parseHex(hex);
    return 0.2126 * channelLuminance(r) + 0.7152 * channelLuminance(g) + 0.0722 * channelLuminance(b);
}

/**
 * Pick the foreground (dark or white) with the highest WCAG contrast against a
 * given background. Used for treemap tile labels so light tiles get dark text
 * instead of failing-contrast white.
 */
export function readableTextColor(bgHex: string): string {
    const l = relativeLuminance(bgHex);
    const contrastWhite = 1.05 / (l + 0.05);
    const contrastBlack = (l + 0.05) / 0.05;
    return contrastBlack >= contrastWhite ? DARK : LIGHT;
}
