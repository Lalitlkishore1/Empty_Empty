<?php
/**
 * Customer operations module.
 *
 * @package GalaxyOne\Core\Customers
 */

namespace GalaxyOne\Core\Customers;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Orders\OrderQueryService;
use GalaxyOne\Core\Security\Capabilities;
use WP_User;

final class CustomerModule implements ModuleInterface {

	/**
	 * Customer page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'galaxyone-customers';

	/**
	 * Registers customer administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
	}

	/**
	 * Registers the customers submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Customers', 'galaxyone-core' ),
			__( 'Customers', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders customer search and order history.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->assert_access();

		$search       = $this->get_query_string( 'customer_search' );
		$customer_id  = absint( $this->get_query_string( 'customer_id' ) );
		$customers    = $this->get_customers( $search );
		$customer     = $customer_id > 0 ? get_userdata( $customer_id ) : false;
		$order_history = $customer instanceof WP_User
			? OrderQueryService::get_customer_orders( $customer->ID )
			: array();

		require GALAXYONE_CORE_PATH . 'templates/admin/customers/customers-page.php';
	}

	/**
	 * Returns customers matching an optional search term.
	 *
	 * @param string $search Search term.
	 * @return array<int, WP_User>
	 */
	private function get_customers( string $search ): array {
		$args = array(
			'role__in' => array( 'customer' ),
			'number'   => 20,
			'orderby'  => 'registered',
			'order'    => 'DESC',
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $args );

		return is_array( $users )
			? array_values(
				array_filter(
					$users,
					static fn( $user ): bool => $user instanceof WP_User
				)
			)
			: array();
	}

	/**
	 * Returns a sanitized query-string value.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private function get_query_string( string $key ): string {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
	}

	/**
	 * Stops unauthorized access before customer data is read.
	 *
	 * @return void
	 */
	private function assert_access(): void {
		if ( Capabilities::can_manage_galaxyone() ) {
			return;
		}

		wp_die(
			esc_html__( 'You do not have permission to access customer information.', 'galaxyone-core' ),
			esc_html__( 'Customers', 'galaxyone-core' ),
			array( 'response' => 403 )
		);
	}
}
