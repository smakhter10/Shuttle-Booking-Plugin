<?php
/**
 * Manages custom database table creation and upgrades.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Database
 *
 * Uses dbDelta to create / update plugin tables.
 * Always references $wpdb->prefix so the plugin works on non-standard table prefixes.
 *
 * DB version history:
 *   2.0.0 — initial tables (Phase 1)
 *   2.1.0 — flight_number, airline_name, luggage_count (Phase 2)
 *   3.0.0 — customer_email_sent, admin_email_sent, paid_at (Phase 3A)
 *   3.1.0 — admin_notes (Phase 3B)
 *   3.2.1 — return_date and return_time for premium booking UI
 *   3.3.0 — availability limits/time slot settings and custom calendar UI
 *   3.3.6 — package_image_id for dynamic package preview images
 */
class NSB_Database {

	/**
	 * Current plugin DB schema version.
	 *
	 * Increment this constant whenever columns are added or the schema changes.
	 * The maybe_upgrade() routine compares this against the stored nsb_db_version
	 * option and runs column additions only when needed.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '3.3.6';

	/**
	 * Create or upgrade all plugin tables.
	 *
	 * Uses dbDelta — safe to call on every activation; it will not destroy
	 * existing data. After table creation we also run column additions that
	 * dbDelta cannot handle (ALTER TABLE for new columns on existing tables).
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Load the dbDelta helper.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ---------------------------------------------------------------
		// Table: wp_nsb_packages
		// ---------------------------------------------------------------
		$packages_table = $wpdb->prefix . 'nsb_packages';
		$sql_packages   = "CREATE TABLE {$packages_table} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name            VARCHAR(255)    NOT NULL,
			slug            VARCHAR(255)    NOT NULL,
			description     TEXT            NULL,
			package_image_id BIGINT UNSIGNED NULL,
			base_price      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			deposit_type    VARCHAR(20)     NOT NULL DEFAULT 'fixed',
			deposit_amount  DECIMAL(10,2)            DEFAULT 0.00,
			duration_minutes INT                      DEFAULT 60,
			max_passengers  INT                      NULL,
			active          TINYINT(1)               DEFAULT 1,
			sort_order      INT                      DEFAULT 0,
			created_at      DATETIME        NOT NULL,
			updated_at      DATETIME        NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY package_image_id (package_image_id)
		) {$charset_collate};";

		dbDelta( $sql_packages );

		// ---------------------------------------------------------------
		// Table: wp_nsb_bookings
		// ---------------------------------------------------------------
		$bookings_table = $wpdb->prefix . 'nsb_bookings';
		$sql_bookings   = "CREATE TABLE {$bookings_table} (
			id                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			booking_reference     VARCHAR(100)     NOT NULL,
			package_id            BIGINT UNSIGNED  NOT NULL,
			package_name          VARCHAR(255)     NOT NULL,
			customer_name         VARCHAR(255)     NOT NULL,
			customer_email        VARCHAR(255)     NOT NULL,
			customer_phone        VARCHAR(100)     NOT NULL,
			pickup_address        TEXT             NULL,
			dropoff_address       TEXT             NULL,
			booking_date          DATE             NOT NULL,
			booking_time          TIME             NOT NULL,
			return_date           DATE             NULL,
			return_time           TIME             NULL,
			passenger_count       INT                       DEFAULT 1,
			notes                 TEXT             NULL,
			base_price            DECIMAL(10,2)    NOT NULL,
			payment_type          VARCHAR(20)      NOT NULL,
			amount_due_now        DECIMAL(10,2)    NOT NULL,
			remaining_balance     DECIMAL(10,2)             DEFAULT 0.00,
			payment_status        VARCHAR(50)               DEFAULT 'pending',
			booking_status        VARCHAR(50)               DEFAULT 'pending_payment',
			wc_order_id           BIGINT UNSIGNED  NULL,
			transaction_id        VARCHAR(255)     NULL,
			flight_number         VARCHAR(100)     NULL,
			airline_name          VARCHAR(255)     NULL,
			luggage_count         INT              NULL,
			customer_email_sent   TINYINT(1)                DEFAULT 0,
			admin_email_sent      TINYINT(1)                DEFAULT 0,
			paid_at               DATETIME         NULL,
			admin_notes           TEXT             NULL,
			created_at            DATETIME         NOT NULL,
			updated_at            DATETIME         NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_reference (booking_reference),
			KEY package_id (package_id),
			KEY wc_order_id (wc_order_id)
		) {$charset_collate};";

		dbDelta( $sql_bookings );

		// Store the DB schema version.
		update_option( 'nsb_db_version', self::SCHEMA_VERSION );
	}

	/**
	 * Run safe incremental column upgrades when the stored schema version is
	 * lower than SCHEMA_VERSION. Each ALTER TABLE is only executed if the
	 * column does not already exist, so this is safe to call repeatedly.
	 *
	 * Called from the main plugin bootstrap (on_plugins_loaded) so upgrades
	 * apply automatically after a plugin update without re-activating.
	 */
	public static function maybe_upgrade(): void {
		$stored_version = get_option( 'nsb_db_version', '0.0.0' );

		// Nothing to do if schema is current.
		if ( version_compare( $stored_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'nsb_bookings';

		// Fetch current columns once.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );

		if ( ! is_array( $columns ) ) {
			// Table might not exist yet — create_tables() will handle it.
			return;
		}

		// -- Phase 2 columns (may already exist on upgraded installs) -------

		if ( ! in_array( 'flight_number', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN flight_number VARCHAR(100) NULL" );
		}
		if ( ! in_array( 'airline_name', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN airline_name VARCHAR(255) NULL" );
		}
		if ( ! in_array( 'luggage_count', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN luggage_count INT NULL" );
		}

		// -- Phase 3A columns -----------------------------------------------

		if ( ! in_array( 'customer_email_sent', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN customer_email_sent TINYINT(1) NOT NULL DEFAULT 0" );
		}
		if ( ! in_array( 'admin_email_sent', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN admin_email_sent TINYINT(1) NOT NULL DEFAULT 0" );
		}
		if ( ! in_array( 'paid_at', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN paid_at DATETIME NULL" );
		}

		// -- Phase 3B columns -----------------------------------------------

		if ( ! in_array( 'admin_notes', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN admin_notes TEXT NULL" );
		}

		// -- UI enhancement columns ------------------------------------------

		if ( ! in_array( 'return_date', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN return_date DATE NULL AFTER booking_time" );
		}
		if ( ! in_array( 'return_time', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN return_time TIME NULL AFTER return_date" );
		}


		// -- Package image column ---------------------------------------------
		$packages_table = $wpdb->prefix . 'nsb_packages';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$package_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$packages_table}", 0 );
		if ( is_array( $package_columns ) && ! in_array( 'package_image_id', $package_columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$packages_table} ADD COLUMN package_image_id BIGINT UNSIGNED NULL AFTER description" );
		}

		// Bump stored version so we only run this once per upgrade.
		update_option( 'nsb_db_version', self::SCHEMA_VERSION );
	}

	/**
	 * Drop all plugin tables. Called by uninstall.php when cleanup is requested.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nsb_bookings' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nsb_packages' );
		// phpcs:enable
	}
}
