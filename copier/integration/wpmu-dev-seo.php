<?php

if ( ! function_exists( 'wds_exclude_cloner_settings' ) ) {
	function wds_exclude_cloner_settings( $exclude_settings ) {
		$exclude_settings[] = 'wds_sitemap_options';

		return $exclude_settings;
	}

	add_filter( 'wpmudev_copier_exclude_settings', 'wds_exclude_cloner_settings' );
}
