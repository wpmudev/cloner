<?php

if ( ! function_exists( 'copier_copy_autoblog_feeds' ) ) {
	/**
	 * WPMU DEV Autoblog Integration
	 * 
	 * @param Integer $source_blog_id Source Blog ID from we are copying stuff
	 */
	function copier_copy_autoblog_feeds( $source_blog_id ) {
		global $wpdb;

		// Site ID
		$current_site = get_current_site();
		$current_site_id = $current_site->id;

		if ( ! $source_blog_id )
			return;
		
		$autoblog_on = false;

		switch_to_blog( $source_blog_id );
		// Is Autoblog activated?
		if ( ! function_exists( 'is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( is_plugin_active( 'autoblog/autoblogpremium.php' ) )
			$autoblog_on = true;

		// We'll need this values later
		$source_url = get_site_url( $source_blog_id );
		$source_url_ssl = get_site_url( $source_blog_id, '', 'https' );

		restore_current_blog();

		if ( ! $autoblog_on )
			return;

		$autoblog_table = $wpdb->base_prefix . 'autoblog';
		$current_blog_id = get_current_blog_id();

		// First, delete the current feeds
		$wpdb->query( $wpdb->prepare( "DELETE FROM $autoblog_table WHERE blog_id = %d AND site_id = %d", $current_blog_id, $current_site_id ) );

		// Getting all the feed data for the source blog ID
		
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $autoblog_table WHERE blog_id = %d AND site_id = %d", $source_blog_id, $current_site_id ) );

		if ( ! empty( $results ) ) {

			$current_url = get_site_url( $current_blog_id );
			$current_url_ssl = get_site_url( $current_blog_id, '', 'https' );

			foreach ( $results as $row ) {
				// Getting the feed metadata
				$feed_meta = maybe_unserialize( $row->feed_meta );

				// We need to replace the source blog URL for the new one
				$feed_meta = str_replace( $source_url, $current_url, $feed_meta );
				$feed_meta = str_replace( $source_url_ssl, $current_url_ssl, $feed_meta );

				// Also the blog ID
				$feed_meta['blog'] = $current_blog_id;

				$row->feed_meta = maybe_serialize( $feed_meta );

				// Inserting feed for the new blog
				$wpdb->insert(
					$autoblog_table,
					array(
						'site_id' => $current_site_id,
						'blog_id' => $current_blog_id,
						'feed_meta' => $row->feed_meta,
						'active' => $row->active,
						'nextcheck' => $row->nextcheck,
						'lastupdated' => $row->lastupdated
					),
					array( '%d', '%d', '%s', '%d', '%d', '%d' )
				);
			}
		}

	}
	add_action( 'wpmudev_copier-copy-options', 'copier_copy_autoblog_feeds', 10, 1 );

}