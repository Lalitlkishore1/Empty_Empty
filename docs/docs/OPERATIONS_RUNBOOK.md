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
