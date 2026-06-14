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

async function newFolder(page, name) {
    await page.locator('.dropdown-toggle:has-text("New")').click();
    await page.locator('.dropdown-menu.show >> text=New Folder').click();
    await page.locator('.modal.show input').first().fill(name);
    await page.locator('.modal.show button:has-text("Create")').click();
    await expect(page.getByText('created successfully', { exact: false })).toBeVisible();
}

test('VSCode-style sidebar tree: expand, reveal child, navigate', async ({ page }) => {
    await login(page);

    const parent = 'Tree-' + Date.now();
    const child = 'Child-' + Date.now();

    // Create a parent folder, navigate into it, create a child folder.
    await newFolder(page, parent);
    await page.locator('tr', { hasText: parent }).getByText(parent).click();
    await expect(page.locator('.breadcrumb')).toContainText(parent);
    await newFolder(page, child);

    // Reload so the lazy tree fetches fresh root children.
    await page.goto('/');

    // Parent folder row is present in the sidebar tree.
    const parentRow = page.locator('.vs-row.folder', { hasText: parent });
    await expect(parentRow).toBeVisible();

    // Expanding the twisty lazily loads + reveals the child folder.
    await parentRow.locator('.vs-twisty').click();
    const childRow = page.locator('.vs-row.folder', { hasText: child });
    await expect(childRow).toBeVisible();

    // Clicking a folder row navigates the main pane (URL gains ?folder=).
    await parentRow.click();
    await expect(page).toHaveURL(/folder=\d+/);
    await expect(page.locator('.breadcrumb')).toContainText(parent);

    // The active folder row is highlighted.
    await expect(page.locator('.vs-row.folder.active', { hasText: parent })).toBeVisible();
});

test('tree lists files inside a folder and opens Quick Look', async ({ page }) => {
    await login(page);

    const folder = 'Files-' + Date.now();
    await newFolder(page, folder);
    await page.locator('tr', { hasText: folder }).getByText(folder).click();
    await expect(page.locator('.breadcrumb')).toContainText(folder);

    // Upload a file into the folder.
    const png = Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'base64',
    );
    await page.getByText('Upload', { exact: false }).first().click();
    await page.locator('.modal.show input[type=file]').setInputFiles({ name: 'in-tree.png', mimeType: 'image/png', buffer: png });
    await page.locator('.modal.show button:has-text("Upload")').click();
    await expect(page.getByText('Files uploaded successfully!')).toBeVisible();

    await page.goto('/');

    // Expand the folder in the tree → the file leaf shows.
    await page.locator('.vs-row.folder', { hasText: folder }).locator('.vs-twisty').click();
    const fileRow = page.locator('.vs-row.file', { hasText: 'in-tree.png' });
    await expect(fileRow).toBeVisible();

    // Clicking the file leaf navigates + opens Quick Look (?open=id).
    await fileRow.click();
    await expect(page.locator('.modal.show img')).toBeVisible();
});
