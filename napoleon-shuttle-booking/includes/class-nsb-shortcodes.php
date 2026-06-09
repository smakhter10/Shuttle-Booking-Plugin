<?php
/**
 * Registers and handles the [napoleon_booking_form] shortcode.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Shortcodes
 *
 * Registers the booking form shortcode, handles form submissions,
 * and coordinates the booking → cart → checkout flow.
 */
class NSB_Shortcodes {

	/**
	 * Single instance.
	 *
	 * @var NSB_Shortcodes|null
	 */
	private static ?NSB_Shortcodes $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return NSB_Shortcodes
	 */
	public static function get_instance(): NSB_Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers hooks.
	 */
	private function __construct() {
		add_shortcode( 'napoleon_booking_form', array( $this, 'render_booking_form' ) );
		// Use 'wp' hook — fires after WooCommerce has fully initialised its
		// session and cart, so WC()->cart is guaranteed to be available.
		add_action( 'wp', array( $this, 'handle_form_submission' ) );
		add_action( 'wp_ajax_nsb_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_nopriv_nsb_get_availability', array( $this, 'ajax_get_availability' ) );
	}

	// -----------------------------------------------------------------------
	// Shortcode Rendering
	// -----------------------------------------------------------------------

	/**
	 * Render the booking form shortcode output.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_booking_form( $atts ): string {
		// Error: WooCommerce inactive.
		if ( ! nsb_is_woocommerce_active() ) {
			return '<div class="nsb-notice nsb-notice-error">' .
				esc_html__( 'Online booking payment is currently unavailable. Please contact us directly to reserve your shuttle.', 'napoleon-shuttle-booking' ) .
				'</div>';
		}

		// Error: hidden product missing.
		if ( ! NSB_WooCommerce::hidden_product_exists() ) {
			return '<div class="nsb-notice nsb-notice-error">' .
				esc_html__( 'Booking checkout is not ready yet. Please contact the site administrator.', 'napoleon-shuttle-booking' ) .
				'</div>';
		}

		// Fetch active packages.
		$packages = NSB_Packages::get_all( true );

		if ( empty( $packages ) ) {
			return '<div class="nsb-notice nsb-notice-info">' .
				esc_html__( 'No booking packages are available at the moment.', 'napoleon-shuttle-booking' ) .
				'</div>';
		}

		// Retrieve any form errors / old input from transient.
		$transient_key = 'nsb_form_state_' . $this->get_visitor_key();
		$form_state    = get_transient( $transient_key );
		delete_transient( $transient_key );

		$errors   = ( $form_state && ! empty( $form_state['errors'] ) ) ? $form_state['errors'] : array();
		$old      = ( $form_state && ! empty( $form_state['input'] ) ) ? $form_state['input'] : array();

		// Package data for JS.
		$packages_js = array();
		foreach ( $packages as $pkg ) {
			$package_image_id  = absint( $pkg['package_image_id'] ?? 0 );
			$package_image_url = $package_image_id ? wp_get_attachment_image_url( $package_image_id, 'large' ) : '';

			$packages_js[ (int) $pkg['id'] ] = array(
				'id'             => (int) $pkg['id'],
				'name'           => $pkg['name'],
				'description'    => $pkg['description'],
				'base_price'     => (float) $pkg['base_price'],
				'deposit_type'   => $pkg['deposit_type'],
				'deposit_amount' => (float) $pkg['deposit_amount'],
				'max_passengers' => $pkg['max_passengers'] ? (int) $pkg['max_passengers'] : null,
				'image_url'      => $package_image_url ? esc_url_raw( $package_image_url ) : '',
			);
		}

		$payment_options_setting = NSB_Settings::get( 'default_payment_options', 'both' );
		$currency_symbol         = NSB_Settings::get( 'currency_symbol', '$' );

		wp_localize_script(
			'nsb-public',
			'nsbData',
			array(
				'packages'       => $packages_js,
				'paymentOptions' => $payment_options_setting,
				'currency'       => $currency_symbol,
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'nsb_booking_form' ),
				'timeSlots'      => NSB_Booking_Handler::get_available_time_slots(),
				'availability'   => array(
					'maxPerDay'  => max( 0, absint( NSB_Settings::get( 'max_bookings_per_day', 0 ) ) ),
					'maxPerSlot' => max( 0, absint( NSB_Settings::get( 'max_bookings_per_time_slot', 1 ) ) ),
				),
			)
		);

		// Render the form view.
		ob_start();
		include NSB_PLUGIN_DIR . 'public/views/booking-form.php';
		return ob_get_clean();
	}


	/**
	 * AJAX: return month availability for the custom calendar.
	 */
	public function ajax_get_availability(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nsb_booking_form' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'napoleon-shuttle-booking' ) ), 403 );
		}

