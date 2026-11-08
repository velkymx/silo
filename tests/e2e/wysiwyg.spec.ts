import { test, expect } from '@playwright/test';

const EMAIL = 'e2e@example.test';
const PASSWORD = 'password';

/**
 * Regression for the VibeUI 1.3 Quill loader wiring in resources/js/app.js.
 * Without `wysiwyg.quillLoader` + `wysiwyg.sanitizer`, VibeFormWysiwyg renders
 * a warning alert instead of the editor. EditorModal uses the same component
 * with the same plugin-level wiring, so this surface proves both.
 */
test('VibeFormWysiwyg loads Quill with the snow toolbar and no loader warning', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await page.goto('/login');
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');

    // Quill is a lazy chunk, so wait for the toolbar instead of racing it.
    await expect(page.locator('.wall-composer .ql-toolbar.ql-snow')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.wall-composer .ql-editor')).toBeVisible();

    await expect(page.getByText(/needs a Quill loader|Failed to load Quill/i)).toHaveCount(0);
    expect(consoleErrors.filter((message) => /quill|content security policy/i.test(message))).toEqual([]);
});
