<?php // phpcs:ignore
/**
 * Plugin Name: Booked Extension
 * Description: An extension to the Booked plugin.
 * Version: 1.0.0
 * Author: Michael Bengtsson
 * Author URI: https://github.com/MichaelBengtsson/
 * Text Domain: booked-extension
 * Domain Path: /languages
 *
 * @package Booked_Extension
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class Booked_Extension {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'booked_shortcode_appointments_additional_information', array( $this, 'add_to_calendar_customer' ) );
		add_action( 'booked_admin_calendar_buttons_after', array( $this, 'add_custom_admin_inputs' ), 15, 3 );
		add_action( 'booked_admin_calendar_buttons_after', array( $this, 'add_to_calendar_admin' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_booked_save_discrepency', array( $this, 'ajax_save_note' ) );
		add_action( 'booked_fe_calendar_date_after', array( $this, 'add_script_to_list' ) );
		add_filter( 'booked_appointments_array', array( $this, 'set_appt_ids' ) );
		add_filter( 'booked_fe_calendar_timeslot_after', array( $this, 'add_hidden_meta' ), 10, 2 );
		add_filter( 'booked_csv_export_columns', array( $this, 'change_custom_field_data' ) );
		add_filter( 'booked_csv_row_data', array( $this, 'set_custom_field_data_csv' ), 10, 2 );
	}

	/**
	 * Adds custom field values to the customers side of the calendar entry.
	 *
	 * @param int $post_id The WordPress post id.
	 * @return void
	 */
	public function add_to_calendar_customer( $post_id ) {
		$postmeta = get_post_meta( $post_id );
		foreach ( $postmeta['_cf_meta_value'] as $meta ) {
			if ( '' !== $meta ) {
				echo wp_kses_post( $meta );
			}
		}
	}

	/**
	 * Adds custom buttons to the admin listing.
	 *
	 * @param int    $calendar_id The Calendar ID.
	 * @param int    $appt_id The appointment post id.
	 * @param string $status The appointment status.
	 * @return void
	 */
	public function add_custom_admin_inputs( $calendar_id, $appt_id, $status ) {
		$type   = get_post_meta( $appt_id, '_booked-discrepancy', true ) ? get_post_meta( $appt_id, '_booked-discrepancy', true ) : false;
		$reason = get_post_meta( $appt_id, '_booked-discrepancy-text', true ) ? get_post_meta( $appt_id, '_booked-discrepancy-text', true ) : '';
		?>
			<form name="booked-discrepancy" id=<?php echo esc_attr( "booked-discrepancy-$appt_id" ); ?> style="clear:both; margin-top:15px; margin-bottom:0;">
				<label for="booked-discrepancy"> <?php esc_html_e( 'Discrepancy', 'booked-extension' ); ?>
				<select name="booked-discrepancy">
					<option value="none" <?php echo ( 'none' === $type || false === $type ) ? 'selected' : ''; ?>><?php esc_html_e( 'None', 'booked-extension' ); ?></option>
					<option value="time" <?php echo ( 'time' === $type ) ? 'selected' : ''; ?>><?php esc_html_e( 'Time', 'booked-extension' ); ?></option>
					<option value="other" <?php echo ( 'other' === $type ) ? 'selected' : ''; ?>><?php esc_html_e( 'Other', 'booked-extension' ); ?></option>
				</select>
				</label>
				<div style="display:block; margin-top:15px" class="booked-discrepancy-wrapper">
					<textarea name="booked-discrepancy-text" placeholder="<?php esc_html_e( 'Note...', 'booked-extension' ); ?>"><?php echo esc_attr( $reason ); ?></textarea>
					<button type="button" 
						class="button button-secondary booked-discrepancy-save" 
						id=<?php echo esc_attr( "booked-discrepancy-save-$appt_id" ); ?>
						data-post-id="<?php echo esc_attr( $appt_id ); ?>">
						<?php esc_html_e( 'Save note', 'booked-extension' ); ?>
					</button>
				</div>
				</form>
		<?php
	}

	/**
	 * Adds custom field values to the admins site of the calendar entry.
	 *
	 * @param int    $calendar_id The Calendar ID.
	 * @param int    $appt_id The appointment post id.
	 * @param string $status The appointment status.
	 * @return void
	 */
	public function add_to_calendar_admin( $calendar_id, $appt_id, $status ) {
		$postmeta = get_post_meta( $appt_id );
		foreach ( $postmeta['_cf_meta_value'] as $meta_data ) {
			if ( '' !== $meta_data ) {
				$metas = explode( '</p>', $meta_data );
				foreach ( $metas as $meta ) {
					if ( '' !== $meta ) {
						// Replace <br> with a colon and space.
						$meta = str_replace( '<br>', ': ', $meta );
						// Add a <br> between different metas.
						?>
						<p style="display:block; margin-bottom:5px;"><?php echo wp_kses_post( wp_strip_all_tags( $meta, false ) ); ?></p>
						<?php
					}
				}
			}
		}
	}

	/**
	 * Enqueues scripts for the admin pages.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		if ( ! isset( $_GET['page'] ) && 'booked-appointments' !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		// Add blockUI dependency.
		wp_register_script( 'jquery-blockui', plugins_url( 'assets/js/blockui.js', __FILE__ ), array( 'jquery' ), '2.70', true );

		wp_register_script(
			'booked_addon',
			plugins_url( 'assets/js/admin.js', __FILE__ ),
			array( 'jquery', 'jquery-blockui' ),
			'1.0.0'
		);

		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'booked_addon', 'bookedParams', $params );

		wp_enqueue_script( 'booked_addon' );
	}

	/**
	 * Ajax function that saves the note to the booking.
	 *
	 * @return void
	 */
	public function ajax_save_note() {
		$post_id = $_POST['id']; // phpcs:ignore
		$values  = $_POST['values']; // phpcs:ignore
		foreach ( $values as $key => $value ) {
			update_post_meta( $post_id, "_$key", $value );
		}
		wp_send_json_success();
		wp_die();
	}

	/**
	 * Adds the script to the frontend page.
	 *
	 * @return void
	 */
	public function add_script_to_list() {
		?>
			<script>
				jQuery(function($) {
					function moveFields() {
						var lists = $('.booked-public-appointment-list');
						lists.each( function( i ) {
							var meta = $(lists[i]).parent().find('.booked-ext-meta-data');
							var li = $(lists[i]).parent().find('li');
							meta.each( function( x ) {
								$( li[x] ).append( $( meta[x] ).val() );
							});
						});
					}
					moveFields();
				});
			</script>
		<?php
	}

	/**
	 * Sets the appt ids for other functions.
	 *
	 * @param array $appt_ids The appt ids.
	 * @return array
	 */
	public function set_appt_ids( $appt_ids ) {
		$this->appt_ids = $appt_ids;
		return $appt_ids;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $html HTML code.
	 * @param string $this_timeslot_timestamp The timestamp for the timeslot.
	 * @return string
	 */
	public function add_hidden_meta( $html, $this_timeslot_timestamp ) {
		$appt_ids = $this->appt_ids;
		if ( empty( $appt_ids ) ) {
			return $html;
		}

		ob_start();
		?>
		<?php
		foreach ( $appt_ids as $appt_id => $values ) {
			if ( $this_timeslot_timestamp == $values['timestamp'] ) { // phpcs:ignore
				$metatext = '';
				$postmeta = get_post_meta( $appt_id );
				foreach ( $postmeta['_cf_meta_value'] as $meta ) {
					if ( '' !== $meta ) {
						$metatext .= $meta;
					}
				}
				?>
					<input type="hidden" class="booked-ext-meta-data" value="<?php echo esc_attr( $metatext ); ?>">
				<?php
			}
		}
		$html .= ob_get_clean();

		return $html;
	}

	/**
	 * Unsets the Custom Field Data column and adds the custom metas as seperate columns.
	 *
	 * @param array $columns The columns.
	 * @return array
	 */
	public function change_custom_field_data( $columns ) {
		// Set columns for custom data from input fields.
		$columns[] = __( 'Discrepency', 'booked-extension' );
		$columns[] = __( 'Note', 'booked-extension' );

		// Unset the custom fields data column.
		foreach ( $columns as $key => $title ) {
			if ( 'Custom Field Data' === $title ) {
				unset( $columns[ $key ] );
			}
		}
		// Get all custom fields from the settings.
		$custom_fields = json_decode( stripslashes( get_option( 'booked_custom_fields' ) ), true );

		// Loop each custom field and set the column based on the name.
		foreach ( $custom_fields as $custom_field ) {
			if ( ! is_bool( $custom_field['value'] ) ) {
				$columns[] = $custom_field['value'];
			}
		}

		return $columns;
	}

	/**
	 * Sets the custom field data for the appointments.
	 *
	 * @param array $appointment The Appointment data.
	 * @param int   $appt_id The post id for the appointment.
	 * @return array
	 */
	public function set_custom_field_data_csv( $appointment, $appt_id ) {
		// Get the custom values from input fields.
		$type   = get_post_meta( $appt_id, '_booked-discrepancy', true ) ? get_post_meta( $appt_id, '_booked-discrepancy', true ) : 'none';
		$reason = get_post_meta( $appt_id, '_booked-discrepancy-text', true ) ? get_post_meta( $appt_id, '_booked-discrepancy-text', true ) : ' ';

		// Set custom values from input fields.
		$appointment['discrepency'] = $type;
		$appointment['note']        = $reason;

		// Process meta data.
		$meta_data = $appointment['custom_field_data'];
		// Explode the meta data on new lines.
		$exploded = explode( "\n", $meta_data );
		// Unset every empty value.
		foreach ( $exploded as $key => $value ) {
			if ( '' === $value ) {
				unset( $exploded[ $key ] );
			}
		}
		// Reset keys.
		$exploded = array_values( $exploded );

		$length = count( $exploded );

		// Remove even keys, we only want the values and the even keys is the meta names.
		for ( $i = 0; $i < $length; $i++ ) {
			if ( ( $i % 2 ) === 0 ) {
				unset( $exploded[ $i ] );
			}
		}

		// Unset the current custom field data.
		unset( $appointment['custom_field_data'] );

		// Set the values to the appointment array.
		foreach ( $exploded as $value ) {
			$appointment[] = $value;
		}

		return $appointment;
	}
}
new Booked_Extension();
