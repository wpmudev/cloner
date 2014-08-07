<div class="wrap">

	<?php if ( ! empty( $errors ) ): ?>
		<?php foreach ( $errors as $error ): ?>
			<div class="error"><p><?php echo $error['message']; ?></p></div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( $updated ): ?>
		<div class="updated"><p><?php _e( 'Settings updated', WPMUDEV_CLONER_LANG_DOMAIN ); ?></p></div>
	<?php endif; ?>

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<form method="post" action="">
		<h3>What to copy</h3>
		<p>Please, select those options that you don't want to clone.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label>What to copy</label></th>
				<td>
					<?php foreach ( $to_copy_labels as $slug => $label ): ?>
						<label for="copy_<?php echo $slug; ?>">
							<input type="checkbox" id="copy_<?php echo $slug; ?>" name="to_copy[<?php echo esc_attr( $slug ); ?>]" <?php checked( in_array( $slug, $to_copy ) ); ?> />
							<?php echo $label; ?>
						</label><br/>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'wpmudev_cloner_settings' ); ?>
		<?php submit_button(); ?>
	</form>

</div>
