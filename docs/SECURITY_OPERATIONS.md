# GalaxyOne V1 Security Operations

## Security Baseline

GalaxyOne treats all price, delivery, reward, order, customer, campaign, and notification decisions as server-side decisions.

The following controls are mandatory:

- GalaxyOne administration requires `manage_woocommerce`.
- State-changing administrative requests require a valid nonce.
- Public reward requests require a valid reward nonce and customer-bound event token.
- WooCommerce remains the authority for products, carts, checkout, and orders.
- GalaxyOne pricing is resolved server-side and browser-submitted price values are not trusted.
- Delivery capacity claims and releases remain server-side and atomic.
- Reward events remain customer-bound, time-limited, and redeemable once.
- Notification delivery logs are restricted to authorized operations users.

## Pre-Release Security Checks

Run these checks for every release candidate:

```sh
composer validate
composer run phpcs
composer run security:audit
composer run test:unit
```

Run integration and staging tests using the commands in `docs/TEST_PLAN.md`.

Review every dependency-audit finding. A critical or high-severity finding must be remediated, replaced, or formally removed from the release candidate before approval.

## Request Security Verification

For every state-changing endpoint, verify:

1. The request requires the correct capability or public nonce.
2. The nonce is checked before data is changed.
3. Input is unslashed, sanitized, validated, and authorized.
4. Server-side business rules determine the final result.
5. Output is escaped for its rendering context.
6. The action creates an activity record when it changes operational data.

## Customer Data Handling

Customer data is visible only to authorized WordPress users with `manage_woocommerce`.

Do not place customer email addresses, phone numbers, postal addresses, payment details, or delivery landmarks in:

- activity-log values;
- browser console logs;
- JavaScript localization payloads;
- error messages;
- test fixtures committed outside the isolated test environment;
- issue comments, pull requests, or screenshots.

Notification delivery logs may store the recipient address only because it is required to audit delivery. Access to those logs remains restricted by the same GalaxyOne operations capability.

## Incident Triage

When a suspected security issue is found:

1. Reproduce it in a non-production environment.
2. Record the affected endpoint, user role, request type, impact, and reproduction steps.
3. Prevent further exposure using the smallest safe configuration or access change.
4. Preserve relevant activity-log and notification-log records without copying customer contact information into tickets.
5. Prepare a scoped remediation change and regression test.
6. Retest with administrator, shop-manager, customer, and unauthenticated contexts.
7. Record the final status and evidence in the change request.

## Security Regression Matrix

| Area | Required result |
| --- | --- |
| Settings and campaign forms | Invalid or missing nonce changes no data. |
| Order status updates | Only approved transitions are accepted. |
| Customer operations | Unauthorized users receive a 403 response before data is read. |
| Checkout | Browser prices, fees, delivery dates, and slots cannot override server results. |
| Rewarded offers | Another customer cannot complete or redeem a reward event. |
| Notifications | Duplicate order events do not create duplicate log records. |
| Activity logs | Operational events are recorded without customer contact information. |
| Database upgrades | Migration is repeatable and creates no duplicate table or records. |
