<?php
/**
 * WooCommerce payment status synchronisation for NSB bookings.
 *
 * Listens to WooCommerce order status hooks and keeps the nsb_bookings
 * record in sync. Sends customer and admin emails after successful payment.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Payment_Sync
 *
 * Hooks into WooCommerce order lifecycle events and updates the linked
 * NSB booking record accordingly. All methods guard against missing data,
 * inactive WooCommerce, and unrelated orders.
 */
class NSB_Payment_Sync {

	/**
	 * Single instance.
	 *
	 * @var NSB_Payment_Sync|null
	 */
	private static ?NSB_Payment_Sync $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return NSB_Payment_Sync
	 */
	public static function get_instance(): NSB_Payment_Sync {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers WooCommerce hooks.
	 * Only registers when WooCommerce is available.
	 */
	private function __construct() {
		if ( ! nsb_is_woocommerce_active() ) {
			return;
		}

		// Successful payment hooks.
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_success' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_success' ), 10, 1 );

		// Failure / cancellation hooks.
		add_action( 'woocommerce_order_status_failed', array( $this, 'on_order_failed' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'on_order_on_hold' ), 10, 1 );
	}

	// -----------------------------------------------------------------------
	// Hook Callbacks
	// -----------------------------------------------------------------------

	/**
	 * Fired by woocommerce_payment_complete — includes transaction ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_payment_complete( int $order_id ): void {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$booking = $this->get_booking_for_order( $order );
		if ( ! $booking ) {
			return;
		}

		$transaction_id = $order->get_transaction_id();
		$this->handle_successful_payment( $booking, $order_id, $transaction_id ?: null );
	}

	/**
	 * Fired by woocommerce_order_status_processing and _completed.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_order_success( int $order_id ): void {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$booking = $this->get_booking_for_order( $order );
		if ( ! $booking ) {
			return;
		}

		$transaction_id = $order->get_transaction_id();
		$this->handle_successful_payment( $booking, $order_id, $transaction_id ?: null );
	}

	/**
	 * Fired by woocommerce_order_status_failed.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_order_failed( int $order_id ): void {
		$this->handle_status_change( $order_id, 'failed', 'failed' );
	}

	/**
	 * Fired by woocommerce_order_status_cancelled.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_order_cancelled( int $order_id ): void {
		$this->handle_status_change( $order_id, 'cancelled', 'cancelled' );
	}

	/**
	 * Fired by woocommerce_order_status_refunded.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_order_refunded( int $order_id ): void {
		$this->handle_status_change( $order_id, 'refunded', 'refunded' );
	}

	/**
	 * Fired by woocommerce_order_status_on-hold.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function on_order_on_hold( int $order_id ): void {
		$this->handle_status_change( $order_id, 'on_hold', 'on_hold' );
	}

	// -----------------------------------------------------------------------
	// Core Logic
	// -----------------------------------------------------------------------

	/**
	 * Handle a successful payment outcome for a booking.
	 *
	 * Sets booking_status and payment_status based on payment_type:
	 *   full    → payment_status = paid,         booking_status = confirmed, remaining = 0
	 *   deposit → payment_status = deposit_paid, booking_status = deposit_paid
	 *
	 * Also saves wc_order_id, transaction_id, paid_at, and fires emails.
	 *
	 * @param array<string, mixed> $booking        Booking record.
	 * @param int                  $order_id       WooCommerce order ID.
	 * @param string|null          $transaction_id Payment gateway transaction ID.
	 */
	private function handle_successful_payment(
		array $booking,
		int $order_id,
		?string $transaction_id
	): void {
		$booking_id   = (int) $booking['id'];
		$payment_type = $booking['payment_type'];

		// Determine new statuses.
		if ( 'deposit' === $payment_type ) {
			$payment_status = 'deposit_paid';
			$booking_status = 'deposit_paid';
			$remaining      = (float) $booking['remaining_balance']; // Preserve original balance.
		} else {
			$payment_status = 'paid';
			$booking_status = 'confirmed';
			$remaining      = 0.00;
		}

		$update = array(
			'payment_status'    => $payment_status,
			'booking_status'    => $booking_status,
			'remaining_balance' => $remaining,
			'wc_order_id'       => $order_id,
			'paid_at'           => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);

		if ( ! empty( $transaction_id ) ) {
			$update['transaction_id'] = sanitize_text_field( $transaction_id );
		}

		$this->update_booking( $booking_id, $update );

		// Re-fetch updated booking to pass fresh data to email methods.
		$fresh_booking = NSB_Booking_Handler::get_by_id( $booking_id );

		if ( $fresh_booking ) {
			// Send emails — each method enforces its own duplicate guard.
			NSB_Emails::send_customer_confirmation( $booking_id );
			NSB_Emails::send_admin_notification( $booking_id, $order_id );
		}
	}

	/**
	 * Handle a non-successful status change (failed / cancelled / refunded / on-hold).
	 *
	 * Does NOT send emails for these outcomes.
	 *
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $payment_status New payment_status string.
	 * @param string $booking_status New booking_status string.
	 */
	private function handle_status_change(
		int $order_id,
		string $payment_status,
		string $booking_status
	): void {
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$booking = $this->get_booking_for_order( $order );
		if ( ! $booking ) {
			return;
		}

		$this->update_booking(
			(int) $booking['id'],
			array(
				'payment_status' => $payment_status,
				'booking_status' => $booking_status,
				'wc_order_id'    => $order_id,
				'updated_at'     => current_time( 'mysql' ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Booking Lookup
	// -----------------------------------------------------------------------

	/**
	 * Find the NSB booking linked to a WooCommerce order.
	 *
	 * Strategy 1: check _nsb_booking_id order meta (fastest).
	 * Strategy 2: check _nsb_booking_reference order meta.
	 * Strategy 3: scan order line item meta for "Booking ID" or "Booking Reference".
	 *
	 * Returns null if no NSB booking is linked (unrelated WC order).
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array<string, mixed>|null Booking record or null.
	 */
	private function get_booking_for_order( WC_Order $order ): ?array {
		// Strategy 1: order meta _nsb_booking_id.
		$booking_id = (int) $order->get_meta( '_nsb_booking_id', true );
		if ( $booking_id > 0 ) {
			$booking = NSB_Booking_Handler::get_by_id( $booking_id );
			if ( $booking ) {
				return $booking;
			}
		}

		// Strategy 2: order meta _nsb_booking_reference.
		$booking_ref = $order->get_meta( '_nsb_booking_reference', true );
		if ( ! empty( $booking_ref ) ) {
			$booking = $this->get_booking_by_reference( sanitize_text_field( $booking_ref ) );
			if ( $booking ) {
				return $booking;
			}
		}

		// Strategy 3: line item meta scan.
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */

			// Try "Booking ID" meta key (as saved by Phase 2 save_order_line_item_meta).
			$booking_id_meta = $item->get_meta( __( 'Booking ID', 'napoleon-shuttle-booking' ), true );
			if ( $booking_id_meta ) {
				$booking = NSB_Booking_Handler::get_by_id( (int) $booking_id_meta );
				if ( $booking ) {
					return $booking;
				}
			}

			// Try "Booking Reference" meta key.
			$booking_ref_meta = $item->get_meta( __( 'Booking Reference', 'napoleon-shuttle-booking' ), true );
			if ( ! empty( $booking_ref_meta ) ) {
				$booking = $this->get_booking_by_reference( sanitize_text_field( $booking_ref_meta ) );
				if ( $booking ) {
					return $booking;
				}
			}
		}

		// No NSB booking found for this order — not our order.
		return null;
	}

	/**
	 * Fetch a booking by its reference string.
	 *
	 * @param string $reference Booking reference, e.g. NSB-20240601-ABCDEF.
	 * @return array<string, mixed>|null
	 */
	private function get_booking_by_reference( string $reference ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_bookings';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE booking_reference = %s LIMIT 1", $reference ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ?: null;
	}

	// -----------------------------------------------------------------------
	// DB Update Helper
	// -----------------------------------------------------------------------

	/**
	 * Update columns on a booking record.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param array<string, mixed> $data       Column → value pairs to update.
	 */
	private function update_booking( int $booking_id, array $data ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_bookings';

		// Build format array dynamically.
		$formats = array();
		foreach ( $data as $key => $value ) {
			if ( is_float( $value ) ) {
				$formats[] = '%f';
			} elseif ( is_int( $value ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $booking_id ),
			$formats,
			array( '%d' )
		);
	}

	// -----------------------------------------------------------------------
	// WC Order Helper
	// -----------------------------------------------------------------------

	/**
	 * Safely retrieve a WooCommerce order object.
	 *
	 * Returns null when WooCommerce is not active or order cannot be loaded,
	 * preventing fatal errors.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return WC_Order|null
	 */
	private function get_order( int $order_id ): ?WC_Order {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return ( $order instanceof WC_Order ) ? $order : null;
	}
}
