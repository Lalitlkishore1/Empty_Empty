<?php
/**
 * Active offer summary template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Active Free-Delivery Campaigns', 'galaxyone-core' ); ?></h2>

<?php if ( empty( $active_free_delivery_campaigns ) ) : ?>
	<p><?php esc_html_e( 'No free-delivery campaign is currently active.', 'galaxyone-core' ); ?></p>
<?php else : ?>
	<ul>
		<?php foreach ( $active_free_delivery_campaigns as $active_campaign ) : ?>
			<li>
				<strong><?php echo esc_html( (string) $active_campaign['name'] ); ?></strong>
				<?php
				if ( 0 === (int) $active_campaign['product_id'] ) {
					esc_html_e( ' — applies to all products.', 'galaxyone-core' );
				} else {
					echo esc_html(
						sprintf(
							/* translators: %d: WooCommerce product ID. */
							__( ' — applies to product ID %d.', 'galaxyone-core' ),
							(int) $active_campaign['product_id']
						)
					);
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
