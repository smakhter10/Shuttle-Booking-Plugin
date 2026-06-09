<?php
/**
 * Admin view: Bookings List
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_num       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page       = 20;
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$booking_status = isset( $_GET['booking_status'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_status'] ) ) : '';
$payment_status = isset( $_GET['payment_status'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_status'] ) ) : '';
$package_id     = isset( $_GET['package_id'] ) ? absint( $_GET['package_id'] ) : 0;

$args = array(
	'page'           => $page_num,
	'per_page'       => $per_page,
	'search'         => $search,
	'booking_status' => $booking_status,
	'payment_status' => $payment_status,
	'package_id'     => $package_id,
);

$bookings       = NSB_Bookings::get_all( $args );
$total_items    = NSB_Bookings::count( $args );
$total_pages    = max( 1, (int) ceil( $total_items / $per_page ) );
$packages       = NSB_Packages::get_all();
$booking_labels = NSB_Bookings::booking_statuses();
$payment_labels = NSB_Bookings::payment_statuses();
?>
<div class="wrap nsb-wrap nsb-wrap--wide">
	<h1 class="wp-heading-inline nsb-page-title">
		<span class="dashicons dashicons-clipboard"></span>
		<?php esc_html_e( 'Bookings', 'napoleon-shuttle-booking' ); ?>
	</h1>
	<hr class="wp-header-end">

	<div class="nsb-list-toolbar">
		<form method="get" class="nsb-filter-form">
			<input type="hidden" name="page" value="nsb-bookings">

			<div class="nsb-filter-row">
				<select name="booking_status">
					<option value=""><?php esc_html_e( 'All booking statuses', 'napoleon-shuttle-booking' ); ?></option>
					<?php foreach ( $booking_labels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="payment_status">
					<option value=""><?php esc_html_e( 'All payment statuses', 'napoleon-shuttle-booking' ); ?></option>
					<?php foreach ( $payment_labels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $payment_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="package_id">
					<option value="0"><?php esc_html_e( 'All packages', 'napoleon-shuttle-booking' ); ?></option>
					<?php foreach ( $packages as $package ) : ?>
						<option value="<?php echo esc_attr( $package['id'] ); ?>" <?php selected( $package_id, (int) $package['id'] ); ?>><?php echo esc_html( $package['name'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'napoleon-shuttle-booking' ); ?></button>
				<a href="<?php echo esc_url( nsb_admin_url( 'nsb-bookings' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'napoleon-shuttle-booking' ); ?></a>
			</div>
		</form>

		<form method="get" class="nsb-search-form">
			<input type="hidden" name="page" value="nsb-bookings">
			<?php if ( '' !== $booking_status ) : ?>
				<input type="hidden" name="booking_status" value="<?php echo esc_attr( $booking_status ); ?>">
			<?php endif; ?>
			<?php if ( '' !== $payment_status ) : ?>
				<input type="hidden" name="payment_status" value="<?php echo esc_attr( $payment_status ); ?>">
			<?php endif; ?>
			<?php if ( $package_id ) : ?>
				<input type="hidden" name="package_id" value="<?php echo esc_attr( $package_id ); ?>">
			<?php endif; ?>
			<label class="screen-reader-text" for="nsb-booking-search-input"><?php esc_html_e( 'Search bookings', 'napoleon-shuttle-booking' ); ?></label>
			<input type="search" id="nsb-booking-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search bookings...', 'napoleon-shuttle-booking' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'napoleon-shuttle-booking' ); ?>">
		</form>
	</div>

	<form method="post" class="nsb-bulk-form">
		<input type="hidden" name="nsb_action" value="booking_bulk_action">
		<?php wp_nonce_field( 'nsb_booking_bulk_action', 'nsb_nonce' ); ?>

		<div class="tablenav top nsb-bulk-toolbar">
			<div class="alignleft actions bulkactions">
				<label for="nsb-bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'napoleon-shuttle-booking' ); ?></label>
				<select name="bulk_action" id="nsb-bulk-action-selector-top">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'napoleon-shuttle-booking' ); ?></option>
					<option value="mark_confirmed"><?php esc_html_e( 'Approve / Confirm', 'napoleon-shuttle-booking' ); ?></option>
					<option value="mark_completed"><?php esc_html_e( 'Mark Completed', 'napoleon-shuttle-booking' ); ?></option>
					<option value="cancel"><?php esc_html_e( 'Cancel', 'napoleon-shuttle-booking' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'napoleon-shuttle-booking' ); ?></option>
				</select>
				<button type="submit" class="button action nsb-bulk-apply" data-confirm-delete="<?php esc_attr_e( 'Delete selected booking records? WooCommerce orders will not be deleted.', 'napoleon-shuttle-booking' ); ?>"><?php esc_html_e( 'Apply', 'napoleon-shuttle-booking' ); ?></button>
			</div>
			<div class="tablenav-pages one-page">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %d: number of booking items */
						esc_html( _n( '%d item', '%d items', $total_items, 'napoleon-shuttle-booking' ) ),
						(int) $total_items
					);
					?>
				</span>
			</div>
		</div>

		<div class="nsb-table-scroll">
			<table class="widefat striped nsb-bookings-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" class="nsb-select-all" aria-label="<?php esc_attr_e( 'Select all bookings', 'napoleon-shuttle-booking' ); ?>"></td>
						<th><?php esc_html_e( 'ID', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Reference', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Package', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Date/Time', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Paid / Balance', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Payment', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Booking', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'WC Order', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Created', 'napoleon-shuttle-booking' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'napoleon-shuttle-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bookings ) ) : ?>
						<tr><td colspan="13"><?php esc_html_e( 'No bookings found.', 'napoleon-shuttle-booking' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $bookings as $booking ) : ?>
							<?php
							$view_url      = nsb_admin_url( 'nsb-bookings', array( 'action' => 'view', 'id' => (int) $booking['id'] ) );
							$order_url     = NSB_Bookings::get_order_admin_url( $booking['wc_order_id'] ?? 0 );
							$confirm_url   = wp_nonce_url( nsb_admin_url( 'nsb-bookings', array( 'nsb_action' => 'booking_quick_action', 'do' => 'mark_confirmed', 'id' => (int) $booking['id'] ) ), 'nsb_booking_quick_' . (int) $booking['id'] . '_mark_confirmed' );
							$complete_url  = wp_nonce_url( nsb_admin_url( 'nsb-bookings', array( 'nsb_action' => 'booking_quick_action', 'do' => 'mark_completed', 'id' => (int) $booking['id'] ) ), 'nsb_booking_quick_' . (int) $booking['id'] . '_mark_completed' );
							$cancel_url    = wp_nonce_url( nsb_admin_url( 'nsb-bookings', array( 'nsb_action' => 'booking_quick_action', 'do' => 'cancel', 'id' => (int) $booking['id'] ) ), 'nsb_booking_quick_' . (int) $booking['id'] . '_cancel' );
							$delete_url    = wp_nonce_url( nsb_admin_url( 'nsb-bookings', array( 'nsb_action' => 'delete_booking', 'id' => (int) $booking['id'] ) ), 'nsb_delete_booking_' . (int) $booking['id'] );
							$booking_date  = ! empty( $booking['booking_date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) : '—';
							$booking_time  = ! empty( $booking['booking_time'] ) ? date_i18n( get_option( 'time_format' ), strtotime( $booking['booking_time'] ) ) : '—';
							$created       = ! empty( $booking['created_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $booking['created_at'] ) ) : '—';
							?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr( $booking['id'] ); ?>" class="nsb-booking-checkbox" aria-label="<?php echo esc_attr( sprintf( __( 'Select booking %s', 'napoleon-shuttle-booking' ), $booking['booking_reference'] ) ); ?>"></th>
								<td><?php echo esc_html( $booking['id'] ); ?></td>
								<td><a href="<?php echo esc_url( $view_url ); ?>"><strong><?php echo esc_html( $booking['booking_reference'] ); ?></strong></a></td>
								<td>
									<strong><?php echo esc_html( $booking['customer_name'] ); ?></strong><br>
									<a href="mailto:<?php echo esc_attr( $booking['customer_email'] ); ?>"><?php echo esc_html( $booking['customer_email'] ); ?></a>
								</td>
								<td><?php echo esc_html( $booking['customer_phone'] ); ?></td>
								<td><?php echo esc_html( $booking['package_name'] ); ?></td>
								<td><?php echo esc_html( $booking_date ); ?><br><?php echo esc_html( $booking_time ); ?></td>
								<td><?php echo wp_kses_post( nsb_format_price( $booking['amount_due_now'] ) ); ?><br><small><?php echo esc_html__( 'Balance:', 'napoleon-shuttle-booking' ); ?> <?php echo wp_kses_post( nsb_format_price( $booking['remaining_balance'] ) ); ?></small></td>
								<td><span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['payment_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::payment_status_label( $booking['payment_status'] ) ); ?></span><br><small><?php echo esc_html( ucfirst( $booking['payment_type'] ) ); ?></small></td>
								<td><span class="nsb-badge <?php echo esc_attr( NSB_Bookings::status_badge_class( $booking['booking_status'] ) ); ?>"><?php echo esc_html( NSB_Bookings::booking_status_label( $booking['booking_status'] ) ); ?></span></td>
								<td>
									<?php if ( $order_url ) : ?>
										<a href="<?php echo esc_url( $order_url ); ?>" target="_blank">#<?php echo esc_html( $booking['wc_order_id'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $created ); ?></td>
								<td class="nsb-row-actions">
									<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'napoleon-shuttle-booking' ); ?></a> |
									<a href="<?php echo esc_url( $confirm_url ); ?>"><?php esc_html_e( 'Confirm', 'napoleon-shuttle-booking' ); ?></a> |
									<a href="<?php echo esc_url( $complete_url ); ?>"><?php esc_html_e( 'Complete', 'napoleon-shuttle-booking' ); ?></a> |
									<a href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'napoleon-shuttle-booking' ); ?></a> |
									<a href="<?php echo esc_url( $delete_url ); ?>" class="nsb-delete-btn" data-confirm="<?php esc_attr_e( 'Delete this booking record? WooCommerce orders will not be deleted.', 'napoleon-shuttle-booking' ); ?>"><?php esc_html_e( 'Delete', 'napoleon-shuttle-booking' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="tablenav bottom nsb-bulk-toolbar">
			<div class="alignleft actions bulkactions">
				<label for="nsb-bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'napoleon-shuttle-booking' ); ?></label>
				<select name="bulk_action_bottom" id="nsb-bulk-action-selector-bottom" class="nsb-bulk-action-mirror">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'napoleon-shuttle-booking' ); ?></option>
					<option value="mark_confirmed"><?php esc_html_e( 'Approve / Confirm', 'napoleon-shuttle-booking' ); ?></option>
					<option value="mark_completed"><?php esc_html_e( 'Mark Completed', 'napoleon-shuttle-booking' ); ?></option>
					<option value="cancel"><?php esc_html_e( 'Cancel', 'napoleon-shuttle-booking' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'napoleon-shuttle-booking' ); ?></option>
				</select>
				<button type="button" class="button action nsb-bulk-apply-bottom"><?php esc_html_e( 'Apply', 'napoleon-shuttle-booking' ); ?></button>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page_num,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</form>
</div>
