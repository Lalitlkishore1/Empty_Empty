# GalaxyOne V1 — Development Guide

## Purpose

This guide defines the Phase 1 engineering workflow for GalaxyOne V1.

GalaxyOne is built with:

- WordPress
- WooCommerce
- Elementor Pro
- GalaxyOne Core Plugin
- GalaxyOne Child Theme

Business rules belong in the GalaxyOne Core plugin. Elementor is limited to layout, design, and editable content. The child theme owns styling and UI customization.

## Prerequisites

Install the following tools before working on the repository:

- PHP 8.1 or later
- Composer 2
- Git
- A local WordPress installation
- WooCommerce
- Elementor Pro

Do not commit local WordPress configuration, database exports, uploads, credentials, Composer dependencies, or environment-specific values.

## Repository setup

From the repository root, install development dependencies:

```sh
composer install
```

Validate the Composer configuration:

```sh
composer validate
```

## Local WordPress environment

Use a local WordPress site outside this repository.

Install and activate:

1. WooCommerce
2. Elementor Pro
3. The GalaxyOne Core plugin when it is introduced in Phase 2
4. The GalaxyOne child theme when it is introduced in Phase 3

Do not copy WordPress core, uploads, database files, `wp-config.php`, or local environment files into this repository.

## Quality commands

Run all quality commands from the repository root:

```sh
composer validate
composer run phpcs
composer run test
```

Command responsibilities:

- `composer validate` validates `composer.json`.
- `composer run phpcs` applies WordPress coding standards to tracked PHP sources configured by `phpcs.xml.dist`.
- `composer run test` executes PHPUnit using `phpunit.xml.dist` and `tests/bootstrap.php`.

## Continuous integration

The GitHub Actions workflow in `.github/workflows/quality.yml` runs:

```sh
composer validate
composer install --prefer-dist --no-interaction --no-progress
composer run phpcs
composer run test
```

The CI environment uses PHP 8.1.

## Coding conventions

- Use WordPress Coding Standards for PHP.
- Use tabs for PHP indentation.
- Use UTF-8 text files with LF line endings.
- Keep business logic out of Elementor pages and the child theme.
- Do not modify WordPress or WooCommerce core files.
- Keep modules small, reusable, and independently testable.
- Do not add future-phase functionality while completing the current phase.

## Testing scope

Phase 1 establishes the PHPUnit bootstrap and quality commands.

Feature-specific unit, integration, and end-to-end tests are introduced only in their scheduled implementation phases.
