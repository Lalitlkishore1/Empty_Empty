<?php
/**
 * Category card template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

$category_heading_id = 'galaxyone-category-' . sanitize_html_class( (string) $category['slug'] );
?>
<article
	class="galaxyone-category-card<?php echo ! empty( $category['is_available'] ) ? '' : ' galaxyone-category-card--future'; ?>"
	aria-labelledby="<?php echo esc_attr( $category_heading_id ); ?>"
>
	<div class="galaxyone-category-card__content">
		<span class="galaxyone-badge">
			<?php echo esc_html( (string) $category['status_label'] ); ?>
		</span>

		<h3 id="<?php echo esc_attr( $category_heading_id ); ?>" class="galaxyone-category-card__title">
			<?php echo esc_html( (string) $category['title'] ); ?>
		</h3>

		<p class="galaxyone-category-card__copy">
			<?php echo esc_html( (string) $category['description'] ); ?>
		</p>

		<?php if ( ! empty( $category['is_available'] ) ) : ?>
			<p class="galaxyone-category-card__count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of products. */
						_n(
							'%d product available',
							'%d products available',
							(int) $category['product_count'],
							'galaxyone-core'
						),
						(int) $category['product_count']
					)
				);
				?>
			</p>

			<a
				class="galaxyone-button galaxyone-button--secondary"
				href="<?php echo esc_url( (string) $category['url'] ); ?>"
			>
				<?php esc_html_e( 'Browse category', 'galaxyone-core' ); ?>
			</a>
		<?php else : ?>
			<p class="galaxyone-category-card__status" role="status">
				<?php esc_html_e( 'Not available to purchase yet.', 'galaxyone-core' ); ?>
			</p>
		<?php endif; ?>
	</div>
</article>
