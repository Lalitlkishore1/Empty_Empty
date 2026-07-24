<?php
/**
 * GalaxyOne child theme functions.
 *
 * @package GalaxyOneChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the active child-theme version for cache busting.
 *
 * @return string
 */
function galaxyone_child_get_version(): string {
	$theme   = wp_get_theme();
	$version = $theme->get( 'Version' );

	return is_string( $version ) && '' !== $version ? $version : '0.1.0';
}

/**
 * Enqueues GalaxyOne child-theme assets.
 *
 * @return void
 */
function galaxyone_child_enqueue_assets(): void {
	$theme_version        = galaxyone_child_get_version();
	$parent_theme         = wp_get_theme( get_template() );
	$parent_theme_version = $parent_theme->get( 'Version' );

	wp_enqueue_style(
		'galaxyone-parent-theme',
		get_template_directory_uri() . '/style.css',
		array(),
		is_string( $parent_theme_version ) ? $parent_theme_version : null
	);

	wp_enqueue_style(
		'galaxyone-child-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		array( 'galaxyone-parent-theme' ),
		$theme_version
	);

	wp_enqueue_style(
		'galaxyone-child-components',
		get_stylesheet_directory_uri() . '/assets/css/components.css',
		array( 'galaxyone-child-tokens' ),
		$theme_version
	);

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style(
			'galaxyone-child-woocommerce',
			get_stylesheet_directory_uri() . '/assets/css/woocommerce.css',
			array( 'galaxyone-child-components' ),
			$theme_version
		);
	}

	wp_enqueue_style(
		'galaxyone-child',
		get_stylesheet_uri(),
		array( 'galaxyone-child-components' ),
		$theme_version
	);

	wp_enqueue_script(
		'galaxyone-child-frontend',
		get_stylesheet_directory_uri() . '/assets/js/frontend.js',
		array(),
		$theme_version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'galaxyone_child_enqueue_assets' );
