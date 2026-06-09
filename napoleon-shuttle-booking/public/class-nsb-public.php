<?php
/**
 * Manages frontend asset enqueueing for the NSB plugin.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Public
 *
 * Enqueues public CSS and JavaScript only on pages that contain
 * the [napoleon_booking_form] shortcode.
 */
class NSB_Public {

	/**
	 * Single instance.
	 *
	 * @var NSB_Public|null
	 */
	private static ?NSB_Public $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return NSB_Public
	 */
	public static function get_instance(): NSB_Public {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers hooks.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue public CSS and JS.
	 * Assets are loaded sitewide (lightweight) so the shortcode works on any page.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'nsb-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'nsb-public',
			NSB_PLUGIN_URL . 'public/assets/public.css',
			array( 'nsb-poppins' ),
			NSB_VERSION
		);

		wp_add_inline_style(
			'nsb-public',
			'.nsb-booking-wrap{' . nsb_get_design_css_variables() . '} .nsb-booking-wrap, .nsb-booking-wrap *{font-family:Poppins, sans-serif !important;}'
		);

		wp_enqueue_script(
			'nsb-public',
			NSB_PLUGIN_URL . 'public/assets/public.js',
			array( 'jquery' ),
			NSB_VERSION,
			true
		);

		// wp_localize_script data is added in NSB_Shortcodes::render_booking_form().
	}
}
