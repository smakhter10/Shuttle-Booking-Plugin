<?php
/**
 * Plugin Name: Napoleon Shuttle Booking
 * Plugin URI:  https://example.com/napoleon-shuttle-booking
 * Description: A custom shuttle/service booking system with WooCommerce payment integration.
 * Version:     3.3.8
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: napoleon-shuttle-booking
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'NSB_VERSION', '3.3.8' );
define( 'NSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NSB_PLUGIN_FILE', __FILE__ );
define( 'NSB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class — singleton entry point.
 */
final class Napoleon_Shuttle_Booking {

	/**
	 * Single instance.
	 *
	 * @var Napoleon_Shuttle_Booking
	 */
	private static ?Napoleon_Shuttle_Booking $instance = null;

	/**
	 * Returns the single instance of the plugin.
	 *
	 * @return Napoleon_Shuttle_Booking
	 */
	public static function get_instance(): Napoleon_Shuttle_Booking {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — loads includes and hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load all required class files.
	 */
	private function load_dependencies(): void {
		// Phase 1 — Core.
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-activator.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-deactivator.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-database.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-packages.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-bookings.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-settings.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-woocommerce.php';
		require_once NSB_PLUGIN_DIR . 'includes/helpers.php';
		require_once NSB_PLUGIN_DIR . 'admin/class-nsb-admin.php';

		// Phase 2 — Frontend booking flow.
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-booking-handler.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-woocommerce-checkout.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-shortcodes.php';
		require_once NSB_PLUGIN_DIR . 'public/class-nsb-public.php';

		// Phase 3A — Payment sync and emails.
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-emails.php';
		require_once NSB_PLUGIN_DIR . 'includes/class-nsb-payment-sync.php';
	}

	/**
	 * Register activation, deactivation hooks and plugin action hooks.
	 */
	private function init_hooks(): void {
		register_activation_hook( NSB_PLUGIN_FILE, array( 'NSB_Activator', 'activate' ) );
		register_deactivation_hook( NSB_PLUGIN_FILE, array( 'NSB_Deactivator', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'init', array( $this, 'on_init' ) );
	}

	/**
	 * Fired after all plugins are loaded. Safe point to check for WooCommerce.
	 */
	public function on_plugins_loaded(): void {
		// Initialize admin.
		if ( is_admin() ) {
			NSB_Admin::get_instance();
		}

		// Initialize WooCommerce integration (safe — checks internally).
		NSB_WooCommerce::get_instance();

		// Phase 2 — WooCommerce cart/checkout hooks (only when WC is active).
		if ( nsb_is_woocommerce_active() ) {
			NSB_WooCommerce_Checkout::get_instance();
		}

		// Phase 3A — WooCommerce payment status sync (only when WC is active).
		if ( nsb_is_woocommerce_active() ) {
			NSB_Payment_Sync::get_instance();
		}

		// Run DB upgrade — checks version and only runs ALTER TABLEs when needed.
		// Covers both Phase 2 columns (flight_number etc.) and Phase 3A columns.
		NSB_Database::maybe_upgrade();
	}

	/**
	 * Fired on WordPress 'init'.
	 */
	public function on_init(): void {
		// Phase 2 — Register shortcodes and handle form submission.
		NSB_Shortcodes::get_instance();

		// Phase 2 — Enqueue public assets.
		NSB_Public::get_instance();
	}
}

// Boot the plugin.
Napoleon_Shuttle_Booking::get_instance();
