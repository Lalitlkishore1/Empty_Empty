FILE:

docs/DEPLOYMENT_GUIDE.md

# GalaxyOne V1 Deployment Guide

## Purpose

This guide defines the controlled release process for GalaxyOne Version 1. It deploys the approved application without changing GalaxyOne business behavior.

WooCommerce remains the authority for products, carts, checkout, and orders. GalaxyOne Core continues to own GalaxyOne pricing, delivery, reward, campaign, operational, and notification rules.

## Release Principles

- Deploy the same reviewed source commit to staging before production.
- Run the Phase 12 quality gate before a release candidate is accepted.
- Never commit production credentials, database exports, uploads, private keys, or environment-specific configuration.
- Create and verify a recoverable backup before a production deployment.
- Keep a verified copy of the previous plugin and child-theme release package.
- Do not manually alter GalaxyOne database tables during deployment.
- Treat plugin schema upgrades as forward-only changes.
- Stop and use the rollback plan if a production validation check fails.

## Environment Separation

| Environment | Purpose | Data rule | Access rule |
| --- | --- | --- | --- |
| Development | Local implementation and automated testing | Synthetic or sanitized data only | Developers use local credentials only. |
| Staging | Production-like validation and release rehearsal | Sanitized data only | Restricted to authorized release and test staff. |
| Production | Customer-facing service | Live operational data | Restricted to authorized operations staff. |

Production credentials, provider credentials, database credentials, mail credentials, and backup credentials must be stored only in the approved hosting or secret-management configuration.

## Required Release Evidence

Before production deployment, record all of the following in the approved release record:

- reviewed source commit SHA;
- release version;
- release-candidate artifact name and SHA-256 checksum;
- successful Phase 12 quality-workflow run;
- successful staging smoke-test evidence;
- successful staging end-to-end test evidence;
- verified backup identifier and restoration location;
- rollback owner and rollback package identifier;
- completed [Release Checklist](RELEASE_CHECKLIST.md);
- production launch approval.

## Release Candidate Packaging

Use the `Release Readiness` GitHub Actions workflow with the `package` operation.

The workflow:

1. validates the release version;
2. runs Composer validation, coding standards, dependency audit, unit tests, and integration tests;
3. installs the plugin's production Composer autoloader;
4. creates deployable ZIP artifacts for GalaxyOne Core and the GalaxyOne child theme;
5. generates a SHA-256 checksum manifest;
6. stores the packages as a GitHub Actions artifact.

The generated GalaxyOne Core package includes its required `vendor/autoload.php`. Do not deploy a plugin source folder that has not been packaged with its production Composer autoloader.

Download the candidate artifact only after the workflow succeeds. Preserve the artifact and checksum manifest for the life of the release and rollback window.

## Staging Deployment

### Preconditions

- The candidate package was produced from the reviewed source commit.
- Staging uses supported PHP 8.1 or later, WordPress, WooCommerce, Elementor Pro, and the approved parent theme.
- Staging has its own database, uploads directory, mail configuration, cache configuration, and provider credentials.
- The staging database is not a live production database.
- A current staging backup exists.

### Procedure

1. Record the source commit SHA, release version, and artifact checksum.
2. Put the staging site into an approved maintenance state if the host requires it.
3. Back up the staging database and the current staging `wp-content` files.
4. Upload the GalaxyOne Core release ZIP through the approved hosting deployment interface and replace only the GalaxyOne Core plugin.
5. Upload the GalaxyOne child-theme release ZIP through the approved hosting deployment interface and replace only the GalaxyOne child theme.
6. Preserve WordPress core, `wp-config.php`, uploads, and host-managed configuration.
7. Clear only the approved staging application and page caches.
8. Confirm that WooCommerce, Elementor Pro, the GalaxyOne Core plugin, and the GalaxyOne child theme are active.
9. Load the WordPress administration area and the storefront once to allow the GalaxyOne schema manager to perform any required forward migration.
10. Confirm that no WordPress or PHP fatal error is recorded.
11. Run the staging smoke tests in the [Test Plan](TEST_PLAN.md).
12. Run the `Release Readiness` workflow with the `verify-staging` operation, using the deployed staging URLs and delivery postcode.
13. Record the successful staging workflow run, source commit SHA, and artifact checksum in the release record.

