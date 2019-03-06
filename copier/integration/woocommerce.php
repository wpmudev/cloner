<?php


if ( ! function_exists( 'copier_woocommerce_remap_termmeta' ) ) {
	add_filter( 'wpmudev_copier-process_row', 'copier_woocommerce_remap_termmeta', 10, 3 );
	function copier_woocommerce_remap_termmeta( $row, $dest_table, $source_blog_id ) {
		global $wpdb;

		if ( ! function_exists( 'WC' ) )
			return $row;

		if ( $dest_table != $wpdb->prefix . 'woocommerce_termmeta' )
			return $row;

		$mapped_terms = get_transient( 'copier_woocommerce_terms' );
		if ( ! $mapped_terms )
			return $row;

		$old_term_id = $row['woocommerce_term_id'];
		if ( array_key_exists( $old_term_id, $mapped_terms ) )
			$row['woocommerce_term_id'] = $mapped_terms[ $old_term_id ];

		return $row;
	}
}

if ( ! function_exists( 'copier_woocommerce_save_mapped_terms' ) ) {
	add_action( 'wpmudev_copier-copy-terms', 'copier_woocommerce_save_mapped_terms', 10, 4 );
	function copier_woocommerce_save_mapped_terms( $user_id, $source_blog_id, $template, $mapped_terms ) {
		if ( ! function_exists( 'WC' ) )
			return;

		set_transient( 'copier_woocommerce_terms', $mapped_terms, 3600 ); //Let's save for 60 minutes
	}
}

if ( ! function_exists( 'copier_woocommerce_delete_transient' ) ) {
	add_action( 'wpmudev_copier-copy-after_copying', 'copier_woocommerce_delete_transient' );
	function copier_woocommerce_delete_transient() {
		delete_transient( 'copier_woocommerce_terms' );
	}
}

if ( ! function_exists( 'copier_woocommerce_order_status' ) ) {
	add_filter( 'wpmudev_copier_get_source_posts_args', 'copier_woocommerce_order_status' );
	/**
	 * Add WooCommerce Order statuses to get_posts arguments so they are cloned too.
	 *
	 * @param Array $args
	 *
	 * @return Array
	 */
	function copier_woocommerce_order_status( $args ) {
		if ( ! function_exists( 'WC' ) )
			return $args;

		$args['post_type'] = ( ! is_array( $args['post_type'] ) ) ? array( $args['post_type'] ) : $args['post_type'];
		
		if ( ! in_array( 'shop_order', $args['post_type'] ) )
			return $args;

		$args['post_status'] = array_merge( $args['post_status'], array(
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-completed',
			'wc-cancelled',
			'wc-refunded',
			'wc-failed',
			'wc-expired'
		) );

		return $args;
	}
}


if ( ! function_exists( 'copier_woocommerce_follow_up_email_status' ) ) {
	add_filter( 'wpmudev_copier_get_source_posts_args', 'copier_woocommerce_follow_up_email_status' );
	/**
	 * Add WooCommerce Follow Up Email statuses to get_posts arguments so they are cloned too.
	 *
	 * @param Array $args
	 *
	 * @return Array
	 */
	function copier_woocommerce_follow_up_email_status( $args ) {
		if ( ! function_exists( 'WC' ) )
			return $args;

		$args['post_type'] = ( ! is_array( $args['post_type'] ) ) ? array( $args['post_type'] ) : $args['post_type'];

		if ( ! in_array( 'follow_up_email', $args['post_type'] ) )
			return $args;

		$args['post_status'] = array_merge( $args['post_status'], array(
			'fue-active', 
			'fue-inactive', 
			'fue-archived'
		) );

		return $args;
	}
}