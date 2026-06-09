<?php
/**
 * Global helper functions for the Napoleon Shuttle Booking plugin.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether WooCommerce is active and its core class is available.
 *
 * @return bool
 */
function nsb_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Format a price using the plugin's configured currency symbol.
 *
 * @param float|string $amount Amount to format.
 * @return string
 */
function nsb_format_price( float|string $amount ): string {
	$symbol = NSB_Settings::get( 'currency_symbol', '$' );
	return esc_html( $symbol ) . number_format( (float) $amount, 2 );
}

/**
 * Get a human-readable label for a deposit type value.
 *
 * @param string $type Deposit type key.
 * @return string
 */
function nsb_deposit_type_label( string $type ): string {
	$labels = array(
		'fixed'      => __( 'Fixed', 'napoleon-shuttle-booking' ),
		'percentage' => __( 'Percentage', 'napoleon-shuttle-booking' ),
		'none'       => __( 'No Deposit', 'napoleon-shuttle-booking' ),
	);

	return $labels[ $type ] ?? ucfirst( $type );
}

/**
 * Return the URL to an NSB admin sub-page.
 *
 * @param string               $page  Page slug suffix (e.g. 'nsb-packages').
 * @param array<string, mixed> $extra Extra query args.
 * @return string
 */
function nsb_admin_url( string $page, array $extra = array() ): string {
	$args = array_merge( array( 'page' => $page ), $extra );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Output an admin notice HTML snippet. Safe to call in template files.
 *
 * @param string $message Notice message.
 * @param string $type    One of: success, error, warning, info.
 * @param bool   $dismissible Whether to add the dismissible class.
 */
function nsb_admin_notice( string $message, string $type = 'success', bool $dismissible = true ): void {
	$classes = 'notice notice-' . esc_attr( $type );
	if ( $dismissible ) {
		$classes .= ' is-dismissible';
	}
	printf(
		'<div class="%s"><p>%s</p></div>',
		esc_attr( $classes ),
		wp_kses_post( $message )
	);
}

/**
 * Retrieve stored flash notices from a transient and delete them.
 *
 * Used to persist notices across wp_redirect() calls.
 *
 * @return array<int, array{type: string, message: string}>
 */
function nsb_get_flash_notices(): array {
	$notices = get_transient( 'nsb_admin_notices_' . get_current_user_id() );
	delete_transient( 'nsb_admin_notices_' . get_current_user_id() );
	return is_array( $notices ) ? $notices : array();
}

/**
 * Store a flash notice that will be displayed after a redirect.
 *
 * @param string $message Notice message.
 * @param string $type    Notice type.
 */
function nsb_add_flash_notice( string $message, string $type = 'success' ): void {
	$key     = 'nsb_admin_notices_' . get_current_user_id();
	$current = get_transient( $key );
	if ( ! is_array( $current ) ) {
		$current = array();
	}
	$current[] = array(
		'type'    => $type,
		'message' => $message,
	);
	set_transient( $key, $current, 60 );
}


/**
 * Return a safe hex color setting.
 *
 * @param string $key Setting key.
 * @param string $fallback Fallback hex color.
 * @return string
 */
function nsb_get_color_setting( string $key, string $fallback ): string {
	$value = NSB_Settings::get( $key, $fallback );
	$value = is_string( $value ) ? sanitize_hex_color( $value ) : '';
	return $value ? $value : $fallback;
}

/**
 * Build CSS custom properties from plugin color settings.
 *
 * @return string
 */
function nsb_get_design_css_variables(): string {
	$colors = array(
		'--nsb-primary'      => nsb_get_color_setting( 'primary_color', '#b9823d' ),
		'--nsb-primary-dark' => nsb_get_color_setting( 'primary_dark_color', '#8a5a23' ),
		'--nsb-accent'       => nsb_get_color_setting( 'accent_color', '#f5eadb' ),
		'--nsb-ink'          => nsb_get_color_setting( 'text_color', '#17120c' ),
		'--nsb-muted'        => nsb_get_color_setting( 'muted_text_color', '#776f66' ),
		'--nsb-cream'        => nsb_get_color_setting( 'background_color', '#fbf7f1' ),
		'--nsb-surface'      => nsb_get_color_setting( 'surface_color', '#ffffff' ),
		'--nsb-border-color' => nsb_get_color_setting( 'border_color', '#ead8c2' ),
	);

	$css = '';
	foreach ( $colors as $var => $color ) {
		$css .= $var . ':' . $color . ';';
	}
	return $css;
}
