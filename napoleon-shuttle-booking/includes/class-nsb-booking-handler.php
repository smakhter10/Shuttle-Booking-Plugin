<?php
/**
 * Handles booking creation, price calculation, and form processing.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Booking_Handler
 *
 * Processes booking form submissions, calculates prices server-side,
 * generates booking references, and inserts booking records.
 *
 * Phase 3A note: Database upgrade logic has been moved to NSB_Database::maybe_upgrade()
 * which is now the single authoritative place for schema management.
 * The maybe_upgrade_bookings_table() method is kept as a no-op alias for
 * backwards compatibility (it was called from the main plugin file in Phase 2).
 */
class NSB_Booking_Handler {

	/**
	 * Backwards-compatibility shim — DB upgrade is now handled by NSB_Database::maybe_upgrade().
	 *
	 * This method is intentionally empty. It remains defined so that any external code or
	 * cached includes that reference it do not cause a fatal error. NSB_Database::maybe_upgrade()
	 * is called from Napoleon_Shuttle_Booking::on_plugins_loaded() instead.
	 *
	 * @deprecated 3.0.0 Use NSB_Database::maybe_upgrade() instead.
	 */
	public static function maybe_upgrade_bookings_table(): void {
		// No-op: superseded by NSB_Database::maybe_upgrade().
	}

	// -----------------------------------------------------------------------
	// Price Calculation
	// -----------------------------------------------------------------------

	/**
	 * Calculate the payment amounts for a booking.
	 *
	 * @param int    $package_id   Package ID.
	 * @param string $payment_type 'full' or 'deposit'.
	 * @return array{base_price: float, amount_due_now: float, remaining_balance: float, payment_type: string}|WP_Error
	 */
	public static function calculate_payment( int $package_id, string $payment_type ): array|WP_Error {
		$package = NSB_Packages::get_by_id( $package_id );

		if ( ! $package || ! (int) $package['active'] ) {
			return new WP_Error( 'invalid_package', __( 'Selected package is no longer available.', 'napoleon-shuttle-booking' ) );
		}

		$base_price    = (float) $package['base_price'];
		$deposit_type  = $package['deposit_type'];
		$deposit_amt   = (float) $package['deposit_amount'];

		// Force full if deposit not available.
		if ( 'none' === $deposit_type || $deposit_amt <= 0 ) {
			$payment_type = 'full';
		}

		if ( 'deposit' === $payment_type ) {
			if ( 'fixed' === $deposit_type ) {
				// Validation: fixed deposit cannot exceed base price.
				if ( $deposit_amt > $base_price ) {
					return new WP_Error( 'invalid_deposit', __( 'Package deposit configuration is invalid.', 'napoleon-shuttle-booking' ) );
				}
				$amount_due_now    = $deposit_amt;
				$remaining_balance = $base_price - $deposit_amt;

			} elseif ( 'percentage' === $deposit_type ) {
				// Validation: percentage must be 0–100.
				if ( $deposit_amt < 0 || $deposit_amt > 100 ) {
					return new WP_Error( 'invalid_deposit', __( 'Package deposit configuration is invalid.', 'napoleon-shuttle-booking' ) );
				}
				$amount_due_now    = round( $base_price * $deposit_amt / 100, 2 );
				$remaining_balance = round( $base_price - $amount_due_now, 2 );

			} else {
				// Fallback to full.
				$payment_type      = 'full';
				$amount_due_now    = $base_price;
				$remaining_balance = 0.0;
			}
		} else {
			$payment_type      = 'full';
			$amount_due_now    = $base_price;
			$remaining_balance = 0.0;
		}

		// Safety clamps — never negative.
		$amount_due_now    = max( 0.0, $amount_due_now );
		$remaining_balance = max( 0.0, $remaining_balance );

		return array(
			'base_price'        => $base_price,
			'amount_due_now'    => $amount_due_now,
			'remaining_balance' => $remaining_balance,
			'payment_type'      => $payment_type,
		);
	}

	// -----------------------------------------------------------------------
	// Booking Reference
	// -----------------------------------------------------------------------

