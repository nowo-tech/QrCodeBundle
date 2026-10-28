import { test, expect } from '@playwright/test';
import { expectQrImagesRendered } from './helpers';

/** REQ-DEMO-013 — QrCode demo happy path (Twig functions, Twig component, PHP service). */
test.describe('QrCode demo', () => {
  test('root redirects to the demo page', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page).toHaveURL(/\/demo$/);
    await expect(page.getByRole('heading', { name: 'QR Code Bundle', level: 1 })).toBeVisible();
  });

  test('every helper renders a PNG QR code', async ({ page }) => {
    await page.goto('/demo');
    await expect(page.getByRole('heading', { name: 'Twig functions' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Twig UX component' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /QrCodeService/ })).toBeVisible();
    await expectQrImagesRendered(page, 7);
  });

  test('profiles produce different sizes (default 300px, compact 128px)', async ({ page }) => {
    await page.goto('/demo');
    await expectQrImagesRendered(page, 7);
    const width = (alt: string) =>
      page.locator(`img[alt="${alt}"]`).evaluate((img: HTMLImageElement) => img.naturalWidth);
    const defaultWidth = await width('default profile QR');
    const compactWidth = await width('compact profile QR');
    expect(defaultWidth).toBeGreaterThanOrEqual(300);
    expect(compactWidth).toBeGreaterThanOrEqual(128);
    expect(compactWidth).toBeLessThan(defaultWidth);
  });
});