A failed staging deployment must not progress to production.

## Production Deployment

### Preconditions

- The exact release candidate passed staging validation.
- The production deployment uses the same source commit and release artifacts validated on staging.
- The release checklist is complete and approved.
- A verified production backup and a known-good rollback package are available.
- A release owner and rollback owner are available for the deployment window.
- Monitoring, transactional mail, external cron, SSL, and caching checks are complete.

### Procedure

1. Announce the approved deployment window to authorized operations staff.
2. Record the production start time, release version, source commit SHA, candidate checksum, and backup identifier.
3. Create a full production backup as described in [Operations Runbook](OPERATIONS_RUNBOOK.md).
4. Confirm the backup completed and is accessible before changing production files.
5. Enable the approved maintenance state only if needed for the hosting deployment process.
6. Upload the approved GalaxyOne Core release ZIP and replace only `wp-content/plugins/galaxyone-core`.
7. Upload the approved child-theme release ZIP and replace only `wp-content/themes/galaxyone-child`.
8. Do not replace WordPress core, WooCommerce, Elementor Pro, `wp-config.php`, uploads, or provider configuration as part of this release.
9. Clear only the approved production application and page caches.
10. Disable maintenance state if it was enabled.
11. Confirm the GalaxyOne Core plugin and child theme are active.
12. Load the WordPress administration area and storefront once to run any required forward-only GalaxyOne schema migration.
13. Complete the production smoke-test checklist.
14. Monitor checkout, PHP errors, scheduled tasks, notification delivery, and cache behavior throughout the approved observation window.
15. Mark the release complete only after all production checks pass.

If any critical check fails, stop the release and follow [Rollback Plan](ROLLBACK_PLAN.md).

## Production WordPress Configuration

Production configuration is managed outside this repository.

The production WordPress configuration must enforce:

| Setting | Required production value or behavior |
| --- | --- |
| `WP_ENVIRONMENT_TYPE` | `production` |
| `WP_DEBUG` | `false` |
| `WP_DEBUG_DISPLAY` | `false` |
| `WP_DEBUG_LOG` | `false` unless a secured, rotated host log is explicitly approved |
| `DISALLOW_FILE_EDIT` | `true` |
| `FORCE_SSL_ADMIN` | `true` |
| `WP_CACHE` | `true` only after the approved cache behavior is verified |
| `DISABLE_WP_CRON` | `true` only after external cron has been verified |

The web server must deny direct public access to `wp-config.php`, backup archives, logs, private configuration files, and directory listings.

## Cache Configuration

Caching may improve public storefront performance, but it must not cache customer-specific commerce behavior.

The cache must bypass or vary correctly for:

- `/cart`;
- `/checkout`;
- `/my-account`;
- authenticated WordPress administration requests;
- WooCommerce cart and checkout cookies;
- WooCommerce fragments and AJAX endpoints;
- nonce-bearing forms;
- reward-event and reward-redemption requests.

After every deployment, confirm that cached pages do not display stale product availability, delivery eligibility, checkout totals, delivery fees, scheduled offers, or reward state.

## External Cron Configuration

WordPress pseudo-cron must not be the only production scheduling mechanism.

After external cron is configured, set `DISABLE_WP_CRON` to `true` and configure the hosting scheduler to request the production site's `wp-cron.php?doing_wp_cron` endpoint every five minutes over HTTPS.

Verify that the following GalaxyOne scheduled events continue to execute:

- `galaxyone_expire_delivery_reservations`;
- `galaxyone_expire_reward_events`.

Do not disable WordPress pseudo-cron until the external scheduler has completed successfully and its execution is visible in the approved hosting logs.

