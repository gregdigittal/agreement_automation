// @ts-check
import { execFileSync } from 'child_process';
import { existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = join(__dirname, '..');

/**
 * Playwright global setup.
 * Seeds the E2E database with fixture data before any tests run.
 * Writes generated tokens/IDs to e2e/fixtures/seeded.json.
 */
export default async function globalSetup() {
    const fixturesDir = join(__dirname, 'fixtures');
    if (!existsSync(fixturesDir)) {
        mkdirSync(fixturesDir, { recursive: true });
    }

    console.log('[E2E] Seeding test database…');

    try {
        execFileSync('php', ['artisan', 'e2e:seed'], {
            cwd: projectRoot,
            stdio: 'inherit',
            timeout: 30_000,
            env: { ...process.env, APP_ENV: 'e2e' },
        });
        console.log('[E2E] Seed complete.');
    } catch (err) {
        console.error('[E2E] Seed command failed:', err.message);
        throw err;
    }
}
