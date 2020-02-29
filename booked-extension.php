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
}
new Booked_Extension();
