<?php
/**
 * Admin view: Dashboard
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_active      = nsb_is_woocommerce_active();
$product_exists  = $woo_active && NSB_WooCommerce::hidden_product_exists();
$product_id      = (int) NSB_Settings::get( 'hidden_product_id', 0 );
$total_packages  = NSB_Packages::count();
$active_packages = NSB_Packages::count( true );
$stats           = NSB_Bookings::get_stats();
$recent_bookings = NSB_Bookings::get_recent( 5 );
?>
<div class="wrap nsb-wrap">

	<h1 class="nsb-page-title">
		<span class="dashicons dashicons-car"></span>
		<?php esc_html_e( 'Napoleon Shuttle Booking', 'napoleon-shuttle-booking' ); ?>
		<span class="nsb-version">v<?php echo esc_html( NSB_VERSION ); ?></span>
	</h1>

	<div class="nsb-dashboard-grid">

		<div class="nsb-card">
			<h2><?php esc_html_e( 'System Status', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="nsb-status-table"><tbody>
				<tr><td><?php esc_html_e( 'Plugin Version', 'napoleon-shuttle-booking' ); ?></td><td><span class="nsb-badge nsb-badge-info"><?php echo esc_html( NSB_VERSION ); ?></span></td></tr>
				<tr><td><?php esc_html_e( 'WooCommerce Status', 'napoleon-shuttle-booking' ); ?></td><td><?php if ( $woo_active ) : ?><span class="nsb-badge nsb-badge-success"><?php esc_html_e( 'Active', 'napoleon-shuttle-booking' ); ?></span><?php else : ?><span class="nsb-badge nsb-badge-error"><?php esc_html_e( 'Inactive', 'napoleon-shuttle-booking' ); ?></span><?php endif; ?></td></tr>
				<tr><td><?php esc_html_e( 'Hidden Booking Product', 'napoleon-shuttle-booking' ); ?></td><td><?php if ( $product_exists ) : ?><span class="nsb-badge nsb-badge-success"><?php printf( esc_html__( 'OK (ID: %d)', 'napoleon-shuttle-booking' ), esc_html( $product_id ) ); ?></span><?php elseif ( ! $woo_active ) : ?><span class="nsb-badge nsb-badge-warning"><?php esc_html_e( 'N/A', 'napoleon-shuttle-booking' ); ?></span><?php else : ?><span class="nsb-badge nsb-badge-error"><?php esc_html_e( 'Missing', 'napoleon-shuttle-booking' ); ?></span> <a href="<?php echo esc_url( wp_nonce_url( nsb_admin_url( 'nsb-settings', array( 'nsb_action' => 'recreate_product' ) ), 'nsb_recreate_product' ) ); ?>" class="button button-small"><?php esc_html_e( 'Recreate', 'napoleon-shuttle-booking' ); ?></a><?php endif; ?></td></tr>
				<tr><td><?php esc_html_e( 'Database Version', 'napoleon-shuttle-booking' ); ?></td><td><span class="nsb-badge nsb-badge-info"><?php echo esc_html( get_option( 'nsb_db_version', '—' ) ); ?></span></td></tr>
				<tr><td><?php esc_html_e( 'Booking Shortcode', 'napoleon-shuttle-booking' ); ?></td><td><code>[napoleon_booking_form]</code></td></tr>
			</tbody></table>
		</div>

		<div class="nsb-card">
			<h2><?php esc_html_e( 'Package Statistics', 'napoleon-shuttle-booking' ); ?></h2>
			<div class="nsb-stats-row">
				<div class="nsb-stat-box"><span class="nsb-stat-number"><?php echo esc_html( $total_packages ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Total Packages', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--success"><span class="nsb-stat-number"><?php echo esc_html( $active_packages ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Active Packages', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--muted"><span class="nsb-stat-number"><?php echo esc_html( $total_packages - $active_packages ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Inactive', 'napoleon-shuttle-booking' ); ?></span></div>
			</div>
			<p style="margin-top:16px;"><a href="<?php echo esc_url( nsb_admin_url( 'nsb-packages' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Packages', 'napoleon-shuttle-booking' ); ?></a></p>
		</div>

		<div class="nsb-card nsb-card--full">
			<h2><?php esc_html_e( 'Booking Statistics', 'napoleon-shuttle-booking' ); ?></h2>
			<div class="nsb-stats-row nsb-stats-row--wrap">
				<div class="nsb-stat-box"><span class="nsb-stat-number"><?php echo esc_html( $stats['total'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Total Bookings', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box"><span class="nsb-stat-number"><?php echo esc_html( $stats['pending_payment'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Pending Payment', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--success"><span class="nsb-stat-number"><?php echo esc_html( $stats['confirmed'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Confirmed', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--success"><span class="nsb-stat-number"><?php echo esc_html( $stats['deposit_paid'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Deposit Paid', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--success"><span class="nsb-stat-number"><?php echo esc_html( $stats['completed'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Completed', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--muted"><span class="nsb-stat-number"><?php echo esc_html( $stats['cancelled'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Cancelled', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box nsb-stat-box--muted"><span class="nsb-stat-number"><?php echo esc_html( $stats['failed'] ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Failed', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box"><span class="nsb-stat-number"><?php echo wp_kses_post( nsb_format_price( $stats['total_collected'] ) ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Collected', 'napoleon-shuttle-booking' ); ?></span></div>
				<div class="nsb-stat-box"><span class="nsb-stat-number"><?php echo wp_kses_post( nsb_format_price( $stats['total_remaining'] ) ); ?></span><span class="nsb-stat-label"><?php esc_html_e( 'Remaining Balance', 'napoleon-shuttle-booking' ); ?></span></div>
			</div>
			<p style="margin-top:16px;"><a href="<?php echo esc_url( nsb_admin_url( 'nsb-bookings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Bookings', 'napoleon-shuttle-booking' ); ?></a></p>
		</div>

		<div class="nsb-card nsb-card--full">
			<h2><?php esc_html_e( 'Recent Bookings', 'napoleon-shuttle-booking' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Reference', 'napoleon-shuttle-booking' ); ?></th><th><?php esc_html_e( 'Customer', 'napoleon-shuttle-booking' ); ?></th><th><?php esc_html_e( 'Package', 'napoleon-shuttle-booking' ); ?></th><th><?php esc_html_e( 'Date/Time', 'napoleon-shuttle-booking' ); ?></th><th><?php esc_html_e( 'Status', 'napoleon-shuttle-booking' ); ?></th><th><?php esc_html_e( 'Action', 'napoleon-shuttle-booking' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $recent_bookings ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No bookings yet.', 'napoleon-shuttle-booking' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $recent_bookings as $booking ) : ?>
						<tr>
							<td><code><?php echo esc_html( $booking['booking_reference'] ); ?></code></td>
							<td><?php echo esc_html( $booking['customer_name'] ); ?></td>
							<td><?php echo esc_html( $booking['package_name'] ); ?></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) ); ?> <?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking['booking_time'] ) ) ); ?></td>
							<td><span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['booking_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::booking_status_label( $booking['booking_status'] ) ); ?></span> <span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['payment_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::payment_status_label( $booking['payment_status'] ) ); ?></span></td>
							<td><a href="<?php echo esc_url( nsb_admin_url( 'nsb-bookings', array( 'action' => 'view', 'id' => (int) $booking['id'] ) ) ); ?>"><?php esc_html_e( 'View', 'napoleon-shuttle-booking' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

	</div>
</div>
