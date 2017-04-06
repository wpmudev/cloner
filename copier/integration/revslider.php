<?php


/**
 * Fixes a fatal error in revSlider when its tables are cloned
 *
 * @param $source_blog_id
 */
function wpmudev_copier_revslider_options( $source_blog_id ) {
	if ( ! is_plugin_active( 'revslider/revslider.php' ) ) {
		return;
	}

	$table_version = get_option( 'revslider_table_version' );
	if ( ! $table_version || ( $table_version && version_compare( $table_version, '1.0.6', '<=' ) ) ) {
		update_option( 'revslider_table_version', '1.0.0' );
	}
}
add_action( 'wpmudev_copier-copy-options', 'wpmudev_copier_revslider_options' );