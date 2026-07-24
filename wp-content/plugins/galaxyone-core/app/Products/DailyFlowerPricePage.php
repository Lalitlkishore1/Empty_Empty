<?php
/**
 * Daily flower-price administration page.
 *
 * @package GalaxyOne\Core\Products
 */

namespace GalaxyOne\Core\Products;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Pricing\DailyFlowerPriceRepository;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Security\NonceVerifier;
use WC_Product;

final class DailyFlowerPricePage {

	/**
	 * Administration page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'galaxyone-flower-daily-prices';

	/**
	 * Form action name.
	 *
	 * @var string
	 */
	private const FORM_ACTION = 'galaxyone_save_flower_daily_prices';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'galaxyone_save_flower_daily_prices';

	/**
	 * Nonce request field.
	 *
	 * @var string
	 */
	private const NONCE_FIELD = 'galaxyone_flower_daily_prices_nonce';

	/**
	 * Registers the administration page and form handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			array( $this, 'register_menu' ),
			20
		);

		add_action(
			'admin_post_' . self::FORM_ACTION,
			array( $this, 'handle_form_submission' )
		);
	}

	/**
	 * Registers the daily Bloom price page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Daily Bloom Prices', 'galaxyone-core' ),
			__( 'Daily Bloom Prices', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the daily Bloom price page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'galaxyone-core' ),
				esc_html__( 'Daily Bloom Prices', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$effective_date = wp_date( 'Y-m-d' );
		$notice         = '';

		if ( isset( $_GET['effective_date'] ) && is_string( $_GET['effective_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_date = sanitize_text_field( wp_unslash( $_GET['effective_date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( DailyFlowerPriceRepository::is_valid_effective_date( $requested_date ) ) {
				$effective_date = $requested_date;
			}
		}

		if ( isset( $_GET['galaxyone_notice'] ) && is_string( $_GET['galaxyone_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = sanitize_key( wp_unslash( $_GET['galaxyone_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$bloom_products = ProductCategoryResolver::get_products_for_category(
			ProductCategoryResolver::BLOOMS_CATEGORY
		);
		$price_records  = array();

		foreach ( $bloom_products as $product ) {
			if ( $product instanceof WC_Product ) {
				$price_records[ $product->get_id() ] = DailyFlowerPriceRepository::get_for_product_date(
					$product->get_id(),
					$effective_date
				);
			}
		}

		require GALAXYONE_CORE_PATH . 'templates/admin/flower-prices/daily-price-page.php';
	}

	/**
	 * Validates and saves daily Bloom price records.
	 *
	 * @return void
	 */
	public function handle_form_submission(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to update daily Bloom prices.', 'galaxyone-core' ),
				esc_html__( 'Daily Bloom Prices', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		if ( ! NonceVerifier::verify_request_nonce( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			wp_die(
				esc_html__( 'The security check failed. No daily prices were changed.', 'galaxyone-core' ),
				esc_html__( 'Daily Bloom Prices', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$effective_date = isset( $_POST['effective_date'] ) && is_string( $_POST['effective_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['effective_date'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		$submitted_prices = isset( $_POST['flower_prices'] ) && is_array( $_POST['flower_prices'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['flower_prices'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: array();

		if (
			! DailyFlowerPriceRepository::is_valid_effective_date( $effective_date ) ||
			empty( $submitted_prices )
		) {
			$this->redirect_with_notice( 'invalid' );
		}

		$updates = array();

		foreach ( $submitted_prices as $product_id => $values ) {
			$product_id = absint( $product_id );

			if ( $product_id <= 0 || ! is_array( $values ) || ! ProductCategoryResolver::is_bloom( $product_id ) ) {
				$this->redirect_with_notice( 'invalid' );
			}

			$raw_price = isset( $values['price'] ) && is_scalar( $values['price'] )
				? trim( (string) $values['price'] )
				: '';

			$is_available_requested = isset( $values['is_available'] );

			if ( '' === $raw_price ) {
				if ( $is_available_requested ) {
					$this->redirect_with_notice( 'invalid' );
				}

				continue;
			}

			$price = DailyFlowerPriceRepository::normalize_price( $raw_price );

			if ( null === $price ) {
				$this->redirect_with_notice( 'invalid' );
			}

			$updates[] = array(
				'product_id'   => $product_id,
				'price'        => $price,
				'is_available' => $is_available_requested &&
					'yes' === sanitize_key( (string) $values['is_available'] ),
			);
		}

		if ( empty( $updates ) ) {
			$this->redirect_with_notice( 'empty' );
		}

		foreach ( $updates as $update ) {
			$old_record = DailyFlowerPriceRepository::get_for_product_date(
				$update['product_id'],
				$effective_date
			);

			if (
				! DailyFlowerPriceRepository::save(
					$update['product_id'],
					$effective_date,
					$update['price'],
					$update['is_available']
				)
			) {
				$this->redirect_with_notice( 'error' );
			}

			ActivityLogRepository::record(
				'flower_daily_price_saved',
				is_array( $old_record ) ? $old_record : array(),
				array(
					'product_id'     => $update['product_id'],
					'effective_date' => $effective_date,
					'price'          => $update['price'],
					'is_available'   => $update['is_available'],
				),
				array(
					'source' => 'daily_flower_price_admin',
				)
			);
		}

		$this->redirect_with_notice( 'saved', $effective_date );
	}

	/**
	 * Redirects to the daily Bloom price page with an administrative notice.
	 *
	 * @param string      $notice         Notice identifier.
	 * @param string|null $effective_date Optional effective date.
	 * @return void
	 */
	private function redirect_with_notice( string $notice, ?string $effective_date = null ): void {
		$arguments = array(
			'page'              => self::PAGE_SLUG,
			'galaxyone_notice'  => $notice,
		);

		if ( null !== $effective_date ) {
			$arguments['effective_date'] = $effective_date;
		}

		wp_safe_redirect(
			add_query_arg(
				$arguments,
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
