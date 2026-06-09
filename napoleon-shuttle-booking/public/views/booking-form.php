<?php
/**
 * Frontend booking form view.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$old_package_id = absint( $old['nsb_package_id'] ?? 0 );
$currency       = esc_html( NSB_Settings::get( 'currency_symbol', '$' ) );
$default_image  = esc_url( NSB_PLUGIN_URL . 'public/assets/booking-preview.svg' );
$first_package  = ! empty( $packages ) ? $packages[0] : array();
$preview_name   = ! empty( $first_package['name'] ) ? $first_package['name'] : __( 'Select a shuttle package', 'napoleon-shuttle-booking' );
$preview_desc   = ! empty( $first_package['description'] ) ? $first_package['description'] : __( 'Choose your package and trip schedule. Contact and payment details will be completed securely on the checkout page.', 'napoleon-shuttle-booking' );
$preview_price  = isset( $first_package['base_price'] ) ? (float) $first_package['base_price'] : 0;
$first_image_id = ! empty( $first_package['package_image_id'] ) ? absint( $first_package['package_image_id'] ) : 0;
$first_image_url = $first_image_id ? wp_get_attachment_image_url( $first_image_id, 'large' ) : '';
$preview_image = $first_image_url ? esc_url( $first_image_url ) : $default_image;
$selected_found = false;
?>

<div class="nsb-booking-wrap" id="nsb-booking-form-wrap">

	<div class="nsb-stepper" aria-label="Booking steps">
		<div class="nsb-step nsb-step-active">
			<span class="nsb-step-number">1</span>
			<span><?php esc_html_e( 'Booking Information', 'napoleon-shuttle-booking' ); ?></span>
		</div>
		<div class="nsb-step-divider" aria-hidden="true"></div>
		<div class="nsb-step">
			<span class="nsb-step-number">2</span>
			<span><?php esc_html_e( 'Payment Information', 'napoleon-shuttle-booking' ); ?></span>
		</div>
	</div>

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="nsb-form-errors" role="alert">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="nsb-booking-layout">
		<form id="nsb-booking-form" class="nsb-form" method="post" novalidate>

			<?php wp_nonce_field( 'nsb_booking_form', 'nsb_nonce' ); ?>
			<input type="hidden" name="nsb_booking_submit" value="1">

			<div class="nsb-form-card">
				<div class="nsb-form-card-header">
					<span class="nsb-eyebrow"><?php esc_html_e( 'Premium Shuttle Reservation', 'napoleon-shuttle-booking' ); ?></span>
					<h3><?php esc_html_e( 'Plan your trip', 'napoleon-shuttle-booking' ); ?></h3>
					<p><?php esc_html_e( 'Add your booking details now. Your contact, billing, and payment information will be collected securely on the checkout page.', 'napoleon-shuttle-booking' ); ?></p>
				</div>

				<div class="nsb-section-block">
					<div class="nsb-section-heading">
						<span><?php esc_html_e( '01', 'napoleon-shuttle-booking' ); ?></span>
						<h4><?php esc_html_e( 'Choose package', 'napoleon-shuttle-booking' ); ?></h4>
					</div>

					<div class="nsb-package-grid" id="nsb-package-grid">
						<?php foreach ( $packages as $index => $pkg ) : ?>
							<?php
							$pkg_id      = (int) $pkg['id'];
							$is_checked  = $old_package_id ? ( $old_package_id === $pkg_id ) : ( 0 === $index );
							$selected_found = $selected_found || $is_checked;
							$deposit_label = __( 'Full payment available', 'napoleon-shuttle-booking' );
							if ( 'none' !== $pkg['deposit_type'] && (float) $pkg['deposit_amount'] > 0 ) {
								$deposit_label = 'percentage' === $pkg['deposit_type']
									? sprintf( /* translators: %s: percentage amount */ __( '%s%% deposit available', 'napoleon-shuttle-booking' ), number_format_i18n( (float) $pkg['deposit_amount'], 0 ) )
									: sprintf( /* translators: %s: deposit price */ __( '%s deposit available', 'napoleon-shuttle-booking' ), $currency . number_format( (float) $pkg['deposit_amount'], 2 ) );
							}
							?>
							<label class="nsb-package-card">
								<input type="radio" name="nsb_package_id" value="<?php echo esc_attr( $pkg_id ); ?>" <?php checked( $is_checked ); ?> required>
								<span class="nsb-package-card-body">
									<span class="nsb-package-topline">
										<span class="nsb-package-name"><?php echo esc_html( $pkg['name'] ); ?></span>
										<span class="nsb-package-price"><?php echo esc_html( $currency . number_format( (float) $pkg['base_price'], 2 ) ); ?></span>
									</span>
									<span class="nsb-package-description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $pkg['description'] ?? '' ), 16, '...' ) ); ?></span>
									<span class="nsb-package-meta"><?php echo esc_html( $deposit_label ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="nsb-section-block">
					<div class="nsb-section-heading">
						<span><?php esc_html_e( '02', 'napoleon-shuttle-booking' ); ?></span>
						<h4><?php esc_html_e( 'Trip schedule', 'napoleon-shuttle-booking' ); ?></h4>
					</div>

					<div class="nsb-trip-schedule">
						<div class="nsb-date-group">
							<div class="nsb-date-group-label"><?php esc_html_e( 'Pick-up', 'napoleon-shuttle-booking' ); ?> <span class="nsb-required">*</span></div>
							<div class="nsb-date-row nsb-picker-row">
								<input type="hidden" id="nsb_booking_date" name="nsb_booking_date" value="<?php echo esc_attr( $old['nsb_booking_date'] ?? '' ); ?>" required>
								<input type="hidden" id="nsb_booking_time" name="nsb_booking_time" value="<?php echo esc_attr( $old['nsb_booking_time'] ?? '' ); ?>" required>
								<button type="button" class="nsb-picker-trigger nsb-date-trigger" data-nsb-picker="pickup">
									<span class="nsb-input-icon" aria-hidden="true">▣</span>
									<span id="nsb_booking_date_label"><?php echo ! empty( $old['nsb_booking_date'] ) ? esc_html( date_i18n( 'd / M / Y', strtotime( $old['nsb_booking_date'] ) ) ) : esc_html__( 'Select date', 'napoleon-shuttle-booking' ); ?></span>
								</button>
								<button type="button" class="nsb-picker-trigger nsb-time-trigger" data-nsb-picker="pickup">
									<span class="nsb-input-icon" aria-hidden="true">◷</span>
									<span id="nsb_booking_time_label"><?php echo ! empty( $old['nsb_booking_time'] ) ? esc_html( substr( $old['nsb_booking_time'], 0, 5 ) ) : esc_html__( 'Select time', 'napoleon-shuttle-booking' ); ?></span>
								</button>
							</div>
						</div>

						<div class="nsb-schedule-arrow" aria-hidden="true">→</div>

						<div class="nsb-date-group">
							<div class="nsb-date-group-label"><?php esc_html_e( 'Drop-off / Return', 'napoleon-shuttle-booking' ); ?> <span><?php esc_html_e( 'Optional', 'napoleon-shuttle-booking' ); ?></span></div>
							<div class="nsb-date-row nsb-picker-row">
								<input type="hidden" id="nsb_return_date" name="nsb_return_date" value="<?php echo esc_attr( $old['nsb_return_date'] ?? '' ); ?>">
								<input type="hidden" id="nsb_return_time" name="nsb_return_time" value="<?php echo esc_attr( $old['nsb_return_time'] ?? '' ); ?>">
								<button type="button" class="nsb-picker-trigger nsb-date-trigger" data-nsb-picker="return">
									<span class="nsb-input-icon" aria-hidden="true">▣</span>
									<span id="nsb_return_date_label"><?php echo ! empty( $old['nsb_return_date'] ) ? esc_html( date_i18n( 'd / M / Y', strtotime( $old['nsb_return_date'] ) ) ) : esc_html__( 'Select date', 'napoleon-shuttle-booking' ); ?></span>
								</button>
								<button type="button" class="nsb-picker-trigger nsb-time-trigger" data-nsb-picker="return">
									<span class="nsb-input-icon" aria-hidden="true">◷</span>
									<span id="nsb_return_time_label"><?php echo ! empty( $old['nsb_return_time'] ) ? esc_html( substr( $old['nsb_return_time'], 0, 5 ) ) : esc_html__( 'Optional', 'napoleon-shuttle-booking' ); ?></span>
								</button>
							</div>
						</div>
					</div>

					<div class="nsb-availability-note">
						<?php esc_html_e( 'Unavailable days and booked-out pickup times are automatically blocked when all confirmed paid seats are booked.', 'napoleon-shuttle-booking' ); ?>
					</div>

					<div class="nsb-seat-availability" id="nsb-seat-availability" style="display:none;" aria-live="polite">
						<div><span><?php esc_html_e( 'Total Seats', 'napoleon-shuttle-booking' ); ?></span><strong id="nsb-seat-total">—</strong></div>
						<div><span><?php esc_html_e( 'Booked Seats', 'napoleon-shuttle-booking' ); ?></span><strong id="nsb-seat-booked">—</strong></div>
						<div><span><?php esc_html_e( 'Available Seats', 'napoleon-shuttle-booking' ); ?></span><strong id="nsb-seat-available">—</strong></div>
					</div>
				</div>

				<div class="nsb-section-block">
					<div class="nsb-section-heading">
						<span><?php esc_html_e( '03', 'napoleon-shuttle-booking' ); ?></span>
						<h4><?php esc_html_e( 'Route details', 'napoleon-shuttle-booking' ); ?></h4>
					</div>

					<div class="nsb-field-row">
						<div class="nsb-field">
							<label for="nsb_pickup_address"><?php esc_html_e( 'Pickup Address', 'napoleon-shuttle-booking' ); ?> <span class="nsb-required">*</span></label>
							<input type="text" id="nsb_pickup_address" name="nsb_pickup_address" value="<?php echo esc_attr( $old['nsb_pickup_address'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Enter pickup location', 'napoleon-shuttle-booking' ); ?>" required>
						</div>

						<div class="nsb-field">
							<label for="nsb_dropoff_address"><?php esc_html_e( 'Drop-off Address', 'napoleon-shuttle-booking' ); ?> <span class="nsb-required">*</span></label>
							<input type="text" id="nsb_dropoff_address" name="nsb_dropoff_address" value="<?php echo esc_attr( $old['nsb_dropoff_address'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Enter drop-off location', 'napoleon-shuttle-booking' ); ?>" required>
						</div>
					</div>

					<div class="nsb-field-row nsb-single-row">
						<div class="nsb-field">
							<label for="nsb_passenger_count"><?php esc_html_e( 'Seats / Passengers', 'napoleon-shuttle-booking' ); ?> <span class="nsb-required">*</span></label>
							<input type="number" id="nsb_passenger_count" name="nsb_passenger_count" value="<?php echo esc_attr( $old['nsb_passenger_count'] ?? '1' ); ?>" min="1" max="99" required>
							<p class="description nsb-seat-helper" id="nsb-seat-helper"><?php esc_html_e( 'Each passenger counts as one seat.', 'napoleon-shuttle-booking' ); ?></p>
						</div>
					</div>
				</div>

				<div class="nsb-accordion">
					<details>
						<summary><?php esc_html_e( 'Airport details / special requests', 'napoleon-shuttle-booking' ); ?> <span><?php esc_html_e( 'Optional', 'napoleon-shuttle-booking' ); ?></span></summary>

						<div class="nsb-field-row nsb-optional-fields">
							<div class="nsb-field">
								<label for="nsb_flight_number"><?php esc_html_e( 'Flight Number', 'napoleon-shuttle-booking' ); ?></label>
								<input type="text" id="nsb_flight_number" name="nsb_flight_number" value="<?php echo esc_attr( $old['nsb_flight_number'] ?? '' ); ?>" placeholder="e.g. EK512">
							</div>

							<div class="nsb-field">
								<label for="nsb_airline_name"><?php esc_html_e( 'Airline Name', 'napoleon-shuttle-booking' ); ?></label>
								<input type="text" id="nsb_airline_name" name="nsb_airline_name" value="<?php echo esc_attr( $old['nsb_airline_name'] ?? '' ); ?>" placeholder="e.g. Emirates">
							</div>

							<div class="nsb-field nsb-field-sm">
								<label for="nsb_luggage_count"><?php esc_html_e( 'Luggage Pieces', 'napoleon-shuttle-booking' ); ?></label>
								<input type="number" id="nsb_luggage_count" name="nsb_luggage_count" value="<?php echo esc_attr( $old['nsb_luggage_count'] ?? '' ); ?>" min="0" max="99">
							</div>
						</div>

						<div class="nsb-field">
							<label for="nsb_notes"><?php esc_html_e( 'Notes / Special Requests', 'napoleon-shuttle-booking' ); ?></label>
							<textarea id="nsb_notes" name="nsb_notes" rows="3" placeholder="<?php esc_attr_e( 'Any special requirements...', 'napoleon-shuttle-booking' ); ?>"><?php echo esc_textarea( $old['nsb_notes'] ?? '' ); ?></textarea>
						</div>
					</details>
				</div>

				<div class="nsb-payment-inline" id="nsb-payment-section">
					<div class="nsb-section-heading nsb-payment-heading">
						<span><?php esc_html_e( '04', 'napoleon-shuttle-booking' ); ?></span>
						<h4><?php esc_html_e( 'Payment choice', 'napoleon-shuttle-booking' ); ?></h4>
					</div>
					<div class="nsb-payment-options" id="nsb-payment-options">
						<label class="nsb-payment-option" id="nsb-pay-full-label">
							<input type="radio" name="nsb_payment_type" value="full" id="nsb-pay-full" <?php checked( ( $old['nsb_payment_type'] ?? 'full' ), 'full' ); ?>>
							<span><?php esc_html_e( 'Pay Full Amount', 'napoleon-shuttle-booking' ); ?></span>
						</label>

						<label class="nsb-payment-option" id="nsb-pay-deposit-label" style="display:none;">
							<input type="radio" name="nsb_payment_type" value="deposit" id="nsb-pay-deposit" <?php checked( ( $old['nsb_payment_type'] ?? '' ), 'deposit' ); ?>>
							<span><?php esc_html_e( 'Pay Deposit', 'napoleon-shuttle-booking' ); ?></span>
						</label>
					</div>
				</div>

				<div class="nsb-checkout-note">
					<strong><?php esc_html_e( 'Next:', 'napoleon-shuttle-booking' ); ?></strong>
					<?php esc_html_e( 'Continue to WooCommerce checkout to enter contact information and complete payment securely.', 'napoleon-shuttle-booking' ); ?>
				</div>

				<div class="nsb-submit-row">
					<button type="submit" class="nsb-submit-btn" id="nsb-submit-btn">
						<?php esc_html_e( 'Continue to Checkout', 'napoleon-shuttle-booking' ); ?>
					</button>
				</div>
			</div>
		</form>

		<aside class="nsb-summary-card" aria-live="polite">
			<img class="nsb-summary-image" id="nsb-summary-image" src="<?php echo $preview_image; ?>" data-default-src="<?php echo $default_image; ?>" alt="<?php esc_attr_e( 'Premium shuttle booking preview', 'napoleon-shuttle-booking' ); ?>">

			<div class="nsb-summary-content">
				<div class="nsb-summary-heading">
					<div>
						<span class="nsb-eyebrow"><?php esc_html_e( 'Selected Package', 'napoleon-shuttle-booking' ); ?></span>
						<h3 id="nsb-summary-package-name"><?php echo esc_html( $preview_name ); ?></h3>
					</div>
					<strong id="nsb-summary-base-price"><?php echo esc_html( $currency . number_format( $preview_price, 2 ) ); ?></strong>
				</div>

				<p id="nsb-summary-description"><?php echo esc_html( $preview_desc ); ?></p>

				<div class="nsb-trip-summary">
					<div class="nsb-trip-summary-item">
						<span><?php esc_html_e( 'Pick-up', 'napoleon-shuttle-booking' ); ?></span>
						<strong id="nsb-summary-pickup-date">—</strong>
						<small id="nsb-summary-pickup-time">—</small>
					</div>
					<div class="nsb-trip-summary-item">
						<span><?php esc_html_e( 'Drop-off / Return', 'napoleon-shuttle-booking' ); ?></span>
						<strong id="nsb-summary-return-date">—</strong>
						<small id="nsb-summary-return-time">Optional</small>
					</div>
				</div>

				<div class="nsb-price-summary" id="nsb-price-summary" style="display:none;">
					<div class="nsb-price-row">
						<span><?php esc_html_e( 'Package Price', 'napoleon-shuttle-booking' ); ?></span>
						<strong id="nsb-display-base-price">—</strong>
					</div>
					<div class="nsb-price-row nsb-price-due">
						<span><?php esc_html_e( 'Due at Checkout', 'napoleon-shuttle-booking' ); ?></span>
						<strong id="nsb-display-due-now">—</strong>
					</div>
					<div class="nsb-price-row nsb-price-remaining" id="nsb-remaining-row">
						<span><?php esc_html_e( 'Remaining Balance', 'napoleon-shuttle-booking' ); ?></span>
						<strong id="nsb-display-remaining">—</strong>
					</div>
				</div>
			</div>
		</aside>
	</div>


	<div class="nsb-calendar-modal" id="nsb-calendar-modal" aria-hidden="true">
		<div class="nsb-calendar-backdrop" data-nsb-calendar-close></div>
		<div class="nsb-calendar-panel" role="dialog" aria-modal="true" aria-labelledby="nsb-calendar-title">
			<div class="nsb-calendar-top">
				<div class="nsb-calendar-selected">
					<span class="nsb-cal-chip"><span aria-hidden="true">▣</span><strong id="nsb-modal-date-label"><?php esc_html_e( 'Select date', 'napoleon-shuttle-booking' ); ?></strong><button type="button" id="nsb-clear-date" aria-label="Clear date">×</button></span>
					<span class="nsb-cal-chip"><span aria-hidden="true">◷</span><strong id="nsb-modal-time-label"><?php esc_html_e( '00:00', 'napoleon-shuttle-booking' ); ?></strong><button type="button" id="nsb-clear-time" aria-label="Clear time">×</button></span>
				</div>
			</div>

			<div class="nsb-calendar-body">
				<div class="nsb-calendar-left">
					<div class="nsb-calendar-nav">
						<button type="button" id="nsb-cal-prev" aria-label="Previous month">←</button>
						<h3 id="nsb-calendar-title">January 2025</h3>
						<button type="button" id="nsb-cal-next" aria-label="Next month">→</button>
					</div>
					<div class="nsb-weekdays" aria-hidden="true"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
					<div class="nsb-calendar-grid" id="nsb-calendar-grid"></div>
				</div>

				<div class="nsb-calendar-times">
					<h4><?php esc_html_e( 'Popular Times', 'napoleon-shuttle-booking' ); ?></h4>
					<div id="nsb-time-slot-list" class="nsb-time-slot-list"></div>
				</div>
			</div>

			<div class="nsb-calendar-actions">
				<button type="button" class="nsb-cal-cancel" data-nsb-calendar-close><?php esc_html_e( 'Cancel', 'napoleon-shuttle-booking' ); ?></button>
				<button type="button" class="nsb-cal-apply" id="nsb-cal-apply" disabled><?php esc_html_e( 'Apply', 'napoleon-shuttle-booking' ); ?></button>
			</div>
		</div>
	</div>
</div>
