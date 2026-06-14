import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

// A few distinct 1x1 PNGs (different colors) so the gallery has multiple tiles.
const PNGS = [
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC',
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP8z8BQDwAFhQGAVAQ8tQAAAABJRU5ErkJggg==',
];

async function login(page) {
    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/');
}

test('image gallery: lightbox slider, filmstrip on-screen, image not oversized', async ({ page }) => {
    await login(page);

    // Upload three images (they aggregate into Photos).
    const files = PNGS.map((b64, i) => ({
        name: `gallery-${Date.now()}-${i}.png`,
        mimeType: 'image/png',
        buffer: Buffer.from(b64, 'base64'),
    }));
    await page.getByText('Upload', { exact: false }).first().click();
    await page.locator('.modal.show input[type=file]').setInputFiles(files);
    await page.locator('.modal.show button:has-text("Upload")').click();
    await expect(page.getByText('Files uploaded successfully!')).toBeVisible();

    // Go to the Photos gallery.
    await page.goto('/photos');
    const cells = page.locator('.photo-cell');
    await expect(cells.first()).toBeVisible();
    expect(await cells.count()).toBeGreaterThanOrEqual(3);

    // Open the lightbox on the first photo.
    await cells.first().click();
    const modal = page.locator('.modal.show');
    await expect(modal).toBeVisible();

    // Carousel image is visible and NOT taller than the viewport (the bug).
    const img = modal.locator('.lightbox-stage img').first();
    await expect(img).toBeVisible();
    const viewport = page.viewportSize();
    const boxImg = await img.boundingBox();
    expect(boxImg.height).toBeLessThanOrEqual(viewport.height);

    // Filmstrip is rendered, has a thumb per photo, and is on-screen (not clipped off-page).
    const strip = modal.locator('.filmstrip');
    await expect(strip).toBeInViewport();
    const thumbs = modal.locator('.film-thumb');
    expect(await thumbs.count()).toBeGreaterThanOrEqual(3);
    await expect(modal.locator('.film-thumb.active')).toBeInViewport();

    // Next button advances the slide; the active thumb stays on-screen.
    await modal.locator('.nav-next').click();
    await expect(modal.locator('.film-thumb.active')).toBeInViewport();

    // Clicking a filmstrip thumb jumps to it.
    await thumbs.last().click();
    await expect(modal.locator('.film-thumb.active')).toBeInViewport();
});
