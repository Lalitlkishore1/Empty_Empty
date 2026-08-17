<?php
/**
 * Checkout delivery-selection template.
 *
 * @package GalaxyOne\Core
 *
 * @var array<string, string> $selection Current delivery selection.
 * @var array<string, mixed>  $options   Available delivery options.
 * @var bool                   $has_water Whether the cart contains Water.
 * @var array<string, string> $water_access_options Water access options.
 */

defined( 'ABSPATH' ) || exit;

$selection = isset( $selection ) && is_array( $selection ) ? $selection : array();
$options   = isset( $options ) && is_array( $options ) ? $options : array();
$has_water = isset( $has_water ) && true === $has_water;
$water_access_options = isset( $water_access_options ) && is_array( $water_access_options )
	? $water_access_options
	: array();

$selected_date = isset( $selection['delivery_date'] ) && is_scalar( $selection['delivery_date'] )
	? sanitize_text_field( (string) $selection['delivery_date'] )
	: '';
$selected_slot = isset( $selection['slot_key'] ) && is_scalar( $selection['slot_key'] )
	? sanitize_title( (string) $selection['slot_key'] )
	: '';
$dates         = isset( $options['dates'] ) && is_array( $options['dates'] )
	? $options['dates']
	: array();
$slots         = isset( $options['slots'] ) && is_array( $options['slots'] )
	? $options['slots']
	: array();
$slots_by_date = isset( $options['slots_by_date'] ) && is_array( $options['slots_by_date'] )
	? $options['slots_by_date']
	: array();
$date_options  = array();
$slot_options  = array();
$slot_labels   = array();
$slot_availability = array();
$water_access  = isset( $selection['water_access'] ) && is_scalar( $selection['water_access'] )
	? sanitize_key( (string) $selection['water_access'] )
	: '';
$water_floor   = isset( $selection['water_floor'] ) && is_scalar( $selection['water_floor'] )
	? sanitize_text_field( (string) $selection['water_floor'] )
	: '';

foreach ( $dates as $date ) {
	if ( ! is_scalar( $date ) ) {
		continue;
	}

	$date = sanitize_text_field( (string) $date );

	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$date_options[ $date ] = $date;
	}
}

foreach ( $slots as $slot ) {
	if (
		! is_object( $slot ) ||
		! isset( $slot->rule_key, $slot->label ) ||
		! is_scalar( $slot->rule_key ) ||
		! is_scalar( $slot->label )
	) {
		continue;
	}

	$slot_key   = sanitize_title( (string) $slot->rule_key );
	$slot_label = sanitize_text_field( (string) $slot->label );

	if ( '' !== $slot_key && '' !== $slot_label ) {
		$slot_labels[ $slot_key ] = $slot_label;
	}
}

foreach ( $slots_by_date as $available_date => $available_slots ) {
	if ( ! is_scalar( $available_date ) || ! is_array( $available_slots ) ) {
		continue;
	}

	$available_date = sanitize_text_field( (string) $available_date );

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $available_date ) ) {
		continue;
	}

	$slot_availability[ $available_date ] = array();

	foreach ( $available_slots as $available_slot ) {
		if ( ! is_object( $available_slot ) || ! isset( $available_slot->rule_key ) || ! is_scalar( $available_slot->rule_key ) ) {
			continue;
		}

		$available_slot_key = sanitize_title( (string) $available_slot->rule_key );

		if ( isset( $slot_labels[ $available_slot_key ] ) ) {
			$slot_availability[ $available_date ][] = $available_slot_key;
		}
	}
}

if ( isset( $slot_availability[ $selected_date ] ) ) {
	foreach ( $slot_availability[ $selected_date ] as $available_slot_key ) {
		$slot_options[ $available_slot_key ] = $slot_labels[ $available_slot_key ];
	}
}
?>

