<?php

/**
 * Helper functions that manages the plugin settings
 */

function wpmudev_cloner_get_settings() {
	$defaults = wpmudev_cloner_get_default_settings();
	$settings = get_site_option( 'wpmudev_cloner_settings' );

	if ( ! $settings )
		$settings = array();

	return wp_parse_args( $settings, $defaults );
}

function wpmudev_cloner_update_settings( $new_settings ) {
	$settings = wpmudev_cloner_get_settings();
	update_site_option( 'wpmudev_cloner_settings', wp_parse_args( $new_settings, $settings ) );
}

function wpmudev_cloner_get_default_settings() {
	return array( 
		'to_copy' => array(
			'settings',
			'posts',
			'pages',
			'terms',
			'menus',
			'users',
			'comments',
			'attachment',
			'tables'
		)
	);
}

function wpmudev_cloner_get_settings_labels() {
	return array(
		'settings' => __( 'Settings', WPMUDEV_CLONER_LANG_DOMAIN ),
        'posts' => __( 'Posts', WPMUDEV_CLONER_LANG_DOMAIN ),
        'pages' => __( 'Pages', WPMUDEV_CLONER_LANG_DOMAIN ),
        'terms' => __( 'Terms', WPMUDEV_CLONER_LANG_DOMAIN ),
        'menus' => __( 'Menus', WPMUDEV_CLONER_LANG_DOMAIN ),
        'users' => __( 'Users', WPMUDEV_CLONER_LANG_DOMAIN ),
        'comments' => __( 'Comments', WPMUDEV_CLONER_LANG_DOMAIN ),
        'attachment' => __( 'Attachments', WPMUDEV_CLONER_LANG_DOMAIN ),
        'tables' => __( 'Custom tables', WPMUDEV_CLONER_LANG_DOMAIN )
	);
}