	/**
	 * Generate a unique booking reference.
	 *
	 * Format: NSB-YYYYMMDD-XXXXXX (uppercase alphanumeric suffix).
	 *
	 * @return string
	 */
	public static function generate_booking_reference(): string {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_bookings';
		$date  = gmdate( 'Ymd' );

		do {
			$suffix    = strtoupper( substr( str_shuffle( '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, 6 ) );
			$reference = "NSB-{$date}-{$suffix}";
			$exists    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE booking_reference = %s", $reference ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} while ( $exists );

		return $reference;
	}

	// -----------------------------------------------------------------------
	// Booking Record
	// -----------------------------------------------------------------------

	/**
	 * Insert a new booking record into the database.
	 *
	 * @param array<string, mixed> $data Sanitized and validated booking data.
	 * @return int|WP_Error Inserted booking ID on success, WP_Error on failure.
	 */
	public static function create_booking( array $data ): int|WP_Error {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_bookings';
		$now   = current_time( 'mysql' );

		$insert = array(
			'booking_reference'   => $data['booking_reference'],
			'package_id'          => (int) $data['package_id'],
			'package_name'        => sanitize_text_field( $data['package_name'] ),
			'customer_name'       => sanitize_text_field( $data['customer_name'] ),
			'customer_email'      => sanitize_email( $data['customer_email'] ),
			'customer_phone'      => sanitize_text_field( $data['customer_phone'] ),
			'pickup_address'      => sanitize_textarea_field( $data['pickup_address'] ),
			'dropoff_address'     => sanitize_textarea_field( $data['dropoff_address'] ),
			'booking_date'        => sanitize_text_field( $data['booking_date'] ),
			'booking_time'        => sanitize_text_field( $data['booking_time'] ),
			'return_date'         => sanitize_text_field( $data['return_date'] ?? '' ),
			'return_time'         => sanitize_text_field( $data['return_time'] ?? '' ),
			'passenger_count'     => absint( $data['passenger_count'] ),
			'notes'               => sanitize_textarea_field( $data['notes'] ?? '' ),
			'base_price'          => (float) $data['base_price'],
			'payment_type'        => sanitize_text_field( $data['payment_type'] ),
			'amount_due_now'      => (float) $data['amount_due_now'],
			'remaining_balance'   => (float) $data['remaining_balance'],
			'payment_status'      => 'pending',
			'booking_status'      => 'pending_payment',
			'wc_order_id'         => null,
			'transaction_id'      => null,
			'customer_email_sent' => 0,
			'admin_email_sent'    => 0,
			'paid_at'             => null,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$formats = array(
			'%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
			'%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%f', '%f',
			'%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s',
		);

		// Optional flight/airline/luggage columns.
		if ( isset( $data['flight_number'] ) ) {
			$insert['flight_number'] = sanitize_text_field( $data['flight_number'] );
			$formats[]               = '%s';
		}
		if ( isset( $data['airline_name'] ) ) {
			$insert['airline_name'] = sanitize_text_field( $data['airline_name'] );
			$formats[]              = '%s';
		}
		if ( isset( $data['luggage_count'] ) && '' !== $data['luggage_count'] ) {
			$insert['luggage_count'] = absint( $data['luggage_count'] );
			$formats[]               = '%d';
		}

		$result = $wpdb->insert( $table, $insert, $formats );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to save booking. Please try again.', 'napoleon-shuttle-booking' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch a booking record by ID.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_id( int $booking_id ): ?array {
		global $wpdb;

		$table  = $wpdb->prefix . 'nsb_bookings';
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result ?: null;
	}

	// -----------------------------------------------------------------------
	// Form Validation
	// -----------------------------------------------------------------------

	/**
	 * Validate the booking form POST data.
	 *
	 * @param array<string, mixed> $post Raw POST data.
	 * @return array<int, string> Error messages; empty = valid.
	 */
	public static function validate_form( array $post ): array {
		$errors = array();

		// Package.
		$package_id = absint( $post['nsb_package_id'] ?? 0 );
		if ( ! $package_id ) {
			$errors[] = __( 'Please select a valid package.', 'napoleon-shuttle-booking' );
		} else {
			$package = NSB_Packages::get_by_id( $package_id );
			if ( ! $package || ! (int) $package['active'] ) {
				$errors[] = __( 'Selected package is no longer available.', 'napoleon-shuttle-booking' );
			}
		}

		// Booking date — required, must be today or future.
		$booking_date = sanitize_text_field( $post['nsb_booking_date'] ?? '' );
		if ( empty( $booking_date ) ) {
			$errors[] = __( 'Please select a valid future booking date.', 'napoleon-shuttle-booking' );
		} else {
			$date_ts = strtotime( $booking_date );
			$today   = strtotime( gmdate( 'Y-m-d' ) );
			if ( false === $date_ts || $date_ts < $today ) {
				$errors[] = __( 'Please select a valid future booking date.', 'napoleon-shuttle-booking' );
			}
		}

		// Booking time.
		if ( empty( trim( $post['nsb_booking_time'] ?? '' ) ) ) {
			$errors[] = __( 'Please select a pickup time.', 'napoleon-shuttle-booking' );
		}


		// Seat availability check — prevents a blocked day/time from being submitted manually.
		if ( empty( $errors ) ) {
			$requested_seats = absint( $post['nsb_passenger_count'] ?? 0 );
			$availability    = self::get_slot_seat_availability( $package_id, $booking_date, sanitize_text_field( $post['nsb_booking_time'] ?? '' ) );

			if ( ! $availability['available'] ) {
				$errors[] = __( 'The selected pickup date or time is no longer available. Please choose another slot.', 'napoleon-shuttle-booking' );
			} elseif ( $requested_seats > 0 && null !== $availability['available_seats'] && $requested_seats > $availability['available_seats'] ) {
				$errors[] = sprintf(
					/* translators: %d: available seats */
					_n( 'Only %d seat is available for this package at the selected pickup time.', 'Only %d seats are available for this package at the selected pickup time.', (int) $availability['available_seats'], 'napoleon-shuttle-booking' ),
					(int) $availability['available_seats']
				);
			}
		}

		// Optional return/drop-off date must not be earlier than pickup date.
		$return_date = sanitize_text_field( $post['nsb_return_date'] ?? '' );
		if ( ! empty( $return_date ) && ! empty( $booking_date ) ) {
			$return_ts = strtotime( $return_date );
			$date_ts   = strtotime( $booking_date );
			if ( false === $return_ts || false === $date_ts || $return_ts < $date_ts ) {
				$errors[] = __( 'Drop-off / return date cannot be earlier than pickup date.', 'napoleon-shuttle-booking' );
			}
		}

		// Customer name/email/phone are intentionally collected on the WooCommerce
		// checkout page to avoid duplicate data entry. If these keys are present
		// from an older template, validate them only when supplied.
		$email = sanitize_email( $post['nsb_customer_email'] ?? '' );
		if ( ! empty( $email ) && ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'napoleon-shuttle-booking' );
		}

		// Pickup / dropoff.
		if ( empty( trim( $post['nsb_pickup_address'] ?? '' ) ) ) {
			$errors[] = __( 'Please enter a pickup address.', 'napoleon-shuttle-booking' );
		}
		if ( empty( trim( $post['nsb_dropoff_address'] ?? '' ) ) ) {
			$errors[] = __( 'Please enter a drop-off address.', 'napoleon-shuttle-booking' );
		}

		// Passenger/seat count.
		$passengers = absint( $post['nsb_passenger_count'] ?? 0 );
		if ( $passengers < 1 ) {
			$errors[] = __( 'Seat count must be at least 1.', 'napoleon-shuttle-booking' );
		}

		// Payment type.
		$payment_type = sanitize_text_field( $post['nsb_payment_type'] ?? '' );
		if ( ! in_array( $payment_type, array( 'full', 'deposit' ), true ) ) {
			$errors[] = __( 'Please select a valid payment option.', 'napoleon-shuttle-booking' );
		}

		return $errors;
	}

	// -----------------------------------------------------------------------
	// Availability Helpers
	// -----------------------------------------------------------------------

	/**
	 * Return paid/confirmed booking statuses that should count against availability limits.
	 *
	 * Pending, failed, cancelled, refunded and on-hold bookings should NOT block
	 * calendar availability because the customer has not successfully paid yet.
	 *
	 * @return array<int, string>
	 */
	public static function get_availability_count_statuses(): array {
		return array( 'confirmed', 'deposit_paid', 'completed' );
	}

	/**
	 * Return payment statuses that should count against availability limits.
	 *
	 * @return array<int, string>
	 */
	public static function get_availability_count_payment_statuses(): array {
		return array( 'paid', 'deposit_paid' );
	}

	/**
	 * Normalize a time string to HH:MM.
	 *
	 * @param string $time Time string.
	 * @return string
	 */
	public static function normalize_time( string $time ): string {
		$time = trim( $time );
		if ( preg_match( '/^(\d{1,2}):(\d{2})/', $time, $m ) ) {
			return sprintf( '%02d:%02d', min( 23, max( 0, absint( $m[1] ) ) ), min( 59, max( 0, absint( $m[2] ) ) ) );
		}
		return '';
	}

	/**
	 * Get configured available time slots.
	 *
	 * @return array<int, string>
	 */
	public static function get_available_time_slots(): array {
		$raw   = (string) NSB_Settings::get( 'available_time_slots', "09:00\n11:00\n12:00\n14:30\n17:30" );
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$slots = array();

		foreach ( (array) $lines as $line ) {
			$slot = self::normalize_time( $line );
			if ( $slot ) {
				$slots[] = $slot;
			}
		}

		$slots = array_values( array_unique( $slots ) );
		sort( $slots );

		return ! empty( $slots ) ? $slots : array( '09:00', '11:00', '12:00', '14:30', '17:30' );
	}

	/**
	 * Count confirmed/paid booked seats for a package on a date and optionally a time slot.
	 *
	 * Each passenger_count equals one booked seat. Pending/unpaid bookings are ignored.
	 *
	 * @param int         $package_id Package ID.
	 * @param string      $date       YYYY-MM-DD.
	 * @param string|null $time       Optional HH:MM.
	 * @return int
	 */
	public static function count_booked_seats( int $package_id, string $date, ?string $time = null ): int {
		global $wpdb;

		if ( $package_id <= 0 ) {
			return 0;
		}

		$table             = $wpdb->prefix . 'nsb_bookings';
		$booking_statuses  = self::get_availability_count_statuses();
		$payment_statuses  = self::get_availability_count_payment_statuses();
		$booking_in        = implode( ',', array_fill( 0, count( $booking_statuses ), '%s' ) );
		$payment_in        = implode( ',', array_fill( 0, count( $payment_statuses ), '%s' ) );

		$params = array_merge( array( $package_id, $date ), $booking_statuses, $payment_statuses );
		$sql    = "SELECT COALESCE(SUM(passenger_count), 0) FROM {$table} WHERE package_id = %d AND booking_date = %s AND booking_status IN ({$booking_in}) AND payment_status IN ({$payment_in})";

		$time = null !== $time ? self::normalize_time( $time ) : '';
		if ( $time ) {
			$sql      .= " AND TIME_FORMAT(booking_time, '%%H:%%i') = %s";
			$params[] = $time;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Backward-compatible wrapper that returns confirmed booking count for old callers.
	 *
	 * @param string      $date YYYY-MM-DD.
	 * @param string|null $time Optional HH:MM.
	 * @return int
	 */
	public static function count_active_bookings( string $date, ?string $time = null ): int {
		global $wpdb;

		$table             = $wpdb->prefix . 'nsb_bookings';
		$booking_statuses  = self::get_availability_count_statuses();
		$payment_statuses  = self::get_availability_count_payment_statuses();
		$booking_in        = implode( ',', array_fill( 0, count( $booking_statuses ), '%s' ) );
		$payment_in        = implode( ',', array_fill( 0, count( $payment_statuses ), '%s' ) );

		$params = array_merge( array( $date ), $booking_statuses, $payment_statuses );
		$sql    = "SELECT COUNT(*) FROM {$table} WHERE booking_date = %s AND booking_status IN ({$booking_in}) AND payment_status IN ({$payment_in})";

		$time = null !== $time ? self::normalize_time( $time ) : '';
		if ( $time ) {
			$sql      .= " AND TIME_FORMAT(booking_time, '%%H:%%i') = %s";
			$params[] = $time;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Get package total seat capacity. Null means unlimited capacity.
	 *
	 * @param int $package_id Package ID.
	 * @return int|null
	 */
	public static function get_package_seat_capacity( int $package_id ): ?int {
		$package = NSB_Packages::get_by_id( $package_id );
		if ( ! $package || empty( $package['max_passengers'] ) ) {
			return null;
		}

		$capacity = absint( $package['max_passengers'] );
		return $capacity > 0 ? $capacity : null;
	}

	/**
	 * Get seat availability for one package/date/time.
	 *
	 * @param int    $package_id Package ID.
	 * @param string $date       YYYY-MM-DD.
	 * @param string $time       HH:MM.
	 * @return array<string, mixed>
	 */
	public static function get_slot_seat_availability( int $package_id, string $date, string $time ): array {
		$date_ts = strtotime( $date );
		$time    = self::normalize_time( $time );
		$capacity = self::get_package_seat_capacity( $package_id );
		$booked   = 0;

		if ( false === $date_ts || $date_ts < strtotime( gmdate( 'Y-m-d' ) ) || ! $time ) {
			return array(
				'available'       => false,
				'capacity'        => $capacity,
				'booked_seats'    => 0,
				'available_seats' => 0,
			);
		}

		$allowed_slots = self::get_available_time_slots();
		if ( ! in_array( $time, $allowed_slots, true ) ) {
			return array(
				'available'       => false,
				'capacity'        => $capacity,
				'booked_seats'    => 0,
				'available_seats' => 0,
			);
		}

		$booked = self::count_booked_seats( $package_id, $date, $time );

		if ( null === $capacity ) {
			return array(
				'available'       => true,
				'capacity'        => null,
				'booked_seats'    => $booked,
				'available_seats' => null,
			);
		}

		$available_seats = max( 0, $capacity - $booked );

		return array(
			'available'       => $available_seats > 0,
			'capacity'        => $capacity,
			'booked_seats'    => $booked,
			'available_seats' => $available_seats,
		);
	}

	/**
	 * Check if a pickup date/time is still available for a package.
	 *
	 * @param int    $package_id      Package ID.
	 * @param string $date            YYYY-MM-DD.
	 * @param string $time            HH:MM.
	 * @param int    $requested_seats Seats requested by customer.
	 * @return bool
	 */
	public static function is_slot_available( int $package_id, string $date, string $time, int $requested_seats = 1 ): bool {
		$availability = self::get_slot_seat_availability( $package_id, $date, $time );

		if ( ! $availability['available'] ) {
			return false;
		}

		if ( null !== $availability['available_seats'] && $requested_seats > (int) $availability['available_seats'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get seat availability data for a full calendar month.
	 *
	 * @param int $year       Year.
	 * @param int $month      Month 1-12.
	 * @param int $package_id Package ID.
	 * @return array<string, mixed>
	 */
	public static function get_month_availability( int $year, int $month, int $package_id = 0 ): array {
		$year       = max( 1970, min( 2100, $year ) );
		$month      = max( 1, min( 12, $month ) );
		$package_id = absint( $package_id );
		$days       = (int) gmdate( 't', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
		$slots      = self::get_available_time_slots();
		$capacity   = $package_id ? self::get_package_seat_capacity( $package_id ) : null;

		$data = array(
			'days'     => array(),
			'slots'    => $slots,
			'capacity' => $capacity,
			'packageId' => $package_id,
		);

		for ( $day = 1; $day <= $days; $day++ ) {
			$date        = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$date_ts     = strtotime( $date );
			$past        = $date_ts < strtotime( gmdate( 'Y-m-d' ) );
			$slot_data   = array();
			$all_blocked = true;
			$day_booked  = 0;

			foreach ( $slots as $slot ) {
				$slot_availability = $package_id ? self::get_slot_seat_availability( $package_id, $date, $slot ) : array(
					'available'       => ! $past,
					'capacity'        => $capacity,
					'booked_seats'    => 0,
					'available_seats' => $capacity,
				);

				$blocked = $past || ! $slot_availability['available'];
				$day_booked += (int) $slot_availability['booked_seats'];

				if ( ! $blocked ) {
					$all_blocked = false;
				}

				$slot_data[ $slot ] = array(
					'bookedSeats'    => (int) $slot_availability['booked_seats'],
					'availableSeats' => null === $slot_availability['available_seats'] ? null : (int) $slot_availability['available_seats'],
					'capacity'       => null === $slot_availability['capacity'] ? null : (int) $slot_availability['capacity'],
					'blocked'        => $blocked,
				);
			}

			$day_blocked = $past || $all_blocked;

			$data['days'][ $date ] = array(
				'bookedSeats' => $day_booked,
				'blocked'     => $day_blocked,
				'slots'       => $slot_data,
			);
		}

		return $data;
	}

}
