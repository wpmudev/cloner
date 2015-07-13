<?php

add_action( 'wpmudev_copier-copy-options', 'wpmudev_cloner_set_blog_privacy' );
function wpmudev_cloner_set_blog_privacy( $source_blog_id ) {
	$option = get_option( 'copier-pending', array() );
	if ( isset( $option['blog_public'] ) ) {
		update_option( 'blog_public', $option['blog_public'] );
	}
}