// @ts-check
import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const fixtures = JSON.parse(
    readFileSync(join(__dirname, '../fixtures/seeded.json'), 'utf-8'),
);

/**
 * Signing page — functional E2E tests.
 * Requires seeded fixture data (run `php artisan e2e:seed` first via globalSetup).
 */

test.describe('Signing page — page structure', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/sign/${fixtures.signing_token}`);
        // Wait for the page to fully load (PDF viewer may be initialising)
        await page.waitForSelector('#signing-form');
    });

    test('renders contract title in heading', async ({ page }) => {
        const heading = page.getByRole('heading', { level: 1 });
        await expect(heading).toBeVisible();
        const text = await heading.textContent();
        expect(text).toContain(fixtures.contract_title);
    });

    test('shows signer name and email', async ({ page }) => {
        await expect(page.getByText(fixtures.signer_name)).toBeVisible();
        await expect(page.getByText(fixtures.signer_email)).toBeVisible();
    });

    test('signature tablist is present', async ({ page }) => {
        await expect(page.getByRole('tablist', { name: /signature method/i })).toBeVisible();
    });

    test('all four signature tabs are present', async ({ page }) => {
        await expect(page.getByRole('tab', { name: 'Draw' })).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Type' })).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Upload' })).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Camera' })).toBeVisible();
    });

    test('Draw tab is active by default', async ({ page }) => {
        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await expect(drawTab).toHaveAttribute('aria-selected', 'true');
    });

    test('skip-to-main-content link is in DOM', async ({ page }) => {
        await expect(page.locator('a[href="#main-content"]')).toHaveCount(1);
    });
});

test.describe('Signing page — signature tab interactions', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/sign/${fixtures.signing_token}`);
        await page.waitForSelector('#signing-form');
    });

    test('clicking Type tab shows the typed signature input', async ({ page }) => {
        await page.getByRole('tab', { name: 'Type' }).click();

        const typePanel = page.locator('#tab-panel-type');
        await expect(typePanel).toBeVisible();

        const typeInput = page.locator('#typed-signature');
        await expect(typeInput).toBeVisible();
    });

    test('clicking Type tab hides the Draw panel', async ({ page }) => {
        await page.getByRole('tab', { name: 'Type' }).click();

        const drawPanel = page.locator('#tab-panel-draw');
        await expect(drawPanel).not.toBeVisible();
    });

    test('clicking Upload tab shows the upload area', async ({ page }) => {
        await page.getByRole('tab', { name: 'Upload' }).click();

        const uploadPanel = page.locator('#tab-panel-upload');
        await expect(uploadPanel).toBeVisible();
    });

    test('arrow keys navigate between tabs (WAI-ARIA tab pattern)', async ({ page }) => {
        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await drawTab.focus();
        await expect(drawTab).toBeFocused();

        // ArrowRight → Type tab becomes focused and active
        await page.keyboard.press('ArrowRight');
        const typeTab = page.getByRole('tab', { name: 'Type' });
        await expect(typeTab).toBeFocused();
        await expect(typeTab).toHaveAttribute('aria-selected', 'true');
    });

    test('ArrowRight wraps from Camera back to Draw', async ({ page }) => {
        const cameraTab = page.getByRole('tab', { name: 'Camera' });
        await cameraTab.focus();
        await page.keyboard.press('ArrowRight');

        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await expect(drawTab).toBeFocused();
        await expect(drawTab).toHaveAttribute('aria-selected', 'true');
    });

    test('ArrowLeft wraps from Draw back to Camera', async ({ page }) => {
        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await drawTab.focus();
        await page.keyboard.press('ArrowLeft');

        const cameraTab = page.getByRole('tab', { name: 'Camera' });
        await expect(cameraTab).toBeFocused();
    });

    test('Home key moves focus to first tab', async ({ page }) => {
        const cameraTab = page.getByRole('tab', { name: 'Camera' });
        await cameraTab.focus();
        await page.keyboard.press('Home');

        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await expect(drawTab).toBeFocused();
    });

    test('End key moves focus to last tab', async ({ page }) => {
        const drawTab = page.getByRole('tab', { name: 'Draw' });
        await drawTab.focus();
        await page.keyboard.press('End');

        const cameraTab = page.getByRole('tab', { name: 'Camera' });
        await expect(cameraTab).toBeFocused();
    });
});

test.describe('Signing page — typed signature and submission', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/sign/${fixtures.signing_token}`);
        await page.waitForSelector('#signing-form');
    });

    test('can type a name in the Type tab', async ({ page }) => {
        await page.getByRole('tab', { name: 'Type' }).click();

        const typeInput = page.locator('#typed-signature');
        await typeInput.fill('E2E Test Signer');
        await expect(typeInput).toHaveValue('E2E Test Signer');
    });

    test('typed-signature input has autocomplete="name"', async ({ page }) => {
        const attr = await page.locator('#typed-signature').getAttribute('autocomplete');
        expect(attr).toBe('name');
    });
});

test.describe('Signing page — decline modal', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`/sign/${fixtures.signing_token}`);
        await page.waitForSelector('#signing-form');
    });

    test('clicking Decline to Sign opens the modal', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();

        const modal = page.locator('#decline-modal');
        await expect(modal).toBeVisible();
    });

    test('modal has correct role and accessible label', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();

        const modal = page.getByRole('dialog', { name: /decline to sign/i });
        await expect(modal).toBeVisible();
    });

    test('focus moves into modal when opened', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();

        // First focusable element in modal should receive focus
        const modal = page.locator('#decline-modal');
        await expect(modal).toBeVisible();

        const focused = page.locator(':focus');
        // Focus should be inside the modal
        const isInsideModal = await focused.evaluate(
            (el) => !!el.closest('#decline-modal'),
        );
        expect(isInsideModal).toBe(true);
    });

    test('Escape key closes the modal', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();
        await expect(page.locator('#decline-modal')).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.locator('#decline-modal')).not.toBeVisible();
    });

    test('Cancel button closes the modal', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();
        await expect(page.locator('#decline-modal')).toBeVisible();

        await page.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(page.locator('#decline-modal')).not.toBeVisible();
    });

    test('focus returns to Decline button after modal closes', async ({ page }) => {
        const declineBtn = page.getByRole('button', { name: /decline to sign/i });
        await declineBtn.click();
        await page.keyboard.press('Escape');

        await expect(declineBtn).toBeFocused();
    });

    test('clicking backdrop closes the modal', async ({ page }) => {
        await page.getByRole('button', { name: /decline to sign/i }).click();
        await expect(page.locator('#decline-modal')).toBeVisible();

        // Click outside the modal inner container (on the semi-transparent backdrop)
        await page.locator('#decline-modal').click({ position: { x: 5, y: 5 } });
        await expect(page.locator('#decline-modal')).not.toBeVisible();
    });
});
