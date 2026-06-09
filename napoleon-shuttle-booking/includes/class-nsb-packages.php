<?php
/**
 * Handles all CRUD operations for shuttle packages.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Packages
 *
 * Provides static methods for creating, reading, updating, and deleting packages
 * from the wp_nsb_packages table.
 */
class NSB_Packages {

	/**
	 * Allowed deposit type values.
	 */
	const DEPOSIT_TYPES = array( 'fixed', 'percentage', 'none' );

	// -----------------------------------------------------------------------
	// Read
	// -----------------------------------------------------------------------

	/**
	 * Get all packages, optionally filtered by active status.
	 *
	 * @param bool|null $active_only Pass true/false to filter, null for all.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all( ?bool $active_only = null ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_packages';

		if ( null === $active_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC", ARRAY_A );
		} else {
			$active  = $active_only ? 1 : 0;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE active = %d ORDER BY sort_order ASC, id ASC", $active ), ARRAY_A );
		}

		return $results ?: array();
	}

	/**
	 * Get a single package by ID.
	 *
	 * @param int $id Package ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_id( int $id ): ?array {
		global $wpdb;

		$table  = $wpdb->prefix . 'nsb_packages';
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Count packages, optionally by active status.
	 *
	 * @param bool|null $active_only Filter flag.
	 * @return int
	 */
	public static function count( ?bool $active_only = null ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_packages';

		if ( null === $active_only ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$active = $active_only ? 1 : 0;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE active = %d", $active ) );
	}

	// -----------------------------------------------------------------------
	// Write
	// -----------------------------------------------------------------------

	/**
	 * Insert a new package.
	 *
	 * @param array<string, mixed> $data Sanitized package data.
	 * @return int|false Inserted ID on success, false on failure.
	 */
	public static function create( array $data ): int|false {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_packages';
		$now   = current_time( 'mysql' );

		$insert = array(
			'name'             => sanitize_text_field( $data['name'] ),
			'slug'             => self::generate_unique_slug( $data['name'] ),
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'package_image_id' => ! empty( $data['package_image_id'] ) ? absint( $data['package_image_id'] ) : null,
			'base_price'       => (float) $data['base_price'],
			'deposit_type'     => sanitize_text_field( $data['deposit_type'] ?? 'none' ),
			'deposit_amount'   => (float) ( $data['deposit_amount'] ?? 0 ),
			'duration_minutes' => (int) ( $data['duration_minutes'] ?? 60 ),
			'max_passengers'   => isset( $data['max_passengers'] ) && '' !== $data['max_passengers']
								  ? (int) $data['max_passengers']
								  : null,
			'active'           => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
			'sort_order'       => (int) ( $data['sort_order'] ?? 0 ),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s' );

		// max_passengers is nullable.
		if ( null === $insert['max_passengers'] ) {
			$formats[8] = '%s'; // Will insert NULL.
		}

		$result = $wpdb->insert( $table, $insert, $formats );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing package.
	 *
	 * @param int                  $id   Package ID.
	 * @param array<string, mixed> $data Sanitized package data.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'nsb_packages';
		$now   = current_time( 'mysql' );

		$update = array(
			'name'             => sanitize_text_field( $data['name'] ),
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'package_image_id' => ! empty( $data['package_image_id'] ) ? absint( $data['package_image_id'] ) : null,
			'base_price'       => (float) $data['base_price'],
			'deposit_type'     => sanitize_text_field( $data['deposit_type'] ?? 'none' ),
			'deposit_amount'   => (float) ( $data['deposit_amount'] ?? 0 ),
			'duration_minutes' => (int) ( $data['duration_minutes'] ?? 60 ),
			'max_passengers'   => isset( $data['max_passengers'] ) && '' !== $data['max_passengers']
								  ? (int) $data['max_passengers']
								  : null,
			'active'           => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
			'sort_order'       => (int) ( $data['sort_order'] ?? 0 ),
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s' );

		// Nullable max_passengers.
		if ( null === $update['max_passengers'] ) {
			$formats[7] = '%s';
		}

		$result = $wpdb->update( $table, $update, array( 'id' => $id ), $formats, array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Delete a package.
	 *
	 * @param int $id Package ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . 'nsb_packages';
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Toggle a package's active status.
	 *
	 * @param int  $id     Package ID.
	 * @param bool $active New status.
	 * @return bool
	 */
	public static function set_active( int $id, bool $active ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . 'nsb_packages';
		$result = $wpdb->update(
			$table,
			array(
				'active'     => (int) $active,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	// -----------------------------------------------------------------------
	// Validation
	// -----------------------------------------------------------------------

	/**
	 * Validate package form data.
	 *
	 * @param array<string, mixed> $data Raw POST data.
	 * @return array<int, string> List of error messages. Empty means valid.
	 */
	public static function validate( array $data ): array {
		$errors = array();

		// Name.
		if ( empty( trim( $data['name'] ?? '' ) ) ) {
			$errors[] = __( 'Package name is required.', 'napoleon-shuttle-booking' );
		}

		// Base price.
		if ( ! isset( $data['base_price'] ) || ! is_numeric( $data['base_price'] ) || (float) $data['base_price'] < 0 ) {
			$errors[] = __( 'Base price must be a valid number (0 or greater).', 'napoleon-shuttle-booking' );
		}

		// Deposit type.
		$deposit_type = $data['deposit_type'] ?? '';
		if ( ! in_array( $deposit_type, self::DEPOSIT_TYPES, true ) ) {
			$errors[] = __( 'Deposit type must be fixed, percentage, or none.', 'napoleon-shuttle-booking' );
		}

		// Deposit amount conditional checks.
		if ( in_array( $deposit_type, array( 'fixed', 'percentage' ), true ) ) {
			$deposit_amount = (float) ( $data['deposit_amount'] ?? 0 );
			$base_price     = (float) ( $data['base_price'] ?? 0 );

			if ( 'percentage' === $deposit_type ) {
				if ( $deposit_amount < 0 || $deposit_amount > 100 ) {
					$errors[] = __( 'Deposit amount must be between 0 and 100 for percentage type.', 'napoleon-shuttle-booking' );
				}
			}

			if ( 'fixed' === $deposit_type && $deposit_amount > $base_price ) {
				$errors[] = __( 'Fixed deposit amount cannot be greater than the base price.', 'napoleon-shuttle-booking' );
			}
		}

		// Duration.
		$duration = $data['duration_minutes'] ?? '';
		if ( '' !== $duration && ( ! is_numeric( $duration ) || (int) $duration <= 0 ) ) {
			$errors[] = __( 'Duration must be a positive integer (in minutes).', 'napoleon-shuttle-booking' );
		}

		// Total seat capacity (optional).
		if ( isset( $data['max_passengers'] ) && '' !== $data['max_passengers'] ) {
			if ( ! is_numeric( $data['max_passengers'] ) || (int) $data['max_passengers'] <= 0 ) {
				$errors[] = __( 'Total seat capacity must be a positive integer, or left empty for unlimited seats.', 'napoleon-shuttle-booking' );
			}
		}

		// Sort order.
		if ( isset( $data['sort_order'] ) && '' !== $data['sort_order'] ) {
			if ( ! is_numeric( $data['sort_order'] ) ) {
				$errors[] = __( 'Sort order must be an integer.', 'napoleon-shuttle-booking' );
			}
		}


		// Package image ID is optional, but if supplied it must be numeric.
		if ( isset( $data['package_image_id'] ) && '' !== $data['package_image_id'] && ! is_numeric( $data['package_image_id'] ) ) {
			$errors[] = __( 'Package image must be a valid media attachment ID.', 'napoleon-shuttle-booking' );
		}

		return $errors;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Generate a unique slug for a package name.
	 *
	 * @param string $name Package name.
	 * @return string
	 */
	private static function generate_unique_slug( string $name ): string {
		global $wpdb;

		$table    = $wpdb->prefix . 'nsb_packages';
		$base     = sanitize_title( $name );
		$slug     = $base;
		$counter  = 1;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
			if ( ! $exists ) {
				break;
			}
			$slug = $base . '-' . $counter;
			++$counter;
		}

		return $slug;
	}
}
