<?php

/** WORDPRESS HTTPS **/
if ( ! function_exists( 'copier_set_https_settings' ) ) {

	function copier_set_https_settings( $source_blog_id ) {
		if ( ! function_exists( 'is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		
		if ( is_plugin_active( 'wordpress-https/wordpress-https.php' ) )
			update_option( 'wordpress-https_ssl_host', get_site_url( get_current_blog_id(), '', 'https' ) );

	}
	add_action( 'wpmudev_copier-copy-options', 'copier_set_https_settings' );
}