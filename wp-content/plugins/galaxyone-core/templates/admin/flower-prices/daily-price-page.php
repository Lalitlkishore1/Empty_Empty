<?php
/**
 * Daily flower-price administration template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Daily Bloom Prices', 'galaxyone-core' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Daily Bloom prices and availability were saved.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'empty' === $notice ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Enter at least one daily price before saving.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( in_array( $notice, array( 'invalid', 'error' ), true ) ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Daily Bloom prices could not be saved. Review the submitted values and try again.', 'galaxyone-core' ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e( 'Set a price and availability for each Bloom product. Leave a price blank to leave that product unchanged.', 'galaxyone-core' ); ?>
	</p>

	<?php if ( empty( $bloom_products ) ) : ?>
		<p><?php esc_html_e( 'No published products are assigned to the Blooms category.', 'galaxyone-core' ); ?></p>
	<?php else : ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="galaxyone_save_flower_daily_prices">
			<?php wp_nonce_field( 'galaxyone_save_flower_daily_prices', 'galaxyone_flower_daily_prices_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="galaxyone-effective-date">
								<?php esc_html_e( 'Effective date', 'galaxyone-core' ); ?>
							</label>
						</th>
						<td>
							<input
								id="galaxyone-effective-date"
								name="effective_date"
								type="date"
								value="<?php echo esc_attr( $effective_date ); ?>"
								required
							>
						</td>
					</tr>
				</tbody>
			</table>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Product', 'galaxyone-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Daily price', 'galaxyone-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Available', 'galaxyone-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bloom_products as $product ) : ?>
						<?php
						$product_id = $product->get_id();
						$record     = $price_records[ $product_id ] ?? null;
						$price      = is_array( $record ) ? (string) $record['price'] : '';
						$available  = is_array( $record ) && ! empty( $record['is_available'] );
						?>
						<tr>
							<td>
								<label for="galaxyone-flower-price-<?php echo esc_attr( (string) $product_id ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</label>
							</td>
							<td>
								<input
									id="galaxyone-flower-price-<?php echo esc_attr( (string) $product_id ); ?>"
									name="flower_prices[<?php echo esc_attr( (string) $product_id ); ?>][price]"
									type="text"
									inputmode="decimal"
									pattern="\d+(\.\d{1,4})?"
									value="<?php echo esc_attr( $price ); ?>"
								>
							</td>
							<td>
								<label>
									<input
										name="flower_prices[<?php echo esc_attr( (string) $product_id ); ?>][is_available]"
										type="checkbox"
										value="yes"
										<?php checked( $available ); ?>
									>
									<?php esc_html_e( 'Available for purchase', 'galaxyone-core' ); ?>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Daily Bloom Prices', 'galaxyone-core' ) ); ?>
		</form>
	<?php endif; ?>
</div>
