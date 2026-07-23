# GalaxyOne

GalaxyOne is a mobile-first local commerce platform for Water and Blooms. Version 1 is built with WordPress, WooCommerce, Elementor Pro, the GalaxyOne Core plugin, and a child theme.

## Repository status

This repository currently contains GalaxyOne V1 documentation and the engineering foundation. Product, delivery, checkout, rewarded-offer, and administration features are implemented only in their scheduled roadmap phases.

## Prerequisites

- PHP 8.1 or later
- Composer 2
- A local WordPress installation
- WooCommerce
- Elementor Pro

## Local setup

1. Clone this repository.

2. Install PHP development dependencies:

   ```sh
   composer install
   ```

3. Create or use a local WordPress site outside this repository.

4. Install and activate WooCommerce and Elementor Pro in that WordPress site.

5. Follow the complete environment procedure in [docs/DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md).

## Quality checks

Run these commands from the repository root:

```sh
composer validate
composer run phpcs
composer run test
```

GitHub Actions runs the same quality commands for pushes and pull requests.

## Documentation

- [Product requirements](docs/PRODUCT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Development guide](docs/DEVELOPMENT_GUIDE.md)
- [Design system](docs/DESIGN_SYSTEM.md)
- [Implementation roadmap](docs/IMPLEMENTATION_PLAN.md)

## Scope control

Follow the ordered phases in `docs/IMPLEMENTATION_PLAN.md`. Do not add future-phase product behavior while completing an earlier phase.
