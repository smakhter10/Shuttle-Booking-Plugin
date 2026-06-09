<?php
/**
 * Admin view: Single Booking
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $booking ) ) :
	?>
	<div class="wrap nsb-wrap">
		<h1><?php esc_html_e( 'Booking Not Found', 'napoleon-shuttle-booking' ); ?></h1>
		<p><?php esc_html_e( 'The requested booking could not be found.', 'napoleon-shuttle-booking' ); ?></p>
		<a href="<?php echo esc_url( nsb_admin_url( 'nsb-bookings' ) ); ?>" class="button"><?php esc_html_e( 'Back to Bookings', 'napoleon-shuttle-booking' ); ?></a>
	</div>
	<?php
	return;
endif;

$booking_id       = (int) $booking['id'];
$order_url        = NSB_Bookings::get_order_admin_url( $booking['wc_order_id'] ?? 0 );
$booking_statuses = NSB_Bookings::booking_statuses();
$payment_statuses = NSB_Bookings::payment_statuses();

$format_date = static function ( $date ) {
	return ! empty( $date ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $date ) ) : '—';
};
$format_trip_date = static function ( $date ) {
	return ! empty( $date ) ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '—';
};
$format_trip_time = static function ( $time ) {
	return ! empty( $time ) ? date_i18n( get_option( 'time_format' ), strtotime( $time ) ) : '—';
};
?>
<div class="wrap nsb-wrap">
	<h1>
		<?php esc_html_e( 'Booking Details', 'napoleon-shuttle-booking' ); ?>
		<code><?php echo esc_html( $booking['booking_reference'] ); ?></code>
	</h1>
	<a href="<?php echo esc_url( nsb_admin_url( 'nsb-bookings' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Bookings', 'napoleon-shuttle-booking' ); ?></a>

	<div class="nsb-booking-view-grid">
		<div class="nsb-card">
			<h2><?php esc_html_e( 'Booking Details', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="widefat striped nsb-detail-table"><tbody>
				<tr><th><?php esc_html_e( 'Booking ID', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking_id ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Reference', 'napoleon-shuttle-booking' ); ?></th><td><code><?php echo esc_html( $booking['booking_reference'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Booking Status', 'napoleon-shuttle-booking' ); ?></th><td><span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['booking_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::booking_status_label( $booking['booking_status'] ) ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Payment Status', 'napoleon-shuttle-booking' ); ?></th><td><span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['payment_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::payment_status_label( $booking['payment_status'] ) ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Created', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_date( $booking['created_at'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Updated', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_date( $booking['updated_at'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Paid At', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_date( $booking['paid_at'] ?? '' ) ); ?></td></tr>
			</tbody></table>
		</div>

		<div class="nsb-card">
			<h2><?php esc_html_e( 'Customer Details', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="widefat striped nsb-detail-table"><tbody>
				<tr><th><?php esc_html_e( 'Name', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['customer_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'napoleon-shuttle-booking' ); ?></th><td><a href="mailto:<?php echo esc_attr( $booking['customer_email'] ); ?>"><?php echo esc_html( $booking['customer_email'] ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Phone', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['customer_phone'] ); ?></td></tr>
			</tbody></table>
		</div>

		<div class="nsb-card">
			<h2><?php esc_html_e( 'Trip Details', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="widefat striped nsb-detail-table"><tbody>
				<tr><th><?php esc_html_e( 'Package', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['package_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pickup Date', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_trip_date( $booking['booking_date'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pickup Time', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_trip_time( $booking['booking_time'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Pickup', 'napoleon-shuttle-booking' ); ?></th><td><?php echo nl2br( esc_html( $booking['pickup_address'] ) ); ?></td></tr>
				<?php if ( ! empty( $booking['return_date'] ) ) : ?><tr><th><?php esc_html_e( 'Drop-off / Return Date', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_trip_date( $booking['return_date'] ) ); ?></td></tr><?php endif; ?>
				<?php if ( ! empty( $booking['return_time'] ) ) : ?><tr><th><?php esc_html_e( 'Drop-off / Return Time', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $format_trip_time( $booking['return_time'] ) ); ?></td></tr><?php endif; ?>
				<tr><th><?php esc_html_e( 'Drop-off', 'napoleon-shuttle-booking' ); ?></th><td><?php echo nl2br( esc_html( $booking['dropoff_address'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Passengers', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['passenger_count'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Notes', 'napoleon-shuttle-booking' ); ?></th><td><?php echo nl2br( esc_html( $booking['notes'] ?? '' ) ); ?></td></tr>
				<?php if ( ! empty( $booking['flight_number'] ) ) : ?><tr><th><?php esc_html_e( 'Flight Number', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['flight_number'] ); ?></td></tr><?php endif; ?>
				<?php if ( ! empty( $booking['airline_name'] ) ) : ?><tr><th><?php esc_html_e( 'Airline', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['airline_name'] ); ?></td></tr><?php endif; ?>
				<?php if ( ! empty( $booking['luggage_count'] ) ) : ?><tr><th><?php esc_html_e( 'Luggage Count', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['luggage_count'] ); ?></td></tr><?php endif; ?>
			</tbody></table>
		</div>

		<div class="nsb-card">
			<h2><?php esc_html_e( 'Payment Details', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="widefat striped nsb-detail-table"><tbody>
				<tr><th><?php esc_html_e( 'Base Price', 'napoleon-shuttle-booking' ); ?></th><td><?php echo wp_kses_post( nsb_format_price( $booking['base_price'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment Type', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( ucfirst( $booking['payment_type'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Amount Paid / Due Now', 'napoleon-shuttle-booking' ); ?></th><td><?php echo wp_kses_post( nsb_format_price( $booking['amount_due_now'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Remaining Balance', 'napoleon-shuttle-booking' ); ?></th><td><?php echo wp_kses_post( nsb_format_price( $booking['remaining_balance'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'WooCommerce Order', 'napoleon-shuttle-booking' ); ?></th><td><?php if ( $order_url ) : ?><a href="<?php echo esc_url( $order_url ); ?>" target="_blank">#<?php echo esc_html( $booking['wc_order_id'] ); ?></a><?php else : ?>—<?php endif; ?></td></tr>
				<tr><th><?php esc_html_e( 'Transaction ID', 'napoleon-shuttle-booking' ); ?></th><td><?php echo esc_html( $booking['transaction_id'] ?? '—' ); ?></td></tr>
			</tbody></table>
		</div>

		<div class="nsb-card nsb-card--full">
			<h2><?php esc_html_e( 'Admin Tools', 'napoleon-shuttle-booking' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="nsb_action" value="update_booking">
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
				<?php wp_nonce_field( 'nsb_update_booking_' . $booking_id, 'nsb_nonce' ); ?>
				<table class="form-table nsb-form-table"><tbody>
					<tr>
						<th scope="row"><label for="booking_status"><?php esc_html_e( 'Booking Status', 'napoleon-shuttle-booking' ); ?></label></th>
						<td><select name="booking_status" id="booking_status"><?php foreach ( $booking_statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking['booking_status'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
					</tr>
					<tr>
						<th scope="row"><label for="payment_status"><?php esc_html_e( 'Payment Status', 'napoleon-shuttle-booking' ); ?></label></th>
						<td><select name="payment_status" id="payment_status"><?php foreach ( $payment_statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking['payment_status'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td>
					</tr>
					<tr>
						<th scope="row"><label for="admin_notes"><?php esc_html_e( 'Internal Admin Notes', 'napoleon-shuttle-booking' ); ?></label></th>
						<td><textarea name="admin_notes" id="admin_notes" rows="5" class="large-text"><?php echo esc_textarea( $booking['admin_notes'] ?? '' ); ?></textarea></td>
					</tr>
				</tbody></table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Booking', 'napoleon-shuttle-booking' ); ?></button></p>
			</form>
		</div>
	</div>
</div>
