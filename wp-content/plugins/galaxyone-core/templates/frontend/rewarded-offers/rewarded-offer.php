<?php
/**
 * Rewarded offer template.
 *
 * @package GalaxyOne\Core
 *
 * @var array<string, int|string> $campaign Reward campaign.
 * @var int                       $product_id Product ID.
 */

defined( 'ABSPATH' ) || exit;

$offer_id = 'galaxyone-reward-offer-' . absint( $product_id );
?>

<section
	id="<?php echo esc_attr( $offer_id ); ?>"
	class="galaxyone-rewarded-offer"
	data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
	aria-labelledby="<?php echo esc_attr( $offer_id . '-title' ); ?>"
>
	<h3 id="<?php echo esc_attr( $offer_id . '-title' ); ?>">
		<?php esc_html_e( 'Optional special offer', 'galaxyone-core' ); ?>
	</h3>

	<p>
		<?php esc_html_e( 'You can always continue at the current price. Complete the optional sponsored reward to unlock this offer.', 'galaxyone-core' ); ?>
	</p>

	<button type="button" class="galaxyone-button galaxyone-rewarded-offer__start">
		<?php esc_html_e( 'Unlock special offer', 'galaxyone-core' ); ?>
	</button>

	<p class="galaxyone-rewarded-offer__status" role="status" aria-live="polite"></p>
</section>
