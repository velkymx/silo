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
        // --no-reload + PHP_CLI_SERVER_WORKERS gives the built-in server multiple
        // workers. A single worker starves under a real page load (HTML + assets
        // + XHR in flight at once), hanging requests and causing 30s timeouts.
        command:
            'php artisan migrate --force && php artisan db:seed --class=E2ESeeder --force && QUEUE_CONNECTION=sync LOGIN_RATE_LIMIT=1000 PHP_CLI_SERVER_WORKERS=4 php artisan serve --port=8811 --no-reload',
        url: 'http://127.0.0.1:8811/login',
        timeout: 60000,
        reuseExistingServer: true,
    },
});
