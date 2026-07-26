
FILE: docs/SECURITY_OPERATIONS.md  
ACTION: CREATE  
PURPOSE: Defines the Phase 12 security verification, vulnerability handling, and customer-data protection process.

```md
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
