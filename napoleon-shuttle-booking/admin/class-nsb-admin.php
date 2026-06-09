<?php
/**
 * Admin panel controller for the Napoleon Shuttle Booking plugin.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSB_Admin
 *
 * Handles all admin-side behaviour: menus, assets, notices, and form processing.
 */
class NSB_Admin {

	/** @var NSB_Admin|null */
	private static ?NSB_Admin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return NSB_Admin
	 */
	public static function get_instance(): NSB_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_flash_notices' ) );
		add_action( 'admin_notices', array( $this, 'maybe_woo_inactive_notice' ) );

		// Handle all GET/POST actions before output starts.
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	// -----------------------------------------------------------------------
	// Menus
	// -----------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-menus.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Napoleon Booking', 'napoleon-shuttle-booking' ),
			__( 'Napoleon Booking', 'napoleon-shuttle-booking' ),
			'manage_options',
			'nsb-dashboard',
			array( $this, 'page_dashboard' ),
			'dashicons-car',
			56
		);

		add_submenu_page(
			'nsb-dashboard',
			__( 'Dashboard', 'napoleon-shuttle-booking' ),
			__( 'Dashboard', 'napoleon-shuttle-booking' ),
			'manage_options',
			'nsb-dashboard',
			array( $this, 'page_dashboard' )
		);

		add_submenu_page(
			'nsb-dashboard',
			__( 'Packages', 'napoleon-shuttle-booking' ),
			__( 'Packages', 'napoleon-shuttle-booking' ),
			'manage_options',
			'nsb-packages',
			array( $this, 'page_packages' )
		);

		add_submenu_page(
			'nsb-dashboard',
			__( 'Bookings', 'napoleon-shuttle-booking' ),
			__( 'Bookings', 'napoleon-shuttle-booking' ),
			'manage_options',
			'nsb-bookings',
			array( $this, 'page_bookings' )
		);

		add_submenu_page(
			'nsb-dashboard',
			__( 'Settings', 'napoleon-shuttle-booking' ),
			__( 'Settings', 'napoleon-shuttle-booking' ),
			'manage_options',
			'nsb-settings',
			array( $this, 'page_settings' )
		);
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS only on NSB admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$nsb_pages = array(
			'toplevel_page_nsb-dashboard',
			'napoleon-booking_page_nsb-packages',
			'napoleon-booking_page_nsb-bookings',
			'napoleon-booking_page_nsb-settings',
		);

		if ( ! in_array( $hook, $nsb_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'nsb-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'nsb-admin',
			NSB_PLUGIN_URL . 'admin/assets/admin.css',
			array( 'nsb-poppins' ),
			NSB_VERSION
		);

		wp_add_inline_style(
			'nsb-admin',
			'.nsb-wrap, .nsb-wrap *{font-family:Poppins, sans-serif;} .nsb-wrap{' . nsb_get_design_css_variables() . '}'
		);

		if ( 'napoleon-booking_page_nsb-packages' === $hook ) {
			wp_enqueue_media();
		}

		$admin_script_deps = array( 'jquery' );
		if ( 'napoleon-booking_page_nsb-settings' === $hook ) {
			wp_enqueue_style( 'wp-color-picker' );
			$admin_script_deps[] = 'wp-color-picker';
		}

		wp_enqueue_script(
			'nsb-admin',
			NSB_PLUGIN_URL . 'admin/assets/admin.js',
			$admin_script_deps,
			NSB_VERSION,
			true
		);

		wp_localize_script(
			'nsb-admin',
			'nsbAdmin',
			array(
				'confirmDelete' => __( 'Are you sure you want to delete this item? This action cannot be undone.', 'napoleon-shuttle-booking' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'nsb_admin_nonce' ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Admin Notices
	// -----------------------------------------------------------------------

	/**
	 * Render any flash notices stored in a transient.
	 */
	public function render_flash_notices(): void {
		$notices = nsb_get_flash_notices();
		foreach ( $notices as $notice ) {
			nsb_admin_notice( $notice['message'], $notice['type'] );
		}
	}

	/**
	 * Show an admin notice when WooCommerce is inactive.
	 */
	public function maybe_woo_inactive_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( nsb_is_woocommerce_active() ) {
			return;
		}

		// Only show on NSB admin pages.
		$screen = get_current_screen();
		if ( $screen && ! str_contains( $screen->id, 'nsb' ) ) {
			return;
		}

		nsb_admin_notice(
			__( '<strong>Napoleon Shuttle Booking:</strong> WooCommerce is not active. Payment features require WooCommerce to be installed and activated.', 'napoleon-shuttle-booking' ),
			'warning',
			false
		);
	}

	// -----------------------------------------------------------------------
	// Action Handler (runs at admin_init, before any output)
	// -----------------------------------------------------------------------

	/**
	 * Central form/action handler. Processes POST data and redirects.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_REQUEST['nsb_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_REQUEST['nsb_action'] ) );

		switch ( $action ) {
			case 'create_package':
				$this->handle_create_package();
				break;

			case 'update_package':
				$this->handle_update_package();
				break;

			case 'delete_package':
				$this->handle_delete_package();
				break;

			case 'toggle_package':
				$this->handle_toggle_package();
				break;

			case 'save_settings':
				$this->handle_save_settings();
				break;

			case 'update_booking':
				$this->handle_update_booking();
				break;

			case 'booking_quick_action':
				$this->handle_booking_quick_action();
				break;

			case 'delete_booking':
				$this->handle_delete_booking();
				break;

			case 'booking_bulk_action':
				$this->handle_booking_bulk_action();
				break;

			case 'clean_abandoned_bookings':
				$this->handle_clean_abandoned_bookings();
				break;

			case 'recreate_product':
				$this->handle_recreate_product();
				break;
		}
	}

	// -----------------------------------------------------------------------
	// Package Action Handlers
	// -----------------------------------------------------------------------

	/**
	 * Handle new package creation form submission.
	 */
	private function handle_create_package(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_create_package' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$data   = $this->extract_package_post_data();
		$errors = NSB_Packages::validate( $data );

		if ( ! empty( $errors ) ) {
			nsb_add_flash_notice( implode( '<br>', $errors ), 'error' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages', array( 'action' => 'new' ) ) );
			exit;
		}

		$id = NSB_Packages::create( $data );

		if ( $id ) {
			nsb_add_flash_notice( __( 'Package created successfully.', 'napoleon-shuttle-booking' ), 'success' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages' ) );
		} else {
			nsb_add_flash_notice( __( 'There was an error creating the package.', 'napoleon-shuttle-booking' ), 'error' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages', array( 'action' => 'new' ) ) );
		}

		exit;
	}

	/**
	 * Handle package update form submission.
	 */
	private function handle_update_package(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_update_package' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;

		if ( ! $id ) {
			wp_die( esc_html__( 'Invalid package ID.', 'napoleon-shuttle-booking' ) );
		}

		$data   = $this->extract_package_post_data();
		$errors = NSB_Packages::validate( $data );

		if ( ! empty( $errors ) ) {
			nsb_add_flash_notice( implode( '<br>', $errors ), 'error' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages', array( 'action' => 'edit', 'id' => $id ) ) );
			exit;
		}

		$success = NSB_Packages::update( $id, $data );

		if ( $success ) {
			nsb_add_flash_notice( __( 'Package updated successfully.', 'napoleon-shuttle-booking' ), 'success' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages' ) );
		} else {
			nsb_add_flash_notice( __( 'There was an error updating the package.', 'napoleon-shuttle-booking' ), 'error' );
			wp_safe_redirect( nsb_admin_url( 'nsb-packages', array( 'action' => 'edit', 'id' => $id ) ) );
		}

		exit;
	}

	/**
	 * Handle package deletion.
	 */
	private function handle_delete_package(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $id || ! wp_verify_nonce( $nonce, 'nsb_delete_package_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$success = NSB_Packages::delete( $id );

		if ( $success ) {
			nsb_add_flash_notice( __( 'Package deleted.', 'napoleon-shuttle-booking' ), 'success' );
		} else {
			nsb_add_flash_notice( __( 'Error deleting package.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-packages' ) );
		exit;
	}

	/**
	 * Handle activate / deactivate toggle.
	 */
	private function handle_toggle_package(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $id || ! wp_verify_nonce( $nonce, 'nsb_toggle_package_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$active  = ( 'activate' === $status );
		$success = NSB_Packages::set_active( $id, $active );
		$label   = $active ? __( 'activated', 'napoleon-shuttle-booking' ) : __( 'deactivated', 'napoleon-shuttle-booking' );

		if ( $success ) {
			/* translators: %s: activated or deactivated */
			nsb_add_flash_notice( sprintf( __( 'Package %s.', 'napoleon-shuttle-booking' ), $label ), 'success' );
		} else {
			nsb_add_flash_notice( __( 'Error updating package status.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-packages' ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Settings Action Handler
	// -----------------------------------------------------------------------

	/**
	 * Handle settings form submission.
	 */
	private function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$data = array(
			// Phase 1 / 2 settings.
			'auto_create_hidden_product'         => isset( $_POST['auto_create_hidden_product'] ) ? 'yes' : 'no',
			'admin_notification_email'           => sanitize_email( wp_unslash( $_POST['admin_notification_email'] ?? '' ) ),
			'default_payment_options'            => sanitize_text_field( wp_unslash( $_POST['default_payment_options'] ?? 'both' ) ),
			'currency_symbol'                    => sanitize_text_field( wp_unslash( $_POST['currency_symbol'] ?? '$' ) ),
			'delete_data_on_uninstall'           => isset( $_POST['delete_data_on_uninstall'] ) ? 'yes' : 'no',
			// Phase 3A settings.
			'enable_customer_confirmation_email' => isset( $_POST['enable_customer_confirmation_email'] ) ? 'yes' : 'no',
			'enable_admin_notification_email'    => isset( $_POST['enable_admin_notification_email'] ) ? 'yes' : 'no',
			'business_name'                      => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
			'abandoned_booking_cleanup_hours'    => max( 1, absint( $_POST['abandoned_booking_cleanup_hours'] ?? 24 ) ),
			'available_time_slots'               => $this->sanitize_time_slots_text( wp_unslash( $_POST['available_time_slots'] ?? '' ) ),
			'max_bookings_per_day'              => max( 0, absint( $_POST['max_bookings_per_day'] ?? 0 ) ),
			'max_bookings_per_time_slot'        => max( 0, absint( $_POST['max_bookings_per_time_slot'] ?? 1 ) ),
			'primary_color'                    => $this->sanitize_hex_setting( $_POST['primary_color'] ?? '#b9823d', '#b9823d' ),
			'primary_dark_color'               => $this->sanitize_hex_setting( $_POST['primary_dark_color'] ?? '#8a5a23', '#8a5a23' ),
			'accent_color'                     => $this->sanitize_hex_setting( $_POST['accent_color'] ?? '#f5eadb', '#f5eadb' ),
			'text_color'                       => $this->sanitize_hex_setting( $_POST['text_color'] ?? '#17120c', '#17120c' ),
			'muted_text_color'                 => $this->sanitize_hex_setting( $_POST['muted_text_color'] ?? '#776f66', '#776f66' ),
			'background_color'                 => $this->sanitize_hex_setting( $_POST['background_color'] ?? '#fbf7f1', '#fbf7f1' ),
			'surface_color'                    => $this->sanitize_hex_setting( $_POST['surface_color'] ?? '#ffffff', '#ffffff' ),
			'border_color'                     => $this->sanitize_hex_setting( $_POST['border_color'] ?? '#ead8c2', '#ead8c2' ),
		);

		// Validate payment options.
		$allowed_payment_opts = array( 'deposit_only', 'full_only', 'both' );
		if ( ! in_array( $data['default_payment_options'], $allowed_payment_opts, true ) ) {
			$data['default_payment_options'] = 'both';
		}

		NSB_Settings::save( $data );

		nsb_add_flash_notice( __( 'Settings saved.', 'napoleon-shuttle-booking' ), 'success' );
		wp_safe_redirect( nsb_admin_url( 'nsb-settings' ) );
		exit;
	}

	/**
	 * Handle recreating the hidden WooCommerce booking product.
	 */
	private function handle_recreate_product(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nsb_recreate_product' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		// Reset saved ID so get_or_create_hidden_product() creates a fresh one.
		NSB_Settings::set( 'hidden_product_id', 0 );
		$id = NSB_WooCommerce::get_or_create_hidden_product();

		if ( $id ) {
			nsb_add_flash_notice(
				/* translators: %d: WooCommerce product ID */
				sprintf( __( 'Booking Payment product recreated successfully (Product ID: %d).', 'napoleon-shuttle-booking' ), $id ),
				'success'
			);
		} else {
			nsb_add_flash_notice( __( 'Failed to recreate the booking product. Make sure WooCommerce is active.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-settings' ) );
		exit;
	}


	// -----------------------------------------------------------------------
	// Booking Action Handlers
	// -----------------------------------------------------------------------

	/**
	 * Handle manual booking/payment status and admin note update.
	 */
	private function handle_update_booking(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		if ( ! $booking_id || ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_update_booking_' . $booking_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$booking_status = sanitize_text_field( wp_unslash( $_POST['booking_status'] ?? '' ) );
		$payment_status = sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? '' ) );
		$admin_notes    = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );

		$success = NSB_Bookings::update(
			$booking_id,
			array(
				'booking_status' => $booking_status,
				'payment_status' => $payment_status,
				'admin_notes'    => $admin_notes,
			)
		);

		if ( $success ) {
			if ( in_array( $booking_status, array( 'confirmed', 'deposit_paid', 'completed' ), true ) ) {
				$this->send_booking_emails_if_ready( $booking_id );
			}
			nsb_add_flash_notice( __( 'Booking updated successfully.', 'napoleon-shuttle-booking' ), 'success' );
		} else {
			nsb_add_flash_notice( __( 'There was an error updating the booking.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-bookings', array( 'action' => 'view', 'id' => $booking_id ) ) );
		exit;
	}

	/**
	 * Handle bookings list quick actions.
	 */
	private function handle_booking_quick_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		$booking_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$do         = isset( $_GET['do'] ) ? sanitize_text_field( wp_unslash( $_GET['do'] ) ) : '';
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $booking_id || ! wp_verify_nonce( $nonce, 'nsb_booking_quick_' . $booking_id . '_' . $do ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$data = array();
		switch ( $do ) {
			case 'mark_confirmed':
				$data['booking_status'] = 'confirmed';
				break;
			case 'mark_completed':
				$data['booking_status'] = 'completed';
				break;
			case 'cancel':
				$data['booking_status'] = 'cancelled';
				$data['payment_status'] = 'cancelled';
				break;
			default:
				wp_die( esc_html__( 'Invalid booking action.', 'napoleon-shuttle-booking' ) );
		}

		if ( NSB_Bookings::update( $booking_id, $data ) ) {
			if ( in_array( $do, array( 'mark_confirmed', 'mark_completed' ), true ) ) {
				$this->send_booking_emails_if_ready( $booking_id );
			}
			nsb_add_flash_notice( __( 'Booking status updated.', 'napoleon-shuttle-booking' ), 'success' );
		} else {
			nsb_add_flash_notice( __( 'Could not update booking status.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-bookings' ) );
		exit;
	}


	/**
	 * Send booking emails from manual admin approval/completion actions.
	 *
	 * Payment sync already sends these after successful WooCommerce payment. The
	 * email class has duplicate guards, so calling it here is safe and will not
	 * resend emails that were already sent.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private function send_booking_emails_if_ready( int $booking_id ): void {
		if ( ! class_exists( 'NSB_Emails' ) ) {
			return;
		}

		$booking = NSB_Bookings::get_by_id( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$order_id = ! empty( $booking['wc_order_id'] ) ? (int) $booking['wc_order_id'] : null;

		NSB_Emails::send_customer_confirmation( $booking_id );
		NSB_Emails::send_admin_notification( $booking_id, $order_id );
	}

	/**
	 * Delete a booking record only. WooCommerce orders are not deleted.
	 */
	private function handle_delete_booking(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		$booking_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $booking_id || ! wp_verify_nonce( $nonce, 'nsb_delete_booking_' . $booking_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		if ( NSB_Bookings::delete( $booking_id ) ) {
			nsb_add_flash_notice( __( 'Booking deleted. WooCommerce orders were not deleted.', 'napoleon-shuttle-booking' ), 'success' );
		} else {
			nsb_add_flash_notice( __( 'Could not delete booking.', 'napoleon-shuttle-booking' ), 'error' );
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-bookings' ) );
		exit;
	}


	/**
	 * Handle bulk booking actions from the bookings list table.
	 */
	private function handle_booking_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_booking_bulk_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$booking_ids = isset( $_POST['booking_ids'] ) && is_array( $_POST['booking_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['booking_ids'] ) ) : array();
		$booking_ids = array_values( array_filter( array_unique( $booking_ids ) ) );

		if ( empty( $bulk_action ) || '-1' === $bulk_action ) {
			nsb_add_flash_notice( __( 'Please select a bulk action.', 'napoleon-shuttle-booking' ), 'warning' );
			wp_safe_redirect( nsb_admin_url( 'nsb-bookings' ) );
			exit;
		}

		if ( empty( $booking_ids ) ) {
			nsb_add_flash_notice( __( 'Please select at least one booking.', 'napoleon-shuttle-booking' ), 'warning' );
			wp_safe_redirect( nsb_admin_url( 'nsb-bookings' ) );
			exit;
		}

		$updated = 0;
		$deleted = 0;

		switch ( $bulk_action ) {
			case 'mark_confirmed':
				foreach ( $booking_ids as $booking_id ) {
					if ( NSB_Bookings::update( $booking_id, array( 'booking_status' => 'confirmed' ) ) ) {
						$this->send_booking_emails_if_ready( $booking_id );
						$updated++;
					}
				}
				break;

			case 'mark_completed':
				foreach ( $booking_ids as $booking_id ) {
					if ( NSB_Bookings::update( $booking_id, array( 'booking_status' => 'completed' ) ) ) {
						$this->send_booking_emails_if_ready( $booking_id );
						$updated++;
					}
				}
				break;

			case 'cancel':
				foreach ( $booking_ids as $booking_id ) {
					if ( NSB_Bookings::update( $booking_id, array( 'booking_status' => 'cancelled', 'payment_status' => 'cancelled' ) ) ) {
						$updated++;
					}
				}
				break;

			case 'delete':
				foreach ( $booking_ids as $booking_id ) {
					if ( NSB_Bookings::delete( $booking_id ) ) {
						$deleted++;
					}
				}
				break;

			default:
				wp_die( esc_html__( 'Invalid bulk action.', 'napoleon-shuttle-booking' ) );
		}

		if ( 'delete' === $bulk_action ) {
			nsb_add_flash_notice(
				sprintf(
					/* translators: %d: number of deleted bookings */
					_n( '%d booking deleted. WooCommerce orders were not deleted.', '%d bookings deleted. WooCommerce orders were not deleted.', $deleted, 'napoleon-shuttle-booking' ),
					$deleted
				),
				'success'
			);
		} else {
			nsb_add_flash_notice(
				sprintf(
					/* translators: %d: number of updated bookings */
					_n( '%d booking updated.', '%d bookings updated.', $updated, 'napoleon-shuttle-booking' ),
					$updated
				),
				'success'
			);
		}

		wp_safe_redirect( nsb_admin_url( 'nsb-bookings' ) );
		exit;
	}

	/**
	 * Manual cleanup for old abandoned pending bookings.
	 */
	private function handle_clean_abandoned_bookings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'napoleon-shuttle-booking' ) );
		}

		if ( ! isset( $_POST['nsb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nsb_nonce'] ) ), 'nsb_clean_abandoned_bookings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'napoleon-shuttle-booking' ) );
		}

		$hours = max( 1, absint( NSB_Settings::get( 'abandoned_booking_cleanup_hours', 24 ) ) );
		$count = NSB_Bookings::clean_abandoned( $hours );

		nsb_add_flash_notice(
			sprintf(
				/* translators: %d: number of abandoned bookings */
				_n( '%d abandoned booking marked as cancelled.', '%d abandoned bookings marked as cancelled.', $count, 'napoleon-shuttle-booking' ),
				$count
			),
			'success'
		);

		wp_safe_redirect( nsb_admin_url( 'nsb-settings' ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// Page Renderers
	// -----------------------------------------------------------------------

	/**
	 * Render the Dashboard page.
	 */
	public function page_dashboard(): void {
		require_once NSB_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the Packages page (list, add, or edit).
	 */
	public function page_packages(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'edit' === $action && $id ) {
			$package = NSB_Packages::get_by_id( $id );
			if ( ! $package ) {
				nsb_add_flash_notice( __( 'Package not found.', 'napoleon-shuttle-booking' ), 'error' );
				wp_safe_redirect( nsb_admin_url( 'nsb-packages' ) );
				exit;
			}
			require_once NSB_PLUGIN_DIR . 'admin/views/package-edit.php';
		} elseif ( 'new' === $action ) {
			$package = null;
			require_once NSB_PLUGIN_DIR . 'admin/views/package-edit.php';
		} else {
			require_once NSB_PLUGIN_DIR . 'admin/views/packages-list.php';
		}
	}

	/**
	 * Render the Bookings page.
	 */
	public function page_bookings(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'view' === $action && $id ) {
			$booking = NSB_Bookings::get_by_id( $id );
			require_once NSB_PLUGIN_DIR . 'admin/views/booking-view.php';
		} else {
			require_once NSB_PLUGIN_DIR . 'admin/views/bookings-list.php';
		}
	}

	/**
	 * Render the Settings page.
	 */
	public function page_settings(): void {
		require_once NSB_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Sanitize a hex color setting with fallback.
	 *
	 * @param mixed  $value    Submitted value.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	private function sanitize_hex_setting( mixed $value, string $fallback ): string {
		$value = is_string( $value ) ? sanitize_hex_color( wp_unslash( $value ) ) : '';
		return $value ? $value : $fallback;
	}

	/**
	 * Sanitize available time slots textarea into one HH:MM value per line.
	 *
	 * @param string $raw Raw textarea value.
	 * @return string
	 */
	private function sanitize_time_slots_text( string $raw ): string {
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $line, $matches ) ) {
				$hour   = min( 23, max( 0, absint( $matches[1] ) ) );
				$minute = min( 59, max( 0, absint( $matches[2] ) ) );
				$clean[] = sprintf( '%02d:%02d', $hour, $minute );
			}
		}

		$clean = array_values( array_unique( $clean ) );

		if ( empty( $clean ) ) {
			$clean = array( '09:00', '11:00', '12:00', '14:30', '17:30' );
		}

		return implode( "\n", $clean );
	}

	// -----------------------------------------------------------------------
	// Utilities
	// -----------------------------------------------------------------------

	/**
	 * Extract and lightly sanitise package data from $_POST.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_package_post_data(): array {
		return array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'package_image_id' => absint( $_POST['package_image_id'] ?? 0 ),
			'base_price'       => sanitize_text_field( wp_unslash( $_POST['base_price'] ?? '0' ) ),
			'deposit_type'     => sanitize_text_field( wp_unslash( $_POST['deposit_type'] ?? 'none' ) ),
			'deposit_amount'   => sanitize_text_field( wp_unslash( $_POST['deposit_amount'] ?? '0' ) ),
			'duration_minutes' => sanitize_text_field( wp_unslash( $_POST['duration_minutes'] ?? '60' ) ),
			'max_passengers'   => sanitize_text_field( wp_unslash( $_POST['max_passengers'] ?? '' ) ),
			'active'           => isset( $_POST['active'] ) ? 1 : 0,
			'sort_order'       => sanitize_text_field( wp_unslash( $_POST['sort_order'] ?? '0' ) ),
		);
	}
}
