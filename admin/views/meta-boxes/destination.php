<div class="cloner-clone-option" id="cloner-create-wrap">
	<p>
		<label for="cloner-create">
			<input type="radio" name="cloner-clone-selection" value="create" id="cloner-create" class="clone_clone_option"/> 
			<?php _e( 'Create a new Site', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
		</label>
	</p>
	<?php if ( is_subdomain_install() ): ?>
		<input name="blog_create" type="text" class="regular-text" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Type your site name here...', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?>"/><br/>
		<span class="no-break">.<?php echo preg_replace( '|^www\.|', '', $current_site->domain ); ?></span>
	<?php else: ?>
		<?php echo $current_site->domain . $current_site->path ?><br/>
		<input name="blog_create" class="regular-text" type="text" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Type your site name here...', WPMUDEV_CLONER_LANG_DOMAIN ) ); ?>"/>
	<?php endif; ?>
	<p class="description"><?php _e( 'Only lowercase letters (a-z) and numbers are allowed.' ); ?></p>


</div>

<div class="cloner-clone-option" id="cloner-replace-wrap">
	<p>
		<label for="cloner-replace">
			<input type="radio" name="cloner-clone-selection" value="replace" id="cloner-replace" class="clone_clone_option"/> 
			<?php _e( 'Replace existing Site', WPMUDEV_CLONER_LANG_DOMAIN ); ?>
		</label>
	</p>
	<?php if ( ! is_subdomain_install() ): ?>
		<br/>
	<?php endif; ?>
	<input name="blog_replace_autocomplete" type="text" class="regular-text ui-autocomplete-input" title="<?php esc_attr_e( 'Domain' ) ?>" placeholder="<?php echo esc_attr( __( 'Start writing to search an existing site' ) ); ?>"/><br/>
	<span class="description"><?php _e( 'Leave it blank to clone to the main site', WPMUDEV_CLONER_LANG_DOMAIN ); ?></span>
	<input name="blog_replace" type="hidden" value=""/>
</div>
<div class="clear"></div>

<?php do_action( 'wpmudev_cloner_destination_meta_box' ); ?>