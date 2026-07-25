<?php
/**
 * Product card template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

$product_heading_id = 'galaxyone-product-' . (int) $card['product_id'];
$product_status_id  = 'galaxyone-product-status-' . (int) $card['product_id'];
?>
<article class="galaxyone-product-card" aria-labelledby="<?php echo esc_attr( $product_heading_id ); ?>">
	<a
		class="galaxyone-product-card__image-link"
		href="<?php echo esc_url( (string) $card['product_url'] ); ?>"
		aria-label="<?php echo esc_attr( (string) $card['name'] ); ?>"
	>
		<?php echo wp_kses_post( (string) $card['image_html'] ); ?>
	</a>

	<div class="galaxyone-product-card__content">
		<span class="galaxyone-badge"><?php echo esc_html( (string) $card['category_label'] ); ?></span>

		<h3 id="<?php echo esc_attr( $product_heading_id ); ?>" class="galaxyone-product-card__title">
			<a href="<?php echo esc_url( (string) $card['product_url'] ); ?>">
				<?php echo esc_html( (string) $card['name'] ); ?>
			</a>
		</h3>

		<?php if ( ! empty( $card['has_price'] ) ) : ?>
			<div class="galaxyone-product-card__price" aria-label="<?php esc_attr_e( 'Current product price', 'galaxyone-core' ); ?>">
				<?php if ( ! empty( $card['has_offer'] ) ) : ?>
					<span class="galaxyone-product-card__normal-price">
						<?php echo wp_kses_post( (string) $card['normal_price_html'] ); ?>
					</span>
				<?php endif; ?>

				<strong><?php echo wp_kses_post( (string) $card['price_html'] ); ?></strong>

				<?php if ( ! empty( $card['has_offer'] ) ) : ?>
					<span class="galaxyone-product-card__offer">
						<?php echo esc_html( (string) $card['offer_label'] ); ?>
					</span>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<p class="galaxyone-product-card__status galaxyone-product-card__status--warning" role="status">
				<?php esc_html_e( 'Current price is unavailable.', 'galaxyone-core' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( empty( $card['is_available'] ) ) : ?>
			<p id="<?php echo esc_attr( $product_status_id ); ?>" class="galaxyone-product-card__status galaxyone-product-card__status--unavailable" role="status">
				<?php esc_html_e( 'Currently unavailable', 'galaxyone-core' ); ?>
			</p>
		<?php endif; ?>

		<p class="galaxyone-product-card__delivery">
			<?php echo esc_html( (string) $card['delivery_information'] ); ?>
		</p>

		<div class="galaxyone-product-card__actions">
			<?php if ( ! empty( $card['is_available'] ) && ! empty( $card['has_add_to_cart'] ) ) : ?>
				<a
					class="galaxyone-button add_to_cart_button ajax_add_to_cart"
					href="<?php echo esc_url( (string) $card['add_to_cart_url'] ); ?>"
					data-quantity="1"
					data-product_id="<?php echo esc_attr( (string) $card['product_id'] ); ?>"
					aria-label="<?php echo esc_attr( (string) $card['add_to_cart_aria'] ); ?>"
				>
					<?php echo esc_html( (string) $card['action_label'] ); ?>
				</a>
			<?php else : ?>
				<a
					class="galaxyone-button galaxyone-button--secondary"
					href="<?php echo esc_url( (string) $card['product_url'] ); ?>"
					<?php if ( ! empty( $card['is_available'] ) : ?>
						aria-label="<?php echo esc_attr( (string) $card['action_label'] . ': ' . $card['name'] ); ?>"
					<?php else : ?>
						aria-describedby="<?php echo esc_attr( $product_status_id ); ?>"
					<?php endif; ?>
				>
					<?php esc_html_e( 'View product', 'galaxyone-core' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</article>
