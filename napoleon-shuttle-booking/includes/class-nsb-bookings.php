<?php
/**
 * Booking admin/data helper methods.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Bookings
 */
class NSB_Bookings {

	/**
	 * Allowed booking statuses.
	 *
	 * @return array<string, string>
	 */
	public static function booking_statuses(): array {
		return array(
			'pending_payment' => __( 'Pending Payment', 'napoleon-shuttle-booking' ),
			'confirmed'       => __( 'Confirmed', 'napoleon-shuttle-booking' ),
			'deposit_paid'    => __( 'Deposit Paid', 'napoleon-shuttle-booking' ),
			'completed'       => __( 'Completed', 'napoleon-shuttle-booking' ),
			'cancelled'       => __( 'Cancelled', 'napoleon-shuttle-booking' ),
			'failed'          => __( 'Failed', 'napoleon-shuttle-booking' ),
			'refunded'        => __( 'Refunded', 'napoleon-shuttle-booking' ),
			'on_hold'         => __( 'On Hold', 'napoleon-shuttle-booking' ),
		);
	}

	/**
	 * Allowed payment statuses.
	 *
	 * @return array<string, string>
	 */
	public static function payment_statuses(): array {
		return array(
			'pending'      => __( 'Pending', 'napoleon-shuttle-booking' ),
			'paid'         => __( 'Paid', 'napoleon-shuttle-booking' ),
			'deposit_paid' => __( 'Deposit Paid', 'napoleon-shuttle-booking' ),
			'failed'       => __( 'Failed', 'napoleon-shuttle-booking' ),
			'cancelled'    => __( 'Cancelled', 'napoleon-shuttle-booking' ),
			'refunded'     => __( 'Refunded', 'napoleon-shuttle-booking' ),
			'on_hold'      => __( 'On Hold', 'napoleon-shuttle-booking' ),
		);
	}

