<?php

add_action( 'wp_ajax_cloner_autocomplete_site', 'cloner_autocomplete_site' );
function cloner_autocomplete_site() {
	global $wpdb, $current_site;

	if ( ! is_multisite() || ! current_user_can( 'manage_network' ) || ! is_super_admin() )
		wp_die( -1 );

	if ( ! isset( $_REQUEST['term'] ) )
		wp_die( -1 );

	$return = array();

	// Exclude the blog that we are trying to clone
	$exclude_blog_id = false;
	if ( isset( $_REQUEST['blog_id'] ) )
		$exclude_blog_id = absint( $_REQUEST['blog_id'] );

	$s = $_REQUEST['term'];
	$like_s = esc_sql( like_escape( $s ) );

	$query = "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";

	if ( is_subdomain_install() ) {
		$blog_s = str_replace( '.' . $current_site->domain, '', $like_s );
		$blog_s .= '%.' . $current_site->domain;
		$query .= " AND ( {$wpdb->blogs}.domain LIKE '$blog_s' ) ";
	} else {
		if ( $like_s != trim( '/', $current_site->path ) )
			$blog_s = $current_site->path . $like_s . '%/';
		else
			$blog_s = $like_s;
		$query .= " AND  ( {$wpdb->blogs}.path LIKE '$blog_s' )";
	}

	$query .= " LIMIT 10";

	$blogs = $wpdb->get_results( $query );

	foreach ( $blogs as $blog ) {
		$details = get_blog_details( $blog->blog_id );
		if ( is_subdomain_install() ) {
			$path = $details->domain;
		}
		else {
<<<<<<< HEAD
			$path = trim( $details->path, '/' );
=======
			$path = str_replace( '/', '', $details->path );
>>>>>>> ecfdaa73c2dcf4d313341b57b7a8083ad045cc70
		}

		$return[] = array(
			'domain' => $path,
			'blog_name' => $details->blogname,
			'blog_id' => $blog->blog_id,
		);
	}

	wp_die( json_encode( $return ) );
}