<?php
/**
 * WooCommerce cart and checkout integration for NSB bookings.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_WooCommerce_Checkout
 *
 * Handles all WooCommerce hooks required for the booking checkout flow:
 * - Adding booking data to cart item
 * - Restoring cart item from session
 * - Displaying booking details in cart and checkout
 * - Dynamically setting the hidden product price
 * - Preventing quantity changes
 * - Saving booking meta to WooCommerce order line items
 */
class NSB_WooCommerce_Checkout {

	/**
	 * Single instance.
	 *
	 * @var NSB_WooCommerce_Checkout|null
	 */
	private static ?NSB_WooCommerce_Checkout $instance = null;

	/**
	 * Cached booking record for the current checkout page load.
	 * Avoids hitting the DB once per checkout field.
	 *
	 * @var array<string, mixed>|null|false  null = not looked up yet, false = not found.
	 */
	private array|null|false $checkout_booking_cache = null;

	/**
	 * Returns the single instance.
	 *
	 * @return NSB_WooCommerce_Checkout
	 */
	public static function get_instance(): NSB_WooCommerce_Checkout {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers WooCommerce hooks.
	 */
	private function __construct() {
		if ( ! nsb_is_woocommerce_active() ) {
			return;
		}

		// Cart item data.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

		// Display booking info in cart and checkout.
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

		// Dynamic price override.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_booking_price' ), 20 );

		// Lock quantity to 1.
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_cart_item_quantity' ), 10, 3 );
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'set_sold_individually' ), 10, 2 );

		// Save booking meta to order line item.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item_meta' ), 10, 4 );

		// Save booking reference to order meta.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 10, 2 );