<section class="galaxyone-checkout-delivery-selection" aria-labelledby="galaxyone-delivery-selection-title">
	<h3 id="galaxyone-delivery-selection-title">
		<?php esc_html_e( 'Delivery selection', 'galaxyone-core' ); ?>
	</h3>

	<?php if ( empty( $date_options ) || empty( $slot_labels ) ) : ?>
		<p>
			<?php esc_html_e( 'Delivery dates or time slots are currently unavailable. Please try again later.', 'galaxyone-core' ); ?>
		</p>
	<?php elseif ( function_exists( 'woocommerce_form_field' ) ) : ?>
		<?php
		woocommerce_form_field(
			'galaxyone_delivery_date',
			array(
				'type'     => 'select',
				'label'    => __( 'Delivery date', 'galaxyone-core' ),
				'required' => true,
				'class'    => array( 'form-row-wide' ),
				'options'  => array(
					'' => __( 'Select a delivery date', 'galaxyone-core' ),
				) + $date_options,
			),
			$selected_date
		);

		woocommerce_form_field(
			'galaxyone_delivery_slot',
			array(
				'type'     => 'select',
				'label'    => __( 'Delivery time slot', 'galaxyone-core' ),
				'required' => true,
				'class'    => array( 'form-row-wide' ),
				'options'  => array(
					'' => __( 'Select a delivery time slot', 'galaxyone-core' ),
				) + $slot_options,
			),
			$selected_slot
		);

		if ( $has_water ) {
			$access_options = array(
				'' => __( 'Select delivery access', 'galaxyone-core' ),
			);

			foreach ( $water_access_options as $access_key => $access_label ) {
				if ( ! is_string( $access_key ) || ! is_scalar( $access_label ) ) {
					continue;
				}

				$access_key = sanitize_key( $access_key );

				if ( '' !== $access_key ) {
					$access_options[ $access_key ] = sanitize_text_field( (string) $access_label );
				}
			}

			woocommerce_form_field(
				'galaxyone_water_delivery_access',
				array(
					'type'     => 'select',
					'label'    => __( 'Water delivery access', 'galaxyone-core' ),
					'required' => true,
					'class'    => array( 'form-row-wide' ),
					'options'  => $access_options,
				),
				$water_access
			);

			woocommerce_form_field(
				'galaxyone_water_delivery_floor',
				array(
					'type'              => 'number',
					'label'             => __( 'Floor for stairs delivery', 'galaxyone-core' ),
					'required'          => false,
					'class'             => array( 'form-row-wide' ),
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
					'description'       => __( 'Required only when stairs are selected. Floor 5 or above requires manual delivery confirmation.', 'galaxyone-core' ),
				),
				$water_floor
			);
		}
		?>
	<?php else : ?>
		<p class="form-row form-row-wide">
			<label for="galaxyone_delivery_date">
				<?php esc_html_e( 'Delivery date', 'galaxyone-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'galaxyone-core' ); ?>">*</abbr>
			</label>
			<select id="galaxyone_delivery_date" name="galaxyone_delivery_date" required>
				<option value=""><?php esc_html_e( 'Select a delivery date', 'galaxyone-core' ); ?></option>
				<?php foreach ( $date_options as $date_value => $date_label ) : ?>
					<option
						value="<?php echo esc_attr( $date_value ); ?>"
						<?php selected( $selected_date, $date_value ); ?>
					>
						<?php echo esc_html( $date_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="form-row form-row-wide">
			<label for="galaxyone_delivery_slot">
				<?php esc_html_e( 'Delivery time slot', 'galaxyone-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'galaxyone-core' ); ?>">*</abbr>
			</label>
			<select id="galaxyone_delivery_slot" name="galaxyone_delivery_slot" required>
				<option value=""><?php esc_html_e( 'Select a delivery time slot', 'galaxyone-core' ); ?></option>
				<?php foreach ( $slot_options as $slot_value => $slot_label ) : ?>
					<option
						value="<?php echo esc_attr( $slot_value ); ?>"
						<?php selected( $selected_slot, $slot_value ); ?>
					>
						<?php echo esc_html( $slot_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php endif; ?>
</section>

<script>
	(function () {
		const dateField = document.getElementById( 'galaxyone_delivery_date' );
		const slotField = document.getElementById( 'galaxyone_delivery_slot' );
		const slotLabels = <?php echo wp_json_encode( $slot_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded JSON contains only sanitized checkout option data. ?>;
		const slotAvailability = <?php echo wp_json_encode( $slot_availability, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded JSON contains only sanitized checkout option data. ?>;

		if ( ! dateField || ! slotField ) {
			return;
		}

		const updateSlots = function () {
			const selectedSlot = slotField.value;
			const availableSlots = slotAvailability[ dateField.value ] || [];

			slotField.replaceChildren();
			slotField.add( new Option( '<?php echo esc_js( __( 'Select a delivery time slot', 'galaxyone-core' ) ); ?>', '' ) );

			availableSlots.forEach( function ( slotKey ) {
				if ( ! Object.prototype.hasOwnProperty.call( slotLabels, slotKey ) ) {
					return;
				}

				slotField.add( new Option( slotLabels[ slotKey ], slotKey ) );
			} );

			if ( availableSlots.includes( selectedSlot ) ) {
				slotField.value = selectedSlot;
			}
		};

		dateField.addEventListener( 'change', updateSlots );
		updateSlots();
	}());
</script>
