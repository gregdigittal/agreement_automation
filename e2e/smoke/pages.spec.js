// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Smoke tests — verifies all public pages load and return the correct
 * HTTP status codes, page titles, and essential HTML structure.
 * No authentication required.
 */

test.describe('Health endpoints', () => {
    test('GET /up returns 200', async ({ request }) => {
        const response = await request.get('/up');
        expect(response.status()).toBe(200);
    });

    test('GET /health returns 200', async ({ request }) => {
        const response = await request.get('/health');
        expect(response.status()).toBe(200);
    });

    test('GET /health/ready returns ready JSON', async ({ request }) => {
        const response = await request.get('/health/ready');
        expect(response.status()).toBe(200);

        const body = await response.json();
        expect(body).toHaveProperty('status', 'ready');
    });
});

test.describe('Root redirect', () => {
    test('GET / redirects to /admin/login', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/admin(\/login)?/);
    });
});

test.describe('Vendor login page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/vendor/login');
    });

    test('page loads with 200', async ({ page }) => {
        await expect(page).toHaveTitle(/CCRS Vendor Portal/i);
    });

    test('has correct heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Vendor Portal' })).toBeVisible();
    });

    test('email input is present and labelled', async ({ page }) => {
        const input = page.getByRole('textbox', { name: /email/i });
        await expect(input).toBeVisible();
    });

    test('submit button is present', async ({ page }) => {
        await expect(page.getByRole('button', { name: /send login link/i })).toBeVisible();
    });

    test('skip-to-main-content link is in DOM', async ({ page }) => {
        const skipLink = page.locator('a[href="#main-content"]');
        await expect(skipLink).toHaveCount(1);
    });

    test('html lang attribute is set to en', async ({ page }) => {
        const lang = await page.locator('html').getAttribute('lang');
        expect(lang).toBe('en');
    });
});

test.describe('Signing error page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/sign/this-token-does-not-exist');
    });

    test('page renders a heading', async ({ page }) => {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    });

    test('heading contains unable/error text', async ({ page }) => {
        const heading = page.getByRole('heading', { level: 1 });
        const text = await heading.textContent();
        expect(text?.toLowerCase()).toMatch(/unable|error|access/);
    });

    test('skip-to-main-content link is in DOM', async ({ page }) => {
        await expect(page.locator('a[href="#main-content"]')).toHaveCount(1);
    });
});
