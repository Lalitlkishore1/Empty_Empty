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
