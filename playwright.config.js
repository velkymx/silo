import { defineConfig, devices } from '@playwright/test';

// End-to-end smoke suite. Boots the app on :8811, migrates, and seeds a
// deterministic admin account before the tests run.
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30000,
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8811',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        // Run the queue synchronously so uploads finish processing inline
        // (no background worker needed) and the UI doesn't poll mid-test.
        command:
            'php artisan migrate --force && php artisan db:seed --class=E2ESeeder --force && QUEUE_CONNECTION=sync php artisan serve --port=8811',
        url: 'http://127.0.0.1:8811/login',
        timeout: 60000,
        reuseExistingServer: true,
    },
});
