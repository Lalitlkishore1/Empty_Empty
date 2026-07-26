import { expect, test } from '@playwright/test';
import { getStagingConfiguration } from './fixtures';

const documentResponseBudgetMs = 2500;

test.describe('GalaxyOne accessibility, mobile, and performance checks', () => {
  test('home, product, cart, and checkout do not overflow horizontally', async ({ page }) => {
    const configuration = getStagingConfiguration();
    const paths = ['/', configuration.normalProductUrl, '/cart/', '/checkout/'];

    for (const path of paths) {
      await page.goto(path, { waitUntil: 'networkidle' });

      const viewportWidth = await page.evaluate(() => window.innerWidth);
      const documentWidth = await page.evaluate(() => document.documentElement.scrollWidth);

      expect(documentWidth).toBeLessThanOrEqual(viewportWidth);
    }
  });

  test('product and checkout controls are keyboard reachable', async ({ page }) => {
    const configuration = getStagingConfiguration();

    await page.goto(configuration.normalProductUrl, { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');

    const productFocusedElement = await page.evaluate(
      () => document.activeElement?.tagName
    );
    expect(['A', 'BUTTON', 'INPUT', 'SELECT']).toContain(productFocusedElement);

    await page.goto('/checkout/', { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');

    const checkoutFocusedElement = await page.evaluate(
      () => document.activeElement?.tagName
    );
    expect(['A', 'BUTTON', 'INPUT', 'SELECT']).toContain(checkoutFocusedElement);
  });

  test('key pages meet the document response budget', async ({ page }) => {
    const configuration = getStagingConfiguration();
    const paths = ['/', configuration.normalProductUrl, '/cart/', '/checkout/'];

    for (const path of paths) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

      expect(response).not.toBeNull();
      expect(response?.status()).toBeLessThan(400);

      const timing = await page.evaluate(() => performance.getEntriesByType('navigation')[0]);
      expect(timing).toBeTruthy();

      const duration = await page.evaluate(() => {
        const entry = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
        return entry.responseStart;
      });

      expect(duration).toBeLessThanOrEqual(documentResponseBudgetMs);
    }
  });
});
