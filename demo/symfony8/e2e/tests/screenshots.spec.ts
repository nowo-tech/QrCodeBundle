import { test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { expectQrImagesRendered, waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshot (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('QrCode screenshots', () => {
  test.use({ viewport: { width: 1280, height: 1250 } });

  test('qr-codes — Twig functions, Twig component and PHP service output', async ({ page }) => {
    await page.goto('/demo');
    await expectQrImagesRendered(page, 7);
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'qr-codes.png') });
  });
});