	/**
	 * Get booking status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function booking_status_label( string $status ): string {
		$statuses = self::booking_statuses();
		return $statuses[ $status ] ?? ucwords( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Get payment status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function payment_status_label( string $status ): string {
		$statuses = self::payment_statuses();
		return $statuses[ $status ] ?? ucwords( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Return badge class for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_badge_class( string $status ): string {
		if ( in_array( $status, array( 'paid', 'confirmed', 'completed' ), true ) ) {
			return 'nsb-badge-success';
		}
		if ( in_array( $status, array( 'deposit_paid', 'processing' ), true ) ) {
			return 'nsb-badge-info';
		}
		if ( in_array( $status, array( 'pending', 'pending_payment', 'on_hold' ), true ) ) {
			return 'nsb-badge-warning';
		}
		if ( in_array( $status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
			return 'nsb-badge-error';
		}
		return 'nsb-badge-info';
	}

	/**
	 * Get one booking by ID.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_id( int $booking_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	/**
	 * Build WHERE clause for list/search/filter queries.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private static function build_where( array $args ): array {
		$where  = array( '1=1' );
		$params = array();

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $GLOBALS['wpdb']->esc_like( $search ) . '%';
			$where[]  = '(booking_reference LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR package_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$booking_status = isset( $args['booking_status'] ) ? sanitize_text_field( (string) $args['booking_status'] ) : '';
		if ( '' !== $booking_status && array_key_exists( $booking_status, self::booking_statuses() ) ) {
			$where[]  = 'booking_status = %s';
			$params[] = $booking_status;
		}

		$payment_status = isset( $args['payment_status'] ) ? sanitize_text_field( (string) $args['payment_status'] ) : '';
		if ( '' !== $payment_status && array_key_exists( $payment_status, self::payment_statuses() ) ) {
			$where[]  = 'payment_status = %s';
			$params[] = $payment_status;
		}

		$package_id = isset( $args['package_id'] ) ? absint( $args['package_id'] ) : 0;
		if ( $package_id ) {
			$where[]  = 'package_id = %d';
			$params[] = $package_id;
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Get bookings for list table.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';

		$defaults = array(
			'page'           => 1,
			'per_page'       => 20,
			'search'         => '',
			'booking_status' => '',
			'payment_status' => '',
			'package_id'     => 0,
		);
		$args = array_merge( $defaults, $args );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		list( $where, $params ) = self::build_where( $args );
		$sql                    = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$params[]               = $per_page;
		$params[]               = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count bookings matching filters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public static function count( array $args = array() ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';

		list( $where, $params ) = self::build_where( $args );
		$sql                    = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Update a booking record.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool
	 */
	public static function update( int $booking_id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';

		$allowed = array(
			'booking_status'    => '%s',
			'payment_status'    => '%s',
			'customer_name'     => '%s',
			'customer_email'    => '%s',
			'customer_phone'    => '%s',
			'admin_notes'       => '%s',
			'wc_order_id'       => '%d',
			'transaction_id'    => '%s',
			'paid_at'           => '%s',
			'remaining_balance' => '%f',
		);

		$update  = array();
		$formats = array();

		foreach ( $allowed as $key => $format ) {
			if ( array_key_exists( $key, $data ) ) {
				$value = $data[ $key ];
				if ( 'booking_status' === $key && ! array_key_exists( (string) $value, self::booking_statuses() ) ) {
					continue;
				}
				if ( 'payment_status' === $key && ! array_key_exists( (string) $value, self::payment_statuses() ) ) {
					continue;
				}
				if ( 'admin_notes' === $key ) {
					$value = sanitize_textarea_field( (string) $value );
				} elseif ( 'customer_email' === $key ) {
					$value = sanitize_email( (string) $value );
				} elseif ( in_array( $key, array( 'customer_name', 'customer_phone' ), true ) ) {
					$value = sanitize_text_field( (string) $value );
				}
				$update[ $key ] = $value;
				$formats[]      = $format;
			}
		}

		$update['updated_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$result = $wpdb->update( $table, $update, array( 'id' => $booking_id ), $formats, array( '%d' ) );
		return false !== $result;
	}

	/**
	 * Delete booking record. Does not delete WooCommerce orders.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public static function delete( int $booking_id ): bool {
		global $wpdb;
		$table  = $wpdb->prefix . 'nsb_bookings';
		$result = $wpdb->delete( $table, array( 'id' => $booking_id ), array( '%d' ) );
		return false !== $result;
	}

	/**
	 * Get dashboard stats.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'total'            => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'pending_payment'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'pending_payment'" ),
			'confirmed'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'confirmed'" ),
			'deposit_paid'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'deposit_paid'" ),
			'completed'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'completed'" ),
			'cancelled'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'cancelled'" ),
			'failed'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE booking_status = 'failed'" ),
			'total_collected'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount_due_now),0) FROM {$table} WHERE payment_status IN ('paid','deposit_paid')" ),
			'total_remaining'  => (float) $wpdb->get_var( "SELECT COALESCE(SUM(remaining_balance),0) FROM {$table} WHERE remaining_balance > 0 AND booking_status NOT IN ('cancelled','failed','refunded')" ),
		);
		// phpcs:enable
	}

	/**
	 * Get recent bookings.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent( int $limit = 5 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';
		$limit = max( 1, min( 20, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Clean abandoned bookings by marking them cancelled.
	 *
	 * @param int $hours Age threshold in hours.
	 * @return int Number of records updated.
	 */
	public static function clean_abandoned( int $hours = 24 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';
		$hours = max( 1, $hours );
		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $hours * HOUR_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, wc_order_id FROM {$table} WHERE booking_status = %s AND payment_status = %s AND created_at < %s",
				'pending_payment',
				'pending',
				$cutoff
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			$order_id = isset( $row['wc_order_id'] ) ? absint( $row['wc_order_id'] ) : 0;
			if ( $order_id && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && $order->is_paid() ) {
					continue;
				}
			}
			if ( self::update( (int) $row['id'], array( 'booking_status' => 'cancelled', 'payment_status' => 'cancelled' ) ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get WooCommerce order edit link if possible.
	 *
	 * @param int|string|null $order_id Order ID.
	 * @return string
	 */
	public static function get_order_admin_url( int|string|null $order_id ): string {
		$order_id = absint( $order_id );
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return '';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		if ( method_exists( $order, 'get_edit_order_url' ) ) {
			return (string) $order->get_edit_order_url();
		}

		return get_edit_post_link( $order_id, '' ) ?: '';
	}
}