## Transactional Mail Configuration

Production notification delivery requires an approved transactional-mail provider configured outside source control.

Before launch:

1. Configure the approved provider with authenticated credentials stored in the hosting secret store.
2. Set the approved sender name and sender address.
3. Send a controlled staging test message.
4. Confirm delivery and sender alignment.
5. Confirm notification delivery logs record queued, sent, failed, or skipped outcomes without exposing customer contact information in the activity log.
6. Confirm that notification failures are visible to authorized operations staff.

## SSL and Monitoring Configuration

Production must use a valid HTTPS certificate for the public site and WordPress administration area.

Before launch, verify:

- HTTPS redirects work for public and administrative requests;
- the certificate is valid and renews through the approved host process;
- public health monitoring requests the configured health-check path over HTTPS;
- uptime monitoring alerts the approved operations channel;
- PHP and WordPress fatal errors are captured in secured host logs;
- database, disk-space, backup, mail, and cron failures are visible to authorized operators;
- monitoring alerts do not include passwords, access tokens, customer contact details, or payment information.

## Database Migration Verification

GalaxyOne runs its schema migration process when the plugin loads.

For every deployment that changes GalaxyOne schema behavior:

1. record the installed schema version before deployment;
2. create the production backup;
3. deploy the approved plugin package;
4. load the plugin through WordPress;
5. confirm the schema version advanced as expected;
6. confirm required GalaxyOne tables exist;
7. confirm no duplicate tables, records, warnings, or fatal errors were created;
8. run cart, checkout, delivery, reward, order, dashboard, and notification smoke tests.

Do not manually downgrade a GalaxyOne schema version. Database rollback requires restoration of a consistent WordPress database and `wp-content` backup as described in the rollback plan.

## Production Smoke Tests

Run these tests immediately after deployment:

- Load the homepage, a product page, cart, checkout, and My Account page over HTTPS.
- Confirm a current product price and availability display.
- Confirm an unavailable product remains non-purchasable.
- Validate one eligible and one ineligible delivery postcode.
- Confirm delivery date and slot selection works.
- Confirm cart and checkout totals are calculated server-side.
- Complete one approved normal-price test order using the controlled production procedure.
- Confirm order metadata, delivery reservation, and notification delivery log behavior.
- Confirm authorized staff can view the operational dashboard and order list.
- Confirm unauthorized users cannot access GalaxyOne administration.
- Confirm scheduled GalaxyOne cleanup events remain registered.
- Confirm no critical errors appear in secured host logs.

## Operational Handoff

At release completion, provide the operations owner with:

- deployed source commit SHA and release version;
- release artifact and SHA-256 manifest;
- backup identifier;
- rollback package identifier;
- staging and production validation evidence;
- current monitoring and alert destinations;
- current transactional-mail provider status;
- current external-cron status;
- known issues, if any;
- links to this guide, the operations runbook, and the rollback plan.

FILE:

docs/OPERATIONS_RUNBOOK.md

# GalaxyOne V1 Operations Runbook

## Purpose

This runbook defines the operational checks and incident procedures required to keep GalaxyOne Version 1 observable and recoverable.

It does not change product, price, delivery, reward, checkout, order, reporting, customer, notification, or WooCommerce behavior.

## Access and Data Protection

Only authorized operations staff may access production administration, hosting controls, backup storage, monitoring, error logs, notification logs, or customer order history.

Do not copy customer names, email addresses, telephone numbers, postal addresses, payment information, delivery landmarks, authentication credentials, or provider tokens into activity logs, issue trackers, screenshots, chat messages, or release records.

Use the minimum data needed to diagnose an issue.

## Daily Operational Checks

Perform and record these checks each business day:

