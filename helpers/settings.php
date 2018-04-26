<?php

/**
 * Helper functions that manages the plugin settings
 */

function wpmudev_cloner_get_settings() {
	$defaults = wpmudev_cloner_get_default_settings();
	$settings = get_site_option( 'wpmudev_cloner_settings' );

	if ( ! $settings )
		$settings = array();

	return apply_filters( 'wpmudev_cloner_settings', wp_parse_args( $settings, $defaults ) );
}

function wpmudev_cloner_update_settings( $new_settings ) {
	$settings = wpmudev_cloner_get_settings();

	$settings = wp_parse_args( $new_settings, $settings );

	$to_copy = $settings['to_copy'];
	// the order is important!
	usort( $to_copy, 'wpmudev_cloner_order_settings_array' );
	$settings['to_copy'] = $to_copy;

	update_site_option( 'wpmudev_cloner_settings', $settings );
}

function wpmudev_cloner_order_settings_array( $a, $b ) {
	$keys_order = array_keys( wpmudev_cloner_get_settings_labels() );

	$a_pos = array_search( $a, $keys_order );
	$b_pos = array_search( $b, $keys_order );

	if ( $a_pos === $b_pos )
		return 0;

	return ( $a_pos < $b_pos ) ? -1 : 1;
	
}



function wpmudev_cloner_get_default_settings() {
	return array( 
		'to_copy' => array(
			'settings',
			'widgets',
			'posts',
			'pages',
			'cpts',
			'terms',
			'menus',
			'users',
			'comments',
			'attachment',
			'tables'
		),
		'to_replace' => array() // Let member decide if he needs to replace urls
	);
}

function wpmudev_cloner_get_settings_labels() {
	return array(
		'settings' => __( 'Settings', WPMUDEV_CLONER_LANG_DOMAIN ),
        'posts' => __( 'Posts', WPMUDEV_CLONER_LANG_DOMAIN ),
        'pages' => __( 'Pages', WPMUDEV_CLONER_LANG_DOMAIN ),
        'cpts' => __( 'Custom Post Types', WPMUDEV_CLONER_LANG_DOMAIN ),
        'terms' => __( 'Terms', WPMUDEV_CLONER_LANG_DOMAIN ),
        'menus' => __( 'Menus', WPMUDEV_CLONER_LANG_DOMAIN ),
        'users' => __( 'Users', WPMUDEV_CLONER_LANG_DOMAIN ),
        'comments' => __( 'Comments', WPMUDEV_CLONER_LANG_DOMAIN ),
        'attachment' => __( 'Attachments', WPMUDEV_CLONER_LANG_DOMAIN ),
        'tables' => __( 'Custom tables', WPMUDEV_CLONER_LANG_DOMAIN )
	);
}