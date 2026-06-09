<?php
/**
 * Admin view: Settings Page
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings    = NSB_Settings::get_all();
$defaults    = NSB_Settings::defaults();
$merged      = array_merge( $defaults, $settings );

$product_id  = (int) $merged['hidden_product_id'];
$woo_active  = nsb_is_woocommerce_active();
?>
<div class="wrap nsb-wrap">

	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Napoleon Booking — Settings', 'napoleon-shuttle-booking' ); ?>
	</h1>
	<hr class="wp-header-end">

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">

		<input type="hidden" name="nsb_action" value="save_settings">
		<?php wp_nonce_field( 'nsb_save_settings', 'nsb_nonce' ); ?>

		<div class="nsb-settings-layout">

			<!-- WooCommerce Integration -->
			<div class="nsb-card">
				<h2><?php esc_html_e( 'WooCommerce Integration', 'napoleon-shuttle-booking' ); ?></h2>

				<table class="form-table nsb-form-table">
					<tbody>

						<tr>
							<th scope="row"><?php esc_html_e( 'WooCommerce Status', 'napoleon-shuttle-booking' ); ?></th>
							<td>
								<?php if ( $woo_active ) : ?>
									<span class="nsb-badge nsb-badge-success"><?php esc_html_e( 'Active', 'napoleon-shuttle-booking' ); ?></span>
								<?php else : ?>
									<span class="nsb-badge nsb-badge-error"><?php esc_html_e( 'Inactive — WooCommerce is required for payment features.', 'napoleon-shuttle-booking' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Hidden Booking Product ID', 'napoleon-shuttle-booking' ); ?></th>
							<td>
								<?php if ( $product_id ) : ?>
									<code><?php echo esc_html( $product_id ); ?></code>
									<?php if ( $woo_active ) : ?>
										<?php $product = wc_get_product( $product_id ); ?>
										<?php if ( $product ) : ?>
											<span class="nsb-badge nsb-badge-success"><?php esc_html_e( 'Product exists', 'napoleon-shuttle-booking' ); ?></span>
											<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" target="_blank" class="button button-small">
												<?php esc_html_e( 'View in WooCommerce', 'napoleon-shuttle-booking' ); ?>
											</a>
										<?php else : ?>
											<span class="nsb-badge nsb-badge-error"><?php esc_html_e( 'Product missing!', 'napoleon-shuttle-booking' ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								<?php else : ?>
									<span class="nsb-badge nsb-badge-warning"><?php esc_html_e( 'Not set', 'napoleon-shuttle-booking' ); ?></span>
								<?php endif; ?>

								<?php if ( $woo_active ) : ?>
									<p style="margin-top:8px;">
										<a href="<?php echo esc_url( wp_nonce_url( nsb_admin_url( 'nsb-settings', array( 'nsb_action' => 'recreate_product' ) ), 'nsb_recreate_product' ) ); ?>"
										   class="button">
											<?php esc_html_e( 'Recreate Booking Product', 'napoleon-shuttle-booking' ); ?>
										</a>
										<span class="description">&nbsp;<?php esc_html_e( 'Only use if the product was accidentally deleted.', 'napoleon-shuttle-booking' ); ?></span>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="nsb_auto_create"><?php esc_html_e( 'Auto-create Hidden Product', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox"
									       id="nsb_auto_create"
									       name="auto_create_hidden_product"
									       value="1"
									       <?php checked( $merged['auto_create_hidden_product'], 'yes' ); ?>>
									<?php esc_html_e( 'Automatically create hidden product on activation if it doesn\'t exist.', 'napoleon-shuttle-booking' ); ?>
								</label>
							</td>
						</tr>

					</tbody>
				</table>
			</div><!-- .nsb-card -->

			<!-- Booking Defaults -->
			<div class="nsb-card">
				<h2><?php esc_html_e( 'Booking Defaults', 'napoleon-shuttle-booking' ); ?></h2>

				<table class="form-table nsb-form-table">
					<tbody>

						<tr>
							<th scope="row">
								<label for="nsb_payment_options"><?php esc_html_e( 'Default Payment Options', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<select id="nsb_payment_options" name="default_payment_options">
									<option value="both" <?php selected( $merged['default_payment_options'], 'both' ); ?>>
										<?php esc_html_e( 'Both (deposit or full payment)', 'napoleon-shuttle-booking' ); ?>
									</option>
									<option value="deposit_only" <?php selected( $merged['default_payment_options'], 'deposit_only' ); ?>>
										<?php esc_html_e( 'Deposit Only', 'napoleon-shuttle-booking' ); ?>
									</option>
									<option value="full_only" <?php selected( $merged['default_payment_options'], 'full_only' ); ?>>
										<?php esc_html_e( 'Full Payment Only', 'napoleon-shuttle-booking' ); ?>
									</option>
								</select>
								<p class="description"><?php esc_html_e( 'This is the site-wide default. Individual package settings (Phase 2) can override this.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="nsb_currency_symbol"><?php esc_html_e( 'Currency Display Symbol', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="text"
								       id="nsb_currency_symbol"
								       name="currency_symbol"
								       class="small-text"
								       maxlength="5"
								       value="<?php echo esc_attr( $merged['currency_symbol'] ); ?>">
								<p class="description"><?php esc_html_e( 'Symbol used for displaying prices in the admin (e.g. $, £, €).', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>


						<tr>
							<th scope="row">
								<label for="nsb_available_time_slots"><?php esc_html_e( 'Available Time Slots', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<textarea id="nsb_available_time_slots" name="available_time_slots" class="large-text code" rows="5" placeholder="09:00
11:00
12:00
14:30
17:30"><?php echo esc_textarea( $merged['available_time_slots'] ?? "09:00
11:00
12:00
14:30
17:30" ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Add one pickup time per line using 24-hour format. These appear as Popular Times in the custom calendar.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="nsb_max_bookings_per_day"><?php esc_html_e( 'Max Bookings Per Day', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="number" id="nsb_max_bookings_per_day" name="max_bookings_per_day" class="small-text" min="0" value="<?php echo esc_attr( $merged['max_bookings_per_day'] ?? 0 ); ?>">
								<p class="description"><?php esc_html_e( 'Set 0 for unlimited daily bookings. When the limit is reached, the date is blocked.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="nsb_max_bookings_per_time_slot"><?php esc_html_e( 'Max Bookings Per Time Slot', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="number" id="nsb_max_bookings_per_time_slot" name="max_bookings_per_time_slot" class="small-text" min="0" value="<?php echo esc_attr( $merged['max_bookings_per_time_slot'] ?? 1 ); ?>">
								<p class="description"><?php esc_html_e( 'Set 0 for unlimited bookings per time. When this limit is reached, that time button is blocked for the selected date.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>

					</tbody>
				</table>
			</div><!-- .nsb-card -->


			<!-- Design Settings -->
			<div class="nsb-card">
				<h2><?php esc_html_e( 'Design & Colors', 'napoleon-shuttle-booking' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Customize the frontend booking form colors. You can use the color picker or type a HEX code manually.', 'napoleon-shuttle-booking' ); ?></p>

				<table class="form-table nsb-form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="nsb_primary_color"><?php esc_html_e( 'Primary Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_primary_color" name="primary_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['primary_color'] ?? '#b9823d' ); ?>" data-default-color="#b9823d"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_primary_dark_color"><?php esc_html_e( 'Primary Dark Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_primary_dark_color" name="primary_dark_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['primary_dark_color'] ?? '#8a5a23' ); ?>" data-default-color="#8a5a23"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_accent_color"><?php esc_html_e( 'Accent / Soft Highlight Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_accent_color" name="accent_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['accent_color'] ?? '#f5eadb' ); ?>" data-default-color="#f5eadb"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_text_color"><?php esc_html_e( 'Main Text Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_text_color" name="text_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['text_color'] ?? '#17120c' ); ?>" data-default-color="#17120c"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_muted_text_color"><?php esc_html_e( 'Muted Text Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_muted_text_color" name="muted_text_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['muted_text_color'] ?? '#776f66' ); ?>" data-default-color="#776f66"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_background_color"><?php esc_html_e( 'Soft Background Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_background_color" name="background_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['background_color'] ?? '#fbf7f1' ); ?>" data-default-color="#fbf7f1"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_surface_color"><?php esc_html_e( 'Card Surface Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_surface_color" name="surface_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['surface_color'] ?? '#ffffff' ); ?>" data-default-color="#ffffff"></td>
						</tr>
						<tr>
							<th scope="row"><label for="nsb_border_color"><?php esc_html_e( 'Border Color', 'napoleon-shuttle-booking' ); ?></label></th>
							<td><input type="text" id="nsb_border_color" name="border_color" class="nsb-color-field" value="<?php echo esc_attr( $merged['border_color'] ?? '#ead8c2' ); ?>" data-default-color="#ead8c2"></td>
						</tr>
					</tbody>
				</table>
			</div><!-- .nsb-card -->

			<!-- Notifications -->
			<div class="nsb-card">
				<h2><?php esc_html_e( 'Notifications', 'napoleon-shuttle-booking' ); ?></h2>

				<table class="form-table nsb-form-table">
					<tbody>

						<tr>
							<th scope="row">
								<label for="nsb_business_name"><?php esc_html_e( 'Business Name', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="text"
								       id="nsb_business_name"
								       name="business_name"
								       class="regular-text"
								       value="<?php echo esc_attr( $merged['business_name'] ?? '' ); ?>">
								<p class="description">
									<?php
									printf(
										/* translators: %s: current site name */
										esc_html__( 'Used in email headers. Leave blank to use the site name: %s', 'napoleon-shuttle-booking' ),
										'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="nsb_admin_email"><?php esc_html_e( 'Admin Notification Email', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="email"
								       id="nsb_admin_email"
								       name="admin_notification_email"
								       class="regular-text"
								       value="<?php echo esc_attr( $merged['admin_notification_email'] ); ?>">
								<p class="description"><?php esc_html_e( 'Email address that receives new booking notifications after successful payment.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Customer Confirmation Email', 'napoleon-shuttle-booking' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
									       id="nsb_enable_customer_email"
									       name="enable_customer_confirmation_email"
									       value="1"
									       <?php checked( $merged['enable_customer_confirmation_email'] ?? 'yes', 'yes' ); ?>>
									<?php esc_html_e( 'Send a confirmation email to the customer after successful payment.', 'napoleon-shuttle-booking' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Admin Notification Email Toggle', 'napoleon-shuttle-booking' ); ?></th>
							<td>
								<label>
									<input type="checkbox"
									       id="nsb_enable_admin_email"
									       name="enable_admin_notification_email"
									       value="1"
									       <?php checked( $merged['enable_admin_notification_email'] ?? 'yes', 'yes' ); ?>>
									<?php esc_html_e( 'Send a notification email to the admin address above after successful payment.', 'napoleon-shuttle-booking' ); ?>
								</label>
							</td>
						</tr>

					</tbody>
				</table>
			</div><!-- .nsb-card -->


			<!-- Cleanup -->
			<div class="nsb-card">
				<h2><?php esc_html_e( 'Abandoned Booking Cleanup', 'napoleon-shuttle-booking' ); ?></h2>

				<table class="form-table nsb-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="nsb_cleanup_hours"><?php esc_html_e( 'Cleanup Hours', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<input type="number"
								       id="nsb_cleanup_hours"
								       name="abandoned_booking_cleanup_hours"
								       class="small-text"
								       min="1"
								       value="<?php echo esc_attr( $merged['abandoned_booking_cleanup_hours'] ?? 24 ); ?>">
								<p class="description"><?php esc_html_e( 'Pending bookings older than this many hours can be marked as cancelled if payment was not completed.', 'napoleon-shuttle-booking' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div><!-- .nsb-card -->

			<!-- Data & Privacy -->
			<div class="nsb-card nsb-card--danger">
				<h2><?php esc_html_e( 'Data & Uninstall', 'napoleon-shuttle-booking' ); ?></h2>

				<table class="form-table nsb-form-table">
					<tbody>

						<tr>
							<th scope="row">
								<label for="nsb_delete_data"><?php esc_html_e( 'Delete Data on Uninstall', 'napoleon-shuttle-booking' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox"
									       id="nsb_delete_data"
									       name="delete_data_on_uninstall"
									       value="1"
									       <?php checked( $merged['delete_data_on_uninstall'], 'yes' ); ?>>
									<?php esc_html_e( 'Remove all plugin data (tables, settings, hidden product) when the plugin is uninstalled.', 'napoleon-shuttle-booking' ); ?>
								</label>
								<p class="description" style="color:#c0392b;">
									<?php esc_html_e( 'Warning: This is irreversible. WooCommerce orders will NOT be deleted.', 'napoleon-shuttle-booking' ); ?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>
			</div><!-- .nsb-card -->

		</div><!-- .nsb-settings-layout -->

		<p class="submit">
			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'Save Settings', 'napoleon-shuttle-booking' ); ?>
			</button>
		</p>

	</form>


	<div class="nsb-card" style="max-width:800px;margin-top:20px;">
		<h2><?php esc_html_e( 'Manual Cleanup', 'napoleon-shuttle-booking' ); ?></h2>
		<p><?php esc_html_e( 'This will mark old pending/unpaid bookings as cancelled. Paid or confirmed bookings will not be changed.', 'napoleon-shuttle-booking' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="nsb_action" value="clean_abandoned_bookings">
			<?php wp_nonce_field( 'nsb_clean_abandoned_bookings', 'nsb_nonce' ); ?>
			<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Clean abandoned bookings now?', 'napoleon-shuttle-booking' ) ); ?>');">
				<?php esc_html_e( 'Clean Abandoned Bookings', 'napoleon-shuttle-booking' ); ?>
			</button>
		</form>
	</div>

</div><!-- .wrap -->
