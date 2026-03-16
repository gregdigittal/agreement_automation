// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E configuration for CCRS / DPP Agreement Automation.
 *
 * Prerequisites:
 *   - App server must be reachable at E2E_BASE_URL (default http://127.0.0.1:8000)
 *   - `php artisan e2e:seed` is run via globalSetup before tests
 *
 * Run:
 *   npm run e2e              # headless
 *   npm run e2e:ui           # Playwright UI (local only)
 *   npm run e2e:smoke        # smoke tests only (no fixtures required)
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './e2e',
    testMatch: '**/*.spec.js',

    /* Run tests in parallel within a file; each file runs serially by default */
    fullyParallel: false,
    workers: 1,

    /* Retry once on CI to absorb flakiness */
    retries: process.env.CI ? 1 : 0,

    reporter: process.env.CI ? 'github' : 'list',

    use: {
        baseURL,
        /* Capture screenshot and trace on failure */
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
        /* Keep test isolation — no shared state between specs */
        storageState: undefined,
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    /* Start a Laravel dev server if none is already running */
    webServer: {
        command: 'APP_ENV=e2e php artisan serve --port=8000',
        url: `${baseURL}/up`,
        reuseExistingServer: true,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },

    globalSetup: './e2e/global-setup.js',
    globalTeardown: './e2e/global-teardown.js',

    /* Output artefacts */
    outputDir: 'e2e/test-results',
});
