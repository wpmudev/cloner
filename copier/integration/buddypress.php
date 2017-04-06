<?php

if ( ! function_exists( 'copier_copy_buddypress_pages_option' ) ) {
	function copier_copy_buddypress_pages_option( $source_blog_id, $posts_mapping ) {
		if ( ! function_exists( 'is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( ! is_plugin_active( 'buddypress/bp-loader.php' ) )
			return;

		$source_bp_pages = get_blog_option( $source_blog_id, 'bp-pages' );

		if ( $source_bp_pages === false )
			return;

		$new_options = get_option( 'bp-pages', array() );
		foreach ( $source_bp_pages as $bp_page_slug => $bp_source_page_id ) {
			if ( isset( $posts_mapping[ $bp_source_page_id ] ) )
				$new_options[ $bp_page_slug ] = $posts_mapping[ $bp_source_page_id ];
		}

		update_option( 'bp-pages', $new_options );
	}

	add_action( 'wpmudev_copier-copied-posts', 'copier_copy_buddypress_pages_option', 10, 2 );
}