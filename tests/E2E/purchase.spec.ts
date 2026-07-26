import { expect, test } from '@playwright/test';
import {
  addCurrentProductToCart,
  completeCheckout,
  getStagingConfiguration
} from './fixtures';

test.describe('GalaxyOne purchase flows', () => {
  test('completes a normal-price purchase', async ({ page }) => {
    const configuration = getStagingConfiguration();

    await addCurrentProductToCart(page, configuration.normalProductUrl);
    await completeCheckout(page, configuration.postcode);
  });

  test('completes a staging rewarded-offer purchase', async ({ page }) => {
    const configuration = getStagingConfiguration();

    await page.goto(configuration.rewardedOfferUrl, { waitUntil: 'networkidle' });

    const startReward = page.locator('.galaxyone-rewarded-offer__start');
    await expect(startReward).toBeVisible();
    await startReward.click();

    const completeReward = page.getByRole('button', {
      name: 'Complete staging reward'
    });
    await expect(completeReward).toBeVisible();
    await completeReward.click();

    const rewardStatus = page.locator('.galaxyone-rewarded-offer__status');
    await expect(rewardStatus).toContainText(/unlocked/i);

    await addCurrentProductToCart(page, configuration.rewardedOfferUrl);
    await completeCheckout(page, configuration.postcode);
  });
});
