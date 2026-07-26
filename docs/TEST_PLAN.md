# GalaxyOne V1 Test Plan

## Scope

This plan verifies the completed Version 1 behavior without changing business rules. It covers unit tests, WordPress and WooCommerce integration tests, staging end-to-end tests, security checks, accessibility, mobile layout, performance, upgrade validation, backup restoration, and regression checks.

## Automated Test Commands

Run from the repository root.

```sh
composer install
composer run phpcs
composer run security:audit
composer run test:unit
```

Integration tests require a WordPress test library, WooCommerce, and MySQL.

```sh
WP_TESTS_DIR=/absolute/path/to/wordpress-develop/tests/phpunit \
WP_CORE_DIR=/absolute/path/to/wordpress-develop/src \
composer run test:integration
```

Staging end-to-end tests require these environment variables:

- `E2E_BASE_URL`
- `E2E_NORMAL_PRODUCT_URL`
- `E2E_REWARDED_OFFER_URL`
- `E2E_POSTCODE`

```sh
npm --prefix tests/E2E install
npx --prefix tests/E2E playwright install chromium
npm --prefix tests/E2E run test
```

## Unit Coverage

The unit suite verifies deterministic business behavior without a WordPress database:

- approved and prohibited order-status transitions;
- status normalization before transition validation;
- rejection of unapproved status values.

## Integration Coverage

The integration suite uses WordPress and WooCommerce with a disposable MySQL database. It verifies:

- WooCommerce CRUD order creation;
- notification-log table creation through `dbDelta()`;
- upgrade from schema version `0.7.0` to `0.8.0`;
- one notification log per order and event;
- safe persistence of failed and skipped notification results.

## Staging Purchase Scenarios

Before running staging end-to-end tests, configure:

1. One in-stock normal-price product.
2. One in-stock product page containing an active staging rewarded-offer shortcode.
3. An active service area matching `E2E_POSTCODE`.
4. At least one currently selectable delivery date and slot.
5. One enabled payment method available to a guest customer.

The automated staging suite verifies both 320 px mobile and 1280 px desktop projects:

- a normal-price product can be added to cart and ordered;
- a staging reward can be completed, unlocked, added to cart, and ordered;
- home, product, cart, and checkout pages have no horizontal overflow;
- product and checkout controls can receive keyboard focus;
- document response timing for home, product, cart, and checkout is no more than 2500 ms.

## Manual Security Regression

Perform every check on staging with an administrator, a shop manager, and an unauthorized account:

- submit every administrative mutation with an invalid nonce;
- submit every administrative mutation with a missing nonce;
- submit direct order-status requests for each prohibited transition;
- submit direct reward-completion requests with another customer’s event token;
- submit changed browser prices, campaign values, delivery fees, dates, and slots;
- access GalaxyOne orders, customers, reports, dashboard, and activity logs without `manage_woocommerce`;
- confirm unauthorized requests make no business-data changes;
- inspect the activity log and confirm it contains no customer email address, phone number, or postal address.

## Accessibility and Mobile Regression

At 320 px and 1280 px, verify home, product, cart, and checkout pages:

- have no horizontal scroll;
- preserve a visible focus indicator;
- allow all interactive controls to be reached by keyboard;
- expose form labels and error text;
- preserve visible unavailable-product and delivery-validation states;
- maintain controls with a minimum 44 × 44 CSS pixel touch target.

## Performance Measurement

Use an authenticated-free staging session with browser cache disabled and Chromium network throttling set to Fast 3G.

Measure:

- home page;
- normal product page;
- cart;
- checkout.

Record response time, largest contentful paint, total blocking time, and layout shift in the pull request or release evidence. Investigate any document response time above 2500 ms before release approval. Do not enable caching that can return stale prices, availability, delivery selections, or rewards.

## Upgrade and Restore Regression

1. Copy a non-production database at schema version `0.7.0`.
2. Deploy the Phase 12 code to the non-production environment.
3. Load WordPress once and confirm the schema option becomes `0.8.0`.
4. Confirm the `galaxy_notification_logs` table exists.
5. Run normal checkout, rewarded checkout, order-status updates, and notification logging.
6. Restore a pre-upgrade backup into a separate non-production database.
7. Confirm WordPress, WooCommerce, GalaxyOne Core, and the child theme load.
8. Repeat the normal checkout smoke test and inspect stored order metadata.

## Required Evidence

Phase 12 is complete only when these records are attached to the change request:

- passing unit and integration test output;
- passing staging Playwright report for desktop and mobile;
- dependency security audit output;
- completed security regression checklist;
- mobile and keyboard verification results;
- performance measurements;
- successful schema-upgrade result;
- successful non-production backup restoration and smoke-test result.
