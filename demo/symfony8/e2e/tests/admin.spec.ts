import { test, expect, type Page } from '@playwright/test';

/** REQ-DEMO-013 / REQ-UI-003 — QR profile admin on the UiKit page contract (list + form scaffolds, confirm dialog). */
const ADMIN = '/admin/qr-code-profiles';

function row(page: Page, name: string) {
  return page.locator('[data-testid="qr-code-profiles"] tbody tr').filter({ has: page.locator('code', { hasText: new RegExp(`^${name}$`) }) });
}

async function fillProfile(page: Page, values: { name?: string; size: string; margin: string; errorCorrection: string; allowlist: string }) {
  if (values.name !== undefined) {
    await page.getByLabel('Profile name').fill(values.name);
  }
  await page.getByLabel('Size (px)').fill(values.size);
  await page.getByLabel('Margin (px)').fill(values.margin);
  await page.getByLabel('Error correction').selectOption({ label: values.errorCorrection });
  await page.getByLabel('URL allowlist').fill(values.allowlist);
}

test.describe('QR profile admin', () => {
  test('lists the seeded database profiles with the YAML baseline', async ({ page }) => {
    const response = await page.goto(ADMIN);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'QR Code profiles', level: 1 })).toBeVisible();
    await expect(page.getByTestId('qr-code-yaml-baseline')).toContainText('compact');
    await expect(row(page, 'wallet-pass')).toContainText('Database only');
    await expect(row(page, 'compact')).toContainText('Overrides YAML');
    await expect(page.getByRole('link', { name: 'New profile' })).toBeVisible();
    await expect(page.getByTestId('qr-code-import-yaml')).toBeVisible();
  });

  test('creates, edits and deletes a profile (delete behind the UiKit confirm dialog)', async ({ page }) => {
    const name = `e2e-${Date.now()}`;
    await page.goto(ADMIN);
    await page.getByRole('link', { name: 'New profile' }).click();
    await expect(page.getByRole('heading', { name: 'New QR profile', level: 1 })).toBeVisible();
    await fillProfile(page, { name, size: '220', margin: '5', errorCorrection: 'Quartile (25%)', allowlist: 'shop.example.com\nexample.com/orders/' });
    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page).toHaveURL(new RegExp(`${ADMIN}$`));
    await expect(page.getByRole('alert').filter({ hasText: 'Profile created.' })).toBeVisible();
    await expect(row(page, name)).toContainText('220');
    await expect(row(page, name)).toContainText('shop.example.com');

    await row(page, name).getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByRole('heading', { name: 'Edit QR profile', level: 1 })).toBeVisible();
    await fillProfile(page, { size: '240', margin: '6', errorCorrection: 'High (30%)', allowlist: 'shop.example.com' });
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Profile updated.' })).toBeVisible();
    await expect(row(page, name)).toContainText('240');

    await row(page, name).getByRole('button', { name: 'Delete' }).click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(`The profile "${name}" will be deleted.`);
    await dialog.getByRole('button', { name: 'Delete profile' }).click();
    await expect(page.getByRole('alert').filter({ hasText: `Profile "${name}" deleted.` })).toBeVisible();
    await expect(row(page, name)).toHaveCount(0);
  });

  test('an invalid profile is re-rendered with errors (HTTP 422)', async ({ page }) => {
    await page.goto(`${ADMIN}/new`);
    await fillProfile(page, { name: 'bad name!', size: '10', margin: '5', errorCorrection: 'Low (7%)', allowlist: '' });
    const [response] = await Promise.all([
      page.waitForResponse((r) => r.url().endsWith(`${ADMIN}/new`) && r.request().method() === 'POST'),
      page.getByRole('button', { name: 'Save' }).click(),
    ]);
    expect(response.status()).toBe(422);
    await expect(page.getByText('Use letters, digits, underscore or hyphen.')).toBeVisible();
  });

  test('imports the YAML profiles', async ({ page }) => {
    await page.goto(ADMIN);
    await page.getByTestId('qr-code-import-yaml').click();
    await expect(page.getByRole('alert').filter({ hasText: 'Imported or updated 2 profile(s) from YAML.' })).toBeVisible();
  });

  test('database profiles drive the rendered QR codes', async ({ page }) => {
    await page.goto('/demo');
    await expect(page.getByTestId('nav-admin')).toBeVisible();
    const compactWidth = await page.locator('img[alt="compact profile QR"]').evaluate((img: HTMLImageElement) => img.naturalWidth);
    expect(compactWidth).toBeGreaterThanOrEqual(128);
    expect(compactWidth).toBeLessThan(300);
  });
});
