<?php
/**
 * Plugin notices.
 *
 * @package GalaxyOne\Core\Support
 */

namespace GalaxyOne\Core\Support;

final class PluginNotice {

	/**
	 * Registers the dependency error notice.
	 *
	 * @return void
	 */
	public static function register_requirement_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			array( self::class, 'render_requirement_notice' )
		);
	}

	/**
	 * Renders the dependency error notice.
	 *
	 * @return void
	 */
	public static function render_requirement_notice(): void {
		$errors = Requirements::get_errors();

		if ( empty( $errors ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo esc_html( implode( ' ', $errors ) );
				?>
			</p>
		</div>
		<?php
	}
}
