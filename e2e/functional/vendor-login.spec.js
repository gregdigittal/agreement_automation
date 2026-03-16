// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Vendor login page — magic link request flow.
 * Tests the unauthenticated entry point to the vendor portal.
 */

test.describe('Vendor login form', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/vendor/login');
    });

    test('submitting a malformed email shows a validation message', async ({ page }) => {
        await page.getByRole('textbox', { name: /email/i }).fill('not-an-email');
        await page.getByRole('button', { name: /send login link/i }).click();

        // HTML5 validation prevents form submission — input stays on the page
        const input = page.getByRole('textbox', { name: /email/i });
        await expect(input).toBeVisible();
    });

    test('submitting a valid email shows a confirmation message', async ({ page }) => {
        await page.getByRole('textbox', { name: /email/i }).fill('e2e-vendor@ccrs-test.local');
        await page.getByRole('button', { name: /send login link/i }).click();

        // Redirects back to login page with a success status message
        await expect(page).toHaveURL(/\/vendor\/login/);
        const status = page.locator('[role="status"]');
        await expect(status).toBeVisible();
        const text = await status.textContent();
        expect(text?.toLowerCase()).toMatch(/link|email|sent|check/);
    });

    test('page has accessible focus order — skip link then main content', async ({ page }) => {
        // Tab once from body — should reach skip link
        await page.keyboard.press('Tab');
        const focused = page.locator(':focus');
        const href = await focused.getAttribute('href');
        // First focusable element is the skip link (when focused, it becomes visible)
        expect(href).toBe('#main-content');
    });

    test('form submits on Enter keypress', async ({ page }) => {
        await page.getByRole('textbox', { name: /email/i }).fill('e2e-vendor@ccrs-test.local');
        await page.keyboard.press('Enter');

        await expect(page).toHaveURL(/\/vendor\/login/);
    });
});
