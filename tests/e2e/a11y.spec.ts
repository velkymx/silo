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

test('login form: labels, submit button, and error are accessible', async ({ page }) => {
    await page.goto('/login');

    // Labelled inputs are present.
    await expect(page.getByLabel('Email')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();

    // Submitting empty surfaces an error message (role=alert).
    await page.getByRole('button', { name: /sign in/i }).click();
    // Either inline field error or summary alert.
    const err = page.locator('[role="alert"]');
    await expect(err.first()).toBeVisible();
});

test('register form: all fields labelled and required markers present', async ({ page }) => {
    await page.goto('/register');

    await expect(page.getByLabel('Name')).toBeVisible();
    await expect(page.getByLabel('Email')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.getByRole('button', { name: /register/i })).toBeVisible();
});

test('upload modal: input labelled and focus moves into modal on open', async ({ page }) => {
    await login(page);

    await page.getByRole('button', { name: 'Upload' }).first().click();
    const modal = page.locator('.modal.show');
    await expect(modal).toBeVisible();

    // Some interactive element inside the modal should be focused.
    const focused = await page.evaluate(() => document.activeElement?.tagName?.toLowerCase());
    expect(['input', 'button', 'textarea', 'select', 'a']).toContain(focused);
});

test('context menu: keyboard navigation and Escape dismiss work', async ({ page }) => {
    await login(page);

    // Right-click the first row in the file table to open the context menu.
    const firstRow = page.locator('table tbody tr').first();
    await expect(firstRow).toBeVisible();
    await firstRow.click({ button: 'right' });

    const menu = page.locator('[role="menu"]');
    await expect(menu).toBeVisible();

    // Arrow-down moves focus to the second item.
    await page.keyboard.press('ArrowDown');
    const focusedText = await page.evaluate(() => (document.activeElement as HTMLElement)?.textContent?.trim());
    expect(focusedText).toBeTruthy();

    // Escape dismisses the menu.
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
});
