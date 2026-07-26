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
