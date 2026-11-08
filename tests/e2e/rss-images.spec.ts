import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

const PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

/**
 * Regression for the RSS remote-image CSP fix. Feed-controlled image URLs must
 * be served from the signed /rss/image proxy, never hotlinked from the feed's
 * host — otherwise Chromium logs an img-src CSP violation for every row.
 */
test('RSS remote images load through the same-origin proxy with no CSP errors', async ({ page }) => {
    const cspErrors: string[] = [];
    page.on('console', (msg) => {
        if (/Content Security Policy/i.test(msg.text())) cspErrors.push(msg.text());
    });
    page.on('pageerror', (error) => cspErrors.push(error.message));

    // Serve the proxy locally so the browser never makes a real outbound fetch.
    let proxied = 0;
    await page.route('**/rss/image**', async (route) => {
        proxied++;
        await route.fulfill({ status: 200, contentType: 'image/png', body: PNG });
    });

    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');

    await page.goto('/rss');
    const thumb = page.locator('.rss-row__thumb');
    await expect(thumb).toHaveCount(1);
    const src = await thumb.getAttribute('src');
    expect(src).toBeTruthy();
    // The remote URL is carried URL-encoded in the query, but the browser
    // must only ever request our own signed proxy route.
    const imageUrl = new URL(src!, page.url());
    expect(imageUrl.origin).toBe(new URL(page.url()).origin);
    expect(imageUrl.pathname).toBe('/rss/image');
    expect(imageUrl.searchParams.has('signature')).toBe(true);
    await expect(thumb).toBeVisible();

    // Inline article images are rewritten at render time too.
    await page.locator('.rss-row', { hasText: 'Fixture article with a remote image' }).click();
    const fullView = page.getByRole('link', { name: /Full view/i });
    await expect(fullView).toBeVisible();
    await fullView.click();
    await expect(page.locator('.rss-content img')).toHaveAttribute('src', /\/rss\/image\?/);

    await expect.poll(() => proxied).toBeGreaterThan(0);
    expect(cspErrors).toEqual([]);
});