		$year       = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : (int) gmdate( 'Y' );
		$month      = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : (int) gmdate( 'n' );
		$package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;

		wp_send_json_success( NSB_Booking_Handler::get_month_availability( $year, $month, $package_id ) );
	}

	// -----------------------------------------------------------------------
	// Form Submission Handler
	// -----------------------------------------------------------------------

	/**
	 * Handle the booking form POST submission.
	 * Runs on 'init' so we can redirect before any output.
	 */
	public function handle_form_submission(): void {
		if (
			! isset( $_POST['nsb_booking_submit'] ) ||
			! isset( $_POST['nsb_nonce'] )
		) {
			return;
		}

		// Step 1: Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_booking_form' ) ) {
			$this->redirect_with_error( array( __( 'Security check failed. Please try again.', 'napoleon-shuttle-booking' ) ), $_POST );
			return;
		}

		// Step 2: WooCommerce active?
		if ( ! nsb_is_woocommerce_active() ) {
			$this->redirect_with_error( array( __( 'Checkout is currently unavailable.', 'napoleon-shuttle-booking' ) ), $_POST );
			return;
		}

		// Step 3: Hidden product exists and is purchasable?
		$hidden_product_id = (int) NSB_Settings::get( 'hidden_product_id', 0 );
		if ( ! $hidden_product_id ) {
			$this->redirect_with_error( array( __( 'Checkout is currently unavailable.', 'napoleon-shuttle-booking' ) ), $_POST );
			return;
		}

		$hidden_product = wc_get_product( $hidden_product_id );
		if ( ! $hidden_product || ! $hidden_product->is_purchasable() ) {
			$this->redirect_with_error( array( __( 'Checkout is currently unavailable.', 'napoleon-shuttle-booking' ) ), $_POST );
			return;
		}

		// Step 4: Validate form fields.
		$post_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$errors    = NSB_Booking_Handler::validate_form( $post_data );

		if ( ! empty( $errors ) ) {
			$this->redirect_with_error( $errors, $post_data );
			return;
		}

		// Step 5 & 6: Fetch and confirm package is active.
		$package_id = absint( $post_data['nsb_package_id'] );
		$package    = NSB_Packages::get_by_id( $package_id );

		if ( ! $package || ! (int) $package['active'] ) {
			$this->redirect_with_error( array( __( 'Selected package is no longer available.', 'napoleon-shuttle-booking' ) ), $post_data );
			return;
		}

		// Resolve payment type against plugin settings.
		$raw_payment_type       = sanitize_text_field( $post_data['nsb_payment_type'] ?? 'full' );
		$payment_options_setting = NSB_Settings::get( 'default_payment_options', 'both' );

		if ( 'full_only' === $payment_options_setting ) {
			$raw_payment_type = 'full';
		} elseif ( 'deposit_only' === $payment_options_setting ) {
			$raw_payment_type = 'deposit';
		}

		// Step 7: Calculate payment amount on backend (never trust frontend).
		$calc = NSB_Booking_Handler::calculate_payment( $package_id, $raw_payment_type );

		if ( is_wp_error( $calc ) ) {
			$this->redirect_with_error( array( $calc->get_error_message() ), $post_data );
			return;
		}

		// Step 8: Create booking record.
		$booking_reference = NSB_Booking_Handler::generate_booking_reference();

		$booking_data = array(
			'booking_reference' => $booking_reference,
			'package_id'        => $package_id,
			'package_name'      => $package['name'],
			// Contact details are collected on the WooCommerce checkout page.
			// These placeholders are replaced from billing details when the order is created.
			'customer_name'     => is_user_logged_in() ? wp_get_current_user()->display_name : __( 'Checkout Customer', 'napoleon-shuttle-booking' ),
			'customer_email'    => is_user_logged_in() ? wp_get_current_user()->user_email : '',
			'customer_phone'    => '',
			'pickup_address'    => sanitize_textarea_field( $post_data['nsb_pickup_address'] ),
			'dropoff_address'   => sanitize_textarea_field( $post_data['nsb_dropoff_address'] ),
			'booking_date'      => sanitize_text_field( $post_data['nsb_booking_date'] ),
			'booking_time'      => sanitize_text_field( $post_data['nsb_booking_time'] ),
			'return_date'       => sanitize_text_field( $post_data['nsb_return_date'] ?? '' ),
			'return_time'       => sanitize_text_field( $post_data['nsb_return_time'] ?? '' ),
			'passenger_count'   => absint( $post_data['nsb_passenger_count'] ),
			'notes'             => sanitize_textarea_field( $post_data['nsb_notes'] ?? '' ),
			'base_price'        => $calc['base_price'],
			'payment_type'      => $calc['payment_type'],
			'amount_due_now'    => $calc['amount_due_now'],
			'remaining_balance' => $calc['remaining_balance'],
			'flight_number'     => sanitize_text_field( $post_data['nsb_flight_number'] ?? '' ),
			'airline_name'      => sanitize_text_field( $post_data['nsb_airline_name'] ?? '' ),
			'luggage_count'     => '' !== ( $post_data['nsb_luggage_count'] ?? '' ) ? absint( $post_data['nsb_luggage_count'] ) : '',
		);

		$booking_id = NSB_Booking_Handler::create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			$this->redirect_with_error( array( $booking_id->get_error_message() ), $post_data );
			return;
		}

		// Step 9 & 10: Prepare the cart item data that add_cart_item_data will pick up.
		$cart_item_data = array(
			'nsb_booking_id'        => $booking_id,
			'nsb_booking_reference' => $booking_reference,
			'nsb_package_id'        => $package_id,
			'nsb_package_name'      => $package['name'],
			'nsb_booking_date'      => sanitize_text_field( $post_data['nsb_booking_date'] ),
			'nsb_booking_time'      => sanitize_text_field( $post_data['nsb_booking_time'] ),
			'nsb_return_date'       => sanitize_text_field( $post_data['nsb_return_date'] ?? '' ),
			'nsb_return_time'       => sanitize_text_field( $post_data['nsb_return_time'] ?? '' ),
			'nsb_payment_type'      => $calc['payment_type'],
			'nsb_amount_due_now'    => $calc['amount_due_now'],
			'nsb_remaining_balance' => $calc['remaining_balance'],
		);

		// Store in a transient keyed by visitor. The cart item data hook reads
		// this when add_to_cart fires.
		set_transient(
			'nsb_pending_booking_cart_' . $this->get_visitor_key(),
			$cart_item_data,
			300 // 5 minutes
		);

		// Ensure WooCommerce session is active before touching the cart.
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// Empty the cart, then add the single hidden booking product.
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $hidden_product_id, 1 );

		// Step 11: Redirect to WooCommerce checkout.
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Redirect back to the referring page with errors stored in a transient.
	 *
	 * @param array<int, string>   $errors Error messages.
	 * @param array<string, mixed> $input  Raw form input for repopulation.
	 */
	private function redirect_with_error( array $errors, array $input ): void {
		$key = 'nsb_form_state_' . $this->get_visitor_key();
		set_transient(
			$key,
			array(
				'errors' => $errors,
				'input'  => $input,
			),
			120
		);

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ?: home_url() );
		exit;
	}

	/**
	 * A stable visitor key that works for both logged-in and anonymous users.
	 * Uses the WooCommerce customer ID (from the WC session cookie) which is
	 * set before the 'wp' hook fires, making it consistent across requests.
	 *
	 * @return string
	 */
	private function get_visitor_key(): string {
		if ( is_user_logged_in() ) {
			return 'u_' . get_current_user_id();
		}

		// WooCommerce session customer ID is the most reliable anonymous key.
		if ( function_exists( 'WC' ) && WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return 'wc_' . $customer_id;
			}
		}

		// Fallback: use or create a lightweight cookie.
		$cookie_name = 'nsb_visitor_key';
		if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
			return 'ck_' . preg_replace( '/[^a-zA-Z0-9]/', '', $_COOKIE[ $cookie_name ] );
		}

		$key = wp_generate_password( 16, false );
		setcookie( $cookie_name, $key, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		return 'ck_' . $key;
	}
}
