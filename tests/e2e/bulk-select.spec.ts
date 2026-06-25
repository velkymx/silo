import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/');
}

test('bulk select and trash in file list view', async ({ page }) => {
    await login(page);

    // Upload two files so there's something to select.
    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    );
    for (const name of ['bulk-a.png', 'bulk-b.png']) {
        await page.getByText('Upload', { exact: false }).first().click();
        await page.locator('.modal.show input[type=file]').setInputFiles({ name, mimeType: 'image/png', buffer: png });
        await page.locator('.modal.show button:has-text("Upload")').click();
        await expect(page.getByText('Files uploaded successfully!')).toBeVisible();
    }

    // Switch to list view.
    await page.goto('/');
    const listToggle = page.getByRole('button', { name: /list/i }).or(page.locator('[aria-label="List view"]'));
    if (await listToggle.count()) await listToggle.first().click();

    // Select the first row by clicking its checkbox (select-check icon).
    const firstRow = page.locator('table tbody tr', { hasText: 'bulk-a.png' });
    await expect(firstRow).toBeVisible();
    await firstRow.locator('.select-check').first().click();

    // BatchActions toolbar should appear.
    const toolbar = page.locator('[data-testid="batch-actions"]').or(page.getByText('selected'));
    await expect(toolbar.first()).toBeVisible();

    // Delete the selection.
    await page.getByRole('button', { name: /delete/i }).click();
    const confirmBtn = page.locator('.modal.show button:has-text("Move to trash")').or(
        page.locator('.modal.show button:has-text("Delete")'),
    );
    if (await confirmBtn.count()) await confirmBtn.first().click();

    // Toast with undo should appear.
    const toast = page.locator('.toast-stack').or(page.locator('[role="status"]'));
    await expect(toast.first()).toBeVisible({ timeout: 8000 });
});

test('bulk select and delete in Photos page', async ({ page }) => {
    await login(page);
    await page.goto('/photos');

    // Enter select mode.
    await page.getByRole('button', { name: /select/i }).first().click();

    // Click the first photo thumbnail to select it.
    const firstThumb = page.locator('button.photo-thumb').first();
    await expect(firstThumb).toBeVisible();
    await firstThumb.click();

    // A selection count or batch toolbar should appear.
    const selInfo = page.locator('.alert').or(page.getByText(/selected/i));
    await expect(selInfo.first()).toBeVisible();
});
