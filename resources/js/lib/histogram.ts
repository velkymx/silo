// RGB + luma histograms (256 buckets each) from decoded pixels. The pixel
// pass is pure so it can be unit-tested with a synthetic ImageData; the
// image loader downsamples big photos onto a small canvas first — bucket
// SHAPE, not pixel count, is what the chart shows.

export interface Histogram {
    r: number[];
    g: number[];
    b: number[];
    luma: number[];
}

export function computeHistogram(pixels: { data: Uint8ClampedArray | number[] }): Histogram {
    const r = new Array(256).fill(0);
    const g = new Array(256).fill(0);
    const b = new Array(256).fill(0);
    const luma = new Array(256).fill(0);
    const d = pixels.data;

    for (let i = 0; i + 3 < d.length; i += 4) {
        if (d[i + 3] === 0) continue; // fully transparent pixels carry no color
        r[d[i]]++;
        g[d[i + 1]]++;
        b[d[i + 2]]++;
        luma[Math.min(255, Math.round(0.2126 * d[i] + 0.7152 * d[i + 1] + 0.0722 * d[i + 2]))]++;
    }

    return { r, g, b, luma };
}

/** Load an (same-origin) image URL and histogram a downsampled copy. */
export async function histogramFromUrl(url: string, sample = 256): Promise<Histogram> {
    const img = new Image();
    img.decoding = 'async';
    await new Promise<void>((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject(new Error('image load failed'));
        img.src = url;
    });

    const scale = Math.min(1, sample / Math.max(img.naturalWidth, img.naturalHeight, 1));
    const w = Math.max(1, Math.round(img.naturalWidth * scale));
    const h = Math.max(1, Math.round(img.naturalHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('canvas unavailable');
    ctx.drawImage(img, 0, 0, w, h);

    return computeHistogram(ctx.getImageData(0, 0, w, h));
}
