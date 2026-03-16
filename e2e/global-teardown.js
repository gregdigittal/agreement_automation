// @ts-check
import { execFileSync } from 'child_process';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = join(__dirname, '..');

/**
 * Playwright global teardown.
 * Removes all E2E test data created by globalSetup.
 */
export default async function globalTeardown() {
    console.log('[E2E] Cleaning up test data…');

    try {
        execFileSync('php', ['artisan', 'e2e:teardown'], {
            cwd: projectRoot,
            stdio: 'inherit',
            timeout: 15_000,
            env: { ...process.env, APP_ENV: 'e2e' },
        });
        console.log('[E2E] Teardown complete.');
    } catch (err) {
        // Non-fatal — teardown failure should not fail the test run
        console.warn('[E2E] Teardown warning:', err.message);
    }
}
