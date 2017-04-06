<?php

/**
 * Module Name: Shortcodes Ultimate
 * Plugin: shortcodes-ultimate/shortcodes-ultimate.php
 */

if ( ! function_exists( 'copier_replace_shortcodes_ultimate_post_shortcode' ) ) {
	/**
	 * Replace the Post Shortcode
	 */
	function copier_replace_shortcodes_ultimate_post_shortcode( $source_blog_id, $user_id, $copier_options ) {
		
		if ( ! function_exists( 'is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( ! is_plugin_active( 'shortcodes-ultimate/shortcodes-ultimate.php' ) )
			return;

		// Posts mapping array
		$posts_map = ! empty( $copier_options['posts_mapping'] ) ? $copier_options['posts_mapping'] : array();

		if ( empty( $posts_map ) )
			return;

		$shortcodes = array(
			'su_post' => 'post_id',
			'su_permalink' => 'id',
			'su_subpages' => 'p',
			'su_meta' => 'post_id',
			'su_posts' => 'id'
		);

		foreach ( $shortcodes as $shortcode => $attribute ) {
			// Get all posts that may have the content block shortcode in them
			$all_posts = copier_search_posts_with_shortcode( $shortcode );

			copier_replace_shortcode_attributes( $shortcode, $all_posts, $attribute, $posts_map );	
		}

	}
	add_action( 'wpmudev_copier-copy-after_copying', 'copier_replace_shortcodes_ultimate_post_shortcode', 10, 3 );
}
