import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/');
}

test('login lands on the file manager', async ({ page }) => {
    await login(page);
    await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Name' })).toBeVisible();
});

test('upload, preview, and trash round-trip', async ({ page }) => {
    await login(page);

    // Upload a 1x1 PNG.
    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    );
    await page.getByText('Upload', { exact: false }).first().click();
    await page.locator('.modal.show input[type=file]').setInputFiles({
        name: 'e2e.png',
        mimeType: 'image/png',
        buffer: png,
    });
    await page.locator('.modal.show button:has-text("Upload")').click();
    await expect(page.getByText('Files uploaded successfully!')).toBeVisible();

    const row = page.locator('tr', { hasText: 'e2e.png' });
    await expect(row).toBeVisible();

    // Quick Look opens (click the file name) and shows the preview image.
    await row.getByText('e2e.png').click();
    const preview = page.locator('.modal.show img');
    await expect(preview).toBeVisible();
    await page.locator('.modal.show').getByTitle('Close').click();
    await expect(page.locator('.modal.show')).toHaveCount(0); // wait for the modal to fully close

    // Move to trash.
    await row.locator('.dropdown-toggle').click();
    page.once('dialog', (d) => d.accept());
    await page.locator('.dropdown-menu.show >> text=Delete').click();
    await expect(page.getByText('Moved to trash.')).toBeVisible();

    // Restore from trash.
    await page.goto('/trash');
    const trashRow = page.locator('tr', { hasText: 'e2e.png' });
    await expect(trashRow).toBeVisible();
    await trashRow.getByText('Restore').click();
    await expect(page.getByText('Trash is empty.')).toBeVisible();
});

test('admin can open the audit log', async ({ page }) => {
    await login(page);
    await page.goto('/audit');
    await expect(page.getByRole('heading', { name: 'Audit Log' })).toBeVisible();
});

test('admin can create a group', async ({ page }) => {
    await login(page);
    await page.goto('/groups');
    const name = 'E2E Group ' + Date.now();
    await page.locator('input').first().fill(name);
    await page.getByText('Add group', { exact: false }).click();
    await expect(page.getByText(name)).toBeVisible();
});
