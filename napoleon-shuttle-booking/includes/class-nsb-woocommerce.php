<?php
/**
 * Handles all WooCommerce integration for the NSB plugin.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_WooCommerce
 *
 * Manages the hidden "Booking Payment" WooCommerce product used for checkout.
 * All methods guard themselves so the plugin won't fatal-error when WooCommerce is absent.
 */
class NSB_WooCommerce {

	/** @var string Meta key that identifies our hidden booking product. */
	const HIDDEN_PRODUCT_META = '_nsb_hidden_booking_product';

	/**
	 * Single instance.
	 *
	 * @var NSB_WooCommerce|null
	 */
	private static ?NSB_WooCommerce $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return NSB_WooCommerce
	 */
	public static function get_instance(): NSB_WooCommerce {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers WooCommerce-specific hooks when WooCommerce is active.
	 */
	private function __construct() {
		if ( ! nsb_is_woocommerce_active() ) {
			return;
		}

		// Keep the hidden product truly hidden from the shop catalog.
		add_action( 'admin_notices', array( $this, 'maybe_show_missing_product_notice' ) );
	}

	// -----------------------------------------------------------------------
	// Hidden Product
	// -----------------------------------------------------------------------

	/**
	 * Get the existing hidden product, or create one if it doesn't exist.
	 *
	 * @return int|false Product ID on success, false on failure.
	 */
	public static function get_or_create_hidden_product(): int|false {
		if ( ! nsb_is_woocommerce_active() ) {
			return false;
		}

		// 1. Check if a valid product ID is already saved in settings.
		$saved_id = (int) NSB_Settings::get( 'hidden_product_id', 0 );
		if ( $saved_id > 0 ) {
			$product = wc_get_product( $saved_id );
			if ( $product && 'yes' === get_post_meta( $saved_id, self::HIDDEN_PRODUCT_META, true ) ) {
				return $saved_id;
			}
		}

		// 2. Search for an existing product by meta key (handles edge cases after reinstall).
		$existing_id = self::find_hidden_product_by_meta();
		if ( $existing_id ) {
			NSB_Settings::set( 'hidden_product_id', $existing_id );
			return $existing_id;
		}

		// 3. Create a brand-new hidden product.
		return self::create_hidden_product();
	}

	/**
	 * Search the database for a product with our custom meta key.
	 *
	 * @return int Product ID, or 0 if not found.
	 */
	private static function find_hidden_product_by_meta(): int {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'   => self::HIDDEN_PRODUCT_META,
					'value' => 'yes',
				),
			),
			'fields'         => 'ids',
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Create the hidden WooCommerce product used for booking payments.
	 *
	 * @return int|false Product ID on success, false on failure.
	 */
	private static function create_hidden_product(): int|false {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return false;
		}

		$product = new WC_Product_Simple();

		$product->set_name( 'Booking Payment' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( '0' );
		$product->set_price( '0' );

		$product_id = $product->save();

		if ( ! $product_id ) {
			return false;
		}

		// Tag this product so we can identify it later.
		update_post_meta( $product_id, self::HIDDEN_PRODUCT_META, 'yes' );

		// Persist the product ID to settings.
		NSB_Settings::set( 'hidden_product_id', $product_id );

		return $product_id;
	}

	/**
	 * Check whether the hidden product exists and is valid.
	 *
	 * @return bool
	 */
	public static function hidden_product_exists(): bool {
		if ( ! nsb_is_woocommerce_active() ) {
			return false;
		}

		$product_id = (int) NSB_Settings::get( 'hidden_product_id', 0 );

		if ( ! $product_id ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		return ( $product && 'yes' === get_post_meta( $product_id, self::HIDDEN_PRODUCT_META, true ) );
	}

	// -----------------------------------------------------------------------
	// Admin Notices
	// -----------------------------------------------------------------------

	/**
	 * Show an admin notice if the hidden product is missing while WooCommerce is active.
	 */
	public function maybe_show_missing_product_notice(): void {
		// Only show to admins on NSB admin pages or the plugins page.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::hidden_product_exists() ) {
			return;
		}

		// Only show on NSB admin pages or the plugins list.
		$screen = get_current_screen();
		if ( $screen && ! str_contains( $screen->id, 'nsb' ) && 'plugins' !== $screen->id ) {
			return;
		}

		$recreate_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'nsb-settings',
					'nsb_action' => 'recreate_product',
				),
				admin_url( 'admin.php' )
			),
			'nsb_recreate_product'
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s" class="button button-small">%s</a></p></div>',
			esc_html__( 'Napoleon Shuttle Booking:', 'napoleon-shuttle-booking' ),
			esc_html__( 'The hidden booking payment product is missing. Payment features will not work until it is recreated.', 'napoleon-shuttle-booking' ),
			esc_url( $recreate_url ),
			esc_html__( 'Recreate Product', 'napoleon-shuttle-booking' )
		);
	}
}
