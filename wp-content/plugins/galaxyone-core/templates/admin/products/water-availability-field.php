<?php
/**
 * Water availability product-editor field.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

woocommerce_wp_checkbox(
	array(
		'id'          => \GalaxyOne\Core\Inventory\InventoryService::WATER_AVAILABILITY_META_KEY,
		'label'       => __( 'Water available', 'galaxyone-core' ),
		'value'       => $is_available ? 'yes' : 'no',
		'description' => __(
			'Keep the product visible while preventing customers from purchasing it when Water is temporarily unavailable.',
			'galaxyone-core'
		),
	)
);
