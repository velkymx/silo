import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

async function login(page) {
    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    // Login redirects to /dashboard; land on the file manager (app root) so
    // callers start where they expect.
    await page.waitForURL('**/dashboard');
    await page.goto('/');
}

test('login lands on the file manager', async ({ page }) => {
    await login(page);
    await expect(page.getByRole('button', { name: 'Upload' })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Name' })).toBeVisible();
});

test('upload, preview, and trash round-trip', async ({ page }) => {
    await login(page);

    // Unique name per run — the e2e DB is migrated (not reset) between runs, so
    // fixed filenames accumulate and make row locators ambiguous.
    const fname = `e2e-${Date.now()}.png`;
    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    );
    await page.getByRole('button', { name: 'Upload' }).first().click();
    await page.locator('.modal.show input[type=file]').setInputFiles({
        name: fname,
        mimeType: 'image/png',
        buffer: png,
    });
    // Upload auto-starts on file pick; the modal auto-closes and the list
    // refreshes once it finishes.
    await expect(page.locator('.modal.show')).toHaveCount(0);

    const row = page.locator('tr', { hasText: fname });
    await expect(row).toBeVisible();

    // Click the row to open the detail pane, then its primary button opens the
    // Quick Look preview modal (labelled Open for non-editable files).
    await row.getByText(fname).click();
    await page.getByRole('button', { name: /^(Open|Preview)$/ }).click();
    const preview = page.locator('.modal.show img');
    await expect(preview.first()).toBeVisible();
    await page.locator('.modal.show').getByRole('button', { name: /close/i }).first().click();
    await expect(page.locator('.modal.show')).toHaveCount(0); // wait for the modal to fully close

    // Move to trash (confirm via the in-app dialog, not a native window.confirm).
    await row.locator('.dropdown-toggle').click();
    await page.locator('.dropdown-menu.show >> text=Delete').click();
    await page.locator('.modal.show button:has-text("Move to trash")').click();
    await expect(page.getByText(/moved to trash/i).first()).toBeVisible();

    // Restore from trash — the row leaves the trash list.
    await page.goto('/trash');
    const trashRow = page.locator('tr', { hasText: fname });
    await expect(trashRow).toBeVisible();
    // Open the detail pane for the trashed row, then Restore from there.
    await trashRow.getByText(fname).click();
    await page.getByRole('button', { name: 'Restore' }).click();
    await expect(trashRow).toHaveCount(0);
});

test('admin can open the audit log', async ({ page }) => {
    await login(page);
    await page.goto('/audit');
    // ShellPage renders the page title as the active breadcrumb, not a heading.
    await expect(page.getByText('Audit Log').first()).toBeVisible();
});

test('admin can create a group', async ({ page }) => {
    await login(page);
    await page.goto('/groups');
    const name = 'E2E Group ' + Date.now();
    // Group creation is a prompt dialog (usePrompt), opened by the New group CTA.
    await page.getByRole('button', { name: 'New group' }).click();
    await page.locator('.modal.show input').first().fill(name);
    await page.locator('.modal.show').getByRole('button', { name: 'Create' }).click();
    await expect(page.getByText(name).first()).toBeVisible();
});
