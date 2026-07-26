import { expect, Page } from '@playwright/test';

export type StagingConfiguration = {
  normalProductUrl: string;
  rewardedOfferUrl: string;
  postcode: string;
};

export function getStagingConfiguration(): StagingConfiguration {
  const normalProductUrl = process.env.E2E_NORMAL_PRODUCT_URL;
  const rewardedOfferUrl = process.env.E2E_REWARDED_OFFER_URL;
  const postcode = process.env.E2E_POSTCODE;

  if (!normalProductUrl || !rewardedOfferUrl || !postcode) {
    throw new Error(
      'E2E_NORMAL_PRODUCT_URL, E2E_REWARDED_OFFER_URL, and E2E_POSTCODE are required.'
    );
  }

  return { normalProductUrl, rewardedOfferUrl, postcode };
}

export async function addCurrentProductToCart(page: Page, productUrl: string): Promise<void> {
  await page.goto(productUrl, { waitUntil: 'networkidle' });

  const addToCart = page.locator('form.cart button[type="submit"]').first();
  await expect(addToCart).toBeVisible();
  await addToCart.click();

  const successNotice = page
    .locator('.woocommerce-message')
    .filter({ hasText: /added to your cart/i })
    .first();

  await expect(successNotice).toBeVisible();
}

export async function completeCheckout(page: Page, postcode: string): Promise<void> {
  await page.goto('/checkout/', { waitUntil: 'networkidle' });

  await page.locator('#billing_first_name').fill('GalaxyOne');
  await page.locator('#billing_last_name').fill('Quality');
  await page.locator('#billing_email').fill('galaxyone-quality@example.test');
  await page.locator('#billing_phone').fill('9000000000');
  await page.locator('#billing_address_1').fill('1 Quality Street');
  await page.locator('#billing_city').fill('Chennai');
  await page.locator('#billing_postcode').fill(postcode);
  await page.locator('#billing_postcode').blur();

  const deliveryDate = page.locator('#galaxyone_delivery_date');
  await expect(deliveryDate).toBeVisible();
  await deliveryDate.selectOption({ index: 1 });

  const deliverySlot = page.locator('#galaxyone_delivery_slot');
  await expect(deliverySlot).toBeVisible();
  await deliverySlot.selectOption({ index: 1 });

  const paymentMethod = page.locator('input[name="payment_method"]').first();
  await expect(paymentMethod).toBeVisible();
  await paymentMethod.check();

  const placeOrder = page.locator('#place_order');
  await expect(placeOrder).toBeEnabled();
  await placeOrder.click();

  await expect(page).toHaveURL(/order-received|thank-you/i);
}
