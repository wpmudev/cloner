<?php

if ( ! function_exists( 'copier_exclude_epanel_temp_path' ) ) {
	/**
	 * Exclude EPanel temporary folder paths. (EPanel is a settings panel made by Elegant Themes)
	 */
	function copier_exclude_epanel_temp_path ( $exclude ) {
		$exclude[] = 'et_images_temp_folder';
		return $exclude;
	}
	add_filter( 'wpmudev_copier_exclude_settings', 'copier_exclude_epanel_temp_path');
}