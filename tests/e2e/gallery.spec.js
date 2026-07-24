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
    // Login redirects to /dashboard; land on the file manager (app root).
    await page.waitForURL('**/dashboard');
    await page.goto('/');
}

test('image gallery: lightbox opens, next works, filmstrip on-screen', async ({ page }) => {
    await login(page);

    // Upload three images (they aggregate into Photos).
    const files = PNGS.map((b64, i) => ({
        name: `gallery-${Date.now()}-${i}.png`,
        mimeType: 'image/png',
        buffer: Buffer.from(b64, 'base64'),
    }));
    await page.getByRole('button', { name: 'Upload' }).first().click();
    await page.locator('.modal.show input[type=file]').setInputFiles(files);
    // Upload auto-starts on file pick; the modal auto-closes when it finishes.
    await expect(page.locator('.modal.show')).toHaveCount(0);

    // Go to the Photos gallery.
    await page.goto('/photos');
    const thumbBtns = page.locator('button.photo-thumb');
    await expect(thumbBtns.first()).toBeVisible();
    expect(await thumbBtns.count()).toBeGreaterThanOrEqual(3);

    // Open the QuickLook lightbox on the first photo.
    await thumbBtns.first().click();
    const modal = page.locator('.modal.show');
    await expect(modal).toBeVisible();

    // Preview image is visible inside the quicklook body.
    const img = modal.locator('.quicklook-body img').first();
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
    await modal.getByRole('button', { name: 'Next file' }).click();
    await expect(modal.locator('.film-thumb.active')).toBeInViewport();

    // Clicking a filmstrip thumb jumps to it.
    await thumbs.last().click();
    await expect(modal.locator('.film-thumb.active')).toBeInViewport();
});
