<?php

if ( ! function_exists( 'copier_popover_remove_install_setting' ) ) {
	/**
	 * WPMU DEV Pop Up integration
	 * 
	 * @param type 
	 * @return type
	 */
	function copier_popover_remove_install_setting( $settings ) {
		$settings[] = "popover_installed";
		return $settings;
	}
	add_filter( 'wpmudev_copier_exclude_settings', 'copier_popover_remove_install_setting', 10, 1 );
}