<?php
/**
 * Handles plugin deactivation tasks.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Deactivator
 *
 * Runs on plugin deactivation. Data is intentionally kept — uninstall.php handles cleanup.
 */
class NSB_Deactivator {

	/**
	 * Plugin deactivation entry point.
	 */
	public static function deactivate(): void {
		// Flush rewrite rules on deactivation so stale rules are removed.
		flush_rewrite_rules();
	}
}
