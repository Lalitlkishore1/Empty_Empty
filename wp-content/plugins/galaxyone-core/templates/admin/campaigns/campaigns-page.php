<?php
/**
 * Offer campaign administration template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

$is_editing = is_array( $editing_campaign );
$campaign   = $is_editing
	? $editing_campaign
	: array(
		'campaign_key'     => '',
		'name'             => '',
		'campaign_type'    => 'product_price',
		'product_id'       => '',
		'offer_price'      => '',
		'status'           => 'paused',
		'starts_at_input'  => '',
		'ends_at_input'    => '',
	);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Offers and Campaigns', 'galaxyone-core' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'The campaign was saved and audited.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'deleted' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'The campaign was deleted and audited.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'invalid' === $notice ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'The campaign could not be saved. Review the submitted values and try again.', 'galaxyone-core' ); ?></p>
		</div>
	<?php endif; ?>

	<?php require GALAXYONE_CORE_PATH . 'templates/admin/offers/offer-summary.php'; ?>

	<hr>

	<h2>
		<?php
		echo esc_html(
			$is_editing
				? __( 'Edit Campaign', 'galaxyone-core' )
				: __( 'Create Campaign', 'galaxyone-core' )
		);
		?>
	</h2>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="galaxyone_save_offer_campaign">
		<input type="hidden" name="offer_campaign_action" value="save">
		<?php wp_nonce_field( 'galaxyone_save_offer_campaign', 'galaxyone_offer_campaign_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-key"><?php esc_html_e( 'Campaign key', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<?php if ( $is_editing ) : ?>
							<input
								id="galaxyone-campaign-key"
								name="campaign_key"
								type="hidden"
								value="<?php echo esc_attr( (string) $campaign['campaign_key'] ); ?>"
							>
							<code><?php echo esc_html( (string) $campaign['campaign_key'] ); ?></code>
						<?php else : ?>
							<input
								id="galaxyone-campaign-key"
								name="campaign_key"
								type="text"
								value=""
								pattern="[A-Za-z0-9-]+"
								required
							>
							<p class="description">
								<?php esc_html_e( 'Use a stable lowercase identifier, for example: water-weekend-offer.', 'galaxyone-core' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-name"><?php esc_html_e( 'Campaign name', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<input
							id="galaxyone-campaign-name"
							name="name"
							type="text"
							class="regular-text"
							value="<?php echo esc_attr( (string) $campaign['name'] ); ?>"
							required
						>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-type"><?php esc_html_e( 'Campaign type', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<select id="galaxyone-campaign-type" name="campaign_type">
							<option value="product_price" <?php selected( 'product_price', (string) $campaign['campaign_type'] ); ?>>
								<?php esc_html_e( 'Scheduled product price', 'galaxyone-core' ); ?>
							</option>
							<option value="free_delivery" <?php selected( 'free_delivery', (string) $campaign['campaign_type'] ); ?>>
								<?php esc_html_e( 'Free delivery', 'galaxyone-core' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-product"><?php esc_html_e( 'Product ID', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<input
							id="galaxyone-campaign-product"
							name="product_id"
							type="number"
							min="0"
							step="1"
							value="<?php echo esc_attr( (string) $campaign['product_id'] ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'Required for scheduled product prices. Free-delivery campaigns with product ID 0 apply to all products.', 'galaxyone-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-offer-price"><?php esc_html_e( 'Offer price', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<input
							id="galaxyone-campaign-offer-price"
							name="offer_price"
							type="text"
							inputmode="decimal"
							value="<?php echo esc_attr( (string) $campaign['offer_price'] ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'Required for scheduled product prices. It is ignored for free-delivery campaigns.', 'galaxyone-core' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-status"><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<select id="galaxyone-campaign-status" name="status">
							<option value="active" <?php selected( 'active', (string) $campaign['status'] ); ?>>
								<?php esc_html_e( 'Active', 'galaxyone-core' ); ?>
							</option>
							<option value="paused" <?php selected( 'paused', (string) $campaign['status'] ); ?>>
								<?php esc_html_e( 'Paused', 'galaxyone-core' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-start"><?php esc_html_e( 'Start date and time', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<input
							id="galaxyone-campaign-start"
							name="starts_at"
							type="datetime-local"
							value="<?php echo esc_attr( (string) $campaign['starts_at_input'] ); ?>"
						>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="galaxyone-campaign-end"><?php esc_html_e( 'End date and time', 'galaxyone-core' ); ?></label>
					</th>
					<td>
						<input
							id="galaxyone-campaign-end"
							name="ends_at"
							type="datetime-local"
							value="<?php echo esc_attr( (string) $campaign['ends_at_input'] ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'Leave either date empty when the campaign should have no start or end limit.', 'galaxyone-core' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $is_editing ? __( 'Update Campaign', 'galaxyone-core' ) : __( 'Save Campaign', 'galaxyone-core' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Configured Campaigns', 'galaxyone-core' ); ?></h2>

	<?php if ( empty( $campaigns ) ) : ?>
		<p><?php esc_html_e( 'No campaigns have been configured.', 'galaxyone-core' ); ?></p>
	<?php else : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Product', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Offer', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $configured_campaign ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( (string) $configured_campaign['name'] ); ?></strong>
							<br>
							<code><?php echo esc_html( (string) $configured_campaign['campaign_key'] ); ?></code>
						</td>
						<td><?php echo esc_html( (string) $configured_campaign['campaign_type'] ); ?></td>
						<td>
							<?php
							echo esc_html(
								0 === (int) $configured_campaign['product_id']
									? __( 'All products', 'galaxyone-core' )
									: (string) $configured_campaign['product_id']
							);
							?>
						</td>
						<td>
							<?php
							echo esc_html(
								'' === (string) $configured_campaign['offer_price']
									? __( 'Free delivery', 'galaxyone-core' )
									: (string) $configured_campaign['offer_price']
							);
							?>
						</td>
						<td><?php echo esc_html( (string) $configured_campaign['status'] ); ?></td>
						<td>
							<a
								href="<?php echo esc_url( add_query_arg( array( 'page' => 'galaxyone-offers', 'campaign_key' => $configured_campaign['campaign_key'] ), admin_url( 'admin.php' ) ) ); ?>"
							>
								<?php esc_html_e( 'Edit', 'galaxyone-core' ); ?>
							</a>
							<form
								action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								method="post"
								style="display: inline;"
							>
								<input type="hidden" name="action" value="galaxyone_save_offer_campaign">
								<input type="hidden" name="offer_campaign_action" value="delete">
								<input
									type="hidden"
									name="campaign_key"
									value="<?php echo esc_attr( (string) $configured_campaign['campaign_key'] ); ?>"
								>
								<?php wp_nonce_field( 'galaxyone_save_offer_campaign', 'galaxyone_offer_campaign_nonce' ); ?>
								<button type="submit" class="button-link-delete">
									<?php esc_html_e( 'Delete', 'galaxyone-core' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
