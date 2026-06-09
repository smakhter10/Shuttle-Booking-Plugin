<?php
/**
 * Manages plugin settings stored in WordPress options.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Settings
 *
 * Provides a simple wrapper around get_option / update_option for all NSB settings.
 */
class NSB_Settings {

	/** @var string The WordPress option key that holds all plugin settings. */
	const OPTION_KEY = 'nsb_settings';

	/**
	 * Default setting values.
	 *
	 * Phase 3A additions:
	 *   enable_customer_confirmation_email — yes/no
	 *   enable_admin_notification_email   — yes/no
	 *   business_name                     — falls back to site name at runtime
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			// Phase 1 / 2 defaults.
			'hidden_product_id'                  => 0,
			'auto_create_hidden_product'         => 'yes',
			'admin_notification_email'           => get_option( 'admin_email', '' ),
			'default_payment_options'            => 'both',
			'currency_symbol'                    => '$',
			'delete_data_on_uninstall'           => 'no',
			// Phase 3A defaults.
			'enable_customer_confirmation_email' => 'yes',
			'enable_admin_notification_email'    => 'yes',
			'business_name'                      => '',
			// Phase 3B defaults.
			'abandoned_booking_cleanup_hours'    => 24,
			// Availability defaults.
			'available_time_slots'               => "09:00\n11:00\n12:00\n14:30\n17:30",
			'max_bookings_per_day'              => 0,
			'max_bookings_per_time_slot'        => 1,
			// Design defaults.
			'primary_color'                    => '#b9823d',
			'primary_dark_color'               => '#8a5a23',
			'accent_color'                     => '#f5eadb',
			'text_color'                       => '#17120c',
			'muted_text_color'                 => '#776f66',
			'background_color'                 => '#fbf7f1',
			'surface_color'                    => '#ffffff',
			'border_color'                     => '#ead8c2',
		);
	}

	/**
	 * Save defaults on first activation (only fills in keys that don't exist yet).
	 */
	public static function set_defaults(): void {
		$existing = self::get_all();
		$defaults = self::defaults();
		$merged   = array_merge( $defaults, $existing );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Retrieve all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Get a single setting value with an optional fallback.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if key is not set.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all      = self::get_all();
		$defaults = self::defaults();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $defaults[ $key ] ?? $default;
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 */
	public static function set( string $key, mixed $value ): void {
		$all         = self::get_all();
		$all[ $key ] = $value;
		update_option( self::OPTION_KEY, $all );
	}

	/**
	 * Save all settings from a sanitized array.
	 *
	 * @param array<string, mixed> $data Sanitized settings array.
	 */
	public static function save( array $data ): void {
		$existing = self::get_all();
		$merged   = array_merge( $existing, $data );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Delete all plugin settings. Used on uninstall.
	 */
	public static function delete_all(): void {
		delete_option( self::OPTION_KEY );
		delete_option( 'nsb_db_version' );
	}
}
