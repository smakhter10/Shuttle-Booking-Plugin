<?php
/**
 * Handles plugin activation tasks.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Activator
 *
 * Runs on plugin activation: creates tables, sets defaults, handles WooCommerce product.
 */
class NSB_Activator {

	/**
	 * Plugin activation entry point.
	 */
	public static function activate(): void {
		// Create / upgrade database tables (includes Phase 3A columns).
		NSB_Database::create_tables();

		// Save default plugin settings if they don't already exist.
		// NSB_Settings::defaults() now includes Phase 3A email settings.
		NSB_Settings::set_defaults();

		// Handle hidden WooCommerce product if WooCommerce is active.
		if ( nsb_is_woocommerce_active() ) {
			NSB_WooCommerce::get_or_create_hidden_product();
		}

		// Flush rewrite rules (reserved for future shortcodes / CPTs).
		flush_rewrite_rules();
	}
}
