<?php
/**
 * Uninstall script for Napoleon Shuttle Booking.
 *
 * This file runs when the plugin is deleted through the WordPress admin.
 * It only deletes data if the "delete_data_on_uninstall" setting is set to "yes".
 *
 * @package NapoleonShuttleBooking
 */

// Safety check — only run during WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load just the settings class so we can read the option without booting the full plugin.
$settings_file = plugin_dir_path( __FILE__ ) . 'includes/class-nsb-settings.php';

if ( ! file_exists( $settings_file ) ) {
	return;
}

require_once $settings_file;

// Read the cleanup preference.
$delete_data = NSB_Settings::get( 'delete_data_on_uninstall', 'no' );

if ( 'yes' !== $delete_data ) {
	// User chose to keep data — nothing to do.
	return;
}

global $wpdb;

// -----------------------------------------------------------------------
// 1. Drop custom tables
// -----------------------------------------------------------------------
require_once plugin_dir_path( __FILE__ ) . 'includes/class-nsb-database.php';
NSB_Database::drop_tables();

// -----------------------------------------------------------------------
// 2. Delete plugin options
// -----------------------------------------------------------------------
NSB_Settings::delete_all();

// -----------------------------------------------------------------------
// 3. Delete the hidden WooCommerce booking product (if it exists)
//    We only delete the product we created — identified by custom meta.
// -----------------------------------------------------------------------
if ( class_exists( 'WooCommerce' ) ) {
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'   => '_nsb_hidden_booking_product',
				'value' => 'yes',
			),
		),
		'fields'         => 'ids',
	);

	$posts = get_posts( $args );

	if ( ! empty( $posts ) ) {
		$product_id = (int) $posts[0];
		// wp_delete_post with true forces permanent deletion (skips trash).
		wp_delete_post( $product_id, true );
	}
} else {
	// WooCommerce might not be loaded during uninstall — query directly.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$product_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_nsb_hidden_booking_product' AND meta_value = 'yes' LIMIT 1"
		)
	);

	if ( $product_id ) {
		$wpdb->delete( $wpdb->posts, array( 'ID' => $product_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $product_id ), array( '%d' ) );
	}
	// phpcs:enable
}
