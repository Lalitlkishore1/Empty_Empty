# GalaxyOne V1 Rollback Plan

## Purpose

This plan restores GalaxyOne to a known-good operational state after a failed production deployment.

GalaxyOne schema migrations are forward-only. Do not manually lower the GalaxyOne schema-version option or alter GalaxyOne tables to simulate a downgrade.

## Rollback Triggers

Start rollback immediately when any of the following is confirmed:

- the storefront, cart, checkout, or WordPress administration area is unavailable;
- a production deployment causes a PHP or WordPress fatal error;
- checkout totals, server-side pricing, delivery fees, delivery capacity, or reward pricing are materially incorrect;
- an approved GalaxyOne schema migration fails or causes data-integrity risk;
- notification delivery creates a material security, privacy, or duplicate-delivery incident;
- scheduled delivery-reservation or reward-event cleanup cannot be restored safely;
- a production security control is bypassed;
- the release owner determines that containment cannot safely restore service.

## Rollback Owners

The release record must identify:

- the release owner;
- the rollback owner;
- the hosting-access owner;
- the backup-access owner;
- the operations communications owner.

Only authorized staff may execute rollback or access production backups.

## Required Recovery Materials

Before each production deployment, verify that all of the following are available:

- a completed production backup identifier;
- the previous known-good GalaxyOne Core package;
- the previous known-good GalaxyOne child-theme package;
- SHA-256 checksum manifests for both release packages;
- the source commit SHA and release version for the deployed release;
- the source commit SHA and release version for the rollback release;
- a secure location for hosting, PHP, WordPress, cron, mail, and monitoring logs.

## Decision Rule

Use a file-only rollback only when all of the following are true:

- no GalaxyOne schema migration was run;
- no release-related data change requires reversal;
- restoring the previous plugin and theme packages resolves the incident;
- the rollback owner confirms that existing database state remains compatible.

Use a full restore of the database and `wp-content` backup together when:

- a schema migration ran and must be reversed;
- a release-related data-integrity issue occurred;
- file-only rollback leaves the production database incompatible;
- the rollback owner cannot establish that database state is safe.

Never restore only files when a consistent database restore is required.

## File-Only Rollback Procedure

1. Declare the rollback and stop unrelated production changes.
2. Record the incident time, deployed release version, source commit SHA, and observed impact.
3. Preserve secured host logs and monitoring evidence.
4. Enable the approved maintenance state if required by the hosting deployment process.
5. Verify the checksum of the known-good GalaxyOne Core package.
6. Verify the checksum of the known-good child-theme package.
7. Replace only `wp-content/plugins/galaxyone-core` with the known-good plugin package.
8. Replace only `wp-content/themes/galaxyone-child` with the known-good child-theme package.
9. Do not replace WordPress core, WooCommerce, Elementor Pro, `wp-config.php`, uploads, or external provider configuration.
10. Clear only approved application and page caches.
11. Disable maintenance state if it was enabled.
12. Confirm WordPress, WooCommerce, GalaxyOne Core, and the GalaxyOne child theme load.
13. Run the rollback smoke tests.
14. Continue the observation window until service is stable.

If any rollback smoke test fails, move to the full restore procedure.

## Full Restore Procedure

1. Declare a production recovery incident and stop unrelated production changes.
2. Enable approved maintenance state.
3. Record the restoration start time, backup identifier, deployed release identifier, and rollback owner.
4. Restore the selected WordPress database backup.
5. Restore the matching `wp-content` backup, including GalaxyOne Core, the child theme, uploads, and required host-managed configuration.
6. Restore the matching known-good plugin and child-theme packages if they are not already included in the selected backup.
7. Confirm production configuration continues to use production secrets from the approved secret store.
8. Clear only approved application and page caches.
9. Disable maintenance state.
10. Confirm WordPress administration and storefront pages load over HTTPS.
11. Confirm the expected GalaxyOne schema version and required GalaxyOne tables are present.
12. Run the rollback smoke tests.
13. Confirm external cron, transactional mail, monitoring, and backups are functioning.
14. Record the restoration result and keep the incident open until the observation window completes.

## Rollback Smoke Tests

After every rollback or restoration, verify:

- homepage, product page, cart, checkout, and My Account load over HTTPS;
- product price and availability display correctly;
- unavailable products remain non-purchasable;
- an eligible delivery postcode can be validated;
- an ineligible delivery postcode is rejected;
- delivery date and slot selection work;
- cart and checkout totals are calculated server-side;
- one controlled normal-price checkout smoke test succeeds;
- order metadata, delivery reservation, and notification logging behave normally;
- authorized staff can access the operational dashboard and order management;
- unauthorized users cannot access GalaxyOne administration;
- `galaxyone_expire_delivery_reservations` and `galaxyone_expire_reward_events` remain scheduled;
- monitoring, external cron, transactional mail, and backups report healthy status;
- secured logs contain no unresolved critical errors.

## Post-Rollback Actions

After service is stable:

1. Record the final rollback status, timestamps, release identifiers, backup identifier, and smoke-test evidence.
2. Preserve secured diagnostics without copying customer contact information into general incident records.
3. Reconcile orders, delivery reservations, reward events, and notification outcomes created during the incident window through authorized operational tools.
4. Do not retry the failed release until its root cause is reproduced and resolved in staging.
5. Require a new staging validation and a new release approval before another production deployment.
6. Rehearse this rollback plan on staging after any material change to hosting, cache, cron, mail, monitoring, backup, or deployment processes.