		// Pre-fill checkout billing fields from the booking record.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout_fields' ), 10, 2 );
	}

	// -----------------------------------------------------------------------
	// Helper: get hidden product ID
	// -----------------------------------------------------------------------

	/**
	 * Return the hidden booking product ID from settings.
	 *
	 * @return int
	 */
	private function get_hidden_product_id(): int {
		return (int) NSB_Settings::get( 'hidden_product_id', 0 );
	}

	/**
	 * Check if a cart item belongs to the hidden booking product.
	 *
	 * @param array<string, mixed> $cart_item Cart item.
	 * @return bool
	 */
	private function is_booking_cart_item( array $cart_item ): bool {
		$hidden_id = $this->get_hidden_product_id();
		return $hidden_id > 0
			&& isset( $cart_item['product_id'] )
			&& (int) $cart_item['product_id'] === $hidden_id
			&& isset( $cart_item['nsb_booking_id'] );
	}

	// -----------------------------------------------------------------------
	// Cart Item Data
	// -----------------------------------------------------------------------

	/**
	 * Add NSB booking data to cart item when the hidden product is added.
	 *
	 * @param array<string, mixed> $cart_item_data Existing cart item data.
	 * @param int                  $product_id     Product ID being added.
	 * @param int                  $variation_id   Variation ID (0 if none).
	 * @return array<string, mixed>
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$hidden_id = $this->get_hidden_product_id();

		if ( ! $hidden_id || $product_id !== $hidden_id ) {
			return $cart_item_data;
		}

		// Pull booking data from the transient set during form submission.
		// Use the same visitor key logic as NSB_Shortcodes.
		$visitor_key  = $this->get_cart_visitor_key();
		$transient_key = 'nsb_pending_booking_cart_' . $visitor_key;
		$booking_data  = get_transient( $transient_key );

		if ( $booking_data && is_array( $booking_data ) ) {
			$cart_item_data = array_merge( $cart_item_data, $booking_data );
			delete_transient( $transient_key );
		}

		return $cart_item_data;
	}

	/**
	 * Resolve the visitor key — must mirror NSB_Shortcodes::get_visitor_key().
	 *
	 * @return string
	 */
	private function get_cart_visitor_key(): string {
		if ( is_user_logged_in() ) {
			return 'u_' . get_current_user_id();
		}
		if ( WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return 'wc_' . $customer_id;
			}
		}
		$cookie_name = 'nsb_visitor_key';
		if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
			return 'ck_' . preg_replace( '/[^a-zA-Z0-9]/', '', $_COOKIE[ $cookie_name ] );
		}
		return 'unknown';
	}

	/**
	 * Restore NSB booking data from WooCommerce session.
	 *
	 * @param array<string, mixed> $cart_item      Cart item.
	 * @param array<string, mixed> $values         Session values.
	 * @return array<string, mixed>
	 */
	public function get_cart_item_from_session( array $cart_item, array $values ): array {
		$nsb_keys = array(
			'nsb_booking_id', 'nsb_booking_reference', 'nsb_package_id',
			'nsb_package_name', 'nsb_booking_date', 'nsb_booking_time',
			'nsb_return_date', 'nsb_return_time',
			'nsb_payment_type', 'nsb_amount_due_now', 'nsb_remaining_balance',
		);

		foreach ( $nsb_keys as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$cart_item[ $key ] = $values[ $key ];
			}
		}

		return $cart_item;
	}

	// -----------------------------------------------------------------------
	// Display in Cart / Checkout
	// -----------------------------------------------------------------------

	/**
	 * Display booking details below the product name in cart and checkout.
	 *
	 * @param array<int, array{name: string, value: string}> $item_data  Existing item data.
	 * @param array<string, mixed>                           $cart_item  Cart item.
	 * @return array<int, array{name: string, value: string}>
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( ! $this->is_booking_cart_item( $cart_item ) ) {
			return $item_data;
		}

		$booking_id = (int) $cart_item['nsb_booking_id'];
		$booking    = NSB_Booking_Handler::get_by_id( $booking_id );

		if ( ! $booking ) {
			return $item_data;
		}

		$rows = array(
			array(
				'name'  => __( 'Booking Reference', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['booking_reference'] ),
			),
			array(
				'name'  => __( 'Package', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['package_name'] ),
			),
			array(
				'name'  => __( 'Pickup Date', 'napoleon-shuttle-booking' ),
				'value' => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) ),
			),
			array(
				'name'  => __( 'Pickup Time', 'napoleon-shuttle-booking' ),
				'value' => esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking['booking_time'] ) ) ),
			),
			array(
				'name'  => __( 'Pickup Address', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['pickup_address'] ),
			),
			array(
				'name'  => __( 'Drop-off Address', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['dropoff_address'] ),
			),
			array(
				'name'  => __( 'Passengers', 'napoleon-shuttle-booking' ),
				'value' => esc_html( (string) $booking['passenger_count'] ),
			),
			array(
				'name'  => __( 'Payment Type', 'napoleon-shuttle-booking' ),
				'value' => 'deposit' === $booking['payment_type']
					? esc_html__( 'Deposit', 'napoleon-shuttle-booking' )
					: esc_html__( 'Full Payment', 'napoleon-shuttle-booking' ),
			),
		);

		if ( ! empty( $booking['return_date'] ) ) {
			$rows[] = array(
				'name'  => __( 'Drop-off / Return Date', 'napoleon-shuttle-booking' ),
				'value' => esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['return_date'] ) ) ),
			);
		}
		if ( ! empty( $booking['return_time'] ) ) {
			$rows[] = array(
				'name'  => __( 'Drop-off / Return Time', 'napoleon-shuttle-booking' ),
				'value' => esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking['return_time'] ) ) ),
			);
		}

		if ( (float) $booking['remaining_balance'] > 0 ) {
			$rows[] = array(
				'name'  => __( 'Remaining Balance', 'napoleon-shuttle-booking' ),
				'value' => esc_html( nsb_format_price( $booking['remaining_balance'] ) ),
			);
		}

		// Optional fields.
		if ( ! empty( $booking['flight_number'] ) ) {
			$rows[] = array(
				'name'  => __( 'Flight Number', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['flight_number'] ),
			);
		}
		if ( ! empty( $booking['airline_name'] ) ) {
			$rows[] = array(
				'name'  => __( 'Airline', 'napoleon-shuttle-booking' ),
				'value' => esc_html( $booking['airline_name'] ),
			);
		}
		if ( ! empty( $booking['luggage_count'] ) ) {
			$rows[] = array(
				'name'  => __( 'Luggage Count', 'napoleon-shuttle-booking' ),
				'value' => esc_html( (string) $booking['luggage_count'] ),
			);
		}

		return array_merge( $item_data, $rows );
	}

	// -----------------------------------------------------------------------
	// Dynamic Price
	// -----------------------------------------------------------------------

	/**
	 * Override the hidden product price with the booking's amount_due_now.
	 *
	 * @param WC_Cart $cart WooCommerce cart object.
	 */
	public function set_booking_price( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$hidden_id = $this->get_hidden_product_id();
		if ( ! $hidden_id ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! $this->is_booking_cart_item( $cart_item ) ) {
				continue;
			}

			$booking_id = (int) $cart_item['nsb_booking_id'];
			$booking    = NSB_Booking_Handler::get_by_id( $booking_id );

			if ( $booking ) {
				$cart_item['data']->set_price( (float) $booking['amount_due_now'] );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Quantity Lock
	// -----------------------------------------------------------------------

	/**
	 * Replace quantity input with a plain "1" for booking cart items.
	 *
	 * @param string               $product_quantity HTML quantity input.
	 * @param string               $cart_item_key    Cart item key.
	 * @param array<string, mixed> $cart_item        Cart item.
	 * @return string
	 */
	public function lock_cart_item_quantity( string $product_quantity, string $cart_item_key, array $cart_item ): string {
		if ( $this->is_booking_cart_item( $cart_item ) ) {
			return '1';
		}
		return $product_quantity;
	}

	/**
	 * Mark the hidden booking product as sold individually.
	 *
	 * @param bool       $sold_individually Current value.
	 * @param WC_Product $product           Product object.
	 * @return bool
	 */
	public function set_sold_individually( bool $sold_individually, WC_Product $product ): bool {
		$hidden_id = $this->get_hidden_product_id();
		if ( $hidden_id && $product->get_id() === $hidden_id ) {
			return true;
		}
		return $sold_individually;
	}

	// -----------------------------------------------------------------------
	// Order Meta
	// -----------------------------------------------------------------------

	/**
	 * Save booking details as WooCommerce order line item meta.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function save_order_line_item_meta(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		WC_Order $order
	): void {
		if ( ! isset( $values['nsb_booking_id'] ) ) {
			return;
		}

		$booking_id = (int) $values['nsb_booking_id'];
		$booking    = NSB_Booking_Handler::get_by_id( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$meta_map = array(
			__( 'Booking ID', 'napoleon-shuttle-booking' )         => $booking['id'],
			__( 'Booking Reference', 'napoleon-shuttle-booking' )  => $booking['booking_reference'],
			__( 'Package Name', 'napoleon-shuttle-booking' )       => $booking['package_name'],
			__( 'Pickup Date', 'napoleon-shuttle-booking' )        => $booking['booking_date'],
			__( 'Pickup Time', 'napoleon-shuttle-booking' )        => $booking['booking_time'],
			__( 'Pickup Address', 'napoleon-shuttle-booking' )     => $booking['pickup_address'],
			__( 'Drop-off Address', 'napoleon-shuttle-booking' )   => $booking['dropoff_address'],
			__( 'Passenger Count', 'napoleon-shuttle-booking' )    => $booking['passenger_count'],
			__( 'Payment Type', 'napoleon-shuttle-booking' )       => $booking['payment_type'],
			__( 'Amount Due Now', 'napoleon-shuttle-booking' )     => $booking['amount_due_now'],
			__( 'Remaining Balance', 'napoleon-shuttle-booking' )  => $booking['remaining_balance'],
		);

		if ( ! empty( $booking['return_date'] ) ) {
			$meta_map[ __( 'Drop-off / Return Date', 'napoleon-shuttle-booking' ) ] = $booking['return_date'];
		}
		if ( ! empty( $booking['return_time'] ) ) {
			$meta_map[ __( 'Drop-off / Return Time', 'napoleon-shuttle-booking' ) ] = $booking['return_time'];
		}

		if ( ! empty( $booking['flight_number'] ) ) {
			$meta_map[ __( 'Flight Number', 'napoleon-shuttle-booking' ) ] = $booking['flight_number'];
		}
		if ( ! empty( $booking['airline_name'] ) ) {
			$meta_map[ __( 'Airline Name', 'napoleon-shuttle-booking' ) ] = $booking['airline_name'];
		}
		if ( ! empty( $booking['luggage_count'] ) ) {
			$meta_map[ __( 'Luggage Count', 'napoleon-shuttle-booking' ) ] = $booking['luggage_count'];
		}

		foreach ( $meta_map as $label => $value ) {
			$item->add_meta_data( $label, $value, true );
		}
	}

	/**
	 * Save _nsb_booking_id and _nsb_booking_reference to order post meta.
	 *
	 * @param WC_Order             $order Order object.
	 * @param array<string, mixed> $data  POST data.
	 */
	public function save_order_meta( WC_Order $order, array $data ): void {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['nsb_booking_id'] ) ) {
				continue;
			}

			$booking_id = (int) $cart_item['nsb_booking_id'];
			$booking    = NSB_Booking_Handler::get_by_id( $booking_id );

			if ( $booking ) {
				$order->update_meta_data( '_nsb_booking_id', $booking['id'] );
				$order->update_meta_data( '_nsb_booking_reference', $booking['booking_reference'] );
				$this->sync_booking_customer_from_checkout( (int) $booking['id'], $order, $data );
				$order->save_meta_data();
			}

			break; // Only one booking per order.
		}
	}


	/**
	 * Copy WooCommerce checkout billing details back to the pending booking.
	 * This keeps the first booking step focused on trip details only, while the
	 * booking record still receives the final customer name/email/phone before
	 * payment sync and confirmation emails run.
	 *
	 * @param int                  $booking_id Booking ID.
	 * @param WC_Order             $order      WooCommerce order object.
	 * @param array<string, mixed> $data       Checkout posted data.
	 */
	private function sync_booking_customer_from_checkout( int $booking_id, WC_Order $order, array $data ): void {
		$first_name = sanitize_text_field( $data['billing_first_name'] ?? $order->get_billing_first_name() );
		$last_name  = sanitize_text_field( $data['billing_last_name'] ?? $order->get_billing_last_name() );
		$name       = trim( $first_name . ' ' . $last_name );
		$email      = sanitize_email( $data['billing_email'] ?? $order->get_billing_email() );
		$phone      = sanitize_text_field( $data['billing_phone'] ?? $order->get_billing_phone() );

		$update = array();

		if ( '' !== $name ) {
			$update['customer_name'] = $name;
		}
		if ( '' !== $email && is_email( $email ) ) {
			$update['customer_email'] = $email;
		}
		if ( '' !== $phone ) {
			$update['customer_phone'] = $phone;
		}

		if ( ! empty( $update ) && class_exists( 'NSB_Bookings' ) ) {
			NSB_Bookings::update( $booking_id, $update );
		}
	}

	// -----------------------------------------------------------------------
	// Checkout Field Pre-fill (Option A)
	// -----------------------------------------------------------------------

	/**
	 * Pre-fill WooCommerce billing fields from the booking record so the
	 * customer doesn't have to re-enter details they already provided.
	 *
	 * Hooks into woocommerce_checkout_get_value which fires once per field
	 * when the checkout page renders. We only act on the three fields we have
	 * data for; everything else returns null so WooCommerce handles it normally.
	 *
	 * @param mixed  $value Current field value (null if not set).
	 * @param string $input Field key, e.g. 'billing_first_name'.
	 * @return mixed Pre-filled value, or null to let WooCommerce decide.
	 */
	public function prefill_checkout_fields( mixed $value, string $input ): mixed {
		// Only act on the fields we care about.
		$our_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
		);

		if ( ! in_array( $input, $our_fields, true ) ) {
			return $value;
		}

		// If a value is already set (e.g. logged-in customer with saved billing
		// details), respect it and don't overwrite.
		if ( ! empty( $value ) ) {
			return $value;
		}

		$booking = $this->get_checkout_booking();

		if ( ! $booking ) {
			return $value;
		}

		// Anonymous bookings no longer collect contact details on step 1, so avoid
		// pre-filling WooCommerce checkout with placeholder values.
		if ( empty( $booking['customer_email'] ) && __( 'Checkout Customer', 'napoleon-shuttle-booking' ) === (string) $booking['customer_name'] ) {
			return $value;
		}

		switch ( $input ) {
			case 'billing_first_name':
				return $this->split_name( $booking['customer_name'], 'first' );

			case 'billing_last_name':
				return $this->split_name( $booking['customer_name'], 'last' );

			case 'billing_email':
				return $booking['customer_email'];

			case 'billing_phone':
				return $booking['customer_phone'];
		}

		return $value;
	}

	/**
	 * Find the booking record for the current cart session.
	 * Result is cached in $checkout_booking_cache so the DB is only
	 * queried once per page load regardless of how many fields are rendered.
	 *
	 * @return array<string, mixed>|false Booking row, or false if not found.
	 */
	private function get_checkout_booking(): array|false {
		// Return cached result if already looked up.
		if ( null !== $this->checkout_booking_cache ) {
			return $this->checkout_booking_cache;
		}

		$this->checkout_booking_cache = false; // Assume not found.

		if ( ! is_checkout() || is_null( WC()->cart ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['nsb_booking_id'] ) ) {
				continue;
			}

			$booking = NSB_Booking_Handler::get_by_id( (int) $cart_item['nsb_booking_id'] );

			if ( $booking ) {
				$this->checkout_booking_cache = $booking;
			}

			break; // Only one booking per cart.
		}

		return $this->checkout_booking_cache;
	}

	/**
	 * Split a full name string into first or last name.
	 *
	 * @param string $full_name Full name from booking record.
	 * @param string $part      'first' or 'last'.
	 * @return string
	 */
	private function split_name( string $full_name, string $part ): string {
		$full_name = trim( $full_name );
		$space_pos = strpos( $full_name, ' ' );

		if ( false === $space_pos ) {
			// Single word name — put it all in first name.
			return 'first' === $part ? $full_name : '';
		}

		if ( 'first' === $part ) {
			return substr( $full_name, 0, $space_pos );
		}

		return substr( $full_name, $space_pos + 1 );
	}
}
