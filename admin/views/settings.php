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

	<form method="post" id="cloner-settings-form" action="">
		<h3><?php _e( 'Getting started:', WPMUDEV_CLONER_LANG_DOMAIN ); ?></h3>
		<div class="cloner-settings-image-wrap">
			<img src="<?php echo WPMUDEV_CLONER_ASSETS_URL . '/images/step_1.jpg'; ?>">
			<p class="description"><?php printf( __( 'Navigate to <a href="%s">Network Admin &raquo; Sites</a>', WPMUDEV_CLONER_LANG_DOMAIN ), network_admin_url( 'sites.php' ) ); ?></p>
		</div>
		<div class="cloner-settings-image-wrap">
			<img src="<?php echo WPMUDEV_CLONER_ASSETS_URL . '/images/step_2.jpg'; ?>">
			<p class="description"><?php _e( 'Hover over any site & click \'Clone\'', WPMUDEV_CLONER_LANG_DOMAIN ); ?></p>
		</div>
		<div class="clear"></div>
		
		<h3><?php _e( 'Select content you want to be copied:', WPMUDEV_CLONER_LANG_DOMAIN ); ?></h3>
		<ul id="cloner-content-list" class="cloner-list">
			<?php $item_no = 1; ?>
			<?php foreach ( $to_copy_labels as $slug => $label ): ?>
				<li>
					<label for="copy_<?php echo $slug; ?>">
						<input type="checkbox" id="copy_<?php echo $slug; ?>" name="to_copy[<?php echo esc_attr( $slug ); ?>]" <?php checked( in_array( $slug, $to_copy ) ); ?> />
						<?php echo $label; ?>
					</label>
				</li>
				<?php if ( ( $item_no % 3 ) == 0 ): ?>
					<div class="clear"></div>
				<?php endif; ?>

				<?php $item_no++; ?>

			<?php endforeach; ?>
		</ul>
		<div class="clear"></div>

		<hr />

		<h3>
			<?php _e( 'Replace urls and images', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
		</h3>

		<ul id="cloner-replace-list" class="cloner-list">
			
			<li>
				<label>
					<input type="checkbox" id="to_replace_links" name="to_replace[href]" <?php checked( in_array( 'href', $to_replace ) ); ?> />
					<?php _e( 'Replace links', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
				</label>
			</li>
			
			<li>
				<label>
					<input type="checkbox" id="to_replace_images" name="to_replace[src]" <?php checked( in_array( 'src', $to_replace ) ); ?> />
					<?php _e( 'Replace images', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
				</label>
			</li>

		</ul>

		<div class="clear"></div>

		<?php wp_nonce_field( 'wpmudev_cloner_settings' ); ?>
		<?php submit_button(); ?>
	</form>

	<style>
		.cloner-list li {
			display:block;
			float:left;
			width:25%;
			list-style: none;
			font-size: 14px;
			font-weight: bold
		}

		#cloner-settings-form img {
			max-width:100%;
		}

		#cloner-settings-form .description {
			font-size:14px;
			font-style: normal;
		}

		.cloner-settings-image-wrap {
			float:left;
			margin-right:50px;
		}
	</style>

</div>
