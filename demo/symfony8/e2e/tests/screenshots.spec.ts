import { test, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { expectQrImagesRendered, waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

async function shot(page: Page, name: string): Promise<void> {
  await waitForWebProfiler(page);
  await page.screenshot({ path: resolve(outDir, `${name}.png`) });
}

test.describe('QrCode screenshots', () => {
  test.use({ viewport: { width: 1280, height: 1250 } });

  test('qr-codes — Twig functions, Twig component and PHP service output', async ({ page }) => {
    await page.goto('/demo');
    await expectQrImagesRendered(page, 7);
    await shot(page, 'qr-codes');
  });

  test.describe('admin', () => {
    test.use({ viewport: { width: 1280, height: 860 } });

    test('qr-code-profiles — database profiles list (UiKit list scaffold)', async ({ page }) => {
      await page.goto('/admin/qr-code-profiles');
      await page.getByTestId('qr-code-profiles').waitFor();
      await shot(page, 'qr-code-profiles');
    });

    test('qr-code-profile-edit — profile form with the delete zone (UiKit form scaffold)', async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 1120 });
      await page.goto('/admin/qr-code-profiles');
      await page
        .locator('[data-testid="qr-code-profiles"] tbody tr')
        .filter({ has: page.locator('code', { hasText: /^wallet-pass$/ }) })
        .getByRole('link', { name: 'Edit' })
        .click();
      await page.getByTestId('qr-code-profile-form').waitFor();
      await shot(page, 'qr-code-profile-edit');
    });
  });
});
