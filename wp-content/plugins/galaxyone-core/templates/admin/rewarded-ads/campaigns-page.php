<?php
/**
 * Reward campaign administration template.
 *
 * @package GalaxyOne\Core
 *
 * @var array<int, array<string, int|string>> $campaigns Reward campaigns.
 * @var string                                 $notice    Notice key.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Rewarded Offers', 'galaxyone-core' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Reward campaign saved.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'invalid' === $notice ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'The reward campaign could not be saved. Check every value and ensure the reward price is lower than the current server price.', 'galaxyone-core' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="galaxyone_save_reward_campaign">
		<?php wp_nonce_field( 'galaxyone_save_reward_campaign', 'galaxyone_reward_campaign_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="campaign_key"><?php esc_html_e( 'Campaign key', 'galaxyone-core' ); ?></label></th>
				<td><input class="regular-text" id="campaign_key" name="campaign_key" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="name"><?php esc_html_e( 'Campaign name', 'galaxyone-core' ); ?></label></th>
				<td><input class="regular-text" id="name" name="name" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="product_id"><?php esc_html_e( 'Product ID', 'galaxyone-core' ); ?></label></th>
				<td><input class="small-text" id="product_id" name="product_id" type="number" min="1" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="offer_price"><?php esc_html_e( 'Reward price', 'galaxyone-core' ); ?></label></th>
				<td><input class="small-text" id="offer_price" name="offer_price" inputmode="decimal" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="provider_key"><?php esc_html_e( 'Provider', 'galaxyone-core' ); ?></label></th>
				<td>
					<select id="provider_key" name="provider_key">
						<option value="staging"><?php esc_html_e( 'Staging test provider', 'galaxyone-core' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="required_completions"><?php esc_html_e( 'Required completions', 'galaxyone-core' ); ?></label></th>
				<td><input class="small-text" id="required_completions" name="required_completions" type="number" min="1" max="10" value="1" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="reward_ttl_minutes"><?php esc_html_e( 'Reward validity (minutes)', 'galaxyone-core' ); ?></label></th>
				<td><input class="small-text" id="reward_ttl_minutes" name="reward_ttl_minutes" type="number" min="1" max="1440" value="30" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="status"><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></label></th>
				<td>
					<select id="status" name="status">
						<option value="paused"><?php esc_html_e( 'Paused', 'galaxyone-core' ); ?></option>
						<option value="active"><?php esc_html_e( 'Active', 'galaxyone-core' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="starts_at"><?php esc_html_e( 'Starts at', 'galaxyone-core' ); ?></label></th>
				<td><input id="starts_at" name="starts_at" type="datetime-local"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ends_at"><?php esc_html_e( 'Ends at', 'galaxyone-core' ); ?></label></th>
				<td><input id="ends_at" name="ends_at" type="datetime-local"></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save reward campaign', 'galaxyone-core' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Configured reward campaigns', 'galaxyone-core' ); ?></h2>

	<?php if ( empty( $campaigns ) ) : ?>
		<p><?php esc_html_e( 'No reward campaigns have been configured.', 'galaxyone-core' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Campaign', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Product', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Reward price', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Validity', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $campaign['name'] ); ?></td>
						<td><?php echo esc_html( (string) $campaign['product_id'] ); ?></td>
						<td><?php echo wp_kses_post( wc_price( (string) $campaign['offer_price'] ) ); ?></td>
						<td><?php echo esc_html( (string) $campaign['status'] ); ?></td>
						<td>
							<?php
							echo esc_html(
								trim(
									(string) $campaign['starts_at'] . ' – ' . (string) $campaign['ends_at'],
									' –'
								)
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
