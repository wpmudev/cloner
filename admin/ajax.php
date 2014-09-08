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

	$query = "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";

	$s = $_REQUEST['term'];

	$wild = '%';
	if ( false !== strpos( $s, '*' ) ) {
		$wild = '%';
		$s = trim( $s, '*' );
	}
	if ( is_numeric( $s ) ) {
			$query .= $wpdb->prepare( " AND ( {$wpdb->blogs}.blog_id = %s )", $s );
	} 
	elseif ( is_subdomain_install() ) {
		$blog_s = str_replace( '.' . $current_site->domain, '', $s );
		$blog_s = $wild . $wpdb->esc_like( $blog_s ) . $wild;
		$query .= $wpdb->prepare( " AND ( {$wpdb->blogs}.domain LIKE %s ) ", $blog_s );
	} 
	else {
		if ( $s != trim('/', $current_site->path) ) {
			$blog_s = $wpdb->esc_like( $current_site->path . $s ) . $wild . $wpdb->esc_like( '/' );
		} else {
			$blog_s = $wpdb->esc_like( $s );
		}
		$query .= $wpdb->prepare( " AND  ( {$wpdb->blogs}.path LIKE %s )", $blog_s );
	}

	$query .= $wpdb->prepare( " AND blog_id != %d", $exclude_blog_id );

	$query .= " LIMIT 10";

	$blogs = $wpdb->get_results( $query );

	foreach ( $blogs as $blog ) {
		$details = get_blog_details( $blog->blog_id );
		if ( is_subdomain_install() ) {
			$path = $details->domain;
		}
		else {
			$path = trim( $details->path, '/' );
		}

		$return[] = array(
			'domain' => $path,
			'blog_name' => $details->blogname,
			'blog_id' => $blog->blog_id,
		);
	}

	wp_die( json_encode( $return ) );
}