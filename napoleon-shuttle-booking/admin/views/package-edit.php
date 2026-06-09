<?php
/**
 * Admin view: Package Add / Edit Form
 *
 * Variable $package is either an associative array (edit) or null (new).
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit = ! empty( $package );
$pkg_id  = $is_edit ? (int) $package['id'] : 0;

// Helpers to pre-fill form fields with old POST data (when validation fails) or existing record.
$get = function( string $key, mixed $default = '' ) use ( $package ): mixed {
	// POST data takes priority (e.g. after failed validation redirect — not needed here since we redirect).
	// If editing, fall back to DB values.
	if ( $package && array_key_exists( $key, $package ) ) {
		return $package[ $key ];
	}
	return $default;
};

$page_title = $is_edit
	/* translators: %s: Package name */
	? sprintf( __( 'Edit Package: %s', 'napoleon-shuttle-booking' ), esc_html( $package['name'] ) )
	: __( 'Add New Package', 'napoleon-shuttle-booking' );
?>
<div class="wrap nsb-wrap">

	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>"></span>
		<?php echo esc_html( $page_title ); ?>
	</h1>
	<a href="<?php echo esc_url( nsb_admin_url( 'nsb-packages' ) ); ?>" class="page-title-action">
		&larr; <?php esc_html_e( 'Back to Packages', 'napoleon-shuttle-booking' ); ?>
	</a>
	<hr class="wp-header-end">

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="nsb-package-form">

		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="package_id" value="<?php echo esc_attr( $pkg_id ); ?>">
			<input type="hidden" name="nsb_action" value="update_package">
			<?php wp_nonce_field( 'nsb_update_package', 'nsb_nonce' ); ?>
		<?php else : ?>
			<input type="hidden" name="nsb_action" value="create_package">
			<?php wp_nonce_field( 'nsb_create_package', 'nsb_nonce' ); ?>
		<?php endif; ?>

		<div class="nsb-form-layout">

			<!-- Main column -->
			<div class="nsb-form-main">

				<div class="nsb-card">
					<h2><?php esc_html_e( 'Package Details', 'napoleon-shuttle-booking' ); ?></h2>

					<table class="form-table nsb-form-table">
						<tbody>

							<tr>
								<th scope="row">
									<label for="nsb_name"><?php esc_html_e( 'Package Name', 'napoleon-shuttle-booking' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<input type="text"
									       id="nsb_name"
									       name="name"
									       class="regular-text"
									       value="<?php echo esc_attr( $get( 'name' ) ); ?>"
									       required>
									<p class="description"><?php esc_html_e( 'The display name customers will see for this package.', 'napoleon-shuttle-booking' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="nsb_description"><?php esc_html_e( 'Description', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<textarea id="nsb_description"
									          name="description"
									          rows="4"
									          class="large-text"><?php echo esc_textarea( $get( 'description' ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Optional description of the package services.', 'napoleon-shuttle-booking' ); ?></p>
								</td>
							</tr>


							<tr>
								<th scope="row">
									<label><?php esc_html_e( 'Package Image', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<?php
									$package_image_id  = absint( $get( 'package_image_id', 0 ) );
									$package_image_url = $package_image_id ? wp_get_attachment_image_url( $package_image_id, 'medium' ) : '';
									?>
									<div class="nsb-image-field" id="nsb-package-image-field">
										<input type="hidden" id="nsb_package_image_id" name="package_image_id" value="<?php echo esc_attr( $package_image_id ); ?>">
										<div class="nsb-image-preview <?php echo $package_image_url ? 'has-image' : ''; ?>" id="nsb-package-image-preview">
											<?php if ( $package_image_url ) : ?>
												<img src="<?php echo esc_url( $package_image_url ); ?>" alt="<?php esc_attr_e( 'Package image preview', 'napoleon-shuttle-booking' ); ?>">
											<?php else : ?>
												<span class="dashicons dashicons-format-image"></span>
												<em><?php esc_html_e( 'No image selected', 'napoleon-shuttle-booking' ); ?></em>
											<?php endif; ?>
										</div>
										<p class="nsb-image-actions">
											<button type="button" class="button" id="nsb-select-package-image"><?php esc_html_e( 'Select Image', 'napoleon-shuttle-booking' ); ?></button>
											<button type="button" class="button" id="nsb-remove-package-image" <?php echo $package_image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'napoleon-shuttle-booking' ); ?></button>
										</p>
									</div>
									<p class="description"><?php esc_html_e( 'This image appears on the right-side booking preview when this package is selected. If empty, the default shuttle image is used.', 'napoleon-shuttle-booking' ); ?></p>
								</td>
							</tr>

						</tbody>
					</table>
				</div><!-- .nsb-card -->

				<div class="nsb-card">
					<h2><?php esc_html_e( 'Pricing & Deposit', 'napoleon-shuttle-booking' ); ?></h2>

					<table class="form-table nsb-form-table">
						<tbody>

							<tr>
								<th scope="row">
									<label for="nsb_base_price"><?php esc_html_e( 'Base Price', 'napoleon-shuttle-booking' ); ?> <span class="required">*</span></label>
								</th>
								<td>
									<span class="nsb-input-prefix"><?php echo esc_html( NSB_Settings::get( 'currency_symbol', '$' ) ); ?></span>
									<input type="number"
									       id="nsb_base_price"
									       name="base_price"
									       class="small-text"
									       value="<?php echo esc_attr( $get( 'base_price', '0.00' ) ); ?>"
									       min="0"
									       step="0.01"
									       required>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="nsb_deposit_type"><?php esc_html_e( 'Deposit Type', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<select id="nsb_deposit_type" name="deposit_type" class="nsb-deposit-type-select">
										<option value="none" <?php selected( $get( 'deposit_type', 'none' ), 'none' ); ?>>
											<?php esc_html_e( 'No Deposit (full payment required)', 'napoleon-shuttle-booking' ); ?>
										</option>
										<option value="fixed" <?php selected( $get( 'deposit_type', 'none' ), 'fixed' ); ?>>
											<?php esc_html_e( 'Fixed Amount', 'napoleon-shuttle-booking' ); ?>
										</option>
										<option value="percentage" <?php selected( $get( 'deposit_type', 'none' ), 'percentage' ); ?>>
											<?php esc_html_e( 'Percentage of Base Price', 'napoleon-shuttle-booking' ); ?>
										</option>
									</select>
								</td>
							</tr>

							<tr id="nsb-deposit-amount-row" <?php echo 'none' === $get( 'deposit_type', 'none' ) ? 'style="display:none;"' : ''; ?>>
								<th scope="row">
									<label for="nsb_deposit_amount">
										<?php esc_html_e( 'Deposit Amount', 'napoleon-shuttle-booking' ); ?>
										<span id="nsb-deposit-suffix">
											<?php echo 'percentage' === $get( 'deposit_type' ) ? '(%)' : esc_html( '(' . NSB_Settings::get( 'currency_symbol', '$' ) . ')' ); ?>
										</span>
									</label>
								</th>
								<td>
									<input type="number"
									       id="nsb_deposit_amount"
									       name="deposit_amount"
									       class="small-text"
									       value="<?php echo esc_attr( $get( 'deposit_amount', '0.00' ) ); ?>"
									       min="0"
									       step="0.01">
									<p class="description nsb-deposit-hint">
										<?php esc_html_e( 'For percentage type, enter a value between 0 and 100. For fixed, must not exceed base price.', 'napoleon-shuttle-booking' ); ?>
									</p>
								</td>
							</tr>

						</tbody>
					</table>
				</div><!-- .nsb-card -->

			</div><!-- .nsb-form-main -->

			<!-- Sidebar column -->
			<div class="nsb-form-sidebar">

				<div class="nsb-card">
					<h2><?php esc_html_e( 'Publish', 'napoleon-shuttle-booking' ); ?></h2>
					<label class="nsb-toggle-label">
						<input type="checkbox"
						       id="nsb_active"
						       name="active"
						       value="1"
						       <?php checked( (bool) $get( 'active', 1 ) ); ?>>
						<?php esc_html_e( 'Active (visible to booking form)', 'napoleon-shuttle-booking' ); ?>
					</label>
					<div class="nsb-publish-actions">
						<button type="submit" class="button button-primary button-large">
							<?php echo $is_edit ? esc_html__( 'Update Package', 'napoleon-shuttle-booking' ) : esc_html__( 'Create Package', 'napoleon-shuttle-booking' ); ?>
						</button>
						<a href="<?php echo esc_url( nsb_admin_url( 'nsb-packages' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'napoleon-shuttle-booking' ); ?>
						</a>
					</div>
				</div>

				<div class="nsb-card">
					<h2><?php esc_html_e( 'Service Options', 'napoleon-shuttle-booking' ); ?></h2>
					<table class="form-table nsb-form-table">
						<tbody>

							<tr>
								<th scope="row">
									<label for="nsb_duration"><?php esc_html_e( 'Duration (minutes)', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<input type="number"
									       id="nsb_duration"
									       name="duration_minutes"
									       class="small-text"
									       value="<?php echo esc_attr( $get( 'duration_minutes', 60 ) ); ?>"
									       min="1">
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="nsb_max_pax"><?php esc_html_e( 'Total Seat Capacity', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<input type="number"
									       id="nsb_max_pax"
									       name="max_passengers"
									       class="small-text"
									       value="<?php echo esc_attr( $get( 'max_passengers', '' ) ); ?>"
									       min="1"
									       placeholder="<?php esc_attr_e( 'Unlimited seats', 'napoleon-shuttle-booking' ); ?>">
									<p class="description"><?php esc_html_e( 'Total seats available for this package per pickup time slot. Leave empty for unlimited seats.', 'napoleon-shuttle-booking' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="nsb_sort_order"><?php esc_html_e( 'Sort Order', 'napoleon-shuttle-booking' ); ?></label>
								</th>
								<td>
									<input type="number"
									       id="nsb_sort_order"
									       name="sort_order"
									       class="small-text"
									       value="<?php echo esc_attr( $get( 'sort_order', 0 ) ); ?>">
									<p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'napoleon-shuttle-booking' ); ?></p>
								</td>
							</tr>

						</tbody>
					</table>
				</div><!-- .nsb-card -->

				<?php if ( $is_edit ) : ?>
				<div class="nsb-card nsb-card--meta">
					<h2><?php esc_html_e( 'Record Info', 'napoleon-shuttle-booking' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Package ID:', 'napoleon-shuttle-booking' ); ?></strong>
						<?php echo esc_html( $pkg_id ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Slug:', 'napoleon-shuttle-booking' ); ?></strong>
						<code><?php echo esc_html( $package['slug'] ?? '' ); ?></code>
					</p>
					<p>
						<strong><?php esc_html_e( 'Created:', 'napoleon-shuttle-booking' ); ?></strong>
						<?php echo esc_html( $package['created_at'] ?? '—' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Updated:', 'napoleon-shuttle-booking' ); ?></strong>
						<?php echo esc_html( $package['updated_at'] ?? '—' ); ?>
					</p>
				</div>
				<?php endif; ?>

			</div><!-- .nsb-form-sidebar -->

		</div><!-- .nsb-form-layout -->

	</form>

</div><!-- .wrap -->
