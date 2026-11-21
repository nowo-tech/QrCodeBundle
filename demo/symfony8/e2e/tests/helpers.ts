import { expect, type Page } from '@playwright/test';

/** Assert every QR image on the page decoded (data: URI PNG with real pixels). */
export async function expectQrImagesRendered(page: Page, minimum: number): Promise<void> {
  const images = page.locator('[data-testid="qr-cell"] img');
  await expect(images.first()).toBeVisible();
  expect(await images.count()).toBeGreaterThanOrEqual(minimum);
  await page.waitForFunction(() =>
    Array.from(document.querySelectorAll<HTMLImageElement>('[data-testid="qr-cell"] img')).every(
      (img) => img.complete && img.naturalWidth > 0 && img.src.startsWith('data:image/png'),
    ),
  );
}
