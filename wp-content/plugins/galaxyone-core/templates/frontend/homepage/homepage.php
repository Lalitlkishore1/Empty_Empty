<?php
/**
 * GalaxyOne storefront homepage template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="galaxyone-storefront">
	<section class="galaxyone-storefront__hero" aria-labelledby="galaxyone-homepage-title">
		<div class="galaxyone-container">
			<div class="galaxyone-storefront__hero-content">
				<p class="galaxyone-badge"><?php esc_html_e( 'Local delivery', 'galaxyone-core' ); ?></p>
				<h1 id="galaxyone-homepage-title" class="galaxyone-storefront__hero-title">
					<?php esc_html_e( 'Everyday essentials, delivered simply.', 'galaxyone-core' ); ?>
				</h1>
				<p class="galaxyone-storefront__hero-copy">
					<?php esc_html_e( 'Browse Water and Blooms with current availability and server-provided prices.', 'galaxyone-core' ); ?>
				</p>
			</div>
		</div>
	</section>

	<section class="galaxyone-section" aria-labelledby="galaxyone-categories-title">
		<div class="galaxyone-container">
			<div class="galaxyone-section__header">
				<h2 id="galaxyone-categories-title" class="galaxyone-title">
					<?php esc_html_e( 'Shop by category', 'galaxyone-core' ); ?>
				</h2>
				<p class="galaxyone-copy">
					<?php esc_html_e( 'Choose from our currently available categories.', 'galaxyone-core' ); ?>
				</p>
			</div>

			<div class="galaxyone-category-grid">
				<?php echo wp_kses_post( $category_cards ); ?>
			</div>
		</div>
	</section>

	<section class="galaxyone-section galaxyone-section--muted" aria-labelledby="galaxyone-water-title">
		<div class="galaxyone-container">
			<div class="galaxyone-section__header">
				<h2 id="galaxyone-water-title" class="galaxyone-title">
					<?php esc_html_e( 'Water', 'galaxyone-core' ); ?>
				</h2>
				<p class="galaxyone-copy">
					<?php esc_html_e( 'Current availability and pricing are shown for every product.', 'galaxyone-core' ); ?>
				</p>
			</div>

			<?php if ( $water_is_empty ) : ?>
				<p class="galaxyone-storefront-state galaxyone-storefront-state--empty" role="status">
					<?php esc_html_e( 'Water products are not available right now.', 'galaxyone-core' ); ?>
				</p>
			<?php else : ?>
				<?php echo wp_kses_post( $water_cards ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="galaxyone-section" aria-labelledby="galaxyone-blooms-title">
		<div class="galaxyone-container">
			<div class="galaxyone-section__header">
				<h2 id="galaxyone-blooms-title" class="galaxyone-title">
					<?php esc_html_e( 'Blooms', 'galaxyone-core' ); ?>
				</h2>
				<p class="galaxyone-copy">
					<?php esc_html_e( 'Fresh flowers and fragrances with today’s availability and price.', 'galaxyone-core' ); ?>
				</p>
			</div>

			<?php if ( $bloom_is_empty ) : ?>
				<p class="galaxyone-storefront-state galaxyone-storefront-state--empty" role="status">
					<?php esc_html_e( 'Blooms products are not available right now.', 'galaxyone-core' ); ?>
				</p>
			<?php else : ?>
				<?php echo wp_kses_post( $bloom_cards ); ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="galaxyone-section galaxyone-section--muted" aria-labelledby="galaxyone-future-categories-title">
		<div class="galaxyone-container">
			<div class="galaxyone-section__header">
				<h2 id="galaxyone-future-categories-title" class="galaxyone-title">
					<?php esc_html_e( 'More on the way', 'galaxyone-core' ); ?>
				</h2>
				<p class="galaxyone-copy">
					<?php esc_html_e( 'These categories are not yet available to purchase.', 'galaxyone-core' ); ?>
				</p>
			</div>

			<div class="galaxyone-category-grid">
				<?php echo wp_kses_post( $future_cards ); ?>
			</div>
		</div>
	</section>
</div>
