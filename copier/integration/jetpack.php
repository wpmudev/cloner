<?php

add_filter( 'wpmudev_copier_exclude_settings', 'cloner_jetpack_exclude_settings' );
if ( ! function_exists( 'cloner_jetpack_exclude_settings' ) ) {
	function cloner_jetpack_exclude_settings( $exclude_settings ) {
		$exclude_settings[] = 'jetpack_private_options';
		return $exclude_settings;
	}
}
