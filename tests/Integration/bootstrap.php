<?php
/**
 * WordPress and WooCommerce integration-test bootstrap.
 *
 * @package GalaxyOne\Tests
 */

declare(strict_types=1);

$project_root = dirname( __DIR__, 2 );

require_once $project_root . '/vendor/autoload.php';

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
$wp_core_dir  = getenv( 'WP_CORE_DIR' );

if ( ! is_string( $wp_tests_dir ) || '' === $wp_tests_dir ) {
	throw new RuntimeException(
		'WP_TESTS_DIR must reference the WordPress test library.'
	);
}

if ( ! is_string( $wp_core_dir ) || '' === $wp_core_dir ) {
	throw new RuntimeException(
		'WP_CORE_DIR must reference the WordPress core directory.'
	);
}

$wordpress_functions = $wp_tests_dir . '/includes/functions.php';
$woocommerce_file    = $wp_core_dir . '/wp-content/plugins/woocommerce/woocommerce.php';

if (
	! file_exists( $wordpress_functions ) ||
	! file_exists( $woocommerce_file )
) {
	throw new RuntimeException(
		'The WordPress test library or WooCommerce plugin is unavailable.'
	);
}

require_once $wordpress_functions;

/*
 * Load WooCommerce before the WordPress test environment finishes booting.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () use ( $woocommerce_file, $project_root ): void {
		require_once $woocommerce_file;

		if ( ! defined( 'GALAXYONE_CORE_PATH' ) ) {
			define(
				'GALAXYONE_CORE_PATH',
				$project_root . '/wp-content/plugins/galaxyone-core/'
			);
		}

		if ( ! defined( 'GALAXYONE_CORE_FILE' ) ) {
			define(
				'GALAXYONE_CORE_FILE',
				GALAXYONE_CORE_PATH . 'galaxyone-core.php'
			);
		}

		if ( ! defined( 'GALAXYONE_CORE_VERSION' ) ) {
			define( 'GALAXYONE_CORE_VERSION', '0.2.0' );
		}
	}
);

/*
 * Install WooCommerce and GalaxyOne database schemas before WordPress reaches
 * init. This mirrors WooCommerce's own PHPUnit bootstrap ordering.
 */
tests_add_filter(
	'setup_theme',
	static function (): void {
		if ( ! class_exists( 'WC_Install' ) ) {
			throw new RuntimeException(
				'WooCommerce installer is unavailable.'
			);
		}

		WC_Install::install();

		\GalaxyOne\Core\Database\SchemaManager::maybe_upgrade();
	}
);

require_once $wp_tests_dir . '/includes/bootstrap.php';

if ( ! defined( 'GALAXYONE_INTEGRATION_BOOTSTRAPPED' ) ) {
	define( 'GALAXYONE_INTEGRATION_BOOTSTRAPPED', true );
}
