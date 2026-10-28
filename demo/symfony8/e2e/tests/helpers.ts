import { expect, type Page } from '@playwright/test';

/** Wait until the Symfony Web Debug Toolbar has finished loading (optional outside dev). */
export async function waitForWebProfiler(page: Page): Promise<void> {
  await page.waitForSelector('.sf-toolbar', { state: 'visible', timeout: 20000 }).catch(() => undefined);
  const mini = page.locator('.sf-minitoolbar a').first();
  if (await mini.isVisible().catch(() => false)) {
    await mini.click();
  }
  await page
    .waitForFunction(
      () => {
        const bar = document.querySelector('.sf-toolbar') as HTMLElement | null;
        if (!bar || bar.offsetParent === null) {
          return true;
        }
        return !!document.querySelector('.sf-toolbar-block');
      },
      { timeout: 25000, polling: 250 },
    )
    .catch(() => undefined);
  await new Promise((r) => setTimeout(r, 400));
}

/** Assert every QR image on the page decoded (data: URI PNG with real pixels). */
export async function expectQrImagesRendered(page: Page, minimum: number): Promise<void> {
  const images = page.locator('.qr-cell img');
  await expect(images.first()).toBeVisible();
  expect(await images.count()).toBeGreaterThanOrEqual(minimum);
  await page.waitForFunction(() =>
    Array.from(document.querySelectorAll<HTMLImageElement>('.qr-cell img')).every(
      (img) => img.complete && img.naturalWidth > 0 && img.src.startsWith('data:image/png'),
    ),
  );
}