- Public HTTPS health monitor is successful.
- SSL certificate is valid and has no renewal warning.
- WordPress administration is reachable by an authorized user.
- Homepage, product page, cart, and checkout load without critical error.
- Recent PHP, WordPress, and web-server logs contain no unresolved fatal errors.
- External cron has executed successfully.
- `galaxyone_expire_delivery_reservations` remains scheduled and executes.
- `galaxyone_expire_reward_events` remains scheduled and executes.
- Transactional-mail provider is operational.
- Recent notification delivery logs contain no unexplained failures.
- Backup job completed according to the approved schedule.
- Available storage, database capacity, and host resource alerts are within approved limits.

## Weekly Operational Checks

Perform and record these checks each week:

- Review failed and skipped notification outcomes with authorized operations staff.
- Review activity logs for unexpected administrative changes.
- Verify current product availability and daily flower pricing administration work as expected.
- Review delivery capacity and reservations for stale or unexpected records.
- Review paused, future, active, and expired campaigns.
- Confirm cache exclusions still protect cart, checkout, My Account, WooCommerce fragments, and nonce-bearing requests.
- Review dependency-audit and security findings from the most recent approved release.
- Confirm monitoring alerts reach the approved operations channel.

## Monthly Operational Checks

Perform and record these checks each month:

- Restore the latest backup into a non-production environment.
- Confirm WordPress, WooCommerce, Elementor Pro, GalaxyOne Core, and the GalaxyOne child theme load after restoration.
- Run the approved smoke tests against the restored environment.
- Verify the latest release artifact and SHA-256 checksum manifest remain available.
- Verify the known-good rollback package remains available.
- Review production administrator and shop-manager access.
- Remove access that is no longer required.
- Review SSL renewal status, host resource consumption, and backup retention.

## Backup Procedure

A production backup must include a consistent copy of:

- the WordPress database;
- `wp-content/plugins/galaxyone-core`;
- `wp-content/themes/galaxyone-child`;
- `wp-content/uploads`;
- active host-managed WordPress configuration required to restore the site;
- the release artifact and its SHA-256 checksum manifest.

Backups must be encrypted and stored in the approved restricted-access backup location.

Before any production deployment:

1. create a new backup;
2. record its identifier, completion time, and storage location;
3. verify that it completed without error;
4. confirm the prior known-good release package is available;
5. do not proceed until the backup is usable.

Keep backups for at least the retention period configured in the approved production environment inventory. Do not store database exports or backups in the repository.

## Restore Test Procedure

Perform restores only in a non-production environment unless the rollback owner has declared a production recovery incident.

1. Create an isolated non-production database and file location.
2. Restore the selected database and `wp-content` backup together.
3. Configure the restored environment with non-production credentials and non-production mail handling.
4. Confirm the restored site cannot send unintended customer notifications.
5. Load WordPress administration, the storefront, cart, checkout, and My Account.
6. Confirm GalaxyOne schema migration does not produce duplicate tables, records, warnings, or errors.
7. Run the approved smoke tests.
8. Record the restoration result, backup identifier, restore time, and any issue found.

## Monitoring and Logging

### Required Monitoring

Monitoring must cover:

- HTTPS uptime for the public health-check path;
- SSL certificate validity and renewal;
- host availability and disk capacity;
- database availability;
- PHP and WordPress fatal errors;
- external cron execution;
- backup completion and failure;
- transactional-mail provider availability;
- notification delivery failures;
- release and rollback completion.

### Logging Rules

Use secured, access-controlled host logs for PHP, web-server, WordPress, cron, backup, and provider diagnostics.

GalaxyOne activity logs must retain operational audit data without customer contact information. Notification logs may retain recipient information only where GalaxyOne requires it for notification-delivery auditing and only for authorized operations users.

Do not enable public error display in production.

## Scheduled Task Failure

When an external cron monitor reports a failure:

1. Confirm the hosting scheduler is enabled and uses the production HTTPS endpoint.
2. Confirm the WordPress site is reachable over HTTPS.
3. Review secured host logs for HTTP, TLS, PHP, or WordPress errors.
4. Confirm the WordPress scheduler has registered `galaxyone_expire_delivery_reservations` and `galaxyone_expire_reward_events`.
5. Restore the scheduler configuration if it was changed.
6. Confirm a subsequent scheduled execution succeeds.
7. Check for expired delivery reservations and reward events that require normal service cleanup.
8. Record the incident and resolution without recording customer contact information.

