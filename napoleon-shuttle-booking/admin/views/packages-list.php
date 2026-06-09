<?php
/**
 * Admin view: Packages List
 *
 * @package NapoleonShuttleBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$packages = NSB_Packages::get_all();
?>
<div class="wrap nsb-wrap">

	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Packages', 'napoleon-shuttle-booking' ); ?>
	</h1>
	<a href="<?php echo esc_url( nsb_admin_url( 'nsb-packages', array( 'action' => 'new' ) ) ); ?>" class="page-title-action">
		<?php esc_html_e( '+ Add New Package', 'napoleon-shuttle-booking' ); ?>
	</a>

	<hr class="wp-header-end">

	<?php if ( empty( $packages ) ) : ?>
		<div class="nsb-empty-state">
			<span class="dashicons dashicons-car" style="font-size:48px;width:48px;height:48px;color:#ccc;"></span>
			<p><?php esc_html_e( 'No packages found. Create your first shuttle package to get started.', 'napoleon-shuttle-booking' ); ?></p>
			<a href="<?php echo esc_url( nsb_admin_url( 'nsb-packages', array( 'action' => 'new' ) ) ); ?>" class="button button-primary button-large">
				<?php esc_html_e( 'Create First Package', 'napoleon-shuttle-booking' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat striped nsb-packages-table">
			<thead>
				<tr>
					<th scope="col" class="col-id"><?php esc_html_e( 'ID', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-image"><?php esc_html_e( 'Image', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-name"><?php esc_html_e( 'Name', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-price"><?php esc_html_e( 'Base Price', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-deposit-type"><?php esc_html_e( 'Deposit Type', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-deposit"><?php esc_html_e( 'Deposit Amount', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-duration"><?php esc_html_e( 'Duration', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-pax"><?php esc_html_e( 'Seats', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-sort"><?php esc_html_e( 'Sort', 'napoleon-shuttle-booking' ); ?></th>
					<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'napoleon-shuttle-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $packages as $pkg ) : ?>
					<?php
					$pkg_id       = (int) $pkg['id'];
					$is_active    = (bool) $pkg['active'];
					$deposit_type = $pkg['deposit_type'];

					// Deposit display.
					if ( 'none' === $deposit_type ) {
						$deposit_display = '—';
					} elseif ( 'percentage' === $deposit_type ) {
						$deposit_display = esc_html( $pkg['deposit_amount'] ) . '%';
					} else {
						$deposit_display = nsb_format_price( $pkg['deposit_amount'] );
					}

					$edit_url   = nsb_admin_url( 'nsb-packages', array( 'action' => 'edit', 'id' => $pkg_id ) );
					$delete_url = wp_nonce_url(
						nsb_admin_url( 'nsb-packages', array( 'nsb_action' => 'delete_package', 'id' => $pkg_id ) ),
						'nsb_delete_package_' . $pkg_id
					);
					$toggle_url = wp_nonce_url(
						nsb_admin_url( 'nsb-packages', array(
							'nsb_action' => 'toggle_package',
							'id'         => $pkg_id,
							'status'     => $is_active ? 'deactivate' : 'activate',
						) ),
						'nsb_toggle_package_' . $pkg_id
					);
					?>
					<tr class="<?php echo $is_active ? '' : 'nsb-inactive-row'; ?>">
						<td><?php echo esc_html( $pkg_id ); ?></td>
						<td>
							<?php
							$image_id  = absint( $pkg['package_image_id'] ?? 0 );
							$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
							?>
							<?php if ( $image_url ) : ?>
								<img class="nsb-package-thumb" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $pkg['name'] ); ?>">
							<?php else : ?>
								<span class="nsb-package-thumb nsb-package-thumb-empty">—</span>
							<?php endif; ?>
						</td>
						<td>
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $pkg['name'] ); ?></a>
							</strong>
							<?php if ( ! empty( $pkg['description'] ) ) : ?>
								<br><small class="nsb-row-desc"><?php echo esc_html( wp_trim_words( $pkg['description'], 12 ) ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( nsb_format_price( $pkg['base_price'] ) ); ?></td>
						<td><?php echo esc_html( nsb_deposit_type_label( $deposit_type ) ); ?></td>
						<td><?php echo esc_html( $deposit_display ); ?></td>
						<td>
							<?php
							/* translators: %d: number of minutes */
							printf( esc_html__( '%d min', 'napoleon-shuttle-booking' ), (int) $pkg['duration_minutes'] );
							?>
						</td>
						<td><?php echo $pkg['max_passengers'] ? esc_html( $pkg['max_passengers'] ) : '∞'; ?></td>
						<td>
							<?php if ( $is_active ) : ?>
								<span class="nsb-badge nsb-badge-success"><?php esc_html_e( 'Active', 'napoleon-shuttle-booking' ); ?></span>
							<?php else : ?>
								<span class="nsb-badge nsb-badge-error"><?php esc_html_e( 'Inactive', 'napoleon-shuttle-booking' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $pkg['sort_order'] ); ?></td>
						<td class="nsb-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'napoleon-shuttle-booking' ); ?>
							</a>
							<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small">
								<?php echo $is_active ? esc_html__( 'Deactivate', 'napoleon-shuttle-booking' ) : esc_html__( 'Activate', 'napoleon-shuttle-booking' ); ?>
							</a>
							<a href="<?php echo esc_url( $delete_url ); ?>"
							   class="button button-small nsb-delete-btn"
							   data-confirm="<?php esc_attr_e( 'Are you sure you want to delete this package? This cannot be undone.', 'napoleon-shuttle-booking' ); ?>">
								<?php esc_html_e( 'Delete', 'napoleon-shuttle-booking' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="nsb-table-footer">
			<?php
			/* translators: %d: total count */
			printf( esc_html__( 'Total: %d package(s)', 'napoleon-shuttle-booking' ), count( $packages ) );
			?>
		</p>
	<?php endif; ?>

</div><!-- .wrap -->
