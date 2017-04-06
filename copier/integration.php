<?php

/**
 * Functions that integrate common plugins.
 *
 * Every code that implies a clone of plugins/themes settings/options... that
 * do not belong to the WP Core should be in a separate file in includes dir or coded as an external plugin (an mu-plugins would be the best)
 *
 * Please, use includes folder only for very common plugins
 *
 */

$includes_dir = dirname( __FILE__ ) . '/integration/';

$files_list = apply_filters( 'copier_integration_files', array(
	'autoblog',
	'buddypress',
	'custom-sidebars-pro',
	'epanel',
	'membership',
	'popover',
	'shortcodes-ultimate',
	'wp-https',
	'wpmu-dev-seo',
	'woocommerce',
	'cookie-notice',
	'jetpack',
	'ubermenu',
	'revslider'
) );

foreach ( $files_list as $file ) {
	if ( is_file( $includes_dir . $file . '.php' ) ) {
		include_once( $includes_dir . $file . '.php' );
	}
}


if ( ! function_exists( 'copier_search_posts_with_shortcode' ) ) {
	/**
	 * Search posts that may have a defined shortcode in them
	 *
	 * @param String $shortcode Shortcode slug (no [])
	 *
	 * @return Array List of posts
	 */
	function copier_search_posts_with_shortcode( $shortcode ) {
		return get_posts( array(
			'post_type'           => 'any',
			'posts_per_page'      => - 1,
			'ignore_sticky_posts' => true,
			's'                   => $shortcode
		) );
	}
}


if ( ! function_exists( 'copier_replace_shortcode_attributes' ) ) {
	/**
	 * Replace a shortcode attribute in a list of posts based on a post mapping array
	 *
	 * @param String $shortcode Shortcode slug
	 * @param Array $all_posts List of posts
	 * @param String $shortcode_attribute Shortcode attribute that we are searching for
	 * @param Array $posts_map List of source/destination post IDs [source_post_id] => [new_post_id]
	 */
	function copier_replace_shortcode_attributes( $shortcode, $all_posts, $shortcode_attribute, $posts_map ) {
		// Shortcode patterns
		$shortcode_pattern = get_shortcode_regex();

		foreach ( $all_posts as $post ) {
			$_post = (array) $post;

			// Search for shortcodes in the post content
			if (
				preg_match_all( '/' . $shortcode_pattern . '/s', $_post['post_content'], $matches )
				&& array_key_exists( 2, $matches )
				&& in_array( $shortcode, $matches[2] )
			) {
				$do_replace = false;
				foreach ( $matches[2] as $key => $shortcode_type ) {

					if ( $shortcode == $shortcode_type ) {
						// Yeah! We have found the shortcode in this post, let's replace the ID if we can

						// Get the shortcode attributes
						$atts = shortcode_parse_atts( $matches[3][ $key ] );

						if ( isset( $atts[ $shortcode_attribute ] ) ) {
							// There is an ID attribute, let's replace it
							$source_post_id = absint( $atts[ $shortcode_attribute ] );

							if ( ! isset( $posts_map[ $source_post_id ] ) ) {
								// There's not such post ID mapped in the array, let's continue
								continue;
							}

							if ( $source_post_id == $posts_map[ $source_post_id ] ) {
								continue;
							}

							$new_post_id = $posts_map[ $source_post_id ];

							// Get the original full shortcode
							$full_shortcode = $matches[0][ $key ];

							// Replace the ID
							$new_atts_ids = str_replace( (string) $source_post_id, $new_post_id, $atts[ $shortcode_attribute ] );

							// Now replace the attributes in the source shortcode
							$new_full_shortcode = str_replace( $atts[ $shortcode_attribute ], $new_atts_ids, $full_shortcode );

							// And finally replace the source shortcode for the new one in the post content
							$_post['post_content'] = str_replace( $full_shortcode, $new_full_shortcode, $_post['post_content'] );

							// So we have found a replacement to make, haven't we?
							$do_replace = true;

						}

					}
				}

				if ( $do_replace ) {
					// Update the post!
					$postarr = array(
						'ID'           => $_post['ID'],
						'post_content' => $_post['post_content']
					);

					wp_update_post( $postarr );
				}
			}
		}
	}
}