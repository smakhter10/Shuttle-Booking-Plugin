<?php
/**
 * Email notifications for the Napoleon Shuttle Booking plugin.
 *
 * Uses WordPress wp_mail() to send customer and admin booking emails.
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NSB_Emails {

	/**
	 * Send customer confirmation email.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public static function send_customer_confirmation( int $booking_id ): bool {
		if ( 'yes' !== NSB_Settings::get( 'enable_customer_confirmation_email', 'yes' ) ) {
			return false;
		}

		$booking = self::get_booking( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		if ( ! empty( $booking['customer_email_sent'] ) && 1 === (int) $booking['customer_email_sent'] ) {
			return true;
		}

		$to = sanitize_email( (string) ( $booking['customer_email'] ?? '' ) );
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}

		$subject = self::interpolate(
			__( 'Thanks for booking - {booking_reference}', 'napoleon-shuttle-booking' ),
			$booking
		);

		$sent = wp_mail( $to, $subject, self::build_customer_email_body( $booking ), self::get_mail_headers() );

		if ( $sent ) {
			self::mark_customer_email_sent( $booking_id );
		}

		return $sent;
	}

	/**
	 * Send admin booking notification email.
	 *
	 * @param int      $booking_id Booking ID.
	 * @param int|null $order_id WooCommerce order ID.
	 * @return bool
	 */
	public static function send_admin_notification( int $booking_id, ?int $order_id = null ): bool {
		if ( 'yes' !== NSB_Settings::get( 'enable_admin_notification_email', 'yes' ) ) {
			return false;
		}

		$booking = self::get_booking( $booking_id );
		if ( ! $booking ) {
			return false;
		}

		if ( ! empty( $booking['admin_email_sent'] ) && 1 === (int) $booking['admin_email_sent'] ) {
			return true;
		}

		$admin_email = sanitize_email( NSB_Settings::get( 'admin_notification_email', get_option( 'admin_email', '' ) ) );
		if ( ! $admin_email || ! is_email( $admin_email ) ) {
			return false;
		}

		$subject = self::interpolate(
			__( 'New shuttle booking received - {booking_reference}', 'napoleon-shuttle-booking' ),
			$booking
		);

		$sent = wp_mail( $admin_email, $subject, self::build_admin_email_body( $booking, $order_id ), self::get_mail_headers() );

		if ( $sent ) {
			self::mark_admin_email_sent( $booking_id );
		}

		return $sent;
	}

	/**
	 * Build customer email body.
	 *
	 * @param array<string,mixed> $booking Booking row.
	 * @return string
	 */
	private static function build_customer_email_body( array $booking ): string {
		$business_name = self::get_business_name();
		$currency      = NSB_Settings::get( 'currency_symbol', '$' );
		$pickup_date   = self::format_date( $booking['booking_date'] ?? '' );
		$pickup_time   = self::format_time( $booking['booking_time'] ?? '' );
		$return_date   = ! empty( $booking['return_date'] ) ? self::format_date( $booking['return_date'] ) : '';
		$return_time   = ! empty( $booking['return_time'] ) ? self::format_time( $booking['return_time'] ) : '';
		$is_deposit    = 'deposit' === (string) ( $booking['payment_type'] ?? '' );
		$amount_paid   = $currency . number_format( (float) ( $booking['amount_due_now'] ?? 0 ), 2 );
		$remaining     = $currency . number_format( (float) ( $booking['remaining_balance'] ?? 0 ), 2 );
		$customer_name = ! empty( $booking['customer_name'] ) ? $booking['customer_name'] : __( 'Customer', 'napoleon-shuttle-booking' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'Booking Confirmation', 'napoleon-shuttle-booking' ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f1ea;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ea;padding:32px 12px;">
	<tr>
		<td align="center">
			<table width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #eadfce;box-shadow:0 14px 40px rgba(47,36,25,0.08);">
				<tr>
					<td style="background:#17120d;padding:30px 36px;text-align:center;">
						<h1 style="margin:0;color:#ffffff;font-size:24px;line-height:1.3;"> <?php esc_html_e( 'Thanks for booking with us', 'napoleon-shuttle-booking' ); ?></h1>
						<p style="margin:8px 0 0;color:#d8c3a2;font-size:14px;"> <?php echo esc_html( $business_name ); ?></p>
					</td>
				</tr>
				<tr>
					<td style="padding:30px 36px 10px;">
						<p style="margin:0 0 12px;font-size:16px;line-height:1.6;">Hi <?php echo esc_html( $customer_name ); ?>,</p>
						<p style="margin:0;font-size:16px;line-height:1.7;color:#4b5563;">
							<?php esc_html_e( 'Thank you for your booking. Your shuttle reservation has been received and confirmed. Please review your pickup details below and make sure you are ready on time.', 'napoleon-shuttle-booking' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 36px;">
						<table width="100%" cellpadding="0" cellspacing="0" style="background:#fbf7f0;border:1px solid #e7d5b8;border-radius:14px;">
							<tr>
								<td style="padding:18px 20px;">
									<p style="margin:0;color:#8b5e24;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:700;"> <?php esc_html_e( 'Booking Reference', 'napoleon-shuttle-booking' ); ?></p>
									<p style="margin:6px 0 0;color:#1f2937;font-size:22px;font-weight:800;letter-spacing:0.04em;"> <?php echo esc_html( $booking['booking_reference'] ?? '' ); ?></p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:0 36px 22px;">
						<h2 style="margin:0 0 12px;color:#17120d;font-size:17px;"> <?php esc_html_e( 'Pickup Details', 'napoleon-shuttle-booking' ); ?></h2>
						<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
							<?php echo self::email_row( __( 'Package', 'napoleon-shuttle-booking' ), (string) ( $booking['package_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Date', 'napoleon-shuttle-booking' ), $pickup_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Time', 'napoleon-shuttle-booking' ), $pickup_time ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Location', 'napoleon-shuttle-booking' ), (string) ( $booking['pickup_address'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Drop-off Location', 'napoleon-shuttle-booking' ), (string) ( $booking['dropoff_address'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Passengers', 'napoleon-shuttle-booking' ), (string) ( $booking['passenger_count'] ?? '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( $return_date ) : ?><?php echo self::email_row( __( 'Return Date', 'napoleon-shuttle-booking' ), $return_date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( $return_time ) : ?><?php echo self::email_row( __( 'Return Time', 'napoleon-shuttle-booking' ), $return_time ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['flight_number'] ) ) : ?><?php echo self::email_row( __( 'Flight Number', 'napoleon-shuttle-booking' ), (string) $booking['flight_number'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['airline_name'] ) ) : ?><?php echo self::email_row( __( 'Airline', 'napoleon-shuttle-booking' ), (string) $booking['airline_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:0 36px 24px;">
						<table width="100%" cellpadding="0" cellspacing="0" style="background:#fff7ed;border-left:5px solid #b9823d;border-radius:12px;">
							<tr>
								<td style="padding:16px 18px;color:#6b3f13;font-size:14px;line-height:1.7;">
									<strong><?php esc_html_e( 'Important:', 'napoleon-shuttle-booking' ); ?></strong>
									<?php esc_html_e( 'Please do not miss your shuttle. Arrive and be ready before your scheduled pickup time. This booking is non-refundable.', 'napoleon-shuttle-booking' ); ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:0 36px 26px;">
						<h2 style="margin:0 0 12px;color:#17120d;font-size:17px;"> <?php esc_html_e( 'Payment Summary', 'napoleon-shuttle-booking' ); ?></h2>
						<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
							<?php echo self::email_row( __( 'Payment Type', 'napoleon-shuttle-booking' ), $is_deposit ? __( 'Deposit', 'napoleon-shuttle-booking' ) : __( 'Full Payment', 'napoleon-shuttle-booking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Amount Paid', 'napoleon-shuttle-booking' ), $amount_paid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( $is_deposit ) : ?><?php echo self::email_row( __( 'Remaining Balance', 'napoleon-shuttle-booking' ), $remaining ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
						</table>
					</td>
				</tr>
				<tr>
					<td style="background:#faf7f1;padding:22px 36px;text-align:center;border-top:1px solid #eadfce;">
						<p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;"> <?php esc_html_e( 'This is an automated booking confirmation email. Please keep it for your records.', 'napoleon-shuttle-booking' ); ?></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build admin email body.
	 *
	 * @param array<string,mixed> $booking Booking row.
	 * @param int|null            $order_id WooCommerce order ID.
	 * @return string
	 */
	private static function build_admin_email_body( array $booking, ?int $order_id = null ): string {
		$currency    = NSB_Settings::get( 'currency_symbol', '$' );
		$amount_paid = $currency . number_format( (float) ( $booking['amount_due_now'] ?? 0 ), 2 );
		$remaining   = $currency . number_format( (float) ( $booking['remaining_balance'] ?? 0 ), 2 );
		$order_id    = $order_id ?: ( ! empty( $booking['wc_order_id'] ) ? (int) $booking['wc_order_id'] : 0 );
		$order_link  = '';

		if ( $order_id > 0 ) {
			$order_link = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		}

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'New Booking Information', 'napoleon-shuttle-booking' ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f1ea;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ea;padding:32px 12px;">
	<tr>
		<td align="center">
			<table width="680" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #eadfce;box-shadow:0 14px 40px rgba(47,36,25,0.08);">
				<tr>
					<td style="background:#17120d;padding:28px 36px;">
						<h1 style="margin:0;color:#ffffff;font-size:22px;line-height:1.3;"> <?php esc_html_e( 'New Booking Information', 'napoleon-shuttle-booking' ); ?></h1>
						<p style="margin:8px 0 0;color:#d8c3a2;font-size:14px;"> <?php echo esc_html( self::get_business_name() ); ?></p>
					</td>
				</tr>
				<tr>
					<td style="padding:24px 36px 10px;">
						<table width="100%" cellpadding="0" cellspacing="0" style="background:#fbf7f0;border:1px solid #e7d5b8;border-radius:14px;">
							<tr>
								<td style="padding:16px 18px;">
									<p style="margin:0;color:#8b5e24;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:700;"> <?php esc_html_e( 'Booking Reference', 'napoleon-shuttle-booking' ); ?></p>
									<p style="margin:6px 0 0;color:#1f2937;font-size:20px;font-weight:800;letter-spacing:0.04em;"> <?php echo esc_html( $booking['booking_reference'] ?? '' ); ?></p>
								</td>
								<?php if ( $order_id > 0 ) : ?>
								<td style="padding:16px 18px;border-left:1px solid #e7d5b8;">
									<p style="margin:0;color:#8b5e24;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:700;"> <?php esc_html_e( 'WooCommerce Order', 'napoleon-shuttle-booking' ); ?></p>
									<p style="margin:6px 0 0;color:#1f2937;font-size:20px;font-weight:800;">#<?php echo esc_html( (string) $order_id ); ?></p>
								</td>
								<?php endif; ?>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:16px 36px 0;">
						<h2 style="margin:0 0 10px;color:#17120d;font-size:17px;"> <?php esc_html_e( 'Customer Details', 'napoleon-shuttle-booking' ); ?></h2>
						<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
							<?php echo self::email_row( __( 'Name', 'napoleon-shuttle-booking' ), (string) ( $booking['customer_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Email', 'napoleon-shuttle-booking' ), (string) ( $booking['customer_email'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Phone', 'napoleon-shuttle-booking' ), (string) ( $booking['customer_phone'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 36px 0;">
						<h2 style="margin:0 0 10px;color:#17120d;font-size:17px;"> <?php esc_html_e( 'Trip Details', 'napoleon-shuttle-booking' ); ?></h2>
						<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
							<?php echo self::email_row( __( 'Package', 'napoleon-shuttle-booking' ), (string) ( $booking['package_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Date', 'napoleon-shuttle-booking' ), self::format_date( $booking['booking_date'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Time', 'napoleon-shuttle-booking' ), self::format_time( $booking['booking_time'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Pickup Location', 'napoleon-shuttle-booking' ), (string) ( $booking['pickup_address'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Drop-off Location', 'napoleon-shuttle-booking' ), (string) ( $booking['dropoff_address'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Passengers', 'napoleon-shuttle-booking' ), (string) ( $booking['passenger_count'] ?? '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( ! empty( $booking['return_date'] ) ) : ?><?php echo self::email_row( __( 'Return Date', 'napoleon-shuttle-booking' ), self::format_date( $booking['return_date'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['return_time'] ) ) : ?><?php echo self::email_row( __( 'Return Time', 'napoleon-shuttle-booking' ), self::format_time( $booking['return_time'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['notes'] ) ) : ?><?php echo self::email_row( __( 'Notes', 'napoleon-shuttle-booking' ), (string) $booking['notes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['flight_number'] ) ) : ?><?php echo self::email_row( __( 'Flight Number', 'napoleon-shuttle-booking' ), (string) $booking['flight_number'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['airline_name'] ) ) : ?><?php echo self::email_row( __( 'Airline', 'napoleon-shuttle-booking' ), (string) $booking['airline_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
							<?php if ( ! empty( $booking['luggage_count'] ) ) : ?><?php echo self::email_row( __( 'Luggage Count', 'napoleon-shuttle-booking' ), (string) $booking['luggage_count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 36px 24px;">
						<h2 style="margin:0 0 10px;color:#17120d;font-size:17px;"> <?php esc_html_e( 'Payment Details', 'napoleon-shuttle-booking' ); ?></h2>
						<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
							<?php echo self::email_row( __( 'Payment Type', 'napoleon-shuttle-booking' ), 'deposit' === (string) ( $booking['payment_type'] ?? '' ) ? __( 'Deposit', 'napoleon-shuttle-booking' ) : __( 'Full Payment', 'napoleon-shuttle-booking' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Amount Paid', 'napoleon-shuttle-booking' ), $amount_paid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Remaining Balance', 'napoleon-shuttle-booking' ), $remaining ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Payment Status', 'napoleon-shuttle-booking' ), ucfirst( str_replace( '_', ' ', (string) ( $booking['payment_status'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo self::email_row( __( 'Booking Status', 'napoleon-shuttle-booking' ), ucfirst( str_replace( '_', ' ', (string) ( $booking['booking_status'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</table>
						<?php if ( $order_link ) : ?>
							<p style="margin:18px 0 0;"><a href="<?php echo esc_url( $order_link ); ?>" style="display:inline-block;background:#17120d;color:#ffffff;text-decoration:none;border-radius:10px;padding:11px 16px;font-weight:700;"> <?php esc_html_e( 'View WooCommerce Order', 'napoleon-shuttle-booking' ); ?></a></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Email row helper.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return string
	 */
	private static function email_row( string $label, string $value ): string {
		if ( '' === trim( $value ) ) {
			$value = '—';
		}

		return sprintf(
			'<tr><td style="padding:7px 0;color:#7c6f61;width:180px;vertical-align:top;">%s</td><td style="padding:7px 0;color:#1f2937;font-weight:700;">%s</td></tr>',
			esc_html( $label ),
			nl2br( esc_html( $value ) )
		);
	}

	/**
	 * Mark customer email as sent.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function mark_customer_email_sent( int $booking_id ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'nsb_bookings',
			array(
				'customer_email_sent' => 1,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark admin email as sent.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private static function mark_admin_email_sent( int $booking_id ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'nsb_bookings',
			array(
				'admin_email_sent' => 1,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get booking row.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array<string,mixed>|null
	 */
	private static function get_booking( int $booking_id ): ?array {
		return NSB_Booking_Handler::get_by_id( $booking_id );
	}

	/**
	 * Business name.
	 *
	 * @return string
	 */
	private static function get_business_name(): string {
		$name = NSB_Settings::get( 'business_name', '' );
		return $name ? $name : ( get_bloginfo( 'name' ) ?: __( 'Shuttle Booking', 'napoleon-shuttle-booking' ) );
	}

	/**
	 * Mail headers.
	 *
	 * @return array<int,string>
	 */
	private static function get_mail_headers(): array {
		$business_name = self::get_business_name();
		$from_email    = sanitize_email( get_option( 'admin_email' ) );
		$headers       = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $from_email && is_email( $from_email ) ) {
			$headers[] = 'From: ' . $business_name . ' <' . $from_email . '>';
		}

		return $headers;
	}

	/**
	 * Format date.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private static function format_date( string $date ): string {
		if ( empty( $date ) ) {
			return '';
		}
		$timestamp = strtotime( $date );
		return $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : $date;
	}

	/**
	 * Format time.
	 *
	 * @param string $time Time.
	 * @return string
	 */
	private static function format_time( string $time ): string {
		if ( empty( $time ) ) {
			return '';
		}
		$timestamp = strtotime( $time );
		return $timestamp ? date_i18n( get_option( 'time_format' ), $timestamp ) : $time;
	}

	/**
	 * Interpolate subject placeholders.
	 *
	 * @param string              $template Template.
	 * @param array<string,mixed> $booking Booking.
	 * @return string
	 */
	private static function interpolate( string $template, array $booking ): string {
		return str_replace( '{booking_reference}', (string) ( $booking['booking_reference'] ?? '' ), $template );
	}
}
