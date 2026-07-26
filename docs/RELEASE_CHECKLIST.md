# GalaxyOne V1 Pre-Release Checklist

This checklist confirms Phase 12 validation readiness. It does not authorize or describe a production deployment.

## Automated Quality

- [ ] `composer validate` completed successfully.
- [ ] `composer run phpcs` completed successfully.
- [ ] `composer run security:audit` has no unresolved critical or high-severity findings.
- [ ] `composer run test:unit` completed successfully.
- [ ] `composer run test:integration` completed successfully against WordPress, WooCommerce, and MySQL.
- [ ] Staging Playwright tests completed successfully at 320 px and 1280 px.

## Purchase and Delivery Regression

- [ ] A normal-price guest purchase completed successfully.
- [ ] A staging rewarded-offer purchase completed successfully.
- [ ] Unavailable products remained non-purchasable.
- [ ] Invalid delivery area, date, slot, and full-capacity selections were rejected.
- [ ] Final order price, delivery fee, slot, and delivery metadata were stored.
- [ ] Cancelled and failed orders released delivery capacity exactly once.
- [ ] Reward events were redeemed once and recorded on order items.

## Operations Regression

- [ ] Authorized staff could search, filter, view, and manage orders.
- [ ] Every allowed order status transition worked.
- [ ] Every prohibited order status transition was rejected.
- [ ] Customer history was inaccessible without `manage_woocommerce`.
- [ ] Dashboard and report figures reconciled with the orders included in their views.
- [ ] Confirmation and status notifications created one delivery log per order event.
- [ ] Sent, failed, and skipped notification outcomes were visible to authorized staff only.
- [ ] Activity-log entries contained no customer contact information.

## Accessibility and Performance

- [ ] Home, product, cart, and checkout pages had no horizontal overflow at 320 px and 1280 px.
- [ ] Keyboard navigation reached every checkout and product action.
- [ ] Focus indicators, labels, validation messages, and unavailable states were visible.
- [ ] Key-page performance measurements were recorded using throttled mobile conditions.
- [ ] No cache configuration served stale pricing, availability, delivery, or reward state.

## Upgrade and Recovery

- [ ] Upgrade from schema version `0.7.0` created the notification-log table and advanced schema version to `0.8.0`.
- [ ] Re-running the migration created no duplicate table or records.
- [ ] A non-production backup restoration completed successfully.
- [ ] WordPress, WooCommerce, GalaxyOne Core, and the child theme loaded after restoration.
- [ ] A normal checkout smoke test passed after restoration.