If WP-CLI is available in the approved hosting environment, authorized operators may use it to inspect scheduled events. Do not require WP-CLI for production operation.

## Checkout or Pricing Incident

If checkout totals, product availability, campaign pricing, delivery fees, or rewards appear incorrect:

1. Stop any affected operational campaign only through the authorized GalaxyOne administration workflow.
2. Preserve the release identifier, relevant order identifiers, and secured error-log evidence.
3. Do not edit WooCommerce orders, GalaxyOne tables, price snapshots, delivery reservations, or reward events directly in the database.
4. Reproduce the issue in staging using sanitized data.
5. Confirm whether the issue affects normal-price checkout, scheduled offers, delivery fees, rewarded offers, or cache behavior.
6. Check that cart and checkout pages are excluded from inappropriate caching.
7. Escalate to the release owner if rollback criteria are met.
8. Record the operational action in GalaxyOne activity logging where the existing administration workflow does so.

## Delivery Capacity Incident

If delivery capacity appears blocked, overbooked, or stale:

1. Confirm the delivery date, slot, service area, and reservation state through authorized administration tools.
2. Confirm external cron is executing.
3. Check the delivery-reservation cleanup event.
4. Do not directly alter delivery-capacity or reservation database records.
5. Reproduce the issue in staging before changing configuration.
6. Use existing authorized delivery configuration workflows for any required operational adjustment.
7. Escalate for rollback if the incident began with the current release and cannot be safely contained.

## Rewarded Offer Incident

If a reward is unavailable, incorrectly applied, or suspected to be fraudulent:

1. Confirm the reward campaign status using authorized administration tools.
2. Confirm the reward event is customer-bound, unexpired, and not already redeemed.
3. Review secured provider and application logs without exposing customer details.
4. Do not accept browser-submitted reward price or reward state as evidence.
5. Pause the affected campaign through the existing authorized workflow if containment is required.
6. Reproduce the issue in staging using the staging provider.
7. Escalate for rollback if the issue affects trusted reward-price enforcement.

## Notification Delivery Incident

If notifications fail or are duplicated:

1. Review the notification delivery log using an authorized operations account.
2. Confirm whether the outcome is queued, sent, failed, or skipped.
3. Confirm the transactional-mail provider is available and authenticated.
4. Confirm the sender configuration is correct.
5. Do not manually create duplicate notification-log records.
6. Preserve delivery evidence without copying customer contact information into general operational records.
7. Reproduce the issue in staging before making a configuration change.
8. Escalate if duplicate delivery or data exposure is suspected.

## Release Observation Window

For every production deployment:

1. Monitor public HTTPS health, PHP errors, cron, transactional mail, checkout, notification logs, and cache behavior throughout the approved observation window.
2. Keep the release owner and rollback owner available.
3. Do not start unrelated production changes during the observation window.
4. Complete the deployment record only after the production smoke tests and monitoring checks pass.
5. Start the rollback plan immediately if a rollback trigger is met.

## Escalation and Recovery

Escalate immediately to the rollback owner when any of the following occurs:

- public storefront or checkout is unavailable;
- an unrecoverable PHP or WordPress fatal error occurs;
- checkout totals, delivery capacity, or reward pricing are materially incorrect;
- customer data may have been exposed;
- schema migration causes data integrity failure;
- scheduled cleanup cannot be restored safely;
- notification behavior creates material duplicate delivery or security impact;
- staging-validated behavior is not reproduced in production.

Use [Rollback Plan](ROLLBACK_PLAN.md) for recovery. Record the incident, actions, timestamps, release identifier, backup identifier, and final validation result.

FILE:

docs/ROLLBACK_PLAN.md

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
